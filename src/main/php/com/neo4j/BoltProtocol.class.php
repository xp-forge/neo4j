<?php namespace com\neo4j;

use peer\Socket;
use peer\URL;

class BoltProtocol extends Protocol {
  private $sock, $init;

  const PREAMBLE    = "\x60\x60\xb0\x17";
  const INIT        = 0x01;
  const ACK_FAILURE = 0x0e;
  const RESET       = 0x0f;
  const RUN         = 0x10;
  const PULL_ALL    = 0x3f;
  const NODE        = 0x4e;
  const SUCCESS     = 0x70;
  const RECORD      = 0x71;
  const FAILURE     = 0x7f;
  const IGNORE      = 0x7e;

  /**
   * Creates a new Neo4J graph connection
   *
   * @param  peer.URL $endpoint
   */
  public function __construct(URL $endpoint) {
    $this->sock= new Socket($endpoint->getHost(), $endpoint->getPort(7687));
    if ($user= $endpoint->getUser()) {
      $this->init= ['scheme' => 'basic', 'principal' => $user, 'credentials' => $endpoint->getPassword()];
    } else {
      $this->init= ['scheme' => 'none'];
    }
  }

  private function marker($base, $top, $length) {
    if ($length < 16) {
      return pack('c', $base + $length);
    } else if ($length < 256) {
      return pack('cc', $top, $length);
    } else if ($length < 65536) {
      return pack('cn', $top + 1, $length);
    } else {
      return pack('cN', $top + 2, $length);
    }
  }

  private function encode($value) {
    if (null === $value) {
      return "\xc0";
    } else if (true === $value) {
      return "\xc3";
    } else if (false === $value) {
      return "\xc2";
    } else if (is_int($value)) {
      return "\xca".pack('N', $value);        
    } else if (is_string($value)) {
      return $this->marker(0x80, 0xd0, strlen($value)).$value;
    } else if (is_array($value)) {
      $r= $this->marker(0xa0, 0xd8, sizeof($value));
      foreach ($value as $key => $val) {
        $r.= $this->encode($key).$this->encode($val);
      }
      return $r;
    }
  }

  private function decode($value, &$offset) {
    $marker= $value{$offset};
    if ("\xc0" === $marker) {
      $offset+= 1;
      return null;
    } else if ("\xc1" === $marker) {
      $offset+= 9;
      return unpack('d', strrev(substr($value, $offset - 8, 8)))[1];
    } else if ("\xc2" === $marker) {
      $offset+= 1;
      return true;
    } else if ("\xc3" === $marker) {
      $offset+= 1;
      return true;
    } else if ("\xc9" === $marker) {
      $offset+= 3;
      return unpack('n', substr($value, $offset - 2, 2))[1];
    } else if ("\xca" === $marker) {
      $offset+= 5;
      return unpack('N', substr($value, $offset - 4, 4))[1];
    } else if ("\xcb" === $marker) {
      $offset+= 9;
      return unpack('J', substr($value, $offset - 8, 8))[1];
    } else if ("\xd0" === $marker) {
      $l= unpack('c', $value{$offset + 1})[1];
      $offset+= $l + 2;
      return substr($value, $offset - $l, $l);
    } else if ("\xd1" === $marker) {
      $l= unpack('n', substr($value, $offset + 1, 2))[1];
      $offset+= $l + 3;
      return substr($value, $offset - $l, $l);
    } else if ("\xd2" === $marker) {
      $l= unpack('N', substr($value, $offset + 1, 4))[1];
      $offset+= $l + 5;
      return substr($value, $offset - $l, $l);
    } else if ("\xd4" === $marker) {
      $l= ord($value{$offset + 1});
      $offset+= 2;
      $r= [];
      for ($i= 0; $i < $l; $i++) {
        $val= $this->decode($value, $offset);
        $r[]= $val;
      }
      return $r;
    } else if ($marker >= "\x00" && $marker <= "\x7f") {
      $offset+= 1;
      return ord($marker);
    } else if ($marker >= "\x80" && $marker <= "\x8f") {
      $l= ord($marker) - 0x80;
      $offset+= $l + 1;
      return substr($value, $offset - $l, $l);
    } else if ($marker >= "\x90" && $marker <= "\x9f") {
      $l= ord($marker) - 0x90;
      $offset++;
      $r= [];
      for ($i= 0; $i < $l; $i++) {
        $val= $this->decode($value, $offset);
        $r[]= $val;
      }
      return $r;
    } else if ($marker >= "\xb0" && $marker <= "\xbf") {
      $l= ord($marker) - 0xb0;
      $offset++;
      $r= new Struct($this->decode($value, $offset));
      for ($i= 1; $i <= $l; $i++) {
        $val= $this->decode($value, $offset);
        $r->put($i, $val);
      }
      return $r;
    } else if ($marker >= "\xa0" && $marker <= "\xaf") {
      $l= ord($marker) - 0xa0;
      $offset++;
      $r= [];
      for ($i= 0; $i < $l; $i++) {
        $key= $this->decode($value, $offset);
        $val= $this->decode($value, $offset);
        $r[$key]= $val;
      }
      return $r;
    } else {
      return sprintf("MARKER:0x%02x", ord($marker));
    }
  }

  /** Sends a message */
  private function send($signature, ... $args) {
    $chunk= pack('cc', 0xb0 + sizeof($args), $signature);
    foreach ($args as $arg) {
      $chunk.= $this->encode($arg);
    }

    $send= pack('n', strlen($chunk)).$chunk."\x00\x00";
    $this->sock->write($send);
  }

  /** Receives one answer at a time */
  private function receive() {
    $chunk= '';
    while ("\x00\x00" !== ($length= $this->sock->readBinary(2))) {
      $chunk.= $this->sock->readBinary(unpack('n', $length)[1]);
    }

    $offset= 0;
    return $this->decode($chunk, $offset);
  }

  /**
   * Commits multiple statements using `transaction/commit` endpoint.
   *
   * @param  [:var][] $payload
   * @return [:var] Results
   */
  public function commit($payload) {
    if (!$this->sock->isConnected()) {
      $this->sock->connect();

      // Handshake
      $this->sock->write(self::PREAMBLE.pack('NNNN', 1, 0, 0, 0));
      $protocol= unpack('N', $this->sock->readBinary(4));
      if (0 === $protocol[1]) {
        throw new QueryFailed(['Protocol handshake failed, server does not support protocol version']);
      }

      // Init
      $this->send(self::INIT, nameof($this), $this->init);
      $res= $this->receive();
      if (self::SUCCESS !== $res->signature) {
        throw new CannotAuthenticate([$res->fields['metadata']]);
      }
    }

    $r= ['results' => [], 'errors' => []];
    foreach ($payload['statements'] as $s) {
      $this->send(self::RUN, $s['statement'], isset($s['parameters']) ? $s['parameters'] : []);

      $res= $this->receive();
      if (self::FAILURE === $res->signature) {
        $r['errors'][]= $res->fields['metadata'];
        $this->send(self::ACK_FAILURE);
        $this->receive();
      } else if (self::IGNORE === $res->signature) {
        $r['errors'][]= ['Ignored'];
        $this->send(self::RESET);
        $this->receive();
      } else {
        $result= ['data' => [], 'columns' => $res->fields['metadata']['fields']];

        $this->send(self::PULL_ALL);
        do {
          $res= $this->receive();
          if (self::RECORD === $res->signature) {
            $row= [];
            foreach ($res->fields['fields'] as $record) {
              if ($record instanceof Struct) {
                $row[]= $record->fields['properties'];
              } else {
                $row[]= $record;
              }
            }
            $result['data'][]= ['row' => $row, 'meta' => null];  // FIXME: Fill meta
          }
        } while (self::RECORD === $res->signature);

        $r['results'][]= $result;
      }
    }
    return $r;
  }
}