<?php namespace com\neo4j;

use lang\IllegalArgumentException;
use peer\URL;

abstract class Protocol {

  public static function for($endpoint) {
    $url= $endpoint instanceof URL ? $url : new URL($endpoint);
    switch ($url->getScheme()) {
      case 'http': case 'https': return new HttpProtocol($url);
      case 'bolt': return new BoltProtocol($url);
      default: throw new IllegalArgumentException('Unsupported protocol "'.$url->getScheme().'"');
    }
  }

  public abstract function commit($payload);
}