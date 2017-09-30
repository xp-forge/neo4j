<?php namespace com\neo4j;

use webservices\rest\Endpoint;
use webservices\rest\RestFormat;

/**
 * Neo4J interface using its HTTP API
 * 
 * @see   http://neo4j.com/docs/developer-manual/current/http-api/
 * @see   https://neo4j.com/blog/streaming-rest-api-interview-with-michael-hunger/
 * @test  xp://com.neo4j.unittest.GraphTest
 */
class Graph {
  private $endpoint, $cypher;

  /**
   * Creates a new Neo4J graph connection
   *
   * @param  string $url
   */
  public function __construct($url) {
    $this->endpoint= new Endpoint($url);
    $this->cypher= new Cypher();
  }

  /**
   * Commits multiple statements using `transaction/commit` endpoint.
   *
   * @param  [:var][] $payload
   * @return [:var] Results
   */
  protected function commit($payload) {
    return $this->endpoint->resource('transaction/commit')->with(['X-Stream' => 'true'])->post($payload, RestFormat::$JSON)->data();
  }

  /**
   * Prepares a cypher query
   *
   * @param  string $cypher
   * @param  var... $args
   * @return [:var] query
   */
  public function prepare($cypher, ... $args) {
    return $this->cypher->format($cypher, ...$args);
  }

  /**
   * Runs a single query and yields its results
   *
   * @param  string $cypher
   * @param  var... $args
   * @return iterable
   */
  public function open($cypher, ... $args) {
    $result= $this->execute([$this->cypher->format($cypher, ...$args)])[0];
    foreach ($result['data'] as $data) {
      $record= [];
      foreach ($data['row'] as $i => $value) {
        $record[$result['columns'][$i]]= $value;
      }
      yield $record;
    }
  }

  /**
   * Runs a single query and returns its results in an array
   *
   * @param  string $cypher
   * @param  var... $args
   * @return iterable
   */
  public function query($cypher, ... $args) {
    return iterator_to_array($this->open($cypher, ...$args));
  }

  /**
   * Executes multiple statements
   *
   * @param  [:var][] $statements
   * @return var[]
   */
  public function execute($statements) {
    $list= [];
    foreach ($statements as $statement) {
      $list[]= is_array($statement) ? $statement : ['statement' => $statement];
    }

    $response= $this->commit(['statements' => $list]);
    if (empty($response['errors'])) {
      return $response['results'];
    } else {
      throw new QueryFailed($response['errors']);
    }
  }
}