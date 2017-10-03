<?php namespace com\neo4j;

use lang\IllegalStateException;
use io\ByteOrder;

/**
 * Bolt protocol message serialization
 *
 * @see   https://boltprotocol.org/v1/#serialization
 */
class Serialization {
  private static $reverse;

  static function __static() {
    self::$reverse= ByteOrder::LITTLE_ENDIAN === ByteOrder::nativeOrder();
  }

  /** Creates a marker */
  private function marker($base, $top, $length) {
    if ($length < 16) {
      return pack('C', $base + $length);
    } else if ($length < 256) {
      return pack('CC', $top, $length);
    } else if ($length < 65536) {
      return pack('Cn', $top + 1, $length);
    } else {
      return pack('CN', $top + 2, $length);
    }
  }

  /**
   * Serialize a value
   * 
   * @param  var $value
   * @return string
   */
  public function serialize($value) {
    if (null === $value) {
      return "\xc0";
    } else if (true === $value) {
      return "\xc3";
    } else if (false === $value) {
      return "\xc2";
    } else if (is_int($value)) {
      if ($value > 2147483647 || $value < -2147483648) {
        $packed= pack('q', $value);
        return "\xcb".(self::$reverse ? strrev($packed) : $packed);
      } else if ($value > 32767 || $value < -32768) {
        $packed= pack('l', $value);
        return "\xca".(self::$reverse ? strrev($packed) : $packed);
      } else if ($value > 127 || $value < -128) {
        $packed= pack('s', $value);
        return "\xc9".(self::$reverse ? strrev($packed) : $packed);
      } else {
        return "\xc8".pack('c', $value);
      }
    } else if (is_string($value)) {
      return $this->marker(0x80, 0xd0, strlen($value)).$value;
    } else if (is_array($value)) {
      if (0 === key($value)) {
        $r= $this->marker(0x90, 0xd4, sizeof($value));
        foreach ($value as $val) {
          $r.= $this->serialize($val);
        }
        return $r;
      } else {
        $r= $this->marker(0xa0, 0xd8, sizeof($value));
        foreach ($value as $key => $val) {
          $r.= $this->serialize($key).$this->serialize($val);
        }
        return $r;
      }
    } else if (is_float($value)) {
      $packed= pack('d', $value);
      return "\xc1".(self::$reverse ? strrev($packed) : $packed);
    } else {
      throw new IllegalStateException('Cannot serialize '.typeof($value)->getName());
    }
  }

  /** Unserializes lists */
  private function lists($l, $value, &$offset= 0) {
    $r= [];
    for ($i= 0; $i < $l; $i++) {
      $r[]= $this->unserialize($value, $offset);
    }
    return $r;
  }

  /** Unserializes maps */
  private function maps($l, $value, &$offset= 0) {
    $r= [];
    for ($i= 0; $i < $l; $i++) {
      $r[$this->unserialize($value, $offset)]= $this->unserialize($value, $offset);
    }
    return $r;
  }

  /** Unserializes structs */
  private function structs($l, $value, &$offset= 0) {
    $signature= $value{$offset++};
    $args= [];
    for ($i= 0; $i < $l; $i++) {
      $args[]= $this->unserialize($value, $offset);
    }

    if ("\x71" === $signature) {          // Record
      return ['row' => $args[0], 'meta' => null];
    } else if ("\x4e" === $signature) {   // Node, return properties
      return $args[2];
    } else if ("\x50" === $signature) {   // Path, return (a.properties)-[r.properties]->(b.properties)<...>
      $p= [$args[0][0]];
      for ($i= 0, $s= sizeof($args[2]); $i < $s; ) {
        $r= $args[2][$i++];
        if ($r < 0) {
          $p[]= $args[1][-$r - 1];
        } else {
          $p[]= $args[1][$r - 1];
        }
        $p[]= $args[0][$args[2][$i++]];
      }
      return $p;
    } else if ("\x52" === $signature) {   // Relationship, return properties
      return $args[4];
    } else if ("\x72" === $signature) {   // UnboundRelationship, return properties
      return $args[2];
    } else {
      throw new IllegalStateException(sprintf('Unknown value struct with signature 0x%02x', ord($signature)));
    }
  }

  /**
   * Unserialize a given input string
   *
   * @param  string $input
   * @return var
   */
  public function unserialize($input, &$offset= 0) {
    $marker= $input{$offset};
    if ("\xc0" === $marker) {
      $offset+= 1;
      return null;
    } else if ("\xc1" === $marker) {
      $offset+= 9;
      $bytes= substr($input, $offset - 8, 8);
      return unpack('d', self::$reverse ? strrev($bytes) : $bytes)[1];
    } else if ("\xc2" === $marker) {
      $offset+= 1;
      return false;
    } else if ("\xc3" === $marker) {
      $offset+= 1;
      return true;
    } else if ("\xc8" === $marker) {
      $offset+= 2;
      return unpack('c', $input{$offset - 1})[1];
    } else if ("\xc9" === $marker) {
      $offset+= 3;
      $bytes= substr($input, $offset - 2, 2);
      return unpack('s', self::$reverse ? strrev($bytes) : $bytes)[1];
    } else if ("\xca" === $marker) {
      $offset+= 5;
      $bytes= substr($input, $offset - 4, 4);
      return unpack('l', self::$reverse ? strrev($bytes) : $bytes)[1];
    } else if ("\xcb" === $marker) {
      $offset+= 9;
      $bytes= substr($input, $offset - 8, 8);
      return unpack('q', self::$reverse ? strrev($bytes) : $bytes)[1];
    } else if ("\xd0" === $marker) {
      $l= unpack('C', $input{$offset + 1})[1];
      $offset+= $l + 2;
      return substr($input, $offset - $l, $l);
    } else if ("\xd1" === $marker) {
      $l= unpack('n', substr($input, $offset + 1, 2))[1];
      $offset+= $l + 3;
      return substr($input, $offset - $l, $l);
    } else if ("\xd2" === $marker) {
      $l= unpack('N', substr($input, $offset + 1, 4))[1];
      $offset+= $l + 5;
      return substr($input, $offset - $l, $l);
    } else if ("\xd4" === $marker) {
      $l= ord($input{$offset + 1});
      $offset+= 2;
      return $this->lists($l, $input, $offset);
    } else if ("\xd5" === $marker) {
      $l= unpack('n', substr($input, $offset + 1, 2))[1];
      $offset+= 3;
      return $this->lists($l, $input, $offset);
    } else if ("\xd6" === $marker) {
      $l= unpack('N', substr($input, $offset + 1, 4))[1];
      $offset+= 5;
      return $this->lists($l, $input, $offset);
    } else if ("\xd8" === $marker) {
      $l= unpack('C', $input{$offset + 1})[1];
      $offset+= 2;
      return $this->maps($l, $input, $offset);
    } else if ("\xd9" === $marker) {
      $l= unpack('n', substr($input, $offset + 1, 2))[1];
      $offset+= 3;
      return $this->maps($l, $input, $offset);
    } else if ("\xda" === $marker) {
      $l= unpack('N', substr($input, $offset + 1, 4))[1];
      $offset+= 5;
      return $this->maps($l, $input, $offset);
    } else if ($marker >= "\x00" && $marker <= "\x7f") {
      $offset+= 1;
      return ord($marker);
    } else if ($marker >= "\xf0" && $marker <= "\xff") {
      $offset+= 1;
      return ord($marker) - 0x100;
    } else if ($marker >= "\x80" && $marker <= "\x8f") {
      $l= ord($marker) - 0x80;
      $offset+= $l + 1;
      return 0 === $l ? '' : substr($input, $offset - $l, $l);
    } else if ($marker >= "\x90" && $marker <= "\x9f") {
      $l= ord($marker) - 0x90;
      $offset++;
      return $this->lists($l, $input, $offset);
    } else if ($marker >= "\xa0" && $marker <= "\xaf") {
      $l= ord($marker) - 0xa0;
      $offset++;
      return $this->maps($l, $input, $offset);
    } else if ($marker >= "\xb0" && $marker <= "\xbf") {
      $l= ord($marker) - 0xb0;
      $offset++;
      return $this->structs($l, $input, $offset);
    } else {
      throw new IllegalStateException(sprintf('Unknown marker 0x%02x', ord($marker)));
    }
  }
}
