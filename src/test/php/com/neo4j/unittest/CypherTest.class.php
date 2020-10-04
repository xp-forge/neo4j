<?php namespace com\neo4j\unittest;

use com\neo4j\Cypher;
use lang\{FormatException, IndexOutOfBoundsException};
use unittest\{Expect, Test, Values};

class CypherTest extends \unittest\TestCase {
  private $fixture;

  /** @return void */
  public function setUp() {
    $this->fixture= new Cypher();
  }

  #[Test, Values([['match (e:Employee{id:{P0}})', ['P0' => 1549], 'match (e:Employee{id:%d})', 1549], ['match (e:Employee{id:{P0}})', ['P0' => 1549], 'match (e:Employee{id:%d})', '1549'], ['match (e:Employee{age:{P0}})', ['P0' => 15.5], 'match (e:Employee{age:%f})', '15.5'], ['match (e:Employee{age:{P0}})', ['P0' => 15.5], 'match (e:Employee{age:%f})', '15.5'], ['match (e:Employee{uid:{P0}})', ['P0' => 'friebe'], 'match (e:Employee{uid:%s})', 'friebe'], ['match (e:Employee{uid:{P0}})', ['P0' => '1549'], 'match (e:Employee{uid:%s})', 1549], ['match (e:Employee{head:{P0}})', ['P0' => true], 'match (e:Employee{head:%b})', true], ['match (e:Employee{head:{P0}})', ['P0' => true], 'match (e:Employee{head:%b})', 1], ['match (e:Employee{id:{P0}})', ['P0' => null], 'match (e:Employee{id:%d})', null], ['match (e:Employee{age:{P0}})', ['P0' => null], 'match (e:Employee{age:%f})', null], ['match (e:Employee{uid:{P0}})', ['P0' => null], 'match (e:Employee{uid:%s})', null], ['match (e:Employee{head:{P0}})', ['P0' => null], 'match (e:Employee{head:%b})', null]])]
  public function format_scalars($query, $params, $format, ... $args) {
    $this->assertEquals(
      ['statement' => $query, 'parameters' => $params],
      $this->fixture->format($format, ...$args)
    );
  }

  #[Test, Values([['e.id in {P0}', ['P0' => []], 'e.id in %d', []], ['e.id in {P0}', ['P0' => [1549]], 'e.id in %d', [1549]], ['e.id in {P0}', ['P0' => [1549, 1552]], 'e.id in %d', [1549, '1552']], ['e.age in {P0}', ['P0' => [15.5, 15.0]], 'e.age in %f', [15.5, '15.0']], ['e.uid in {P0}', ['P0' => ['1549', 'friebe']], 'e.uid in %s', [1549, 'friebe']],])]
  public function format_arrays($query, $params, $format, ... $args) {
    $this->assertEquals(
      ['statement' => $query, 'parameters' => $params],
      $this->fixture->format($format, ...$args)
    );
  }

  #[Test, Values([['match (n{uid:{P0}})', ['P0' => ['friebe']], 'match (n{uid:%v})', ['friebe']], ['match (n{uid:{P0}})', ['P0' => [1549]], 'match (n{uid:%v})', [1549]],])]
  public function format_value($query, $params, $format, ... $args) {
    $this->assertEquals(
      ['statement' => $query, 'parameters' => $params],
      $this->fixture->format($format, ...$args)
    );
  }

  #[Test]
  public function positional_format_args() {
    $this->assertEquals(
      ['statement' => 'match (e:Employee{id:{P1}}), (t:Topic{name:{P0}})', 'parameters' => ['P1' => 1549, 'P0' => 'test']],
      $this->fixture->format('match (e:Employee{id:%2$d}), (t:Topic{name:%1$s})', 'test', 1549)
    );
  }

  #[Test]
  public function label_format_arg() {
    $this->assertEquals(
      ['statement' => 'match (e:Employee) return id(e) as `ID``OF`, e', 'parameters' => []],
      $this->fixture->format('match (e:Employee) return id(e) as %l, e', 'ID`OF')
    );
  }

  #[Test]
  public function copy_through_format_arg() {
    $this->assertEquals(
      ['statement' => 'match (e:Employee)-[:WORKS_ON]->(n)', 'parameters' => []],
      $this->fixture->format('match (e:Employee)-[:%c]->(n)', 'WORKS_ON')
    );
  }

  #[Test, Expect(FormatException::class)]
  public function illegal_format_arg() {
    $this->fixture->format('match (e:Employee)-[:%X]->(n)', 'WORKS_ON');
  }

  #[Test, Expect(IndexOutOfBoundsException::class), Values([['match (e:Employee)-[:%0$d]->(n)'], ['match (e:Employee)-[:%2$d]->(n)'], ['match (e:Employee)-[:%0$d]->(n)', 'WORKS_ON'], ['match (e:Employee)-[:%2$d]->(n)', 'WORKS_ON']])]
  public function invalid_positional_arg($query, ... $args) {
    $this->fixture->format($query, ...$args);
  }
}