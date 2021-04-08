<?php

use PHPUnit\Framework\TestCase;
use Muvon\KISS\RequestTrait;

final class Request {
  use RequestTrait;

  public function setRequestType(string $value): self {
    $this->request_type = $value;
    return $this;
  }

  public function setRequestConnectTimeout(int $value): self {
    $this->request_connect_timeout = $value;
    return $this;
  }

  public function setRequestTimeout(int $value): self {
    $this->request_timeout = $value;
    return $this;
  }

  public function run(string $url, array $payload = [], string $method = 'POST') {
    return $this->request($url, $payload, $method);
  }

  public function runMulti(array $requests): array {
    $this->multi();
    foreach ($requests as $request) {
      $this->request(...$request);
    }
    return $this->exec();
  }
}

final class RequestTest extends TestCase {
  protected Request $Client;

  public function setUp(): void {
    $this->Client = new Request;
  }

  public function testUnavailableUrlRerturnsError() {
    [$err, $res] = $this->Client->run('undefined');
    $this->assertEquals('e_request_failed', $err);
    $this->assertEquals('CURL 6: Could not resolve host: undefined', $res);
  }

  public function testRequestGoogleSucceed() {
    [$err, $res] = $this->Client->run('https://www.google.com/', [], 'GET');
    $this->assertEquals(null, $err);
    $this->assertIsString($res);
  }

  public function testWeDoNotFollowRedirects() {
    [$err, $res] = $this->Client->run('https://google.com', [], 'GET');
    $this->assertEquals('e_request_failed', $err);
  }

  public function testRequestMultiSucceed() {
    $responses = $this->Client->runMulti([
      ['https://www.google.com', [], 'GET'],
      ['https://yandex.ru', [], 'GET'],
    ]);
    $this->assertEquals(2, sizeof($responses));
    $this->assertEquals(null, $responses[0][0]);
    $this->assertEquals(null, $responses[1][0]);
    $this->assertIsString($responses[0][1]);
    $this->assertIsString($responses[1][1]);
    $this->assertNotEquals($responses[0][1], $responses[1][1]);
  }
}