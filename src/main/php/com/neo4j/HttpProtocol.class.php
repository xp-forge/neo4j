<?php namespace com\neo4j;

use peer\URL;
use peer\http\HttpConnection;
use peer\http\HttpRequest;
use peer\http\RequestData;
use text\json\Json;
use text\json\Format;
use text\json\StreamInput;
use io\IOException;

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
   * @throws com.neo4j.UnexpectedResponse
   */
  public function commit($payload) {
    $req= $this->conn->create(new HttpRequest());
    $req->setMethod('POST');
    $req->setTarget($this->base.'/transaction/commit');
    $req->setHeader('X-Stream', 'true');
    $req->setHeader('Content-Type', 'application/json');
    $req->setParameters(new RequestData(Json::of($payload, $this->json)));

    try {
      $res= $this->conn->send($req);
    } catch (IOException $e) {
      throw new QueryFailed(['I/O error'], $e);
    }

    if (200 === $res->statusCode()) {
      return Json::read(new StreamInput($res->in()));
    } else if (401 === $res->statusCode()) {
      throw new CannotAuthenticate([$res->readData()]);
    } else {
      throw new UnexpectedResponse(['Unexpected HTTP response status '.$res->statusCode(), $res->readData()]);
    }
  }
}