<?php namespace com\neo4j\unittest;

use com\neo4j\{Graph, QueryFailed};
use lang\{FormatException, IndexOutOfBoundsException};
use peer\URL;
use peer\http\HttpConnection;
use test\{Assert, Expect, Test, TestCase};

class GraphTest {
  public static $ROW = ['columns' => ['id(n)'], 'data' => [['row' => [6], 'meta' => [null]]]];
  public static $EMPTY = ['columns' => [], 'data' => []];
  private static $ECHO;

  static function __static() {
    self::$ECHO= function($payload) {
      $results= [];
      foreach ($payload['statements'] as $s) {
        $results[]= [
          'columns' => ['query', 'params'],
          'data'    => [['row' => [$s['statement'], $s['parameters'] ?? null], 'meta' => [null]]]
        ];
      }
      return ['results' => $results, 'errors'  => []];
    };
  }

  /** Creates a fixture with a given function for producing results */
  private function newFixture($resultsFor= null) {
    return new class($resultsFor) extends Graph {
      private $resultsFor;

      public function __construct($resultsFor) {
        parent::__construct('http://localhost:7474/db/data');
        $this->resultsFor= $resultsFor;
      }

      public function commit($payload) {
        return $this->resultsFor ? ($this->resultsFor)($payload) : null;
      }
    };
  }

  #[Test]
  public function can_create() {
    new Graph('http://localhost:7474/db/data');
  }

  #[Test]
  public function can_create_with_url() {
    new Graph(new URL('http://localhost:7474/db/data'));
  }

  #[Test]
  public function can_create_with_http_connection() {
    new Graph(new HttpConnection('http://localhost:7474/db/data'));
  }

  #[Test]
  public function query_returns_results() {
    $fixture= $this->newFixture(function($payload) {
      return ['results' => [self::$ROW], 'errors' => []];
    });
    Assert::equals([['id(n)' => 6]], $fixture->query('...'));
  }

  #[Test]
  public function query_returns_empty_array_for_no_results() {
    $fixture= $this->newFixture(function($payload) {
      return ['results' => [self::$EMPTY], 'errors' => []];
    });
    Assert::equals([], $fixture->query('...'));
  }

  #[Test, Expect(QueryFailed::class)]
  public function query_raises_error() {
    $fixture= $this->newFixture(function($payload) {
      return ['results' => [], 'errors' => [['code' => 'Neo.ClientError.Statement.SyntaxError', 'message' => '...']]];
    });
    $fixture->query('...');
  }

  #[Test]
  public function query_formatted() {
    Assert::equals(
      [['query' => 'match (n:{id:{P0}})', 'params' => ['P0' => 1549]]],
      $this->newFixture(self::$ECHO)->query('match (n:{id:%v})', 1549)
    );
  }

  #[Test]
  public function query_parameterized() {
    Assert::equals(
      [['query' => 'create (n {props}) return n', 'params' => ['props' => ['name' => 'Test']]]],
      $this->newFixture(self::$ECHO)->query('create (n {props}) return n', ['props' => ['name' => 'Test']])
    );
  }

  #[Test]
  public function fetch_returns_single_result() {
    $fixture= $this->newFixture(function($payload) {
      return ['results' => [self::$ROW], 'errors' => []];
    });
    Assert::equals(['id(n)' => 6], $fixture->fetch('...'));
  }

  #[Test]
  public function fetch_returns_null_for_no_results() {
    $fixture= $this->newFixture(function($payload) {
      return ['results' => [self::$EMPTY], 'errors' => []];
    });
    Assert::equals(null, $fixture->fetch('...'));
  }

  #[Test, Expect(QueryFailed::class)]
  public function fetch_raises_error() {
    $fixture= $this->newFixture(function($payload) {
      return ['results' => [], 'errors' => [['code' => 'Neo.ClientError.Statement.SyntaxError', 'message' => '...']]];
    });
    $fixture->fetch('...');
  }

  #[Test]
  public function execute_returns_result() {
    $fixture= $this->newFixture(function($payload) {
      return ['results' => [self::$ROW], 'errors' => []];
    });
    Assert::equals([self::$ROW], $fixture->execute(['...']));
  }

  #[Test, Expect(QueryFailed::class)]
  public function execute_raises_error() {
    $fixture= $this->newFixture(function($payload) {
      return ['results' => [], 'errors' => [['code' => 'Neo.ClientError.Statement.SyntaxError', 'message' => '...']]];
    });
    $fixture->execute(['...']);
  }

  #[Test]
  public function execute_one() {
    Assert::equals(
      [[
        'columns' => ['query', 'params'],
        'data'    => [['row' => ['match (n) return n', null], 'meta' => [null]]]
      ]],
      $this->newFixture(self::$ECHO)->execute(['match (n) return n'])
    );
  }

  #[Test]
  public function execute_multiple() {
    Assert::equals(
      [[
        'columns' => ['query', 'params'],
        'data'    => [['row' => ['match (n) return n', null], 'meta' => [null]]]
      ], [
        'columns' => ['query', 'params'],
        'data'    => [['row' => ['match (n) where exists(n.delete) detach delete n', null], 'meta' => [null]]]
      ]],
      $this->newFixture(self::$ECHO)->execute(['match (n) return n', 'match (n) where exists(n.delete) detach delete n'])
    );
  }
}