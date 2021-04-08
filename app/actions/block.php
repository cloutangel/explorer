<?php
/**
 * @route block/([0-9]+): height
 * @param uint $height
 */

$api = container('api');

[$err, $block] = $api->getBlock($height);
if ($err) {
  $block = null;
  $block_not_found = true;
}