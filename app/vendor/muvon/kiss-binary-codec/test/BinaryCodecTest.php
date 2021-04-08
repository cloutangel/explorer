<?php
use Muvon\KISS\BinaryCodec;
use PHPUnit\Framework\TestCase;

class BinaryCodecTest extends TestCase {
  public function setUp(): void {
    parent::setUp();
    $this->Codec = BinaryCodec::create([

    ]);
  }

  public function testStringEncode(): void {
    $this->testInputs([
      [uniqid()],
      ['Simple string to be encoded'],
      ['Tratata lalala opaopaopa da'],
    ]);
  }

  public function testBoolEncode(): void {
    $this->testInputs([[true], [false]]);
  }

  public function testIntegerEncode(): void {
    $this->testInputs([
      [0, 102325],
      [-12312353],
      [234234235],
      [PHP_INT_MAX]
    ]);
  }

  public function testFloatEncode(): void {
    $this->testInputs([
      [1.2315324234, 1447223.7732],
      [lcg_value(), 0.1232333],
      [lcg_value()],
      [-lcg_value(), -1332222.3333],
      [lcg_value() * 100000],
    ]);
  }

  public function testHexEncode(): void {
    $this->testInputs([
      ['0000112233'],
      ['1f1d0032'],
      ['00000000000000000448ee09360b6363d9a32fd623ca2fa299b6bd9236df3461'],
      ['3da1843c63e2cbb3b4ed4694921ddfbcd911fe5c0b8bd31637a444b9bbf42879'],
      ['89717217b88b']
    ]);
  }

  public function testIntListEncode(): void {
    $this->testInputs([
      ['list_uint1' => range(1, 100)],
      ['list_uint2' => range(2 ** 16 - 100, 2 ** 16 - 1)],
      ['list_uint4' => range(2 ** 32 - 100, 2 ** 32 - 1)],
      ['list_uint8' => [2 ** 48, 2 ** 49, 2 ** 50, 2 ** 51, 2 ** 52, 2 ** 56, 2 ** 60 - mt_rand(1, 100)]],
    ]);
  }

  public function testHexListEncode(): void {
    $txs = json_decode('{"tx":["c603883724ca408643dc32ea273d3e0ec4171f1e5be90559e186af882053bbcd","e868e4503201d2d66545ff2b203022d7400a386261148f27c30739c59becee1e","9982fdca82804ba74b35e1232d4cce23f8a23e9bb978bd04266efd006eb658e8","d4e9014cf46f7c626310fee5785a311c5e1d05ae7997bb746fd3785d247b9091","c8949989387547f9c925e3a350233b8bd87630722f412efd455e702de9513cda","969763faa8f528db1a0d8e1ff07d6e1ee68629b0b3c290c673f1c7fbd86b1878","da06d4d9b667c184d58bbe626950562d9865cf4fe74ac2c1c7ab0ff37496c3c0","26ecd6f60b17d55dd7786c63032da17fc37d04b03d68025784b579429c1ba7a0","9582663804c6c491a5f60d7ab53497d8a3fdfe174bd2429cc152b36c675e511f","49f42405a66fce764ade456df448219b6acc6bd907cd9adc6e0937b5a779bb98","12bbc1ca1cdcc8de7c1451d0cac5c4fbdd43e8307ab4bcf4215131757c1bfea1","a804c86dbc4230ce7243ef730b60b0de6091f566d750a72d372901aa912e28c8","ce611f58d75c315eb519d2ce00188cf1de7f983e945e412ef2632f3d58580bfc","7c20de2fd8a4c771fd91e033ed56ea0476a501527a9ba3983b2d68d8860813e0","54fc771e801ccfb5eeceae4a795e570569d98cd408b7afdd81cc79c0f858656a","c129328ef095ff281b30fec6289b8af8d2a9f1bb5d62881f5cb0ceaca0cfed33","ae31cb503852941aefd120a5981429b6d1a907e077f3ced794a28df0b6b9ffc2","391a1d280fb4b91a8f9203772c5be909071b2d756f7cd42ab23a3194a35c44f8","ceb49d1ee09fce4fed9a086967a2db8ae75002f5ccd0e70c66214dd9ef4d3c4b","cac44bdf0a5b7a2eb4305b7d9ca3d6d6bb7cae6bce4cb614f3837fc0eaaef397","02738e6d23d66b60158287858ea67330db103254b0c077b44e6c3a1000ae964d","14addb5aa0de0bfba9818255952e2cd2dcdb862d197f110eb734e2a1cc59892a","cd6991669dc7e4d8dbb533aa96cbebece6401f4d5de9fef36dee8fcfb162c1a9","7f4729f02a71a71bff0478bf6696194014409ef0a825205d5fae5f54008e58f7","b252be80e57ee86438c35980f02d40bf08973b8d2afacd3df00f67ca3c25dc11","96645a07023a23b105a26ad5f8acae0fb6c185b6f08c1b3aaa04e33c0bbd7832","20b7346078f1c870db97557d0853771fa7f23ff6768e94c925a29d2465ad63a4"]}', true);

    $this->testInputs([
      $txs,
    ]);
  }

  public function testNullEncode(): void {
    $this->testInputs([
      [null],
      [null, null, null, 2],
      ['key' => null]
    ]);
  }

  public function testIntAsStringEncode(): void {
    $this->testInputs([
      ['236893652839462389462398462389642936489236423894'],
      ['1123182361289631289368912361289648963298462398462936432'],
      ['6654752348752347823684753287235478532847523784582376487632847532784582374582345823854782358423'],
    ]);
  }

  public function testFloatAsStringEncode(): void {
    $this->testInputs([
      ['1.3424234234', '0.12318237983648236428936423894', 'a.123970139123'],
      ['0.123919023619028368a'],
      ['1.2123123123', '0.23423423423423'],
      ['000123.123123123']
    ]);
  }

  public function testIpv4Encode(): void {
    $this->testInputs([
      ['127.123.11.1', '0.0.0.0'],
      ['125.234.32.33'],
      ['2555.34.34.34', '300.255.255.255'],
    ]);
  }

  public function testListEncode(): void {
    $this->testInputs([
      range(0, 100),
      ['hello', 123, true, null],
      [[1, 2 ,3 ], range(0, 10), [true, true, false]],
    ]);
  }

  public function testMapEncode(): void {
    $this->testInputs([
      ['key' => mt_rand(1, 10000), 'value' => uniqid()],
      ['numeric' => '3492347023702934720397402394732', 'list' => range(1, 100)]
    ]);
  }

  protected function testInputs(array $inputs): void {
    foreach ($inputs as $input) {
      $packed = $this->Codec->pack($input);
      $this->assertNotEquals($input, $packed);
      $this->assertEquals($input, $this->Codec->unpack($packed));
    }
  }
}