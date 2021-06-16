<?php
namespace App\Lib;

use Muvon\KISS\RequestTrait;

final class Cloutangel {
  use RequestTrait;

  protected string $network;
  final protected function __construct(protected string $url, protected string $token) {}

  public static function create(string $url, string $token) {
    $Obj = new self($url, $token);
    $Obj->request_type = 'json';
    return $Obj;
  }

  public function setNetwork(string $network) {
    $this->network = $network;
  }

  public function getBlockCount() {
    return $this->call('block/height');
  }

  public function getBlock(int|string $height, int $offset = 0, int $limit = 100) {
    return $this->call('block/get/' . $height, ['expand' => true, 'offset' => $offset, 'limit' => $limit]);
  }

  public function getBlockList(array $heights) {
    return $this->call('block/list', ['heights' => $heights], 'POST');
  }

  public function getTx(string $hash) {
    return $this->call('tx/get/' . $hash);
  }

  public function getTxList(array $hashes) {
    return $this->call('tx/list', ['hashes' => $hashes], 'POST');
  }

  public function getAddressBalance(string $address) {
    return $this->call('address/balance/' . $address);
  }

  public function getAddressInfo(string $address, int $offset = 0, int $limit = 100) {
    [$err, $result] = $this->call('address/get/' . $address, ['expand' => true, 'offset' => $offset, 'limit' => $limit]);
    if ($err) {
      return [$err, $result];
    }

    $result['txs'] = array_map(function ($item) {
      $item['IsMempool'] = $item['BlockHeight'] === -1;
      return $item;
    }, $result['txs']);
    return [null, $result];
  }

  protected function call(string $method, array $params = [], $http_method = 'GET') {
    [$err, $response] = $this->request(
      $this->url . '/v1/' . $method,
      $params,
      $http_method,
      [
        'X-API-Token: ' . $this->token
      ]
    );

    if ($err) {
      return [$err, null];
    }

    return $response;
  }
}