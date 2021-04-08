<?php
/**
 * @route home
 */

$api = container('api');

$block_height = result($api->getBLockCount());
$block_heights = range($block_height - 5, $block_height);
$blocks = array_values(result($api->getBlockList($block_heights)));