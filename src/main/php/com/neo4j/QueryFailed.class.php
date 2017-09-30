<?php namespace com\neo4j;

use lang\XPException;
use lang\Throwable;
use util\Objects;

class QueryFailed extends XPException {

  /** Creates a new instance */
  public function __construct(array $errors, Throwable $cause= null) {
    parent::__construct('Query failed '.Objects::stringOf($errors), $cause);
  }
}