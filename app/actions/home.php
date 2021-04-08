<?php
/**
 * @route home
 */

$api = container('api');

$block_height = result($api->getBLockCount()) - 1;
$block_heights = array_reverse(range($block_height - 8, $block_height));
$blocks = array_values(result($api->getBlockList($block_heights)));
