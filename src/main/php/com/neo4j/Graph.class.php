<?php namespace com\neo4j;

use peer\http\{HttpConnection, HttpRequest, RequestData};
use text\json\{Format, Json, StreamInput};

/**
 * Neo4J interface using its HTTP API
 * 
 * @see   http://neo4j.com/docs/developer-manual/current/http-api/
 * @see   https://neo4j.com/blog/streaming-rest-api-interview-with-michael-hunger/
 * @test  xp://com.neo4j.unittest.GraphTest
 */
class Graph implements \lang\Value {
  private $conn, $cypher, $json, $base;

  /**
   * Creates a new Neo4J graph connection
   *
   * @param  string|peer.URL|peer.http.HttpConnection $endpoint
   */
  public function __construct($endpoint) {
    $this->conn= $endpoint instanceof HttpConnection ? $endpoint : new HttpConnection($endpoint);
    $this->cypher= new Cypher();
    $this->json= Format::dense();
    $this->base= rtrim($this->conn->getURL()->getPath(), '/');
  }

  /**
   * Commits multiple statements using `transaction/commit` endpoint.
   *
   * @param  [:var][] $payload
   * @return [:var] Results
   */
  protected function commit($payload) {
    $req= $this->conn->create(new HttpRequest());
    $req->setMethod('POST');
    $req->setTarget($this->base.'/transaction/commit');
    $req->setHeader('X-Stream', 'true');
    $req->setHeader('Content-Type', 'application/json');
    $req->setParameters(new RequestData(Json::of($payload, $this->json)));

    $res= $this->conn->send($req);
    if (200 !== $res->statusCode()) {
      throw new QueryFailed(['Unexpected HTTP response status '.$res->statusCode()]);
    }

    return Json::read(new StreamInput($res->in()));
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

  /** @return string */
  public function toString() {
    return nameof($this).'(->'.$this->conn->toString().')';
  }

  /** @return string */
  public function hashCode() {
    return spl_object_hash($this);
  }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $this === $value ? 0 : 1;
  }
}