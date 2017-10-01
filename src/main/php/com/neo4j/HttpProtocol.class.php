<?php namespace com\neo4j;

use peer\URL;
use peer\http\HttpConnection;
use peer\http\HttpRequest;
use peer\http\RequestData;
use text\json\Json;
use text\json\Format;
use text\json\StreamInput;

class HttpProtocol extends Protocol {
  private $conn, $json, $base;

  /**
   * Creates a new Neo4J graph connection
   *
   * @param  peer.URL $endpoint
   */
  public function __construct(URL $endpoint) {
    $this->conn= new HttpConnection($endpoint);
    $this->json= Format::dense();
    $this->base= rtrim($this->conn->getURL()->getPath(), '/');
  }

  /**
   * Commits multiple statements using `transaction/commit` endpoint.
   *
   * @param  [:var][] $payload
   * @return [:var] Results
   */
  public function commit($payload) {
    $req= $this->conn->create(new HttpRequest());
    $req->setMethod('POST');
    $req->setTarget($this->base.'/transaction/commit');
    $req->setHeader('X-Stream', 'true');
    $req->setHeader('Content-Type', 'application/json');
    $req->setParameters(new RequestData(Json::of($payload, $this->json)));

    $res= $this->conn->send($req);
    if (200 !== $res->statusCode()) {
      throw new QueryFailed(['Unexpected HTTP response status '.$res->statusCode(), $res->readData()]);
    }

    return Json::read(new StreamInput($res->in()));
  }
}