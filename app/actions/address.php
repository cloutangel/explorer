<?php
/**
 * @route address/(BC1[a-zA-Z0-9]{52}): address
 * @route address/(\@[a-zA-Z0-9]+): username
 * @param string $address
 * @param string $username
 * @param uint $p 1
 */
use App\Component\Pagination;

$api = container('api');

$limit = 100;
$offset = $p * $limit - $limit;
[$err, $info] = $api->getAddressInfo($username ?: $address, $offset, $limit);
if (!$err) {
  $info['txs'] = array_values($info['txs']);
  $last = ceil($info['tx_count'] / $limit);
  $pagination = Pagination::get('/address/' . ($username ?: $address), $p, $last);
}

if ($err) {
  $info = null;
}