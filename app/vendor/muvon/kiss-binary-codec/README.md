# php-kiss-binary-codec

Binary codec for encoding multiple structures with predefined config

It works only with array as input cuz created for complex structure and detect best encoding option for data and return mostly optimized data for hashes and ints with big arrays

## Encoder flow

Encoder uses pack to encode and unpack to decode with special modifications and extra addons.

Binary contains of data

- 1 byte protocol version
- 4 bytes size of meta
- x bytes meta data with pack info and keys encoded with gzip
- x bytes encoded binary data

## Sample of usage

```php
$data = json_decode('[null,[{"hash":"80ca095ed10b02e53d769eb6eaf92cd04e9e0759e5be4a8477b42911ba49c78f","strippedsize":215,"size":215,"weight":860,"height":1,"version":1,"versionHex":"00000001","merkleroot":"fa3906a4219078364372d0e2715f93e822edd0b47ce146c71ba7ba57179b50f6","tx":["fa3906a4219078364372d0e2715f93e822edd0b47ce146c71ba7ba57179b50f6"],"time":1318055359,"mediantime":1318055359,"nonce":272255,"bits":"1e0ffff0","difficulty":"0.000244140625","chainwork":"0000000000000000000000000000000000000000000000000000000000200020","nTx":1,"previousblockhash":"12a765e31ffd4059bada1e25190f6e98c99d9714d334efa41a195a7e7e04bfe2","nextblockhash":"13957807cdd1d02f993909fa59510e318763f99a506c4c426e3b254af09f40d7"},{"hash":"a4ad8c78654e76a729c12dd10522a40ba7077167bbf5b28fc9c71d25fcd67226","strippedsize":215,"size":215,"weight":860,"height":20,"version":1,"versionHex":"00000001","merkleroot":"732300fac0d661a2bf01ef2f521460cc26eeb608939c196ab34221f6533d60f7","tx":["732300fac0d661a2bf01ef2f521460cc26eeb608939c196ab34221f6533d60f7"],"time":1318474885,"mediantime":1318474854,"nonce":1164,"bits":"1e0ffff0","difficulty":"0.000244140625","chainwork":"0000000000000000000000000000000000000000000000000000000001500150","nTx":1,"previousblockhash":"6ea63d7aa791c13042b72c6855f089ef78ed45c3b8dc3263fc0e8cb8b3070f05","nextblockhash":"31ed087d6effc647d37b6e04f7e7da9e04b270d95262b21c8275aa10fc0c7104"},{"hash":"537ad94bc3b5927e32cdb6bc2d59adf54a010901e45f1602738a709d4d67f53b","strippedsize":215,"size":215,"weight":860,"height":3422,"version":1,"versionHex":"00000001","merkleroot":"003632bba01886ab81df69d734665ec00562f6363e82286c4ace45d700d86f8e","tx":["003632bba01886ab81df69d734665ec00562f6363e82286c4ace45d700d86f8e"],"time":1318483933,"mediantime":1318483922,"nonce":1246,"bits":"1e0fffff","difficulty":"0.0002441371325370145","chainwork":"00000000000000000000000000000000000000000000000000000000d5f0837f","nTx":1,"previousblockhash":"4e2695a5d2d0c05ba6c264a51cea731cd61567b66e54cb0450f05b169fc27ea5","nextblockhash":"2707f4d6ca57db167afb88b98e97f8956d4c34cc9c6cc5155ad1e48330278241"}]]', true);

var_dump(binary_pack($data));

// string(953)

var_dump(msgpack_pack($data));

// string(1844)
```

## Unique features

The coded uses automatic detection data and encode is with best way.

1. Hex strings (transaction hash for example)
2. Big numeric as string converted to binary without losing precision
3. Big float as string converted to binary without losing precision
4. Hash map extracts all keys and converted as plain
5. Int size determined by input value and it uses smaller one

## Test coverage

- [x] String encode
- [x] Bool encode
- [x] Integer encode
- [x] Float encode
- [x] Hex encode
- [x] Int list encode
- [x] Hex list encode
- [x] Null encode
- [x] Int as string encode
- [x] Float as string encode
- [x] Ipv 4 encode
- [x] List encode
- [x] Map encode