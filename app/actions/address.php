<?php
/**
 * @route address/(BC1[a-zA-Z0-9]{52}): address
 * @route address/\@([a-zA-Z0-9]+): username
 * @param string $address
 * @param string $username
 */

$api = container('api');

[$err, $info] = $api->getAddressInfo($username ?: $address);
if (!$err) {
  $info['txs'] = array_values($info['txs']);
}

if ($err) {
  $info = null;
}