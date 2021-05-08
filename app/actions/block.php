<?php
/**
 * @route block/([0-9]+): height
 * @route block/([a-f0-9]{64}): hash
 * @param uint $height
 * @param string $hash
 * @param uint $p 1
 */

use App\Component\Pagination;

$api = container('api');
$limit = 100;
$offset = $p * $limit - $limit;
[$err, $block] = $api->getBlock($hash ?: $height, $offset, $limit);
if ($err) {
  $block = null;
} else {
  $last = ceil($block['TransactionCount'] / $limit);
  $pagination = Pagination::get('/block/' . $height, $p, $last);
}