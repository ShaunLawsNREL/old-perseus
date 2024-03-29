<?php
namespace Perseus;

/**
 * @file
 * Class to manage system variables and processes.
 */
define('SYSTEM_NOTICE',  1);
define('SYSTEM_WARNING', 2);
define('SYSTEM_ERROR',   3);

class System {
  // Database connctions
  private $db = array();

  // The server path to the root of the website.
  protected $siteroot;
  public $config_file;

  // The twig environment instance for theming.
  public $twig;

  // Registered theme locations
  protected $themes = array();

  // Configuration settings
  private $config;

  // Site settings
  public $settings;

  /**
   * Constructor
   *
   * @param $siteroot
   *   The server path to the root of the website.
   */
  public function __construct($siteroot) {
    $this->siteroot    = $siteroot;
    $this->config_file = $this->siteroot . '/settings/perseus.php';

    // Initialize subsystems
    try {
      $this->initMessages();
      $this->initConfig();
      $this->initSettings();
      $this->initErrorHandler();
      $this->initTheme();
    }
    catch (Exception $e) {$this->handleException($e);}
  }

  /**
   * Initialize the messaging system.
   */
  private function initMessages() {
    // Instantiate system messages.
    if (!isset($_SESSION['messages'])) {
      $_SESSION['messages'] = array();
    }
  }

  /**
   * Initialize the system configuration variables.
   */
  private function initConfig() {
    $init = array();

    if (file_exists($this->config_file)) {
      include($this->config_file);
      $this->config = $vars;
    }
    else {
      throw new Exception('Unable to load perseus settings at ' . $this->config_file . '.', SYSTEM_ERROR);
    }
  }


  /**
   * Get the site settings from the settings.php file.
   */
  private function initSettings() {
    // Ensure the file exists
    $file = $this->siteroot . '/settings/settings.php';
    $settings = array();

    if (file_exists($file)) {
      include($file);
      $this->settings = $settings;
    }
  }

  /**
   * Initialize the error handling system.
   */
  private function initErrorHandler() {
    set_error_handler(array($this, 'handleErrors'));
  }

  /**
   * Initialize the theme system.
   */
  private function initTheme() {
    $templates = array();
    $settings = $this->config['twig'];

    // First, the default theme
    $this->themes[] = PROOT . '/theme';
    $templates[] = PROOT . '/theme/templates';

    // Next, site overrides
    $site_theme = $this->siteroot . '/theme';
    if (file_exists($site_theme)) {
      $this->themes[] = $site_theme;
      $templates[] = "{$site_theme}/templates";
    }

    // Instantiate Twig
    $loader = new \Twig_Loader_Filesystem(array_reverse($templates));
    $this->twig = new \Twig_Environment($loader, $settings);

    // Add HTML Attributes function to Twig
    $function = new \Twig_SimpleFunction('html_attributes', function(array $attributes = array()) {
      foreach ($attributes as $attribute => &$data) {
        $data = implode(' ', (array) $data);
        $data = $attribute . '="' . check_plain($data) . '"';
      }
      return $attributes ? ' ' . implode(' ', $attributes) : '';
    });
    $this->twig->addFunction($function);
  }

  /**
   * Handle PHP errors thrown through our custom PHP Error Handler.
   */
  public function handleErrors($errno, $errstr, $errfile, $errline) {
    try {
      $krumo = $this->config['krumo'];

      // Load the Krumo library.
      if ($krumo['enabled']) {
        $path = PROOT . '/includes/krumo';

        // Write the .ini file
        $ini = array(
          'skin' => array('selected' => $this->config['krumo']['skin']),
          'css'  => array('url' => $path),
        );
        $content = self::parseIniArray($ini);
        file_put_contents("$path/krumo.ini", $content);

        // Include the Krumo class
        include_once("{$path}/class.krumo.php");
      }

      throw new PhpErrorException($errno, $errstr, $errfile, $errline, $krumo['enabled']);
    }
    catch(Exception $e) {System::handleException($e);}
  }

  /**
   * Set a status message.
   */
  static function setMessage($msg, $type = SYSTEM_NOTICE) {
    $_SESSION['messages'][$type][] = $msg;
  }

  /**
   * Retrieve a message.
   */
  static function getMessages($type = NULL, $purge = TRUE) {
    $messages = NULL;
    if (isset($type) && isset($_SESSION['messages'][$type])) {
      $messages = $_SESSION['messages'][$type];
      if ($purge) {
        unset($_SESSION['messages'][$type]);
      }
    }
    elseif (isset($_SESSION['messages'])) {
      $messages = $_SESSION['messages'];
      if ($purge) {
        unset($_SESSION['messages']);
      }
    }

    return (array)$messages;
  }

  /**
   * Return error codes.
   */
  static function errorCodes($code = NULL) {
    $codes = array(
      SYSTEM_NOTICE =>  'notice',
      SYSTEM_WARNING => 'warning',
      SYSTEM_ERROR =>   'error',
    );

    return ($code ? $codes[$code] : $codes);
  }

  /**
   * Retrieve an object from the Session var.
   */
  protected function fetchObject($name) {
    if (!empty($_SESSION['perseus']['object'][$name])) {
      return $_SESSION['perseus']['object'][$name];
    }
  }

  /**
   * Retrieve an object from the Session var.
   */
  protected function storeObject($obj, $name) {
    $_SESSION['perseus']['object'][$name] = $obj;
  }

  /**
   * Remove an object from the session cache.
   */
  protected function expungeObject($name) {
    if (!empty($_SESSION['perseus']['object'][$name])) {
      unset($_SESSION['perseus']['object'][$name]);
    }
  }

  /**
   * Load a new service object.
   */
  public function newService($type, array $settings = array()) {
    switch (strtolower($type)) {
      case 'csv':
        return new \Perseus\CSV($this, $settings);
        break;

      case 'csvexporter':
        return new \Perseus\CSVExporter($this, $settings);
        break;

      case 'phpmail':
      case 'mail':
        return new \Perseus\PhpMail($this, $settings);
        break;

      case 'form':
        return new \Perseus\Form($this, $settings);
        break;

      case 'mysql':
      case 'db':
        // Save the db connection.
        list($key, $value) = each($settings);
        $new_db = new \Perseus\MySQL($this, $settings);
        $this->db[$key] = $new_db;
        return $new_db;
        
        break;

      case 'test':
        return new \Perseus\Test($this, $settings);
        break;

      case 'xml':
        return new \Perseus\XMLParser($this, $settings);
        break;
    }
  }

  /**
   * Include a file.
   */
  static function fileInclude($path) {
    $file = PROOT . "/$path";
    if (file_exists($file)) {
      include $file;
      return $file;
    }
    else {
      throw new Exception("Unable to locate file at $file.", SYSTEM_WARNING);
    }
  }

  /**
   * Require a file.
   */
  static function fileRequire($path) {
    $file = PROOT . "/$path";
    if (is_file($file)) {
      require_once $file;
      return $file;
    }
    else {
      throw new Exception("Unable to locate file at $file.", SYSTEM_ERROR);
    }
  }

  /**
   * Check whether an event is allowed to occur.
   */
  public function floodIsAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    if (!isset($identifier)) {
      $identifier = $_SERVER['REMOTE_ADDR'];
    }

    // Prepare the data
    $data = array(
      "event = '{$name}'",
      "identifier = '{$identifier}'",
      "timestamp > " . (time() - $window),
    );

    // Requires a MySQL connection.
    try {
      $sql = $this->db();
      $res = $sql->select('flood', array('COUNT(*) as count'), $data);
    }
    catch (Exception $e) {$this->handleException($e);}

    return (isset($res) && ($res[0]->count < $threshold));
  }

  /**
   * Flood Controler.  Registers an event into the flood log.
   */
  public function floodRegisterEvent($name, $window = 3600, $identifier = NULL) {
    if (!isset($identifier)) {
      $identifier = $_SERVER['REMOTE_ADDR'];
    }

    // Prepare the data
    $data = array(
      'event' => $name,
      'identifier' => $identifier,
      'timestamp' => time(),
      'expiration' => time() + $window,
    );

    // Requires a MySQL connection.
    try {
      $sql = $this->db();
      $sql->insert('flood', $data);
    }
    catch (Exception $e) {$this->handleException($e);}
  }

  /**
   * Get an instance of the database object.
   */
  public function db($database = 'default') {
    try {
      if (isset($this->db[$database]) && is_object($this->db[$database])) {
        return $this->db[$database];
      }
      else {
        // Get the creds.
        $creds = $this->init('db');
        if (isset($creds[$database])) {
          $db = $this->newService('db', $creds[$database]);

          if ($db->isConnected()) {
            $this->db[$database] = $db;
            return $this->db[$database];
          }
          else {
            throw new Exception('Unable to load database.  Connection error.', SYSTEM_ERROR);
          }
        }
        else {
          throw new Exception('Unable to load database.  Credentials not provided.', SYSTEM_ERROR);
        }
      }
    }
    catch (Exception $e) {$this->handleException($e);}
  }

  /**
   * Exception Handler
   */
  static function handleException($e) {}

  /**
   * Redirect to a new URL.
   */
  public function redirect($path, $options = array(), $code = '302') {
    $url = url($path, $options);
    header("Location: $url", TRUE, $code);
    exit;
  }

  /**
   * Parse an array into an ini file string.
   */
  static function parseIniArray($array, $i = 0) {
    $str = "";

    foreach ($array as $k => $v){
      if (is_array($v)) {
        $str .= str_repeat(" ", $i * 2) . "[$k]" . PHP_EOL;
        $str .= self::parseIniArray($v, $i + 1);
      }
      else {
        $str .= str_repeat(" ", $i * 2) . "$k = $v" . PHP_EOL;
      }
    }

    return $str;
  }

  /**
   * Theme an item.
   */
  public function theme($template, $vars = array()) {
    $out = '';

    try {
      // Call processors for each implementation.
      foreach ($this->themes as $theme) {
        $processor_file = "$theme/processors/{$template}.inc";
        if (file_exists($processor_file)) {
          System::themeProcessVars($processor_file, $vars);
        }
      }

      $out = $this->twig->render("{$template}.html", $vars);
    }
    catch(Exception $e){System::handleException($e);}

    return $out;
  }

  /**
   * Process variables for a theme.
   *
   * Keep this in a separate function to isolate variable scope.
   */
  static function themeProcessVars($file, &$vars) {
    include($file);
  }
}

/**
 * Installer Class
 */
class SystemInstaller extends Installer implements InstallerInterface {
  // Register installation procedures
  private $install = array('flood');

  /**
   * Constructor
   */
  public function __construct($system) {
    parent::__construct($system);
  }

  /**
   * Install/configure the necessary parts for the tool to function properly.
   */
  public function install($do = array()) {
    try {
      $do = (array) $do;

      if (empty($do)) {
        $do = $this->install;
      }

      // Run each installation procedure.
      foreach ($do as $install) {
        switch ($install) {
          case 'flood':
            $this->createTable('flood');
            break;
        }
      }
    }
    catch(Exception $e){System::handleException($e);}
  }

  /**
   * Define the database schemas
   */
  public function schema($table) {
    $schema['flood'] = "CREATE TABLE IF NOT EXISTS flood (
      fid int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique flood event ID.',
      event varchar(64) NOT NULL DEFAULT '' COMMENT 'Name of event (e.g. contact).',
      identifier varchar(128) NOT NULL DEFAULT '' COMMENT 'Identifier of the visitor, such as an IP address or hostname.',
      timestamp int(11) NOT NULL DEFAULT '0' COMMENT 'Timestamp of the event.',
      expiration int(11) NOT NULL DEFAULT '0' COMMENT 'Expiration timestamp. Expired events are purged on cron run.',
      PRIMARY KEY (fid),
      KEY allow (event,identifier,timestamp),
      KEY purge (expiration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Flood controls the threshold of events, such as the...' AUTO_INCREMENT=1 ;";

    return (isset($schema[$table]) ? $schema[$table] : '');
  }
}

