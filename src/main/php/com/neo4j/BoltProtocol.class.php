<?php namespace com\neo4j;

use peer\Socket;
use peer\URL;

class BoltProtocol extends Protocol {
  private $sock, $init, $serialization;

  const EOR                 = "\x00\x00";
  const PREAMBLE            = "\x60\x60\xb0\x17";

  const INIT                = "\x01";
  const ACK_FAILURE         = "\x0e";
  const RESET               = "\x0f";
  const RUN                 = "\x10";
  const PULL_ALL            = "\x3f";
  const NODE                = "\x4e";
  const PATH                = "\x50";
  const RELATIONSHIP        = "\x52";
  const SUCCESS             = "\x70";
  const RECORD              = "\x71";
  const UNBOUNDRELATIONSHIP = "\x72";
  const FAILURE             = "\x7f";
  const IGNORE              = "\x7e";

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
    $s= pack('ca', 0xb0 + sizeof($args), $signature);
    foreach ($args as $arg) {
      $s.= $this->serialization->serialize($arg);
    }

    for ($l= strlen($s), $o= 0; $o < $l; $o+= 65536) {
      $p= min($l - $o, 65535);
      $this->sock->write(pack('n', $p).substr($s, $o, $p));
    }
    $this->sock->write(self::EOR);
  }

  /** Receives one receive at a time */
  private function receive() {
    $r= '';
    while (self::EOR !== ($length= $this->sock->readBinary(2))) {
      $r.= $this->sock->readBinary(unpack('n', $length)[1]);
    }

    return $r;
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
    $answer= $this->receive();
    if (self::SUCCESS !== $answer{1}) {
      $offset= 2;
      throw new CannotAuthenticate([$this->serialization->unserialize($answer, $offset)]);
    }
  }

  /** @return [:var][] */
  private function records() {
    $records= [];
    do {
      $answer= $this->receive();
      if (self::RECORD !== $answer{1}) break;
      $records[]= $this->serialization->unserialize($answer);
    } while (true);

    return $records;
  }

  /**
   * Commits multiple statements
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

      $answer= $this->receive();
      $offset= 2;
      if (self::SUCCESS === $answer{1}) {
        $this->send(self::PULL_ALL);
        $r['results'][]= ['columns' => $this->serialization->unserialize($answer, $offset)['fields'], 'data' => $this->records()];        
      } else if (self::FAILURE === $answer{1}) {
        $this->send(self::ACK_FAILURE);
        $this->receive();
        $r['errors'][]= $this->serialization->unserialize($answer, $offset);        
      } else {
        $this->send(self::RESET);
        $this->receive();
        $r['errors'][]= ['Ignored'];
      }
    }
    return $r;
  }

  /** @return void */
  public function close() {
    $this->sock->isConnected() && $this->socket->close();
  }
}