<?php
include getenv('KISS_CORE');
App::start();

// Process action and get view template if have
$View = App::process()
  ->prepend('_head')
  ->append('_foot')
;

$View->assign('BUNDLE_HASH', filemtime(getenv('VAR_DIR') . '/bundle.css'));

Response::current()->send((string) $View->render());
App::stop();
