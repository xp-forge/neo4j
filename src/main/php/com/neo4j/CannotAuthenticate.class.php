<?php namespace com\neo4j;

use lang\XPException;
use lang\Throwable;
use util\Objects;

class CannotAuthenticate extends XPException {

  /** Creates a new instance */
  public function __construct(array $errors, Throwable $cause= null) {
    parent::__construct('Cannot authenticate '.Objects::stringOf($errors), $cause);
  }
}