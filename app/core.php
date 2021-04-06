<?php declare(strict_types=1);
final class App {
  /** @property bool $debug */
  public static bool $debug;
  protected static array $e_handlers = [];
  protected static array $action_map;

  /**
   * Fetch annotated variables from $file using $map_file
   * @param string $file File that was annotated with import params (action or something else)
   * @param string $map_file File with map of args or empty to use default
   * @return array
   */
  public static function getImportVarsArgs(string $file, string $map_file = null): array {
    $params = Env::load($map_file ?: config('common.param_map_file'));
    $args = [];
    if (isset($params[$file])) {
      foreach ($params[$file] as $param) {
        $args[] = $param['name'] . ':' . $param['type'] . (isset($param['default']) ? '=' . $param['default'] : '');
      }
    }
    return $args;
  }

  /**
   * Write json data into file
   * @param string $file File path to json
   * @param mixed $data Data to put in json file
   * @return bool
   */
  public static function writeJSON(string $file, mixed $data): bool {
    return !!file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  }

  /**
   * Get json data from file
   * @param string $file
   * @return mixed
   */
  public static function getJSON(string $file): mixed {
    if (!is_file($file)) {
      throw new Error('Cant find file ' . $file . '. Be sure you started init script to compile application');
    }

    return json_decode(file_get_contents($file), true);
  }

  /**
   * Log any message
   * @param string $message
   * @param array $dump
   * @param string $type error, info, wanr, notice
   * @return string идентификатор исключения
   */
  public static function log(string $message, array $dump = [], string $type = 'error'): string {
    $id = hash('sha256', $message . ':' . implode('.', array_keys($dump)) . ':' . $type);
    $log_file = getenv('LOG_DIR') . '/' . gmdate('Ymd') . '-' . $type . '.log';
    $message =
      gmdate('[Y-m-d H:i:s T]')
      . "\t" . $id
      . "\t" . $message
      . "\t" . json_encode($dump, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\t"
      . json_encode(filter_input_array(INPUT_COOKIE), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    ;
    error_log($message, 3, $log_file);
    return $id;
  }

  /**
   * Иницилизация работы приложения
   * @param array $config
   */
  public static function start(array $config = []): void {
    foreach ($config as $param => $value) {
      static::$$param = $value;
    }

    if (!isset(static::$debug)) {
      static::$debug = getenv('PROJECT_ENV') === 'dev';
    }

    // Locale settings
    setlocale(LC_ALL, 'ru_RU.UTF8');

    // Timezone settings
    date_default_timezone_set(timezone_name_from_abbr('', intval(Cookie::get('tz_offset')), 0) ?: 'UTC');

    // Error handler
    set_error_handler([static::class, 'handleError'], E_ALL);

    // Handle uncatched exceptions
    set_exception_handler([static::class, 'handleException']);

    // Register default Exception handler
    static::setExceptionHandler(Throwable::class, static::createExceptionHandler());

    Autoload::register('Plugin', getenv('APP_DIR') . '/plugin');
    Autoload::register('App', getenv('APP_DIR') . '/src');
    Autoload::register('App\Model', getenv('APP_DIR') . '/src/model');
    Autoload::register('App\Component', getenv('APP_DIR') . '/src/component');
    Autoload::register('App\Lib', getenv('APP_DIR') . '/src/lib');

    // If we have vendor dir with autoload file load it
    // This is required for composer packages
    $vendor_autoload_file = getenv('APP_DIR') . '/vendor/autoload.php';
    if (file_exists($vendor_autoload_file)) {
      include_once $vendor_autoload_file;
    }

    include_once getenv('APP_DIR') . '/start.php';
  }

  /**
   * Завершение исполнени приложени
   */
  public static function stop(): void {
    include_once getenv('APP_DIR') . '/stop.php';
  }

  /**
   * @param Request $Request
   * @return View
   */
  public static function process(): View {
    if (!isset(static::$action_map)) {
      static::$action_map = Env::load(config('common.action_map_file'));
    }

    $Request = Request::current();
    $Response = Response::current();

    $process = function (&$_RESPONSE) use ($Request, $Response) {
      $_ACTION = static::$action_map[$Request->getAction()];
      extract(Input::get(static::getImportVarsArgs($_ACTION)));
      $_RESPONSE = include $_ACTION;

      return get_defined_vars();
    };

    $vars = $process($response);

    switch (true) {
      case $response === 1:
        $Response->header('Content-type', 'text/html;charset=utf-8');
        return View::create($Request->getAction())->set($vars);
        break;

      case $response instanceof View:
        $Response->header('Content-type', 'text/html;charset=utf-8');
        return $response->set($vars);
        break;

      case is_string($response):
        $Response->header('Content-type', 'text/plain;charset=utf-8');
        return View::fromString($response);
        break;

      case is_array($response):
      case is_object($response):
        $accept = filter_input(INPUT_SERVER, 'HTTP_ACCEPT') ?? '';
        $type = match (true) {
          str_contains('application/json', $accept) => 'json',
          str_contains('application/msgpack', $accept) => 'msgpack',
          default => Input::isMsgpack() ? 'msgpack' : 'json',
        };

        $Response->header('Content-type', 'application/' . $type . ';charset=utf-8');
        $encoded = $type === 'msgpack' ? msgpack_pack($response) : json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (false === $encoded) {
          throw new Error('Failed to encode ' . $type  . ' response');
        }
        return View::fromString($encoded);
        break;

      default:
        $Response->header('Content-type', 'text/plain;charset=utf-8');
        return View::fromString((string) $response);
    }
  }

  /**
   * Замена стандартного обработчика ошибок на эксепшены
   */
  public static function handleError(int $errno, string $errstr, string $errfile, string $errline): void {
    static::error($errstr);
  }

  /**
   * Handle exception. Call handlers and do some staff
   * @param Throwable $Exception
   */
  public static function handleException(Throwable $Exception) {
    $Exception->id = static::log($Exception->getMessage(), ['trace' => $Exception->getTraceAsString()], 'error');

    $exception = get_class($Exception);
    do {
      if (isset(static::$e_handlers[$exception])) {
        return static::$e_handlers[$exception]($Exception);
      }
    } while (false !== $exception = get_parent_class($exception));

    $implements = class_implements($Exception);
    while($implement = array_pop($implements)) {
      if (isset(static::$e_handlers[$implement])) {
        return static::$e_handlers[$implement]($Exception);
      }
    }
  }

  public static function createExceptionHandler(int $code = 500, string $type = null, Callable $format_func = null): Callable {
    static $types = [
      'json' => 'application/json',
      'html' => 'text/html',
      'text' => 'text/plain',
    ];

    if (!isset($type)) {
      $type = match(true) {
        Input::isJson() => 'json',
        Input::isCli() => 'text',
        default => 'html'
      };
    }

    return function (Throwable $Exception) use ($code, $type, $format_func, $types) {
      switch (true) {
        case isset($format_func):
          $response = $format_func($Exception);
          break;
        case $type === 'json':
          $response = json_encode([
            'error' => $Exception->getMessage(),
            'trace' => App::$debug ? $Exception->getTrace() : [],
          ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
          break;

        case $type === 'html':
          $response = '<html><head><title>Error</title></head><body>'
             . '<p>Unhandled exception <b>'
             . get_class($Exception) . '</b> with message "' . $Exception->getMessage()
             . (static::$debug ? '" in file "' . $Exception->getFile() . ':' . $Exception->getLine() : '')
             . '"</p>';

          if (static::$debug) {
            $response .= '<p><ul>'
             . implode('<br/>', array_map(function ($item) { return '<li>' . $item . '</li>'; }, explode(PHP_EOL, $Exception->getTraceAsString())))
             . '</ul></p>'
             . '</body></html>'
            ;
          }
          break;

        default:
          $response = 'Error: ' . $Exception->getMessage();
          if (static::$debug) {
            $response .= PHP_EOL . $Exception->getTraceAsString();
          }
      }

      return Response::current()
        ->status($code)
        ->header('Content-type', $types[$type] . ';charset=utf8')
        ->send($response)
      ;
    };
  }

  /**
   * Assign handler for special exception that will be called when exception raises
   * @param string $exception
   * @param Callable $handler
   */
  public static function setExceptionHandler(string $exception, Callable $handler): void {
    static::$e_handlers[$exception] = $handler;
  }

  /**
   * Хэндлер для управления ошибками ассертов
   * @param	string  $file
   * @param	string	$line
   * @param	int	$code
   * @throws Exception
   */
  public static function handleAssertion(string $file, string $line, ?int $code): void {
    throw new Error('Assertion failed in file ' . $file . ' at line ' . $line . ' with code ' . $code);
  }

  /**
   * Generate error to stop next steps using special exception class name
   * @param string $error Message that describes error
   * @param string $class Exception class name to be raised
   * @throws \Exception
   */
  public static function error(string $error, string $class = 'Exception'): void {
    throw new $class($error);
  }

  /**
   * Execute shell command in KISS core environment
   * @param string $cmd Command to be executed
   * @return string Result of execution
   */
  public static function exec(string $cmd): string {
    $project_dir = getenv('PROJECT_DIR');
    $output = `
      set -e
      cd $project_dir
      source ./env.sh
      $cmd
    `;
    return $output ? trim($output) : '';
  }
}

final class Autoload {
  protected static bool $inited = false;
  protected static array $prefixes = [];

  /**
   * Init autoload mecahnism
   */
  protected static function init(): void {
    spl_autoload_register([static::class, 'load']);
    static::$inited = true;
  }

  /**
   * @param string $class Class to be loaded
   * @return bool|string
   */
  protected static function load(string $class): bool|string {
    $prefix = $class;
    while (false !== $pos = strrpos($prefix, '\\')) {
      $prefix = substr($class, 0, $pos + 1);
      $relative = substr($class, $pos + 1);
      $mapped = static::loadMapped($prefix, $relative);
      if ($mapped) {
        return $mapped;
      }
      $prefix = rtrim($prefix, '\\');
    }

    return false;
  }

  /**
   * @param string $prefix
   * @param string $class
   */
  protected static function loadMapped(string $prefix, string $class): false|string {
    if (!isset(static::$prefixes[$prefix])) {
      return false;
    }

    foreach (static::$prefixes[$prefix] as $dir) {
      $file = $dir . str_replace('\\', '/', $class) . '.php';
      if (is_file($file)) {
        include $file;
        return $file;
      }
    }
    return false;
  }

  /**
   * Register new namespace and folder to be loaded from
   * @param string $prefix
   * @param string $dir
   * @param bool $prepend Priority for this
   */
  public static function register(string $prefix, string $dir, bool $prepend = false): void {
    assert(is_dir($dir) /* Dir $dir does not exist */);

    if (!static::$inited) {
      static::init();
    }

    $prefix = trim($prefix, '\\') . '\\';
    $dir = rtrim($dir, '/') . '/';

    if (!isset(static::$prefixes[$prefix])) {
      static::$prefixes[$prefix] = [];
    }

    if ($prepend) {
      array_unshift(static::$prefixes[$prefix], $dir);
    } else {
      static::$prefixes[$prefix][] = $dir;
    }
  }
}

final class Cli {
  /**
   * This function reads hidden input (password) from stdin
   *
   * @param string|null $promt
   * @return string
   */
  public static function readSecret(?string $promt = null): string {
    if ($promt) {
      echo $promt;
    }

    system('stty -echo');
    $secret = trim(fgets(STDIN));
    system('stty echo');

    return $secret;
  }

  public static function print(string $line): void {
    echo gmdate('[Y-m-d H:i:s T]') . ' ' . rtrim($line) . PHP_EOL;
  }

  public static function printList(array $list): void {
    foreach ($list as $item) {
      static::print($item);
    }
  }
}

/**
 * Class Cookie
 * Work with cookies
 *
 * <code>
 * Cookie::add('first', 'value', time() + 100);
 * Cookie::add('onemore', 'value', time() + 100);
 * Cookie::send(); // Be sure to send cookie before headers sent
 * </code>
 *
 * <code>
 * $first = Cookie:get('first');
 * </code>
 */
final class Cookie {
  protected static $cookies = [];

  /**
   * Get cookie by name
   * @param string $name
   * @param mixed $default
   */
  public static function get(string $name, mixed $default = null): mixed {
    return filter_has_var(INPUT_COOKIE, $name) ? filter_input(INPUT_COOKIE, $name) : $default;
  }

  /**
   * Set new cookie. Replace if exists
   * @param string $name
   * @param string $value
   * @param array $options
   * @return void
   */
  public static function set(string $name, string $value, array $options = []): void {
    static::$cookies[$name] = [
      'name' => $name,
      'value' => $value,
      'options' => $options
    ];
  }

  /**
   * Add new cookie. Create new only if not exists
   * @param string $name
   * @param string $value
   * @param array $options
   * @return void
   */
  public static function add(string $name, string $value, array $options = []): void {
    if (!filter_has_var(INPUT_COOKIE, $name)) {
      static::set($name, $value, $options);
    }
  }

  /**
   * Send cookies headers
   */
  public static function send(): void {
    foreach (static::$cookies as $cookie) {
      $options = array_merge($cookie['options'], [
        'domain' => $cookie['domain'] ?? config('common.domain'),
        'path' => $cookie['path'] ?? '/',
        'expires' => $cookie['expires'] ?? 0,
        'secure' => $cookie['secure'] ?? config('common.proto') === 'https',
        'httponly' => $cookie['httponly'] ?? str_starts_with(getenv('SERVER_PROTOCOL'), 'HTTP'),
      ]);
      setcookie($cookie['name'], $cookie['value'], $options);
    }
  }
}

final class Env {
  protected static $params = [
    'PROJECT',
    'PROJECT_DIR',
    'PROJECT_ENV',
    'PROJECT_REV',
    'APP_DIR',
    'STATIC_DIR',
    'CONFIG_DIR',
    'ENV_DIR',
    'BIN_DIR',
    'RUN_DIR',
    'LOG_DIR',
    'VAR_DIR',
    'TMP_DIR',
    'KISS_CORE',
  ];

  /**
   * Initialization of Application
   *
   * @return void
   */
  public static function init(): void {
    App::$debug = getenv('PROJECT_ENV') === 'dev';
    static::configure(getenv('APP_DIR') . '/config/app.ini.tpl');
    static::compileConfig();
    static::generateActionMap();
    static::generateURIMap();
    static::generateParamMap();
    static::generateTriggerMap();
    static::generateConfigs();
    static::prepareDirs();
  }

  /**
   * Configure all config tempaltes in dir $template or special $template file
   *
   * @param string $template
   * @param array $params
   * @return void
   */
  public static function configure(string $template, array $params = []): void {
    // Add default params
    foreach (static::$params as $param) {
      $params['{{' . $param . '}}'] = getenv($param);
    }

    // Add extra params
    $params += [
      '{{DEBUG}}' => (int) App::$debug,
    ];

    foreach(is_dir($template) ? glob($template . '/*.tpl') : [$template] as $file) {
      file_put_contents(getenv('CONFIG_DIR') . '/' . basename($file, '.tpl'), strtr(file_get_contents($file), $params));
    }
  }

  /**
   * Compile config.json into fast php array to include it ready to use optimized config
   */
  protected static function compileConfig(): void {
    $env = getenv('PROJECT_ENV');

    $config = [];
    // Prepare production config replacement
    foreach (parse_ini_file(getenv('CONFIG_DIR') . '/app.ini', true) as $group => $block) {
      if (str_contains($group, ':') && explode(':', $group)[1] === $env) {
        $origin = strtok($group, ':');
        $config[$origin] = array_merge($config[$origin], $block);
        $group = $origin;
      } else {
        $config[$group] = $block;
      }

      // Make dot.notation for group access
      foreach ($config[$group] as $key => &$val) {
        $config[$group . '.' . $key] = &$val;
      }
    }

    // Iterate to make dot.notation.direct.access
    $Iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($config));
    foreach ($Iterator as $leaf_value) {
      $keys = [];
      foreach (range(0, $Iterator->getDepth()) as $depth) {
        $keys[] = $Iterator->getSubIterator($depth)->key();
      }
      $config[join('.', $keys)] = $leaf_value;
    }

    static::store(getenv('CONFIG_DIR') . '/config.php', $config);
  }

  /**
   * Generate all configs for configurable plugins. It includes all plugin/_/configure.php files
   * @return void
   */
  protected static function generateConfigs(): void {
    $configure = function ($file) {
      return include $file;
    };

    foreach (glob(getenv('APP_DIR') . '/config/*/configure.php') as $file) {
      $configure($file);
    }
  }

  protected static function prepareDirs(): void {
    static::createViewDirs();
    static::createSessionDirs();
  }

  protected static function createViewDirs(): void {
    if (!is_dir(config('view.compile_dir'))) {
      mkdir(config('view.compile_dir'), 0700, true);
    }

    if (config('common.lang_type') !== 'none') {
      foreach (config('common.languages') as $lang) {
        $lang_dir = config('view.compile_dir') . '/' . $lang;
        if (!is_dir($lang_dir)) {
          mkdir($lang_dir, 0700);
        }
      }
    }
  }

  protected static function createSessionDirs(): void {
    $save_handler = config('session.save_handler');
    if ($save_handler !== 'files') {
      return;
    }
    $bits = ini_get('session.sid_bits_per_character');
    $chars='0123456789abcdef';
    if ($bits >= 5) {
      $chars .= 'ghijklmnopqrstuv';
    }

    if ($bits >= 6) {
      $chars .= 'wxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-,';
    }

    $save_path = config('session.save_path');
    if (!is_dir($save_path)) {
      mkdir($save_path, 0700, true);
    }

    $depth = config('session.save_depth');
    if ($depth === 0) {
      return;
    }

    $arrays = [];
    for ($i = 0; $i < $depth; $i++) {
      $arrays[] = str_split($chars);
    }

    foreach (array_cartesian($arrays) as $paths) {
      $dir_path = $save_path . '/' . implode('/', $paths);
      if (!is_dir($dir_path)) {
        mkdir($dir_path, 0700, true);
      }
    }
  }

  /**
   * Generate nginx URI map for route request to special file
   */
  protected static function generateURIMap(): void {
    $map = [];
    foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@route\s+([^\:]+?)(\:(.+))?$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          $params  = isset($m[2][$k]) && $m[2][$k] ? array_map('trim', explode(',', substr($m[2][$k], 1))) : [];
          array_unshift($params, static::getActionByFile($file));
          $map[$pattern] = $params;
        }
      }
    }
    static::store(config('common.uri_map_file'), $map);
  }

  /**
   * Generate action => file_path map
   */
  protected static function generateActionMap(): void {
    $map = [];
    foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
      $map[static::getActionByFile($file)] = $file;
    }
    static::store(config('common.action_map_file'), $map);
  }

  /**
   * Generate parameters map from annotations in actions and triggers files
   */
  protected static function generateParamMap(): void {
    $map_files = [
      'actions'  => config('common.param_map_file'),
      'triggers' => config('common.trigger_param_file'),
    ];
    foreach ($map_files as $folder => $map_file) {
      $map = [];
      foreach (static::getPHPFiles(getenv('APP_DIR') . '/' . $folder) as $file) {
        $content = file_get_contents($file);
        if (preg_match_all('/^\s*\*\s*\@param\s+([a-z]+)\s+(.+?)$/ium', $content, $m)) {
          foreach ($m[0] as $k => $matches) {
            $param = substr(strtok($m[2][$k], ' '), 1);
            $map[$file][] = [
              'name'    => $param,
              'type'    => $m[1][$k],
              'default' => trim(substr($m[2][$k], strlen($param) + 1)) ?: null,
            ];
          }
        }
      }
      static::store($map_file, $map);
    }
  }

  /**
   * Generate trigger map to be called on some event
   */
  protected static function generateTriggerMap(): void {
    $map = [];
    foreach (static::getPHPFiles(getenv('APP_DIR') . '/triggers') as $file) {
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@event\s+([^\$]+?)$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          if (!isset($map[$pattern])) {
            $map[$pattern] = [];
          }
          $map[$pattern] = array_merge($map[$pattern], [$file]);
        }
      }
    }
    static::store(config('common.trigger_map_file'), $map);
  }

   protected static function getActionByFile(string $file): string {
     return substr(trim(str_replace(getenv('APP_DIR') . '/actions', '', $file), '/'), 0, -4);
   }

  /**
   * Helper for getting list of all php files in dir
   * @param string $dir
   * @return array
   */
  protected static function getPHPFiles(string $dir): array {
    assert(is_dir($dir));
    $output = `find -L $dir -name '*.php'`;
    return $output ? explode(PHP_EOL, trim($output)) : [];
  }

  /**
   * This function uses for store variable in php file for next load
   * Its much faster than parse and encode jsons or whatever
   *
   * @param string $file
   * @param mixed $data
   * @return bool
   */
  protected static function store(string $file, mixed $data): bool {
    return !!file_put_contents($file, '<?php return ' . var_export($data, true) . ';');
  }

  public static function load(string $file): array {
    assert(is_file($file));
    return include $file;
  }
}

final class Input {
  public static bool $is_parsed = false;
  public static array $params = [];

  public static function isCli(): bool {
    return !!filter_input(INPUT_SERVER, 'argc');
  }

  public static function isJson(): bool {
    return str_starts_with(filter_input(INPUT_SERVER, 'CONTENT_TYPE') ?? '', 'application/json');
  }

  public static function isMsgpack(): bool {
    return str_starts_with(filter_input(INPUT_SERVER, 'CONTENT_TYPE') ?? '', 'application/msgpack');
  }

  public static function isRaw(): bool {
    return filter_has_var(INPUT_SERVER, 'REQUEST_URI') && !static::isJson();
  }

  /**
   * Парсит и сохраняет все параметры в переменной self::$params
   *
   * @access protected
   * @return $this
   */
  protected static function parse(): void {
    if (static::isCli()) {
      $argv = filter_input(INPUT_SERVER, 'argv');
      array_shift($argv); // file
      static::$params['ACTION'] = array_shift($argv);
      static::$params += $argv;
    } elseif (static::isJson()) {
      static::$params = (array) filter_input_array(INPUT_GET) + (array) json_decode(file_get_contents('php://input'), true);
    } elseif (static::isMsgpack()) {
      static::$params = (array) filter_input_array(INPUT_GET) + (array) msgpack_unpack(file_get_contents('php://input'));
    } else {
      static::$params = (array) filter_input_array(INPUT_POST) + (array) filter_input_array(INPUT_GET);
    }

    static::$is_parsed = true;
  }

  public static function set(string $key, mixed $value): void {
    static::$is_parsed || static::parse();
    static::$params[$key] = $value;
  }

  /**
   * Получение переменной запроса
   *
   * <code>
   * $test = Input::get('test');
   *
   * $params = Input::get(['test:int=1']);
   * </code>
   */
  public static function get(...$args): mixed {
    static::$is_parsed || static::parse();

    if (!isset($args[0])) {
      return static::$params;
    }

    // String key?
    if (is_string($args[0])) {
      return isset(static::$params[$args[0]])
        ? static::$params[$args[0]]
        : ($args[1] ?? null);
    }

    if (is_array($args[0])) {
      return static::extractTypified($args[0], function ($key, $default = null) {
        return static::get($key, $default);
      });
    }
    // Exctract typifie var by mnemonic rules as array


    trigger_error('Error while fetch key from input');
  }

  /**
   * Извлекает и типизирует параметры из массива args с помощью функции $fetcher, которая
   * принимает на вход ключ из массива args и значение по умолчанию, если его там нет
   *
   * @param array $args
   * @param Closure $fetcher ($key, $default)
   */
  public static function extractTypified(array $args, Closure $fetcher): array {
    $params = [];
    foreach ($args as $arg) {
      preg_match('#^([a-zA-Z0-9_]+)(?:\:([a-z]+))?(?:\=(.+))?$#', $arg, $m);
      $params[$m[1]]  = $fetcher($m[1], $m[3] ?? '');

      // Нужно ли типизировать
      if (isset($m[2])) {
        typify($params[$m[1]], $m[2]);
      }
    }
    return $params;
  }
}

final class Lang {
  const DEFAULT_LANG = 'en';

  const LANGUAGE_MAP = [
    'ru' => 'Русский',
    'en' => 'English',
    'it' => 'Italiano',
    'ko' => '한국어',
    'zh' => '中文',
    'th' => 'ไทย',
    'ar' => 'العربية',
    'ja' => '日本語',
    'vi' => 'Tiếng Việt',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'es' => 'Español',
    'pt' => 'Português',
    'tl' => 'Filipino',
    'eo' => 'Esperanto',
    'eu' => 'Euskara',
    'fy' => 'Frysk',
    'ff' => 'Fula',
    'fo' => 'Føroyskt',
    'ga' => 'Gaeilge',
    'gl' => 'Galego',
    'gn' => 'Guarani',
    'ha' => 'Hausa',
    'hr' => 'Hrvatski',
    'pl' => 'Polski',
    'ro' => 'Română',
    'cs' => 'Čeština',
    'tr' => 'Türkçe',
    'fi' => 'Suomi',
    'sv' => 'Svenska',
    'el' => 'Ελληνικά',
    'be' => 'Беларуская',
    'uk' => 'Українська',
    'kk' => 'Қазақша',
  ];

  protected static string $current;
  protected static bool $is_enabled = true;

  public static function init(Request|string $Request): string {
    $lang_type = config('common.lang_type');
    assert(in_array($lang_type, ['path', 'domain', 'none']));
    if ($lang_type === 'none') {
      static::$is_enabled = false;
      static::$current = static::DEFAULT_LANG;
      return static::$current;
    }

    // Try to find current language from url match
    if (is_string($Request)) {
      $lang = $Request;
    } else {
      $lang = match($lang_type) {
        'domain' => strtok(getenv('HTTP_HOST'), '.'),
        'path' => strtok(substr($Request->getUrlPath(), 1), '/'),
        default => ''
      };
    }

    // If we find current language we return as string
    if (isset(static::LANGUAGE_MAP[$lang]) && in_array($lang, config('common.languages'))) {
      static::$current = $lang;
      return static::$current;
    }

    // No supported language found try to find in headers
    static::$current = static::parse();

    $url_path = match($lang_type) {
      'domain' => $Request->getUrlPath(),
      'path' => substr($Request->getUrlPath(), 3)
    };

    $query_str = $Request->getUrlQuery();
    Response::redirect(static::getUrlPrefix() . ($url_path ? $url_path : '/') . ($query_str ? '?' . $query_str : ''));
  }

  public static function current(): string {
    return static::$current;
  }

  public static function isEnabled(): bool {
    return static::$is_enabled;
  }

  public static function getUrlPrefix(): string {
    $lang_domain = match(config('common.lang_type')) {
      'domain' => static::$current . '.' . config('common.domain'),
      'path' => config('common.domain') . '/' . static::$current,
      'none' => config('common.domain')
    };

    return config('common.proto') . '://' . $lang_domain;
  }
  /**
   * Try to parse locale from headers and auto detect it
   *
   * @return string locale that we found in headers
   */
  public static function parse(): string {
    $accept_language = getenv('HTTP_ACCEPT_LANGUAGE') ?? '';
    $languages = config('common.languages');
    foreach (static::LANGUAGE_MAP as $lang => $name) {
      if (!isset($languages[$lang])) {
        continue;
      }

      if (str_contains($accept_language, $lang)) {
        return $lang;
      }
    }

    return static::DEFAULT_LANG;
  }

  /**
   * Get compiler for View to replace patter with values
   *
   * @param string $lang
   * @return Callable
   */
  public static function getViewCompiler(string $lang): Callable {
    return function ($body, $template) use ($lang) {
      return preg_replace_callback('#\#([A-Za-z0-9_]+)\##ius', function ($matches) use ($template, $lang) {
        return static::translate($template . '.' . $matches[1], $lang);
      }, $body);
    };
  }

  public static function getInfo(string $lang): array {
    return [
      'name' => static::LANGUAGE_MAP[$lang],
      'language' => $lang,
      'is_active' => true,
    ];
  }

  public static function getList(string $lang): array {
    $languages = config('common.languages');
    $list = [];
    foreach (static::LANGUAGE_MAP as $key => $item) {
      if (!in_array($key, $languages)) {
        continue;
      }
      $list[] = [
        'language' => $key,
        'name' => static::LANGUAGE_MAP[$key],
        'is_active' => $lang === $key,
      ];
    }

    return $list;
  }

  public static function translate(string $key, ?string $lang = null): string {
    assert(str_contains($key, '.'));
    static $map = [];
    if (!$map) {
      $lang_file = getenv('APP_DIR') . '/lang/' . ($lang ?: static::$current) . '.yml';
      assert(is_file($lang_file));
      $map = yaml_parse_file($lang_file);
    }

    [$template, $translation] = explode('.', $key);
    return $map[$template][$translation] ?? ($map['common'][$translation] ?? '[missing:' . $translation . ']');
  }
}

/**
 * Класс для работы с запросом и переменными запроса
 *
 * @final
 * @package Core
 * @subpackage Request
 */
final class Request {
  /**
   * @property array $params все параметры, переданные в текущем запросе
   *
   * @property string $route имя действия, которое должно выполнится в выполняемом запросе
   * @property string $url адрес обрабатываемого запроса
   *
   * @property string $method вызываемый метод на данном запросе (GET | POST)
   * @property string $protocol протокол соединения, например HTTP, CLI и т.п.
   * @property string $referer реферер, если имеется
   * @property string $ip IP-адрес клиента
   * @property string $xff ip адрес при использовании прокси, заголовок: X-Forwarded-For
   * @property string $user_agent строка, содержащая USER AGENT браузера клиента
   * @property string $host Хост, который выполняет запрос
   * @property bool $is_ajax запрос посылается через ajax
   */

  private array $params = [];

  private string
    $action  = '',
    $route   = ''
  ;

  public static int
  $time        = 0;

  public static string
  $method      = 'GET',
  $protocol    = 'HTTP',
  $referer     = '',
  $ip          = '0.0.0.0',
  $real_ip     = '0.0.0.0',
  $xff         = '',
  $host        = '',
  $user_agent  = '';

  public static array
  $languages   = [];

  public static bool
  $is_ajax     = false;

  /**
   * @param string|bool $url адрес текущего запроса
   */
  final protected function __construct(protected string $url) {}

  /**
   * Получение ссылки на экземпляр объекта исходного запроса
   *
   * @static
   * @return Request ссылка на объекта запроса
   */
  final protected static function create(): self {
    assert(!filter_input(INPUT_SERVER, 'argc'));
    self::$time = $_SERVER['REQUEST_TIME'];

    self::$protocol = filter_input(INPUT_SERVER, 'HTTPS') ? 'HTTPS' : 'HTTP';
    self::$is_ajax = !!filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH');
    self::$referer = filter_input(INPUT_SERVER, 'HTTP_REFERER') ?? '';
    self::$xff = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR') ?? '';

    // Эти переменные всегда определены в HTTP-запросе
    self::$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
    self::$user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT') ?: 'undefined';
    self::$ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');

    static::parseRealIp();
    if ($http_accept_lang = filter_input(INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE')) {
      preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $http_accept_lang, $lang);
      if ($lang && sizeof($lang[1]) > 0) {
        $langs = array_combine($lang[1], $lang[4]);

        foreach ($langs as $k => $v) {
          if ($v === '') {
            $langs[$k] = 1;
          }
        }
        arsort($langs, SORT_NUMERIC);
        static::$languages = $langs;
      }
    }

    $url = rtrim(filter_input(INPUT_SERVER, 'REQUEST_URI'), ';&?') ?: '/';
    $Request = (new static($url))
      ->setRoute(Input::get('ROUTE'))
      ->setAction(Input::get('ACTION'))
    ;

    // Init language
    Lang::init($Request);

    return $Request;
  }

  /**
   * Return current instance or initialize and parse
   */
  public static function current(): self {
    static $instance;
    if (!isset($instance)) {
      $instance = static::create();
    }

    return $instance;
  }

  /**
   * Parse IPS to prepare request
   * @return void
   */
  protected static function parseRealIp(): void {
    self::$real_ip = self::$ip;
    if (self::$xff && self::$xff !== self::$ip) {
      self::$real_ip = trim(strtok(self::$xff, ','));
    }
  }

  /**
   * Get current handled url for this request
   * @return string
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Get part of url as path. /some/path for url /some/path?fuck=yea
   * @param string
   */
  public function getUrlPath(): string {
    return parse_url($this->url, PHP_URL_PATH);
  }

  /**
   * Get url query
   * @return string
   */
  public function getUrlQuery(): string {
    return parse_url($this->url, PHP_URL_QUERY) ?? '';
  }


  /**
   * Get requested header
   * @param string $header
   * @return string
   */
  public function getHeader(string $header): string {
    return filter_input(INPUT_SERVER, 'HTTP_' . strtoupper(str_replace('-', '_', $header))) ?? '';
  }

  /**
   * Установка текущего роута с последующим парсингом его в действие и модуль
   *
   * @access public
   * @param string|null $route
   * @return $this
   */
  public function setRoute(?string $route): self {
    $this->route = $route ?? '/home';
    return $this;
  }

  /**
   * Current route
   * @access public
   * @return string
   */
  public function getRoute(): string {
    return $this->route ?? '';
  }

  /**
   * Set action that's processing now
   * @access public
   * @param string|null$route
   * @return $this
   */
  public function setAction(?string $action): self {
    $this->action = $action
      ? trim(preg_replace('|[^a-z0-9\_\-\/]+|is', '', $action), '/')
      : 'home'
    ;
    return $this;
  }

  /**
   * Get current action
   * @access public
   * @return string
   */
  public function getAction(): string {
    return $this->action ?? config('default.action');
  }
}

/**
 * Класс для формирования ответа клиенту
 *
 * @final
 *  @package Core
 * @subpackage Config
 */

final class Response {
  /**
   * @property array $headers Список заголовков, которые отправляются клиенту
   * @property string $body ответ клиенту, содержаший необходимый контент на выдачу
   * @property int $status код HTTP-статуса
   *
   * @property array $messages возможные статусы и сообщения HTTP-ответов
   */
  protected array
  $headers  = [
    'Referrer-Policy' => 'origin-when-cross-origin',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'X-Content-Type-Options' => 'nosniff',
    'Content-Security-Policy' => "frame-ancestors 'none'",
  ];

  protected string
  $body     = '';

  protected int
  $status   = 200;

  protected static array
  $messages = [
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authoritative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',

    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    307 => 'Temporary Redirect',

    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Timeout',
    409 => 'Conflict',
    410 => 'Gone',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Long',
    415 => 'Unsupported Media Type',
    416 => 'Requested Range Not Satisfiable',
    417 => 'Expectation Failed',

    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Timeout',
    505 => 'HTTP Version Not Supported',
  ];

  /**
   * Init of new response
   * @param int $status HTTP Status of response
   * @return void
   */
  final protected function __construct(int $status = 200) {
    $this->status($status);
  }

  /**
   * Return current instance or initialize and parse
   */
  public static function current(): self {
    static $instance;
    if (!isset($instance)) {
      $instance = new static(200);
    }

    return $instance;
  }


  /**
   * Change HTTP status of response
   * @param int $status New HTTP status to be set
   * @return $this
   */
  public function status(int $status): self {
    assert(isset(self::$messages[$status]));
    if (isset(self::$messages[$status])) {
      $this->status = $status;
    }
    return $this;
  }

  /**
  * Get response body
  * @access public
  * @return string данные ответа клиенту
  */
  public function __toString(): string {
    return (string) $this->body;
  }

  /**
   * Send body to output
   * @return $this
   */
  public function sendBody(): self {
    echo (string) $this;
    return $this;
  }

  /**
   * Send all staff to output: headers, body and so on
   * @return $this
   */
  public function send(string $content = ''): self {
    return $this->sendHeaders()->setBody($content)->sendBody();
  }

  /**
  * Relocate user to url
  * @param string $url полный HTTP-адрес страницы для редиректа
  * @param int $code код редиректа (301 | 302)
  * @return void
  */
  public static function redirect(string $url, int $code = 302): void {
    assert(in_array($code, [301, 302]));

    if ($url[0] === '/') {
      $url = Lang::getUrlPrefix() . $url;
    }

    (new static($code))
      ->header('Content-type', '')
      ->header('Location', $url)
      ->sendHeaders()
    ;
    exit;
  }

  /**
  * Reset headers stack
  * @return Response
  */
  public function flushHeaders(): self {
    $this->headers = [];
    return $this;
  }

  /**
  * Push header to stack to be sent
  * @param string $header
  * @param string $value
  * @return Response
  */
  public function header(string $header, string $value): self {
    $this->headers[$header] = $value;
    return $this;
  }

  /**
   * Send stacked headers to output
   * @return Response
   */
  protected function sendHeaders(): self {
    Cookie::send(); // This is not good but fuck it :D
    if (headers_sent()) {
      return $this;
    }
    $protocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL') ?: 'HTTP/1.1';

    // HTTP-строка статуса
    header($protocol . ' ' . $this->status . ' ' . self::$messages[$this->status], true);

    foreach ($this->headers as $header=>$value) {
      header($header . ': ' . $value, true);
    }

    // Send header with execution time
    header('X-Server-Time: ' . intval($_SERVER['REQUEST_TIME_FLOAT'] * 1000));
    header('X-Response-Time: ' . intval((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000), true);
    return $this;
  }

  /**
  * Set boy data to response
  * @access public
  * @param string $body
  * @return $this
  */
  public function setBody(string $body): self {
    $this->body = $body;
    return $this;
  }
}

/**
 * Class Session
 * Work with sessions
 *
 * <code>
 * Session::start();
 * Session::set('key', 'Test value');
 * Session::get('key');
 * Session::remove('key');
 * if (Session::has('key')) echo 'Found key in Session';
 * Session::regenerate();
 * </code>
 *
 * Add calculated data if key not exists
 * <code>
 * Session::add('key', function () { return time(); });
 * </code>
 *
 * Get key from session with default value
 * <code>
 * Session:get('key', 'default');
 * </code>
 */
final class Session {
  /** @var Session $Instance */
  protected static self $Instance;

  /** @var array $container */
  protected static array $container = [];

  public final function __construct() {}

  public static function start(): void {
    session_name(config('session.name'));
    session_start();
    static::$container = &$_SESSION;
  }

  public static function id(): string {
    return session_id();
  }

  public static function destroy(): bool {
    return session_destroy();
  }

  /**
   * Regenerate new session ID
   */
  public static function regenerate(bool $destroy = false): void {
    session_regenerate_id($destroy);
  }

  /**
   * @param string $key
   * @return bool
   */
  public static function has(string $key): bool {
    return isset(static::$container[$key]);
  }

  /**
   * Add new session var if it not exists
   * @param string $key
   * @param mixed $value Can be callable function, so it executes and pushes
   * @return void
   */
  public static function add(string $key, mixed $value): void {
    if (!static::has($key)) {
      static::set($key, is_callable($value) ? $value() : $value);
    }
  }

  /**
   * Set new var into session
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public static function set(string $key, mixed $value): void {
    static::$container[$key] = $value;
  }

  /**
   * Remove the key from session array
   * @param string $key
   * @return bool
   */
  public static function remove(string $key): bool {
    if (isset(static::$container[$key])) {
      unset(static::$container[$key]);
      return true;
    }
    return  false;
  }

  /**
   * Alias for self::remove
   * @see self::remove
   */
  public static function delete(string $key): bool {
    return static::remove($key);
  }

  /**
   * Get var with key from session array
   * @param string $key
   * @param mixed $default Return default there is no such key, set on closure
   * @return mixed
   */
  public static function get(string $key, mixed $default = null): mixed {
    if (!static::has($key) && $default && is_callable($default)) {
      $default = $default();
      static::set($key, $default);
    }
    return static::has($key) ? static::$container[$key] : $default;
  }
}

/**
 * Класс реализации представления
 *
 * @final
 * @package Core
 * @subpackage View
 *
 * <code>
 * View::create('template')->set(['test_var' => 'test_val'])->render();
 * </code>
 */
final class View {
  const VAR_PTRN = '\!?[a-z\_]{1}[a-z0-9\.\_]*';

  /**
   * @property array $data массив переменных, которые использует подключаемый шаблон
   * @property string $body обработанные и готовые данные для отдачи их клиенту
   */
  protected array
  $data = [],
  $routes = [],
  $output_filters = [],
  $compilers = [];

  protected string
  $body,
  $source_dir,
  $compile_dir,
  $prefix = 'c';

  protected static array $filter_funcs = [
    'html' => 'htmlspecialchars',
    'url'  => 'rawurlencode',
    'json' => 'json_encode',
    'upper' => 'strtoupper',
    'lower' => 'strtolower',
    'ucfirst' => 'ucfirst',
    'md5' => 'md5',
    'nl2br' => 'nl2br',
    'count' => 'sizeof',
    'base64' => 'base64_encode',
    'lang' => 'Lang::translate',
    'date' => 'view_filter_date',
    'time' => 'view_filter_time',
    'datetime' => 'view_filter_datetime',
    'timestamp' => 'view_filter_timestamp',
    'raw'  => '',
  ];

  /** @var string $template_extension */
  protected string $template_extension = 'tpl';

  /** @var array $block_path */
  protected array $block_path = [];

  /**
   * Финальный приватный конструктор, через него создания вида закрыто
   *
   * @see self::create
   */
  final protected function __construct() {
    $this->routes = [config('default.action')];

    // Setup default settings
    $this->template_extension = config('view.template_extension');
    $this->source_dir = config('view.source_dir');
    $this->compile_dir = config('view.compile_dir');
  }

  /**
   * Add custom filter function that can be used with template var to modify it
   *
   * @param string $name alias to use in template
   * @param string $func global name of function
   * @return void
   */
  public static function registerFilterFunc(string $name, string $func): void {
    assert(!isset(static::$filter_funcs[$name]));
    if (str_contains($func, '::')) {
      [$class, $method] = explode('::', $func);
      assert(method_exists($class, $method));
    } else {
      assert(function_exists($func));
    }
    static::$filter_funcs[$name] = $func;
  }

  public function configure(array $config): self {
    foreach ($config as $prop => $val) {
      if (property_exists($this, $prop)) {
        $this->$prop = $val;
      }
    }
    return $this;
  }

  /**
   * @param string $template
   * @return View
   */
  public function prepend(string $template): self {
    array_unshift($this->routes, $template);
    return $this;
  }

  /**
   * @param string $template
   * @return View
   */
  public function append(string $template): self {
    $this->routes[] = $template;
    return $this;
  }

  /**
   * Создание нового объекта вида
   *
   * @static
   * @access public
   * @param string $route Список всех роутов в нужной последовательности для сборки
   * @return View
   */
  public static function create(...$routes): self {
    $View = new static;
    $View->routes = $routes;
    return $View;
  }

  public static function fromString(string $content): self {
    $View = new static;
    $View->body = $content;
    return $View;
  }

  /**
   * Получает уже обработанные и готовые данные для вывода функцией self::render()
   *
   * @access public
   * @return string
   */
  public function __toString(): string {
    return $this->getBody();
  }

  public function addOutputFilter(Callable $filter): self {
    $this->output_filters = $filter;
    return $this;
  }

  protected function getBody(): string {
    $body = $this->body;
    foreach ($this->output_filters as $filter) {
      $body = $filter($body);
    }
    return $body;
  }

  /**
   * Прикрепление массива как разных переменных в шаблон
   *
   * @access public
   * @param array $data
   * @return View
   */
  public function set(array $data): self {
    $this->data = $data;
    return $this;
  }

  public function assign(string|array $key, mixed $val = null): self {
    if (is_string($key)) {
      $this->data[$key] = $val;
    } elseif (is_array($key)) {
      $this->data = array_merge($this->data, $key);
    }
    return $this;
  }

  public function &access(string $key): mixed {
    return $this->data[$key];
  }

  /**
   * Обработчик блочных элементов скомпилированном шаблоне
   *
   * @param string $key
   *   Имя переменной
   * @param mixed $param
   *   Сам параметр, его значение
   * @param mixed $item
   *   Текущий айтем, т.к. возможно блок является вложенным и нужно передать текущий
   *   обходной элемент, если блок не является массивом
   * @param Closure $block
   *   Скомпилированный код, которые отображается внутри блока
   * @return View
   */
  protected function block(string $key, mixed $param, mixed $item, Closure $block): self {
    static $arrays = [];
    $arrays[$key] = is_array($param);
    if ($arrays[$key] && is_int(key($param))) {
      $last = sizeof($param) - 1;
      $i = 0;
      foreach ($param as $k => $value) {
        if (!is_array($value)) {
          $value = ['parent' => $item, 'this' => $value];
        }

        $value['global']     = &$this->data;
        $value['first']      = $i === 0;
        $value['last']       = $i === $last;
        $value['even']       = $i % 2 ?  true : false;
        $value['odd']        = !$value['even'];
        $value['iteration']  = ++$i;
        $block($value);
      }
    } elseif ($param) {
      if ($arrays[$key]) {
        $item   = $param + ['global' => &$this->data, 'parent' => $item];
        $block($item);
        $item = $item['parent'];
      } else $block($item);

    }
    return $this;
  }


  protected static function chunkVar(string $v, string $container = '$item'): string {
    $var = '';
    foreach (explode('.', $v) as $p) {
      $var .= ($var ? '' : $container) . '[\'' . $p . '\']';
    }
    return $var;
  }


  protected static function chunkVarExists(string $v, string $container = '$item'): string {
    $parts = explode('.', $v);
    $sz = sizeof($parts);
    $var = '';
    $i = 0;
    foreach ($parts as $p) {
      ++$i;
      if ($i !== $sz) {
        $var .= ($var ? '' : $container) . '[\'' . $p . '\']';
      }
    }
    $array = ($var ?: $container);
    return 'isset(' . $array . ') && array_key_exists(\'' . $p . '\', ' . $array . ')';
  }

  protected static function chunkParseParams(string $str): string {
    $str = trim($str);
    if (!$str)
      return '';

    $code = '';
    foreach (array_map('trim', explode(' ', $str)) as $item) {
      [$key, $val] = array_map('trim', explode('=', $item));
      $code .= '<?php ' . static::chunkVar($key) . ' = ' . static::chunkVar($val) . '; ?>';
    }
    return $code;
  }

  /**
   * @param string $str
   * @return string
   */
  protected static function chunkTransformVars(string $str): string {
    $filter_ptrn = implode(
      '|' ,
      array_map(
        function($v) {
          return '\:' . $v;
        },
        array_keys(static::$filter_funcs)
      )
    );

    return preg_replace_callback(
      '#\{(' . static::VAR_PTRN . ')(' . $filter_ptrn . ')?\}#ium',
      function ($matches) {
        $filter = 'raw';
        if (isset($matches[2])) {
          $filter = substr($matches[2], 1);
        }

        return '<?php if (isset(' . ($v = static::chunkVar($matches[1], '$item')) . ')) {'
        . 'echo ' . static::$filter_funcs[$filter] . '(' . $v . ');'
        . '} ?>';
      },
      $str
    );
  }

  /**
   * Transform one line blocks to closed blocks
   * @param string $str
   * @return string
   */
  protected function chunkCloseBlocks(string $str): string {
    $line_block = '#\{(' . static::VAR_PTRN . ')\:\}(.+)$#ium';

    // Могут быть вложенные
    while (preg_match($line_block, $str) > 0) {
      $str = preg_replace($line_block, '{$1}' . PHP_EOL . '$2' . PHP_EOL . '{/$1}', $str);
    }

    return $str;
  }

  /**
   * @param string $str
   * @return string
   */
  protected function chunkCompileBlocks(string $str): string {
    return preg_replace_callback(
      '#\{(' . static::VAR_PTRN . ')\}(.+?){\/\\1}#ius',
      function ($m) {
        // Oh Shit so magic :)
        $this->block_path[] = $m[1];
        $compiled  = static::chunkTransformVars($this->chunkCompileBlocks($m[2]));
        array_pop($this->block_path);

        // Если стоит отрицание
        $denial = false;
        $key    = $m[1];

        if (str_starts_with($m[1], '!')) {
          $key = substr($m[1], 1);
        }

        if (strlen($m[1]) !== strlen($key)) {
          $denial = true;
        }

        return
        '<?php $param = ' . static::chunkVarExists($m[1], '$item') . ' ? ' . static::chunkVar($m[1], '$item') . ' : null;'
        . ($denial ? ' if (!isset($param)) $param = !( ' . static::chunkVarExists($key, '$item') . ' ? ' . static::chunkVar($key, '$item') . ' : null);' : '') // Блок с тегом отрицанием (no_ | not_) только если не существует переменной как таковой
        . '$this->block(\'' . $key . '\', $param, $item, function ($item) { ?>'
          . $compiled
        . '<?php }); ?>';
      },
      $str
    );
  }

  /**
   * Optimize output of compiled chunk if needed
   * @param string $str
   * @return string
   */
  protected function chunkMinify(string $str): string {
    // Remove tabs and merge into single line
    if (config('view.merge_lines')) {
      $str = preg_replace(['#^\s+#ium', "|\s*\r?\n|ius"], '', $str);
    }

    // Remove comments
    if (config('view.strip_comments')) {
      $str = preg_replace('/\<\!\-\-.+?\-\-\>/is', '', $str);
    }

    return $str;
  }

  /**
   * Компиляция примитивов шаблона
   *
   * @param string $route
   *   Роут шаблона для компиляции
   * @return string
   *   Имя скомпилированного файла
   */
  protected function compileChunk(string $route): string {
    $source_file = $this->getSourceFile($route);
    $file_c = $this->getCompiledFile([$route]);
    if (!App::$debug && is_file($file_c)) {
      return $file_c;
    }

    $str = file_get_contents($source_file);
    // Do precompile by custom compiler to make it possible to change vars after
    $compilers = array_merge($this->compilers[$route] ?? [], $this->compilers['*'] ?? []);
    if ($compilers) {
      foreach ($compilers as $compiler) {
        $str = $compiler($str, $route);
      }
    }

    $str = $this->chunkCloseBlocks($str);

    // Компиляция блоков
    $str = $this->chunkCompileBlocks($str);

    $str = $this->chunkMinify($str);

    // Замена подключений файлов
    $str = preg_replace_callback('#\{\>([a-z\_0-9\/]+)(.*?)\}#ium', function ($matches) {
      return static::chunkParseParams($matches[2]) . $this->getChunkContent($matches[1]);
    }, $str);

    // Замена динамичных подключений файлов
    $str = preg_replace_callback('#\{\>\>([a-z\_0-9\.]+)(.*?)\}#ium', function ($matches) {
      $route = static::chunkVar($matches[1], '$item');
      return '<?php '
        . '$this->compileChunk(' . $route . ');'
        .'include $this->getCompiledFile([' . $route . ']);'
        .'?>'
      ;
    }, $str);

    // Переменные: {array.index}
    $str = static::chunkTransformVars($str);

    file_put_contents($file_c, $str, LOCK_EX);
    return $file_c;
  }

  /**
   * Компиляция всех чанков и получение результата
   *
   * @return View
   */
  protected function compile(): self {
    $file_c = $this->getCompiledFile();
    if (App::$debug || !is_file($file_c)) {
      // Init global context
      $content = '<?php $item = &$this->data; ?>';
      foreach ($this->routes as $template) {
        $content .= $this->getChunkContent($template);
      }

      file_put_contents($file_c, $content, LOCK_EX);
    }
    include $file_c;
    return $this;
  }

  // This methods initialize and configure language if its required by config
  protected function initLanguage(): static {
    if (Lang::isEnabled()) {
      $lang = Lang::current();
      $this->configure([
        'compile_dir' => config('view.compile_dir') . '/' . $lang,
      ])
        ->addCompiler(Lang::getViewCompiler($lang))
        ->assign('LANGUAGE_LIST', Lang::getList($lang))
        ->assign('CURRENT_LANGUAGE', Lang::getInfo($lang))
        ->assign('LANG', $lang)
      ;
    }

    return $this;
  }

  protected function getChunkContent(string $template): string {
    return file_get_contents($this->compileChunk($template));
  }

  public function addCompiler(Callable $compiler, string $template = '*'): self {
    $this->compilers[$template][] = $compiler;
    return $this;
  }

  protected function getSourceFile(string $route): string {
    assert(is_dir($this->source_dir));
    assert(isset($this->template_extension[0]));

    return $this->source_dir . '/' . $route . '.' . $this->template_extension;
  }

  protected function getCompiledFile(array $routes = []): string {
    assert(is_dir($this->compile_dir) && is_writable($this->compile_dir));
    return $this->compile_dir . '/view-' . $this->prefix . '-' . md5($this->source_dir . ':' . implode(':', $routes ?: $this->routes)) . '.tplc';
  }

  /**
   * Рендеринг и подготовка данных шаблона на вывод
   *
   * @access public
   * @param bool $quiet Quiet mode render empty string if no template found
   * @return View
   *   Записывает результат во внутреннюю переменную $body
   *   и возвращает ссылку на объект
   */
  public function render(bool $quiet = false): self {
    $this->initLanguage();

    if (isset($this->body)) {
      return $this;
    }

    try {
      ob_start();
      $this->compile();
      $this->body = ob_get_clean();
    } catch (Throwable $e) {
      if ($quiet) {
        $this->body = '';
      } else {
        throw $e;
      }
    }
    return $this;
  }

  public static function flush(): void {
    system('for file in `find ' . escapeshellarg(config('view.compile_dir')) . ' -name \'view-*\'`; do rm -f $file; done');
  }
}

/**
 * Config workout for whole app
 * @param  string $param Param using dot for separate packages
 * @return mixed
 */
function config(string $param): mixed {
  static $config = [];
  if (!$config) {
    $config = include getenv('CONFIG_DIR') . '/config.php';
  }

  return $config[$param];
}

/**
 * Typify var to special type
 * @package Core
 * @param string $var Reference to the var that should be typified
 * @param string $type [int|integer, uint|uinteger, double|float, udboule|ufloat, bool|boolean, array, string]
 * @return void
 *
 * <code>
 * $var = '1'; // string(1) "1"
 * typify($var, $type);
 * var_dump($var); // int(1)
 * </code>
 */
function typify(&$var, string $type): void {
  switch ($type) {
    case 'int':
    case 'integer':
      $var = (int) $var;
      break;
    case 'uinteger':
    case 'uint':
      $var = (int) $var;
      if ($var < 0)
        $var = 0;
      break;
    case 'double':
    case 'float':
      $var = (float) $var;
      break;
    case 'udouble':
    case 'ufloat':
      $var = (float) $var;
      if ($var < 0)
        $var = 0.0;
      break;
    case 'boolean':
    case 'bool':
      $var = (in_array($var, ['no', 'none', 'false', 'off'], true) ? false : (bool) $var);
      break;
    case 'array':
      $var = $var ? (array) $var : [];
      break;
    case 'string':
      $var = (string) $var;
      break;
  }
}

/**
 * Triggered events
 * @param string $event
 * @param array $payload Дополнительные данные для манипуляции
 * @return mixed
 */
function trigger_event(string $event, array $payload = []): mixed {
  static $map;
  if (!isset($map)) {
    $map = Env::load(config('common.trigger_map_file'));
  }

  if (isset($map[$event])) {
    array_walk($map[$event], function ($_file) use ($payload) {
      extract(
        Input::extractTypified(
          App::getImportVarsArgs($_file, config('common.trigger_param_file')),
          function ($key, $default = null) use ($payload) {
            return $payload[$key] ?? $default;
          }
        )
      );
      include $_file;
    });
  }
}
/**
 * This is helper function to control dependencies in one container
 *
 * @param string $name
 * @param mixed $value if not set we do get container if set we do set container value
 * @return mixed
 */
function container(string $name, mixed $value = null): mixed {
  static $container = [];

  // Set container logic
  if (isset($value)) {
    assert(!isset($container[$name]));
    $container[$name] = $value;
    return $value;
  }

  // Get container logic
  assert(isset($container[$name]));
  $res = &$container[$name];
  if (is_callable($res)) {
    $res = $res();
  }
  return $res;
}

/**
 * Get short name for full qualified class name
 * @param string $class The name of class with namespaces
 * @return string
 */
function get_class_name(string $class): string {
  return (new ReflectionClass($class))->getShortName();
}

// Missed functions for large integers for BCmath
function bchexdec(string $hex): string {
  $dec = 0;
  $len = strlen($hex);
  for ($i = 1; $i <= $len; $i++) {
    $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
  }
  return $dec;
}

function bcdechex(string $dec): string {
  $hex = '';
  do {
    $last = bcmod($dec, 16);
    $hex = dechex($last).$hex;
    $dec = bcdiv(bcsub($dec, $last), 16);
  } while($dec > 0);
  return $hex;
}

function bench(int|bool $level = 0, ?string $txt = null): void {
  static $t = [], $r = [];
  if ($level === true) {
    foreach ($r as $txt => $vals) {
      echo $txt . ': ' . sprintf('%f', array_sum($vals) / sizeof($vals)) . 's' . PHP_EOL;
    }
    $t = $r = [];
    return;
  }
  $n = microtime(true);

  if ($txt && !isset($r[$txt])) {
    $r[$txt] = [];
  }

  if ($txt && isset($t[$level])) {
    $r[$txt][] = $n - $t[$level][sizeof($t[$level]) - 1];
  }
  $t[$level][] = $n;
}

function array_cartesian(array $arrays): array {
  $result = [];
  $keys = array_keys($arrays);
  $reverse_keys = array_reverse($keys);
  $size = intval(sizeof($arrays) > 0);
  foreach ($arrays as $array) {
    $size *= sizeof($array);
  }
  for ($i = 0; $i < $size; $i ++) {
    $result[$i] = [];
    foreach ($keys as $j) {
      $result[$i][$j] = current($arrays[$j]);
    }
    foreach ($reverse_keys as $j) {
      if (next($arrays[$j])) {
        break;
      } elseif (isset ($arrays[$j])) {
        reset($arrays[$j]);
      }
    }
  }
  return $result;
}

/**
 * This is simple helper in case we need to throw exception when has error
 *
 * @param $response
 *   Stanadrd array in presentation [err, result]
 *   Where err should be string and result mixed
 */
function result(array $response, string $error = 'result'): mixed {
  if (isset($response[0]) && is_array($response[0])) {
    if ($errors = array_filter(array_column($response, 0))) {
      throw new Error('Errors while ' . $error . ' in multiple result: ' . var_export($errors, true));
    }
    return array_column($response, 1);
  } else {
    [$err, $result] = $response;
    if ($err) {
      throw new Error('Error while ' . $error . ': ' . $err . '. Got result: ' . var_export($result, true));
    }
    return $result;
  }
}

// Filter function to format output
function view_filter_date(string $v): string {
  $ts = is_numeric($v) ? intval($v) : strtotime("$v UTC");
  return $ts ? date('Y-m-d', $ts) : $v;
}

function view_filter_time(string $v): string {
  $ts = is_numeric($v) ? intval($v) : strtotime("$v UTC");
  return $ts ? date('H:i', $ts) : $v;
}

function view_filter_datetime(string $v): string {
  $ts = is_numeric($v) ? intval($v) : strtotime("$v UTC");
  return $ts ? date('Y-m-d H:i:s', $ts) : $v;
}

function view_filter_timestamp(string $v): string {
  return strval(strtotime(intval($v)) ?: $v);
}

