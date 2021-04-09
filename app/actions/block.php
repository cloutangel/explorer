<?php
/**
 * @route block/([0-9]+): height
 * @route block/([a-f0-9]{64}): hash
 * @param uint $height
 * @param string $hash
 */

$api = container('api');
[$err, $block] = $api->getBlock($hash ?: $height);
if ($err) {
  $block = null;
}