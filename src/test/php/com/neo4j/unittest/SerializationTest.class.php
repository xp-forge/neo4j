<?php namespace com\neo4j\unittest;

use com\neo4j\Serialization;

class SerializationTest extends \unittest\TestCase {
  const MAX_SIZE = 100000; // Don't use MAX_INT, preventing OOM

  private $fixture;

  /** @return void */
  public function setUp() {
    $this->fixture= new Serialization();
  }

  #[@test]
  public function null() {
    $this->assertEquals(null, $this->fixture->unserialize($this->fixture->serialize(null)));
  }

  #[@test, @values([true, false])]
  public function booleans($value) {
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([-16, -1, 0, 1, 127])]
  public function tiny_int($value) {
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([-128, -100, -17])]
  public function int_8($value) {
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([-32768, -1000, -129, 6100, 32767, 40000])]
  public function int_16($value) {
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([-2147483648, -32769, 2147483647])]
  public function int_32($value) {
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([-9223372036854775807, -2147483649, 2147483648, 9223372036854775807])]
  public function int_64($value) {
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([0, 1, 15])]
  public function tiny_string($length) {
    $value= str_repeat('*', $length);
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([16, 255])]
  public function string_8($length) {
    $value= str_repeat('*', $length);
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([256, 65535])]
  public function string_16($length) {
    $value= str_repeat('*', $length);
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([65536, self::MAX_SIZE])]
  public function string_32($length) {
    $value= str_repeat('*', $length);
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([0, 1, 15])]
  public function tiny_list($size) {
    $value= array_fill(0, $size, '*');
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([16, 255])]
  public function list_8($size) {
    $value= array_fill(0, $size, '*');
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([256, 65535])]
  public function list_16($size) {
    $value= array_fill(0, $size, '*');
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([65536, self::MAX_SIZE])]
  public function list_32($size) {
    $value= array_fill(0, $size, '*');
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([0, 1, 15])]
  public function tiny_map($entries) {
    $value= [];
    for ($i= 0; $i < $entries; $i++) {
      $value['k'.$i]= $i;
    }
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([16, 255])]
  public function map_8($entries) {
    $value= [];
    for ($i= 0; $i < $entries; $i++) {
      $value['k'.$i]= $i;
    }
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([256, 65535])]
  public function map_16($entries) {
    $value= [];
    for ($i= 0; $i < $entries; $i++) {
      $value['k'.$i]= $i;
    }
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }

  #[@test, @values([65536, self::MAX_SIZE])]
  public function map_32($entries) {
    $value= [];
    for ($i= 0; $i < $entries; $i++) {
      $value['k'.$i]= $i;
    }
    $this->assertEquals($value, $this->fixture->unserialize($this->fixture->serialize($value)));
  }
}