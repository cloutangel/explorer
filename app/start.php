<?php
use App\Lib\Cloutangel;

container('api', function() {
  return Cloutangel::create(
    'https://api.cloutangel.com',
    'no-token-yet'
  );
});
