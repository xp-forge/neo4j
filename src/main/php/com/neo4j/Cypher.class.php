<?php namespace com\neo4j;

use lang\FormatException;

/**
 * Formats cypher query language and parameters
 *
 * Format characters:
 * - `%s`: Format a string
 * - `%d`: Format a decimal number
 * - `%f`: Format a floating point numer
 * - `%b`: Format a boolean
 * - `%v`: Copy value into parameter
 * - `%l`: Copy label into query
 * - `%c`: Copy literal into query
 * - `%%`: A literal percent sign
 *
 * Positional parameters (starting at 1) may be used, e.g. `%2$s`.
 *
 * @test  xp://com.neo4j.unittest.CypherTest
 * @see   http://neo4j.com/docs/developer-manual/current/cypher/syntax/expressions
 */
class Cypher {

  /** Casts a given value as integer(s) */
  private function int($val) {
    if (is_array($val)) {
      $r= [];
      foreach ($val as $v) $r[]= null === $v ? null : (int)$v;
      return $r;
    } else {
      return null === $val ? null : (int)$val;
    }
  }

  /** Casts a given value as float(s) */
  private function float($val) {
    if (is_array($val)) {
      $r= [];
      foreach ($val as $v) $r[]= null === $v ? null : (float)$v;
      return $r;
    } else {
      return null === $val ? null : (float)$val;
    }
  }

  /** Casts a given value as strings(s) */
  private function string($val) {
    if (is_array($val)) {
      $r= [];
      foreach ($val as $v) $r[]= null === $v ? null : (string)$v;
      return $r;
    } else {
      return null === $val ? null : (string)$val;
    }
  }

  /** Casts a given value as bool(s) */
  private function bool($val) {
    if (is_array($val)) {
      $r= [];
      foreach ($val as $v) $r[]= null === $v ? null : (bool)$v;
      return $r;
    } else {
      return null === $val ? null : (bool)$val;
    }
  }

  /**
   * Formats a statement
   *
   * @param  string $format Cypher query language including format tokens
   * @param  var... $args
   * @return [:var]
   */
  public function format($format, ... $args) {
    $length= strlen($format);
    if (($span= strcspn($format, '%')) < $length) {
      $return= '';
      $offset= $arg= 0;
      $params= [];
      do {
        $return.= substr($format, $offset, $span);
        $offset+= $span + 1;
        if ($offset >= $length) break;

        if (is_numeric($format[$offset])) {
          $span= strcspn($format, '$', $offset);
          $pos= (int)substr($format, $offset, $span) - 1;
          $offset+= $span + 1;
        } else {
          $pos= $arg++;
        }

        switch ($format[$offset]) {
          case 'd': $return.= '{P'.$pos.'}'; $params['P'.$pos]= $this->int($args[$pos]); break;
          case 'f': $return.= '{P'.$pos.'}'; $params['P'.$pos]= $this->float($args[$pos]); break;
          case 's': $return.= '{P'.$pos.'}'; $params['P'.$pos]= $this->string($args[$pos]); break;
          case 'b': $return.= '{P'.$pos.'}'; $params['P'.$pos]= $this->bool($args[$pos]); break;
          case 'v': $return.= '{P'.$pos.'}'; $params['P'.$pos]= $args[$pos]; break;
          case 'l': $return.= '`'.str_replace('`', '``', $args[$pos]).'`'; break;
          case 'c': $return.= $args[$pos]; break;
          case '%': $return.= '%'; break;
          default: throw new FormatException('Unknown format specifier %'.$format[$offset]);
        }

        $offset++;
        $span= strcspn($format, '%', $offset);
      } while (true);
      return ['statement' => $return, 'parameters' => $params];
    } else if ($args) {
      return ['statement' => $format, 'parameters' => $args[0]];
    } else {
      return ['statement' => $format];
    }
  }
}