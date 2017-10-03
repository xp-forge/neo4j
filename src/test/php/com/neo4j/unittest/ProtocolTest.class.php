<?php namespace com\neo4j\unittest;

use com\neo4j\Protocol;
use com\neo4j\HttpProtocol;
use com\neo4j\BoltProtocol;
use lang\IllegalArgumentException;
use peer\URL;

class ProtocolTest extends \unittest\TestCase {

  #[@test, @values([
  #  'http://localhost:7474/',
  #  new URL('http://localhost:7474/')
  #])]
  public function http($endpoint) {
    $this->assertInstanceOf(HttpProtocol::class, Protocol::forEndpoint($endpoint));
  }

  #[@test, @values([
  #  'bolt://localhost:7687/',
  #  new URL('bolt://localhost:7687/')
  #])]
  public function bolt($endpoint) {
    $this->assertInstanceOf(BoltProtocol::class, Protocol::forEndpoint($endpoint));
  }

  #[@test, @expect(class= IllegalArgumentException::class, withMessage= '/Unsupported protocol "test"/')]
  public function unsupported_protocol() {
    Protocol::forEndpoint('test://localhost');
  }
}