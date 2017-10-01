<?php namespace com\neo4j;

/**
 * Bolt protocol message serialization
 *
 * @see   https://boltprotocol.org/v1/#serialization
 */
class Serialization {

  private function marker($base, $top, $length) {
    if ($length < 16) {
      return pack('c', $base + $length);
    } else if ($length < 256) {
      return pack('cc', $top, $length);
    } else if ($length < 65536) {
      return pack('cn', $top + 1, $length);
    } else {
      return pack('cN', $top + 2, $length);
    }
  }

  public function serialize($value) {
    if (null === $value) {
      return "\xc0";
    } else if (true === $value) {
      return "\xc3";
    } else if (false === $value) {
      return "\xc2";
    } else if (is_int($value)) {
      return "\xca".pack('N', $value);        
    } else if (is_string($value)) {
      return $this->marker(0x80, 0xd0, strlen($value)).$value;
    } else if (is_array($value)) {
      $r= $this->marker(0xa0, 0xd8, sizeof($value));
      foreach ($value as $key => $val) {
        $r.= $this->serialize($key).$this->serialize($val);
      }
      return $r;
    }
  }

  public function unserialize($value, &$offset= 0) {
    $marker= $value{$offset};
    if ("\xc0" === $marker) {
      $offset+= 1;
      return null;
    } else if ("\xc1" === $marker) {
      $offset+= 9;
      return unpack('d', strrev(substr($value, $offset - 8, 8)))[1];
    } else if ("\xc2" === $marker) {
      $offset+= 1;
      return true;
    } else if ("\xc3" === $marker) {
      $offset+= 1;
      return true;
    } else if ("\xc9" === $marker) {
      $offset+= 3;
      return unpack('n', substr($value, $offset - 2, 2))[1];
    } else if ("\xca" === $marker) {
      $offset+= 5;
      return unpack('N', substr($value, $offset - 4, 4))[1];
    } else if ("\xcb" === $marker) {
      $offset+= 9;
      return unpack('J', substr($value, $offset - 8, 8))[1];
    } else if ("\xd0" === $marker) {
      $l= unpack('c', $value{$offset + 1})[1];
      $offset+= $l + 2;
      return substr($value, $offset - $l, $l);
    } else if ("\xd1" === $marker) {
      $l= unpack('n', substr($value, $offset + 1, 2))[1];
      $offset+= $l + 3;
      return substr($value, $offset - $l, $l);
    } else if ("\xd2" === $marker) {
      $l= unpack('N', substr($value, $offset + 1, 4))[1];
      $offset+= $l + 5;
      return substr($value, $offset - $l, $l);
    } else if ("\xd4" === $marker) {
      $l= ord($value{$offset + 1});
      $offset+= 2;
      $r= [];
      for ($i= 0; $i < $l; $i++) {
        $val= $this->unserialize($value, $offset);
        $r[]= $val;
      }
      return $r;
    } else if ($marker >= "\x00" && $marker <= "\x7f") {
      $offset+= 1;
      return ord($marker);
    } else if ($marker >= "\x80" && $marker <= "\x8f") {
      $l= ord($marker) - 0x80;
      $offset+= $l + 1;
      return substr($value, $offset - $l, $l);
    } else if ($marker >= "\x90" && $marker <= "\x9f") {
      $l= ord($marker) - 0x90;
      $offset++;
      $r= [];
      for ($i= 0; $i < $l; $i++) {
        $val= $this->unserialize($value, $offset);
        $r[]= $val;
      }
      return $r;
    } else if ($marker >= "\xb0" && $marker <= "\xbf") {
      $l= ord($marker) - 0xb0;
      $offset++;
      $r= new Struct($this->unserialize($value, $offset));
      for ($i= 1; $i <= $l; $i++) {
        $val= $this->unserialize($value, $offset);
        $r->put($i, $val);
      }
      return $r;
    } else if ($marker >= "\xa0" && $marker <= "\xaf") {
      $l= ord($marker) - 0xa0;
      $offset++;
      $r= [];
      for ($i= 0; $i < $l; $i++) {
        $key= $this->unserialize($value, $offset);
        $val= $this->unserialize($value, $offset);
        $r[$key]= $val;
      }
      return $r;
    } else {
      return sprintf("MARKER:0x%02x", ord($marker));
    }
  }
}
