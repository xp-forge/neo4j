<?php namespace com\neo4j;

use lang\IllegalArgumentException;
use peer\URL;

/**
 * Protocol base class
 *
 * @test  xp://com.neo4j.unittest.ProtocolTest
 */
abstract class Protocol implements \lang\Closeable {

  /**
   * Factory method
   *
   * @param  peer.URL|string $endpoint
   * @return self
   */
  public static function forEndpoint($endpoint) {
    $url= $endpoint instanceof URL ? $endpoint : new URL($endpoint);
    switch ($url->getScheme()) {
      case 'http': case 'https': return new HttpProtocol($url);
      case 'bolt': return new BoltProtocol($url);
      default: throw new IllegalArgumentException('Unsupported protocol "'.$url->getScheme().'"');
    }
  }

  /** Commits a payload and returns records */
  public abstract function commit($payload);
}