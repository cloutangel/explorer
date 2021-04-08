<?php
namespace Muvon\KISS;

// Max \x08
// In case adding new type add it to reserve map
define('BC_BOOL',   "\x01");
define('BC_LIST',   "\x02");
define('BC_KEY',    "\x03");
define('BC_NUM',    "\x04");
define('BC_NULL',   "\x05");
define('BC_HASH',   "\x06");
define('BC_DEC',    "\x07");
define('BC_IPV4',   "\x08");

final class BinaryCodec {
  const VERSION = 1;

  // Reserved map includes a-z A-Z _ / 0-9 \0
  // 0-8, 47-57, 65-90, 95, 97-122
  // last \xff
  const COMPRESS_MAP = [
    // Custom pack related
    BC_IPV4 . 'a/' => "\x9", BC_BOOL . 'C/' => "\xa",
    BC_NULL . 'C/' => "\xb", BC_DEC . 'a/' => "\xc",
    BC_NUM . 'a/' => "\xd", BC_LIST . 'N/' => "\xe",
    BC_HASH . 'N/' => "\xf", BC_KEY . 'C/' => "\x10",
    // Pack related
    'c/' => "\x11", 'C/' => "\x12", 'n/' => "\x13",
    'N/' => "\x14", 'H8/' => "\x15", 'H16/' => "\x16",
    'H32/' => "\x17", 'H64/' => "\x18", 'H128/' => "\x19",
    'E/' => "\x1a", 'G/' => "\x1b", 'J/' => "\x1c",
    'q/' => "\x1d", 's/' => "\x1e",
    // Numbers
    '1/' => "\x1f", '2/' => "\x20", '3/' => "\x21",
    '4/' => "\x22", '5/' => "\x23", '6/' => "\x24",
    '7/' => "\x25", '8/' => "\x26", '9/' => "\x27",
    '0/' => "\x28",
    '10' => "\xdb", '11' => "\xdc", '12' => "\xdd",
    '13' => "\xf8", "14" => "\xdf", "15" => "\xe0",
    '16' => "\xe1", '17' => "\xe2", '18' => "\xe3",
    '19' => "\xe4",
    // Key related
    'created' => "\x29", 'updated' => "\x2a",
    'email' => "\x2b", 'state' => "\x2c",
    'value' => "\xd6", 'amount' => "\xd7",
    'status' => "\xfb", 'accept' => "\xfc",
    'cancel' => "\xfd", 
    'list' => "\xd5", 'type' => "\xf4",
    'info' => "\xf6",
    'time' => "\x2d", 'diff' => "\x2e", 'size' => "\x3a",
    'name' => "\x3b", 'full' => "\x3c",
    'next' => "\x3e", 'prev' => "\x3f", 'hash' => "\x40",
    'ver' => "\x5c", 'ght' => "\x5d",
    'age' => "\xfa", 'dat' => "\x3d",
    'ous' => "\x5e", 'can' => "\xd4", 'ete' => "\x60",
    'use' => "\x7b", 'cur' => "\x7c", 'ble' => "\x7d",
    'ful' => "\x7e", 'ash' => "\x7f", 'err' => "\x80",
    'dex' => "\x81", 'val' => "\x82", 'log' => "\xd3",
    'pub' => "\xd8", 'key' => "\xd9", 'amp' => "\xda",
    'act' => "\xe7", 'ion' => "\xe8", 'lat' => "\xf3",
    'lon' => "\xf1", 'ili' => "\xf7", 'ing' => "\xf2",
    'oes' => "\xf9", 'put' => "\x5b",
    'es' => "\xf0", 'vo' => "\xfe", 'mi' => "\xff",
    'ti' => "\xea", 'fi' => "\xeb", 'if' => "\xec",
    'ev' => "\xed", 'os' => "\xee", 're' => "\xef",
    'tx' => "\x83", 'ts' => "\xde", 'ph' => "\xe9",
    'is' => "\x84", 'gr' => "\x85", 'no' => "\x86",
    'nc' => "\x87", 'mm' => "\x88", 'on' => "\x89",
    'sh' => "\x8a", 'pp' => "\x8b", 'at' => "\x8c",
    'ha' => "\x8d", 'id' => "\xe5", 'uu' => "\xe6",
    'ss' => "\x8e", 'bl' => "\x8f", 'na' => "\x90",
    'me' => "\x91", 'ee' => "\x92", 'oo' => "\x93",
    'll' => "\x94", 'rr' => "\x95", 'ne' => "\x96",
    'si' => "\x97", 'ck' => "\x98", 'to' => "\x99",
    'ei' => "\x9a", 'be' => "\x9b", 'of' => "\x9c",
    'in' => "\x9d", 'ou' => "\x9e", 'er' => "\x9f",
    'do' => "\xa0", 'ba' => "\xa1", 'se' => "\xa2",
    'st' => "\xa3", 'th' => "\xa4", 'ff' => "\xa5",
    'nd' => "\xa6", 'an' => "\xa7", 'ny' => "\xa8",
    'up' => "\xa9", 'ed' => "\xaa", 'ce' => "\xab",
    'ld' => "\xac", 've' => "\xad", 'rk' => "\xae",
    'nt' => "\xaf", 'lt' => "\xb0", 'fy' => "\xb1",
    'ty' => "\xb2", 'or' => "\xb3", 'cu' => "\xb4",
    'di' => "\xb5", 'ch' => "\xb6", 'tr' => "\xb7",
    'bi' => "\xb8", 'nd' => "\xb9", 'ex' => "\xba",
    'tt' => "\xbb", 'ca' => "\xbc", 'ke' => "\xbd",
    'co' => "\xbe", 'cc' => "\xbf", 'su' => "\xc0",
    'ok' => "\xc1", 'pr' => "\xc2", 'du' => "\xc3",
    // Non changed characters with separated symbol
    'y/' => "\xc4", 'h/' => "\xc5", 'r/' => "\xc6",
    'n/' => "\xc7", 't/' => "\xc8", 'd/' => "\xc9",
    'e/' => "\xca", 'a/' => "\xcb", 'k/' => "\xcc",
    'x/' => "\xcd", '/w' => "\xce", 'a/' => "\xcf",
    'f/' => "\xd0", '/n' => "\xd1", '/h' => "\xd2",
  ];

  protected array $key_map;
  protected int $key_idx = 0;
  protected array $format;

  protected final function __construct() {
    $this->decompress_map = array_flip(static::COMPRESS_MAP);
  }

  public static function create(): self {
    return new static;
  }

  public function pack(array $data): string {
    $this->format = [];
    $this->key_idx = 0;
    $this->key_map = [];

    $parts = [];
    $this->format[] = (array_key_first($data) === 0 ? BC_LIST : BC_HASH). 'N';
    $parts = [sizeof($data)];

    array_push($parts, ...$this->encode($data));
    $format_str = implode('/', $this->format);
    $flow = $this->compress($format_str . "\0" . implode("/", array_keys($this->key_map)));

    return pack(
      'CNa*' . str_replace(['/', BC_HASH, BC_KEY, BC_LIST, BC_BOOL, BC_NULL, BC_NUM, BC_DEC, BC_IPV4], '', $format_str),
      static::VERSION,
      strlen($flow),
      $flow,
      ...$parts
    );
  }

  protected function encode(mixed $data): array {
    if (is_array($data)) {
      $parts = [];
      foreach ($data as $k => $v) {
        // First we do hash map key cuz it can contain list
        if (is_string($k)) {
          if (!isset($this->key_map[$k])) {
            $this->key_map[$k] = $this->key_idx++;
          }
          $this->format[] = BC_KEY . 'C';
          $parts[] = $this->key_map[$k];
        }

        // Can be list or hash array
        if (is_array($v)) {
          $sz = sizeof($v);
          $this->format[] = (array_key_first($v) === 0 || $sz === 0)
            ? BC_LIST . 'N'
            : BC_HASH . 'N'
          ;
          $parts[] = $sz;
        }

        array_push($parts, ...$this->encode($v));
      }

      return $parts;
    } else {
      $this->format[] = $this->getDataFormat($data);
      return [$data];
    }
  }

  public function unpack(string $binary): array {
    $version = unpack('C', $binary[0])[1];
    [, $meta_len] = unpack('N', $binary[1] . $binary[2] . $binary[3] . $binary[4]);
    // $meta_len = hexdec(bin2hex($binary[1] . $binary[2] . $binary[3] . $binary[4]));
    return $this->decode($this->decompress(substr($binary, 5, $meta_len)), substr($binary, 5 + $meta_len));
  }

  protected function decode(string $meta, string $binary): mixed {
    $i = 0;
    $keys = [];
    $format = '';
    foreach (explode('/', strtok($meta, "\0")) as $f) {
      $key = 'n' . ($i++);
      $prefix = match ($f[0]) {
        BC_NULL, BC_NUM, BC_BOOL, BC_LIST, BC_KEY, BC_HASH, BC_DEC, BC_IPV4 => substr($f, 1),
        default => $f,
      };

      if ($prefix !== $f) {
        $keys[$key] = $f[0];
      }

      $format .= $prefix . $key . '/';
    }
    $format = rtrim($format, '/');
    $key_map = explode('/', strtok("\0"));
    $result = null;
    $ref = &$result;
    $path = [];
    $ns = [];
    $i = 0;
    $n = 0;

    foreach (unpack($format, $binary) as $k => &$v) {
      if (isset($keys[$k])) {
        $v = match ($keys[$k]) {
          BC_NULL => null,
          BC_NUM => gmp_strval(gmp_init(bin2hex($v), 16)),
          BC_BOOL => !!$v,
          BC_DEC => bcdiv(gmp_strval(gmp_init(bin2hex(substr($v, 1)), 16), 10), gmp_strval(gmp_pow(10, $fraction = unpack('C', $v[0])[1])), $fraction),
          BC_IPV4 => long2ip(unpack('N', $v)[1]),
          default => $v,
        };

        if ($keys[$k] === BC_LIST) {
          $ns[] = $v;
          if (!isset($ref)) {
            $ref = [];
            $path[] = '-';
          } else {
            $ref[$i] = [];
            $ref = &$ref[$i];
            $path[] = $i;
          }

          $i = 0;
          continue;
        }

        if ($keys[$k] === BC_HASH) {
          $ns[] = $v;
          if (is_array($ref)) {
            $ref[$i] = [];
            $ref = &$ref[$i];
            $path[] = $i;
            // $i++;
          } else {
            $ref = [];
            $path[] = '-';
          }
          continue;
        }

        if ($keys[$k] === BC_KEY) {
          $h_key = $key_map[$v];
          $ref = &$ref[$h_key];
          $ns[]   = 1;
          $path[] = $h_key;
          continue;
        }
      }

      // Write data to ref logic
      if (isset($ref)) {
        $ref[$i++] = $v;
      } else {
        $ref = $v;
      }

      // Reset ref pointer logic
      $n = &$ns[array_key_last($ns)];
      if (--$n === 0) {
        do {
          array_pop($path);
          array_pop($ns);
          $n = &$ns[array_key_last($ns)];
        } while(--$n === 0);

        $ref = &$result;
        foreach ($path as $p) {
          if ($p === '-') {
            continue;
          }
          $ref = &$ref[$p];
        }

        if ($ref) {
          $i = sizeof($ref);
        }
      }
    }
    return $result;
  }

  protected function getDataFormat(mixed &$data): string {
    $format = match(true) {
      is_int($data) && $data >= 0 && $data <= 255 => 'C',
      is_int($data) && $data >= -128 && $data <= 127 => 'c',
      is_int($data) && $data >= 0 && $data <= 65535 => 'n',
      is_int($data) && $data >= -32768 && $data <= 32767 => 's',
      is_int($data) && $data >= 0 && $data <= 4294967295 => 'N',
      is_int($data) && $data >= -2147483648 && $data <= 2147483647 => 'l',
      is_int($data) && $data >= 0 && $data <= 18446744073709551615 => 'J',
      is_int($data) && $data >= -9223372036854775808 && $data <= 9223372036854775807 => 'q',
      is_double($data) => 'E',
      is_float($data) => 'G',
      default => 'a',
    };

    if ($format === 'a') {
      switch (true) {
        case is_numeric($data) && $data[0] !== '0' && trim($data, '0..9') === '':
          $val = gmp_strval(gmp_init($data, 10), 16);
          if (strlen($val) % 2 !== 0) {
            $val = '0' . $val;
          }
          $format = BC_NUM . 'a';
          $data = hex2bin($val);
          break;

        case is_numeric($data) && str_contains($data, '.') && (ltrim($data, '0')[0] === '.' || $data[0] !== '0') && trim($data, '0..9.') === '':
          $fraction = strlen($data) - strpos($data, '.') - 1;
          $val = gmp_strval(gmp_init(bcmul($data, gmp_strval(gmp_pow(10, $fraction)), 0), 10), 16);
          if (strlen($val) % 2 !== 0) {
            $val = '0' . $val;
          }
          $format = BC_DEC . 'a';
          $data = pack('C', $fraction) . hex2bin($val);
          break;

        case is_string($data) && trim($data, '0..9A..Fa..f') === '':
          $format = 'H';
          break;

        case is_string($data) && !isset($data[15]) && filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4):
          $format = BC_IPV4 . 'a';
          $data = pack('N', ip2long($data));
          break;

        case is_bool($data):
          $format = BC_BOOL . 'C';
          $data = $data ? 1 : 0;
          break;

        case is_null($data):
          $format = BC_NULL . 'C';
          $data = "\x01";
          break;

      }
      $format .= strlen($data);
    }

    return $format;
  }

  protected function compress(string $string): string {
    return strtr($string, static::COMPRESS_MAP);
  }

  protected function decompress(string $binary): string {
    return strtr($binary, $this->decompress_map);
  }
}
