<?php
/**
 * @route tx/(3J[a-zA-Z0-9]+): hash
 * @param string $hash
 */

$api = container('api');

[$err, $tx] = $api->getTx($hash);
if ($err) {
  $tx = null;
}