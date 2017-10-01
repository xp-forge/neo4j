<?php namespace com\neo4j;

use util\Objects;

class Struct implements \lang\Value {
  private static $FIELDS= [
    BoltProtocol::NODE                => ['Node', 'identity', 'labels', 'properties'],
    BoltProtocol::PATH                => ['Path', 'nodes', 'relationships', 'sequence'],
    BoltProtocol::UNBOUNDRELATIONSHIP => ['UnboundRelationship', 'relIdentity', 'type', 'properties'],
    BoltProtocol::SUCCESS             => ['Success', 'metadata'],
    BoltProtocol::RECORD              => ['Record', 'fields'],
    BoltProtocol::IGNORE              => ['Ignore'],
    BoltProtocol::FAILURE             => ['Failure', 'metadata'],
  ];

  public $signature, $members;

  /**
   * Creates a new struct
   *
   * @param  int $signature
   * @param  [:var] $members
   */
  public function __construct($signature, $members= []) {
    $this->signature= $signature;
    $this->members= $members;
  }

  /**
   * Add member for a given offset
   *
   * @param  int $i
   * @param  var $member
   * @return void
   */
  public function put($i, $member) {
    $this->members[self::$FIELDS[$this->signature][$i]]= $member;
  }

  /** @return string */
  public function hashCode() {
    return $this->signature.Objects::hashOf($this->members);
  }

  /** @return string */
  public function toString() {
    return nameof($this).'<'.self::$FIELDS[$this->signature][0].'>@'.Objects::stringOf($this->members);
  }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self
      ? Objects::compare([$this->signature, $this->members], [$value->signature, $value->members])
      : 1
    ;
  }
}