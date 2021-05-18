<?php
namespace Muvon\KISS;

use Error;
use Throwable;
use CurlHandle;
use CurlMultiHandle;

trait RequestTrait {
  protected int $request_connect_timeout = 5;
  protected int $request_timeout = 30;
  protected int $request_ssl_verify = 0;
  protected int $request_keepalive = 20;
  protected string $request_useragent = 'KISS/Request v0.8.1';

  // The contents of the "Accept-Encoding: " header. This enables decoding of the response. Supported encodings are "identity", "deflate", and "gzip". If an empty string, "", is set, a header containing all supported encoding types is sent.
  protected ?string $request_encoding = '';

  // Type of the request can be one of json, msgpack, binary, raw
  // In case if not supported we use raw
  protected string $request_type = 'raw';

  // Array containing proxy info with next fields
  // { host, port, user, password, type }
  protected array $request_proxy = [];

  protected array $request_json_bigint_keys = [];

  protected array $request_handlers = [];
  protected ?CurlMultiHandle $request_mh = null;

  /**
   * Run multi model
   *
   * @return self
   */
  protected function multi(): self {
    $this->request_mh = curl_multi_init();
    return $this;
  }

  /**
   * Do single or multi request if multi() caleld before
   *
   * @param string $url
   * @param array $payload
   * @param string $method Can be POST or GET only
   * @param array $headers Array with headers. Each entry as string
   * @return self|array in case multi() mode reqturns self otherswise array
   */
  protected function request(string $url, array $payload = [], string $method = 'POST', array $headers = []): self|array {

    if ($method === 'GET' && $payload) {
      $url = rtrim($url, '?') . '?' . http_build_query($payload, false, '&');
    }

    $ch = curl_init($url);

    match ($this->request_type) {
      'msgpack' => array_push($headers, 'Content-type: application/msgpack', 'Accept: application/msgpack'),
      'json' => array_push($headers, 'Content-type: application/json', 'Accept: application/json'),
      'binary' => array_push($headers, 'Content-type: application/binary', 'Accept: application/binary'),
      default => null,
    };

    $opts = [
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_SSL_VERIFYPEER => $this->request_ssl_verify,
      CURLOPT_CONNECTTIMEOUT => $this->request_connect_timeout,
      CURLOPT_TIMEOUT => $this->request_timeout,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_ENCODING => $this->request_encoding,
      CURLOPT_TCP_KEEPALIVE => $this->request_keepalive,
      CURLOPT_USERAGENT => $this->request_useragent,
    ];

    if ($this->request_proxy) {
      $opts[CURLOPT_PROXY] = $this->request_proxy['host'] . ':' . $this->request_proxy['port'];
      if (isset($this->request_proxy['user'])) {
        $opts[CURLOPT_PROXYUSERPWD] = $this->request_proxy['user'] . ':' . $this->request_proxy['password'];
      }
      $opts[CURLOPT_PROXYTYPE] = match ($this->request_proxy['type'] ?? 'http') {
        'socks4' => CURLPROXY_SOCKS4,
        'socks5' => CURLPROXY_SOCKS5,
        default => CURLPROXY_HTTP,
      };
    }

    if ($method === 'POST') {
      $opts[CURLOPT_POST] = 1;
      $opts[CURLOPT_POSTFIELDS] = $this->requestEncode($payload);
    }

    curl_setopt_array($ch, $opts);
    unset($opts);
    if ($this->request_mh) {
      $this->request_handlers[] = $ch;
      curl_multi_add_handle($this->request_mh, $ch);
      return $this;
    }

    return $this->process($ch);
  }

  /**
   * This method execute multiple request in multi() mode
   * If we call this methods without multi it throws Exception
   * In case if one or more responses failed it throws Exception
   *
   * @return array list of results with structure same as single request
   */
  protected function exec(): array {
    if (!$this->request_mh) {
      throw new Error('Trying to exec request that ws not inited');
    }
    do {
      $status = curl_multi_exec($this->request_mh, $active);
      if ($active) {
        curl_multi_select($this->request_mh);
      }
    } while ($active && $status == CURLM_OK);

    $result = [];
    foreach ($this->request_handlers as $ch) {
      $result[] = $this->process($ch);
    }
    curl_multi_close($this->request_mh);
    unset($this->request_handlers);
    $this->request_handlers = [];
    $this->request_mh = null;

    return $result;
  }

  private function process(CurlHandle $ch): array {
    try {
      $fetch_fn = $this->request_mh ? 'curl_multi_getcontent' : 'curl_exec';
      $response = $fetch_fn($ch);
      $err_code = curl_errno($ch);
      if ($err_code) {
        // https://curl.se/libcurl/c/libcurl-errors.html
        return [match ($err_code) {
          7 => 'e_request_refused',
          9 => 'e_request_access_denied',
          28 => 'e_request_timedout',
          52 => 'e_request_got_nothing',
          default => 'e_request_failed',
        }, 'CURL ' . $err_code . ': ' . curl_error($ch)];
      }
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($this->request_mh) {
        curl_multi_remove_handle($this->request_mh, $ch);
      }
      curl_close($ch);
      if (($httpcode !== 200 && $httpcode !== 201)) {
        return [match($httpcode) {
          429 => 'e_http_too_many_request',
          400 => 'e_http_bad_request',
          401 => 'e_http_unathorized',
          403 => 'e_http_forbidden',
          404 => 'e_http_not_found',
          405 => 'e_http_method_not_allowed',
          413 => 'e_http_payload_too_large',
          414 => 'e_http_not_found',
          500 => 'e_http_server_error',
          501 => 'e_http_not_implemented',
          502 => 'e_http_bad_gateway',
          503 => 'e_http_service_unavailable',
          504 => 'e_http_gateway_timeout',
          default => 'e_request_failed',
        }, 'HTTP ' . $httpcode . ': ' . $response];
      }

      if (!$response) {
        return ['e_response_empty', $response];
      }

      $decoded = $this->requestDecode($response);
      if (false === $decoded) {
        return ['e_response_decode_failed', null];
      }
      return [null, $decoded];
    } catch (Throwable $T) {
      return ['e_request_failed', $T->getMessage()];
    }
  }

  /**
   * Encode request depends on type of it
   *
   * @param array $payload
   * @return string encoded string to pass to post data
   */
  protected function requestEncode(array $payload): string {
    return match ($this->request_type) {
      'msgpack' => msgpack_pack($payload),
      'json' => $this->encodeJson($payload),
      'binary' => BinaryCodec::create()->pack($payload),
      default => http_build_query($payload, false, '&'),
    };
  }

  /**
   * Decode response based on current request type
   *
   * @param string $response
   * @return mixed
   */
  protected function requestDecode(string $response): mixed {
    return match ($this->request_type) {
      'msgpack' => msgpack_unpack($response),
      // This hack is needed to prevent converting numbers like 1.3 to 1.2999999999 cuz PHP is shit in this case
      'json' => json_decode($response = preg_replace('/"\s*:\s*([0-9]+\.[0-9]+)([,\}\]])/ius', '":"$1"$2', $response), true, flags: JSON_BIGINT_AS_STRING),
      'binary' => BinaryCodec::create()->unpack($response),
      default => $response,
    };
  }

  protected function encodeJson(mixed $data): string {
    $json = json_encode($data);
    if ($this->request_json_bigint_keys) {
      $json = preg_replace('/"(' . implode('|', $this->request_json_bigint_keys) . ')":"([0-9]+)"/ius', '"$1":$2', $json);
    }

    return $json;
  }
}
