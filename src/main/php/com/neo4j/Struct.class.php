<?php namespace com\neo4j;

class Struct {

  private static $FIELDS= [
    BoltProtocol::NODE    => [null, 'identity', 'labels', 'properties'],
    BoltProtocol::SUCCESS => [null, 'metadata'],
    BoltProtocol::RECORD  => [null, 'fields'],
    BoltProtocol::IGNORE  => [null],
    BoltProtocol::FAILURE => [null, 'metadata'],
  ];

  public $signature, $fields;

  public function __construct($signature, $fields= []) {
    $this->signature= $signature;
    $this->fields= $fields;
  }

  public function put($i, $member) {
    $this->fields[self::$FIELDS[$this->signature][$i]]= $member;
  }
}