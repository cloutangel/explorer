<?php
/**
 * @route address/(BC1[a-zA-Z0-9]{52}): address
 * @param string $address
 */

$api = container('api');

[$err, $info] = $api->getAddressInfo($address);
if ($err) {
  $info = null;
}