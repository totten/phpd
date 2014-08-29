<?php
class PHPD_Client {
  public static function main() {
    $iniFile = getenv('HOME') . '/.phpd.ini';
    $ini = PHPD_INI::load($iniFile);

    $request = new PHPD_Request(
      $iniFile,
      $ini,
      PHPD_Command::createFromGlobals()->encode(),
      time()
    );
    echo self::filterResult($request->send());
  }

  public static function filterResult($result) {
    if (substr($result, 0, 2) == '#!') {
      return substr($result, strpos($result, "\n"));
    } else {
      return $result;
    }
  }
}

class PHPD_Server {
  public static function main() {
    ini_set('max_execution_time', 30*60);
    $request = PHPD_Request::parse($_POST);
    if (!$request->hasValidSignature()) {
      die("Invalid signature\n");
    }
    $command = PHPD_Command::createFromString($request->getPayload());
    // print_r($command);
    $command->run();
  }
}

class PHPD_Command {
  /**
   * @var array
   */
  var $data;
  
  /**
   * Create a command using globals like $argc, $argv
   *
   * @return PHPD_Command
   */
  public static function createFromGlobals() {
    global $_ENV, $argc, $argv;
    $my_argv = $argv;
    array_shift($my_argv);
    
    $result = new PHPD_Command();
    $result->data = array(
      'env' => $_ENV,
      'argv' => $my_argv,
      'argc' => $argc - 1,
      'pwd' => getcwd(),
      'cmd' => realpath($my_argv[0]),
    );
    return $result;
  }

  /**
   * Create a command by parsing a serialized string
   *
   * @return PHPD_Command
   */
  public static function createFromString($str) {
    $result = new PHPD_Command();
    $result->data = json_decode($str, TRUE);
    return $result;
  }

  /**
   * Format the command as a serialized string
   *
   * @return string
   */
  public function encode() {
    return json_encode($this->data);
  }

  public function run() {
    global $_ENV, $argv, $argc;
    foreach ($this->data['env'] as $key => $value) {
      $_ENV[$key] = $value;
      putenv("$key=$value");
    }
    $_SERVER['argv'] = $argv = $this->data['argv'];
    $_SERVER['argc'] = $argc = $this->data['argc'];
    chdir($this->data['pwd']);
    require $this->data['cmd'];
  }
}

class PHPD_Request {
  const TTL = 60;

  protected $iniFle, $ini, $payload, $sig;

  /**
   * @param string $iniFile local file path
   * @param array $ini fully-loaded iniFile
   * @param array $payload
   * @param int $ts the current timestamp (seconds since epoch)
   * @param string $sig md5sum
   */
  public function __construct($iniFile, $ini, $payload, $ts, $sig = NULL) {
    $this->iniFile = realpath($iniFile);
    $this->ini = $ini;
    $this->payload = $payload;
    $this->ts = $ts;
    $this->sig = $sig;
  }

  public static function parse($post) {
    return new PHPD_Request(
      $post['phpd_ini'],
      PHPD_INI::load($post['phpd_ini']),
      $post['phpd_data'],
      $post['phpd_ts'],
      $post['phpd_sig']
    );
  }

  /**
   * @return array (string $key => string $value)  list of POST parameters
   */
  public function encode() {
    return array(
      'phpd_ini' => $this->iniFile,
      'phpd_data' => $this->payload,
      'phpd_ts' => $this->ts,
      'phpd_sig' => $this->createSignature(),
    );
  }

  public function hasValidSignature() {
    if (! $this->sig) {
      throw new Exception("No signature to check");
    }
    if (time() > $this->ts + self::TTL) {
      echo "bad ts\n";
      return FALSE;
    }
    return ($this->createSignature() === $this->sig);
  }

  public function createSignature() {
    return md5($this->iniFile . ':::' . $this->payload . ':::' . $this->ts . ':::' . $this->ini['phpd_auth_token']);
  }
  
  public function getPayload() {
    return $this->payload;
  }

  public function send() {
    $opts = array('http' =>
      array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query($this->encode()),
      )
    );
    $context  = stream_context_create($opts);
    return file_get_contents($this->ini['stub_url'], false, $context);
  }
}

class PHPD_INI {
  public static function load($iniFile) {
    // ';^[a-zA-Z0-9\-_\. \/]/.phpd.ini$;'
    if (empty($iniFile) || !preg_match('/^[a-zA-Z0-9\-\. \/]+\/\.phpd\.ini$/', $iniFile)) {
      die("phpd.ini: Unspecified or malformed [$iniFile]\n");
    }

    if (! file_exists($iniFile)) {
      die("phpd.ini: Missing file \"$iniFile\"\n");
    }

    $ini = parse_ini_file($iniFile);
    if (!$ini) {
      die("phpd.ini: Invalid file \"$iniFile\"");
    }

    foreach (array('phpd_path', 'phpd_auth_token', 'stub_url', 'stub_path') as $key) {
      if (empty($ini[$key])) {
        die("phpd.ini: missing key \"$key\"\n");
      }
    }

    if (!preg_match(';http://.*(localhost|127\.0\.);', $ini['stub_url'])) {
      die("phpd.ini: stub_url must be local\n");
    }

    return $ini;
  }
}
