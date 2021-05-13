<?php namespace com\neo4j\unittest;

use com\neo4j\Cypher;
use lang\{FormatException, IndexOutOfBoundsException};
use unittest\{Assert, Before, Expect, Test, Values};

class CypherTest {
  private $fixture;

  /** @return iterable */
  private function fixtures() {
    yield ['match (e:Employee{id:{P0}})', ['P0' => null], 'match (e:Employee{id:%d})', null];
    yield ['match (e:Employee{id:{P0}})', ['P0' => 1549], 'match (e:Employee{id:%d})', 1549];
    yield ['match (e:Employee{id:{P0}})', ['P0' => 1549], 'match (e:Employee{id:%d})', '1549'];
    yield ['...where id > {P0}', ['P0' => -1], '...where id > %d', -1];

    yield ['match (e:Employee{age:{P0}})', ['P0' => null], 'match (e:Employee{age:%f})', null];
    yield ['match (e:Employee{age:{P0}})', ['P0' => 15.5], 'match (e:Employee{age:%f})', 15.5];
    yield ['match (e:Employee{age:{P0}})', ['P0' => 15.5], 'match (e:Employee{age:%f})', '15.5'];

    yield ['match (e:Employee{uid:{P0}})', ['P0' => null], 'match (e:Employee{uid:%s})', null];
    yield ['match (e:Employee{uid:{P0}})', ['P0' => 'friebe'], 'match (e:Employee{uid:%s})', 'friebe'];
    yield ['match (e:Employee{uid:{P0}})', ['P0' => '1549'], 'match (e:Employee{uid:%s})', 1549];
    yield ['...where e.name = {P0}', ['P0' => ''], '...where e.name = %s', ''];

    yield ['match (e:Employee{head:{P0}})', ['P0' => null], 'match (e:Employee{head:%b})', null];
    yield ['match (e:Employee{head:{P0}})', ['P0' => true], 'match (e:Employee{head:%b})', true];
    yield ['match (e:Employee{head:{P0}})', ['P0' => false], 'match (e:Employee{head:%b})', false];
    yield ['match (e:Employee{head:{P0}})', ['P0' => true], 'match (e:Employee{head:%b})', 1];
    yield ['match (e:Employee{head:{P0}})', ['P0' => false], 'match (e:Employee{head:%b})', 0];

    yield ['e.id in {P0}', ['P0' => []], 'e.id in %d', []];
    yield ['e.id in {P0}', ['P0' => [1549]], 'e.id in %d', [1549]];
    yield ['e.id in {P0}', ['P0' => [1549, 1552]], 'e.id in %d', [1549, '1552']];
    yield ['e.age in {P0}', ['P0' => [15.5, 15.0]], 'e.age in %f', [15.5, '15.0']];
    yield ['e.uid in {P0}', ['P0' => ['1549', 'friebe']], 'e.uid in %s', [1549, 'friebe']];

    yield ['match (n{uid:{P0}})', ['P0' => ['friebe']], 'match (n{uid:%v})', ['friebe']];
    yield ['match (n{uid:{P0}})', ['P0' => [1549]], 'match (n{uid:%v})', [1549]];
  }

  #[Before]
  public function fixture() {
    $this->fixture= new Cypher();
  }

  #[Test, Values('fixtures')]
  public function format($query, $params, $format, ... $args) {
    Assert::equals(
      ['statement' => $query, 'parameters' => $params],
      $this->fixture->format($format, ...$args)
    );
  }

  #[Test]
  public function positional_format_args() {
    Assert::equals(
      ['statement' => 'match (e:Employee{id:{P1}}), (t:Topic{name:{P0}})', 'parameters' => ['P1' => 1549, 'P0' => 'test']],
      $this->fixture->format('match (e:Employee{id:%2$d}), (t:Topic{name:%1$s})', 'test', 1549)
    );
  }

  #[Test]
  public function label_format_arg() {
    Assert::equals(
      ['statement' => 'match (e:Employee) return id(e) as `ID``OF`, e', 'parameters' => []],
      $this->fixture->format('match (e:Employee) return id(e) as %l, e', 'ID`OF')
    );
  }

  #[Test]
  public function copy_through_format_arg() {
    Assert::equals(
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