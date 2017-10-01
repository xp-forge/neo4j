<?php namespace com\neo4j;

use peer\Socket;
use peer\URL;

class BoltProtocol extends Protocol {
  private $sock, $init, $serialization;

  const PREAMBLE            = "\x60\x60\xb0\x17";
  const INIT                = 0x01;
  const ACK_FAILURE         = 0x0e;
  const RESET               = 0x0f;
  const RUN                 = 0x10;
  const PULL_ALL            = 0x3f;
  const NODE                = 0x4e;
  const PATH                = 0x50;
  const SUCCESS             = 0x70;
  const RECORD              = 0x71;
  const UNBOUNDRELATIONSHIP = 0x72;
  const FAILURE             = 0x7f;
  const IGNORE              = 0x7e;

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
    $this->serialization= new Serialization();
  }

  /** Sends a message */
  private function send($signature, ... $args) {
    $chunk= pack('cc', 0xb0 + sizeof($args), $signature);
    foreach ($args as $arg) {
      $chunk.= $this->serialization->serialize($arg);
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

    return $this->serialization->unserialize($chunk);
  }

  /**
   * Initialize communication
   *
   * @see    https://boltprotocol.org/v1/#handshake
   * @return void
   * @throws com.neo4j.UnexpectedResponse
   */
  private function init() {
    $this->sock->write(self::PREAMBLE.pack('NNNN', 1, 0, 0, 0));
    $protocol= unpack('N', $this->sock->readBinary(4));
    if (0 === $protocol[1]) {
      throw new UnexpectedResponse(['Protocol handshake failed, server does not support protocol version']);
    }

    $this->send(self::INIT, nameof($this), $this->init);
    $res= $this->receive();
    if (self::SUCCESS !== $res->signature) {
      throw new CannotAuthenticate([$res->fields['metadata']]);
    }
  }

  /** @return [:var][] */
  private function records() {
    do {
      $res= $this->receive();
      if (self::RECORD === $res->signature) {
        $records[]= ['row' => $res->members['fields'], 'meta' => null];  // FIXME: Fill meta
      }
    } while (self::RECORD === $res->signature);
    return $records;
  }

  /**
   * Commits multiple statements using `transaction/commit` endpoint.
   *
   * @param  [:var][] $payload
   * @return [:var] Results
   * @throws com.neo4j.UnexpectedResponse
   */
  public function commit($payload) {
    if (!$this->sock->isConnected()) {
      $this->sock->connect();
      $this->init();
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
        $this->send(self::PULL_ALL);
        $r['results'][]= ['columns' => $res->fields['metadata']['fields'], 'data' => $this->records()];
      }
    }
    return $r;
  }
}