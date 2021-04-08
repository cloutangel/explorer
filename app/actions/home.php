<?php
/**
 * @route home
 * @param string $search
 */

if ($search) {
  $ns = match (true) {
    str_starts_with($search, 'BC1') => 'address',
    str_starts_with($search, '3J') => 'tx',
    trim($search, '0..9') === '' => 'block',
    strlen($search) === 64 && trim($search, 'a-f0-9') === '' => 'blockhash',
    default => 'username',
  };

  if ($ns === 'username' || $ns === 'blockhash') {
    $ns = null;
  }

  if ($ns) {
    return Response::redirect('/' . $ns . '/' . $search);
  }
}
$api = container('api');

$block_height = result($api->getBLockCount()) - 1;
$block_heights = array_reverse(range($block_height - 19, $block_height));
$blocks = array_values(result($api->getBlockList($block_heights)));
