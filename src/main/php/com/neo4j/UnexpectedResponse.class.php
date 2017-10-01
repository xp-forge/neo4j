<?php namespace com\neo4j;

use lang\XPException;
use lang\Throwable;
use util\Objects;

class UnexpectedResponse extends XPException {

  /** Creates a new instance */
  public function __construct(array $errors, Throwable $cause= null) {
    parent::__construct(Objects::stringOf($errors), $cause);
  }
}