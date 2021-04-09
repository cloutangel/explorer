<?php
use App\Lib\Cloutangel;

container('api', function() {
  return Cloutangel::create(
    'https://api.cloutangel.com',
    'no-token-yet'
  );
});


View::registerFilterFunc('amount', 'nano_to_amount');
View::registerFilterFunc('btc', 'satoshi_to_bitcoin');
function nano_to_amount($v) {
  return bcdiv($v, 10**9, 9);
}
function satoshi_to_bitcoin($v) {
  return bcdiv($v, 10 ** 8, 8);
}