<?php
use App\Lib\Cloutangel;

container('api', function() {
  return Cloutangel::create(
    'https://api.cloutangel.com',
    'no-token-yet'
  );
});


View::registerFilterFunc('amount', 'nano_to_amount');
function nano_to_amount($v) {
  return bcdiv($v, 10**9, 9);
}