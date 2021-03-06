<?php

use App\Component\Pagination;
/**
 * @route home
 * @param string $search
 * @param uint $p 1
 */

if ($search) {
  $ns = match (true) {
    str_starts_with($search, 'BC1') => 'address',
    str_starts_with($search, '3J') => 'tx',
    trim($search, '0..9') === '', strlen($search) === 64 && trim($search, 'a-f0-9') => 'block',
    default => 'username',
  };

  if ($ns === 'username') {
    if (!str_starts_with($search, '@')) {
      $search = '@' . $search;
    }

    $ns = 'address';
  }

  if ($ns) {
    return Response::redirect('/' . $ns . '/' . $search);
  }
}
$api = container('api');

$block_height = result($api->getBLockCount()) - 1;
$limit = 20;
$start_block = $block_height - ($limit * $p) + 1;
$block_heights = array_reverse(range($start_block + 1, $start_block + $limit));
$blocks = array_values(result($api->getBlockList($block_heights)));


$pagination = Pagination::get('/', $p, ceil($block_height / $limit));