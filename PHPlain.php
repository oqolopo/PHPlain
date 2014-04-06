<?php

/**
PHPlain PHP Framework
Copyright 2011-2012 Alexander Angelov, oqolopo (at) gmail (dot) com

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
*/

class PHPlainConfigBase {

	const
		PATH_SQL			= 'sql',
		PATH_INC			= 'inc',
		PATH_ERRORS			= 'inc/errors',
		PATH_IMAGES			= 'inc/images',
		PATH_BIN			= 'bin',
		PATH_ETC			= 'etc',
		PATH_LC				= 'etc/lc',

		UNRESOLVED_URI		= 'unresolved_uri.php',
		SESSION_FAILS		= 'session_error.php',

		DEFAULT_TIMEZONE	= 'Europe/London',
		DEFAULT_LOCALE		= 'en_UK',
		TEXT_DOMAIN			= NULL,

		SUPERUSER_ROLE		= 'root',
		DISABLED_ROLE		= 'disabled',

		USE_PATH_INFO		= FALSE,

		PHPLAIN_STORE		= '_phplain',

		CONSOLE_FIREPHP		= 'FirePHPCore/FirePHP.class.php',
		CONSOLE_CHROMEPHP	= 'ChromePhp.php',

		PLACEHOLDER_WHERE	= '~where',
		PLACEHOLDER_AND		= '~and',
		PLACEHOLDER_ORDER	= '~order',
		PLACEHOLDER_BY		= '~by',
		PLACEHOLDER_PAGE	= '~page',
		PLACEHOLDER_SIZE	= '~size',
		PLACEHOLDER_SKIP	= '~skip',
		PLACEHOLDER_FROM	= '~from',
		PLACEHOLDER_SELECT	= '~select',
		SELECT_TEMPLATE		= '~select ~from ~where ~order ~page';

	static public
		$routes				= array(),
		$connections		= array(),
		$pagination 		= array('select'	=> 'SQL_CALC_FOUND_ROWS',
									'first'		=> 'LIMIT 1',
									'limit'		=> 'LIMIT ~size OFFSET ~skip',
									'count'		=> 'FOUND_ROWS()');

}

class PHPlainCodes {

	const
		OK				=  0,
		UNKNOWN_CLASS	=  1,
		UNKNOWN_METHOD	=  2,
		FORBIDDEN		=  3,
		INACCESSIBLE	=  4,
		NOT_ALLOWED		=  5,
		DENIED			=  6,
		REQUIRED		=  7,
		VALIDATION		=  8,
		SESSION_FAILS	=  9,
		UNRESOLVED_URI	= 10;

	static public
		$messages = array(
			'No errors detected',
			'Unknown application class is requested',
			'Unknown application method is requested',
			'Non-public application method is requested',
			'Inaccessible application method is requested',
			'User has no role enumerated in allowed list',
			'User has role enumerated in denied list',
			'Required argument is not supplied',
			'Validation failed',
			'Session starting failed',
			'URI resolving failed'
		);

}

function set($name, $value) {
	return PHPlainUtil::$variables[$name] = $value;
}

class PHPlainUtil {

	static public $variables = array();

	static public function reindex(array $source, $base = 0, $step = 1) {
		if ($source) return array_combine(range($base,
			count($source) * $step + $base - $step, $step), array_values($source));
		else return array();
	}

	static public function fill(array $indexes, $value) {
		array_combine(array_values($indexes), array_fill(0, count($indexes), $value));
	}

	static public function select($sql, $select = '*', $pagination = NULL)
	{
		$sreg = '/' . preg_quote(PHPlainConfig::PLACEHOLDER_SELECT) . '/';
		if ($pagination) $select = "SELECT $pagination[select] $select";
		else $select = "SELECT $select";
		return preg_replace($sreg, $select, $sql);
	}

	static public function where($sql, $where = '') {
		$wreg = '/\s' . preg_quote(PHPlainConfig::PLACEHOLDER_WHERE) . '/';
		$areg = '/\s' . preg_quote(PHPlainConfig::PLACEHOLDER_AND) . '/';
		if ($where != PHPlainConfig::PLACEHOLDER_AND) $and = "AND ($where)";
		else $and = $where;
		if ($where) return preg_replace($wreg, " WHERE $where",
			preg_replace($areg, " $and", $sql));
		else return preg_replace($wreg, '', preg_replace($areg, '', $sql));
	}

	static public function orderBy($sql, $orderBy = '') {
		$oreg = '/\s' . preg_quote(PHPlainConfig::PLACEHOLDER_ORDER) . '/';
		$breg = '/\s' . preg_quote(PHPlainConfig::PLACEHOLDER_BY) . '/';
		if ($orderBy != PHPlainConfig::PLACEHOLDER_BY) $by = ", $orderBy";
		else $by = $orderBy;
		if ($orderBy) return preg_replace($oreg, " ORDER BY $orderBy",
			preg_replace($breg, $by, $sql));
		else return preg_replace($oreg, '', preg_replace($breg, '', $sql));
	}

	static public function page($sql, $pagination, $size = 0, $page = 1) {
		if ($size) $place = preg_replace('/' . preg_quote(PHPlainConfig::PLACEHOLDER_SIZE)
			. '/', $size, preg_replace('/' . preg_quote(PHPlainConfig::PLACEHOLDER_SKIP)
									   . '/', ($page - 1) * $size, $pagination['limit']));
		else $place = FALSE;
		$preg = '/\s' . preg_quote(PHPlainConfig::PLACEHOLDER_PAGE) . '/';
		if ($place) return preg_replace($preg, " $place", $sql);
		else return preg_replace($preg, '', $sql);
	}

	static public function prepare($sql, $select = '*', $where = '', $orderBy = '',
								   $pagination = NULL, $size = 0, $page = 1)
	{
		return self::page(self::where(self::orderBy(
			self::select($sql, $select, $size? $pagination: NULL), $orderBy), $where),
			$pagination, $size, $page);
	}

	static public function verb($sql) {
		return strtolower(strtok($sql, "\t\n "));
	}

	static public
		$mime = array(
			'js'	=> 'application/x-javascript',
			'css'	=> 'text/css',
			'html'	=> 'text/html'
		);

	static public function mime($file) {
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		if (isset(self::$mime[$ext])) return self::$mime[$ext];
		if (function_exists('finfo_open')) {
			$info = finfo_open(FILEINFO_MIME_TYPE);
			$mime = finfo_file($info, $file);
			finfo_close($info);
		}
		elseif (function_exists('mime_content_type')) $mime = mime_content_type($file);
		else return 'application/octet-stream';
		return $mime;
	}

	static public function download($file) {
		if (file_exists($file)) {
			header('Content-Type: ' . self::mime($file));
			/*
			header('Content-Length: ' . filesize($file));
			header("Content-Disposition: inline; filename=" . basename($file));
			header("Content-Transfer-Encoding: binary\n");
			*/
			readfile($file);
			return TRUE;
		} else return FALSE;
	}

	static public function console() {
		if (PHPlainConfig::CONSOLE_FIREPHP)
			@include_once(PHPlainConfig::CONSOLE_FIREPHP);
		if (PHPlainConfig::CONSOLE_CHROMEPHP)
			@include_once(PHPlainConfig::CONSOLE_CHROMEPHP);
		ob_start();

		function console() {
			if (func_num_args() > 1) $args = func_get_args();
			else $args = func_get_arg(0);
			if (class_exists('FB')) FB::log($args);
			if (class_exists('ChromePhp')) ChromePhp::log($args);
		}
	}

	static public function plug($plugin) {
		include_once(dirname(__FILE__) . "/$plugin.php");
	}

	static public function comparison($col, $op, $val) {
		$maps = array(
			'eq' => "@# = '@^'",
			'ne' => "@# <> '@^'",
			'lt' => "@# < '@^'",
			'le' => "@# <= '@^'",
			'gt' => "@# > '@^'",
			'ge' => "@# >= '@^'",
			'bw' => "@# LIKE '@^%'",
			'bn' => "@# NOT LIKE '@^%'",
			'in' => "'@^' LIKE CONCAT('%', @#, '%')",
			'ni' => "'@^' NOT LIKE CONCAT('%', @#, '%')",
			'ew' => "@# LIKE '%@^'",
			'en' => "@# NOT LIKE '%@^'",
			'cn' => "@# LIKE '%@^%'",
			'nc' => "@# NOT LIKE '%@^%'",
			'nu' => "@# IS NULL",
			'nn' => "@# IS NOT NULL"
		);
		return str_replace('@#', $col, str_replace('@^', $val, $maps[$op]));
	}

}

class PHPlainSession {

	static public
		$application = NULL;

	static public function get($store, $item, $default = NULL, &$exists = NULL) {
		$exists = isset($_SESSION[self::$application][$store])
			&& array_key_exists($item, $_SESSION[self::$application][$store]);
		if ($exists) return $_SESSION[self::$application][$store][$item];
		else return $default;
	}

	static public function set($store, $item, $value, $add = FALSE) {
		if ($add) {
			$old = self::get($store, $item, NULL, $exists);
			if (is_array($old)) $value = array_merge($old, array($value));
			elseif ($exists) $value = array($old, $value);
			else $value = array($value);
		}
		$_SESSION[self::$application][$store][$item] = $value;
	}

	static public function unsetStore($store) {
		$_SESSION[self::$application][$store] = NULL;
		unset($_SESSION[self::$application][$store]);
	}

	static public function unsetItem($store, $item) {
		$_SESSION[self::$application][$store][$item] = NULL;
		unset($_SESSION[self::$application][$store][$item]);
	}

	static public function locale($locale = NULL, $timezone = NULL, $textdomain = NULL)
	{
		$locale || ($locale = self::get(PHPlainConfig::PHPLAIN_STORE,
										'locale', PHPlainConfig::DEFAULT_LOCALE));
		$timezone || ($timezone = self::get(PHPlainConfig::PHPLAIN_STORE,
											'timezone', PHPlainConfig::DEFAULT_TIMEZONE));
		$textdomain || ($textdomain = self::get(PHPlainConfig::PHPLAIN_STORE,
												'textdomain', PHPlainConfig::TEXT_DOMAIN));
		date_default_timezone_set($timezone);
		putenv("LC_ALL=$locale");
		setlocale(LC_ALL, $locale);
		if ($textdomain) {
			bindtextdomain($textdomain, PHPlainConfig::PATH_LC);
			textdomain($textdomain);
		}
	}

	static public function parseMap($map) {
		$weekdays = array('mon' => 1, 'tue' => 2, 'wed' => 4, 'thu' => 8, 'fri' => 16,
						  'sat' => 32, 'sun' => 64);
		$return = array();
		$map = strtolower(preg_replace('/\s+/', ' ', trim($map)));
		$map = preg_replace('/\s*([:-])\s*/', '$1', $map);
		$map = preg_replace('/\s*,\s*/', ' , ', $map);
		$week = 0;
		$time = array();
		$token = strtok($map, ' ');
		while ($token !== FALSE) {
			if ($token == ',') {
				$return[$week? $week: 127] = $time? $time: NULL;
				$week = 0;
				$time = array();
			} else {
				$range = explode('-', $token, 2);
				if (count($range) < 2) $range[1] = $range[0];
				if (isset($weekdays[$range[0]]))
					$week |= ($weekdays[$range[1]] << 1) - $weekdays[$range[0]];
				elseif (preg_match('/^\d/', $range[0])) {
					$interval = array();
					foreach ($range as $r) {
						if (preg_match('/^(\d\d?)(:(\d\d?))?$/', $r, $m)) {
							$interval[] = sprintf("%02s", $m[1])
								. sprintf("%02s", isset($m[3])? $m[3]: 0);
						}
					}
					if (count($interval) == 2) $time[$interval[0]] = $interval[1];
				}
			}
			$token = strtok(' ');
		}
		if ($time || $week) $return[$week? $week: 127] = $time? $time: NULL;
		return $return;
	}

	static public function logon() {
		$args = func_get_args();
		$roles = array();
		foreach ($args as $r) {
			$x = explode(':', $r, 2);
			$y = preg_split('/\s*,\s*/', trim($x[0]));
			if (count($x) == 2) $map = self::parseMap($x[1]);
			else $map = NULL;
			foreach ($y as $role) $roles[$role] = $map;
		}
		if ($roles) self::set(PHPlainConfig::PHPLAIN_STORE, 'roles', $roles);
		else $roles = self::get(PHPlainConfig::PHPLAIN_STORE, 'roles');
		return $roles;
	}

	static public function logoff() {
		session_unset();
		session_destroy();
	}

}

class PHPlainFilter {

	private $filters = array();
	private $tags = NULL;
	private $fail = array();

	private function compose() {
		if ($this->tags) return;
		$this->fail = array();
		$this->tags = '/(^|[_0-9])(';
		$pipe = '';
		foreach ($this->filters as $tag => $filter) {
			$this->tags .= "$pipe$tag";
			$pipe = '|';
		}
		$this->tags .= ')([_0-9]|$)/';
	}

	public function active() {
		return $this->tags !== NULL;
	}

	public function messages() {
		return $this->fail;
	}

	public function add($tag, $fail, $filter, $options = NULL, $flags = NULL) {
		$this->filters[$tag] = array($fail, $filter, $options, $flags);
		$this->tags = FALSE;
	}

	public function __construct($defaults = FALSE) {
		if ($defaults) {
			$this->add('email', _('Invalid e-mail address'), FILTER_VALIDATE_EMAIL);
			$this->add('url', _('Invalid URL'), FILTER_VALIDATE_URL);
		}
	}

	public function filter($name) {
		$this->compose();
		$v = $_REQUEST[$name];
		if (preg_match($this->tags, $name, $m)) {
			$f = $this->filters[$m[2]];
			if ($f[2] !== NULL) {
				$o = array('options' => $f[2]);
				if ($f[3] !== NULL) $o['flags'] = $f[3];
				$v = filter_var($v, $f[1], $o);
			} else {
				$v = filter_var($v, $f[1]);
			}
			if ($v === FALSE) $this->fail[$name] = $f[0];
		}
		return $v;
	}

}

class PHPlainBin {

	const
		INACCESSIBLES = '__construct __destruct __get __set finish effective roles';

	public
		$invoked = NULL,
		$extension = NULL,
		$var = array();

	protected
		$db = NULL,
		$filter = NULL;

	public function select($db = '') {
		if ($db && property_exists($this, $db) && (get_class($this->$db) == 'PHPlainDB'))
			$this->db = $this->$db;
		else $this->db = NULL;
	}

	protected function extension($call) {
		if ($this->extension) {
			$method = $this->extension . $call;
			if (method_exists($this, $method)) $this->$method();
		}
	}

	public function __construct() {
		PHPlainSession::locale();
		if (PHPlainConfig::$connections) {
			PHPlainUtil::plug('PHPlainDB');
			foreach (PHPlainConfig::$connections as $name => $conn)
				$this->$name = new PHPlainDB($conn['dsn'], $conn['username'],
										   $conn['password'], $conn['driver_options'],
										   isset($conn['pagination'])?
										   $conn['pagination']: NULL);
			reset(PHPlainConfig::$connections);
			$this->select(key(PHPlainConfig::$connections));
		}
		$this->filter = new PHPlainFilter();
		$this->extension('Header');
	}

	public function __destruct() {
		$this->extension('Footer');
	}

	public function __get($name) {
		return $this->session($name);
	}

	public function __set($name, $value) {
		if (is_object($value)) $this->$name = $value;
		else $this->session($name, $value);
	}

	public function set($name, $value) {
		return $this->var[$name] = $value;
	}

	final public function filtering() {
		return $this->filter->active()? $this->filter: FALSE;
	}

	public function index() {
		header('Content-type: text/html;');
		echo '<h1>Welcome to PHPlain!</h1>';
	}

	public function json($data) {
		header('Content-type: application/json');
		echo is_string($data)? $data: json_encode($data);
	}

	public function finish($return) {
		if ($return == PHPlainCodes::OK) return;
		$finish = array('invoked'	=> $this->invoked,
						'code'		=> $return,
						'message'	=> PHPlainCodes::$messages[$return],
						'fail'		=> $this->filter->messages());
		if ($this->extension == 'json'
			|| !$this->extension && array_key_exists('X-Requested-With', $_SERVER)
			&& ($_SERVER['X-Requested-With'] == 'XMLHttpRequest')) $this->json($finish);
		else {
			header('Content-type: text/html');
			?><pre><?php print_r($finish); ?><pre><?php
		}
	}

	public function effectiveRoles($roles) {
		$now = $_SERVER['REQUEST_TIME'];
		$weekday = 1 << (date('N', $now) - 1);
		$time = date('Hi', $now);
		$return = array();
		foreach ($roles as $role => $map) {
			if (!$map) $return[] = $role;
			else foreach ($map as $week => $intervals) {
				if ($week & $weekday) {
					if (!$intervals) $return[] = $role;
					else foreach ($intervals as $begin => $end)
						if ($time >= $begin && $time <= $end) $return[] = $role;
				}
			}
		}
		return $return;
	}

	public function effective($role, $roles) {
		if (array_key_exists($role, $roles)) {
			$weekday = 1 << (date('N') - 1);
			$time = date('Hi');
			if (!$roles[$role]) return TRUE;
			foreach ($roles[$role] as $week => $intervals) {
				if ($week & $weekday) {
					if (!$intervals) return TRUE;
					foreach ($intervals as $begin => $end)
						if ($time >= $begin && $time <= $end) return TRUE;
				}
			}
		}
		return FALSE;
	}

	public function baseURI($path = '') {
		return dirname($_SERVER['PHP_SELF']) . '/' . $path;
	}

	public function go($location = '') {
		header("Location: " . $this->baseURI($location));
		exit();
	}

	public function execute($method /*, $arg1, $arg2, ... */) {
		$args = func_get_args();
		$method = array_shift($args);
		if (method_exists($this, $method)) {
			call_user_func_array(array($this, $method), $args);
			$this->finish(PHPlainCodes::OK);
			exit();
		}
	}

	public function roles($check, $allow = TRUE) {
		$roles = PHPlainSession::logon();
		if ($roles) {
			if (array_key_exists(PHPlainConfig::SUPERUSER_ROLE, $roles)) return $allow;
			if (array_key_exists(PHPlainConfig::DISABLED_ROLE, $roles)) return !$allow;
			if ($check)
				foreach ($check as $role) {
					if ($this->effective($role, $roles)) return TRUE;
				}
			else return TRUE;
		} else $this->execute('logon');
		return FALSE;
	}

	protected function allow(/* $role1, $role2, ... */) {
		$roles = func_get_args();
		if ($this->roles($roles)) return;
		else {
			$this->finish(PHPlainCodes::NOT_ALLOWED);
			exit(PHPlainCodes::NOT_ALLOWED);
		}
	}

	protected function deny(/* $role1, $role2, ... */) {
		$roles = func_get_args();
		if ($this->roles($roles, FALSE)) {
			$this->finish(PHPlainCodes::DENIED);
			exit(PHPlainCodes::DENIED);
		}
	}

	public function is(/* $role1, $role2, ... */) {
		$roles = func_get_args();
		return $this->roles($roles);
	}

	protected function session($key, $value = NULL) {
		$class = get_class($this);
		$return = PHPlainSession::get($class, $key, NULL, $exists);
		$argc = func_num_args();
		if ($argc > 1) PHPlainSession::set($class, $key, $value);
		return $return;
	}

	protected function put($file_name) {
		readfile(PHPlainConfig::PATH_INC . "/$file_name");
	}

	protected function inc(/* $view, [$param1, [$param2[, ...]]] */) {
		$args = func_get_args();
		$this;
		$base_uri = $this->baseURI();
		$view_uri = $base_uri . PHPlainConfig::PATH_INC . '/';
		extract(PHPlainUtil::$variables);
		extract($this->var);
		@include(PHPlainConfig::PATH_INC . "/$args[0]");
	}

}

class PHPlainApp {

	static public
		$source_path = '',
		$routed_path = '';

	static private function fail($code) {
		switch ($code) {
			case PHPlainCodes::SESSION_FAILS:
				@include(PHPlainConfig::PATH_ERRORS . '/' . PHPlainConfig::SESSION_ERROR);
				break;
			case PHPlainCodes::UNRESOLVED_URI:
			case PHPlainCodes::UNKNOWN_CLASS:
			case PHPlainCodes::UNKNOWN_METHOD:
				@include(PHPlainConfig::PATH_ERRORS . '/' . PHPlainConfig::UNRESOLVED_URI);
				break;
		}
		exit($code);
	}

	static public function invoke($instance, $method, $args = NULL) {
		$instance->invoked = $method;
		if (preg_match("/\\b$method\\b/", PHPlainBin::INACCESSIBLES))
			return PHPlainCodes::INACCESSIBLE;
		$filter = $instance->filtering();
		$ref = new ReflectionMethod($instance, $method);
		if ($ref && $ref->isPublic()
			&& !($ref->isConstructor() || $ref->isDestructor()
				 || $ref->isAbstract() || $ref->isFinal()))
		{
			if ($args === NULL) {
				$values = array();
				foreach ($ref->getParameters() as $parameter) {
					$position = $parameter->getPosition();
					$values[$position] = NULL;
					$name = $parameter->getName();
					if (array_key_exists($name, $_REQUEST) && $_REQUEST[$name] !== '') {
						$values[$position] = $filter?
							$filter->filter($name): $_REQUEST[$name];
						$instance->var[$name] = $values[$position];
					} elseif (array_key_exists($name, $_FILES)) {
						$values[$position] = $_FILES[$name];
						$instance->var[$name] = $values[$position];
					} elseif ($parameter->isDefaultValueAvailable()) {
						$values[$position] = $parameter->getDefaultValue();
						$instance->var[$name] = $values[$position];
					} else return PHPlainCodes::REQUIRED;
				}
				if ($filter && $filter->messages()) return PHPlainCodes::VALIDATION;
				$ref->invokeArgs($instance, $values);
			} else {
				$ref->invoke($instance, $args);
			}
			return PHPlainCodes::OK;
		} else return PHPlainCodes::FORBIDDEN;
	}

	static public function basePath() {
		$root = realpath($_SERVER['DOCUMENT_ROOT']);
		$path = dirname($_SERVER['SCRIPT_FILENAME']);
		return substr($path, strlen($root));
	}

	static private function path_info() {
		if (PHPlainConfig::USE_PATH_INFO) $info = $_SERVER['PATH_INFO'];
		else {
			$path = preg_quote(dirname($_SERVER['SCRIPT_NAME']), '/');
			$file = preg_quote(basename($_SERVER['SCRIPT_NAME']));
			$base = urldecode($_SERVER['REQUEST_URI']);
			if (($qpos = strpos($base, '?')) !== FALSE) $base = substr($base, 0, $qpos);
			$base = preg_replace('/\/+/', '/', $base);
			$info = preg_replace("/^$path\/($file)?/", '', $base);
		}
		$info = preg_replace('/^\/+|\/+$/', '', $info);
		return $info;
	}

	static private function route($path) {
		self::$source_path = $path;
		if ($path)
			foreach (PHPlainConfig::$routes as $route => $map) {
				$regex = '/' . preg_replace('/\//', '\\/', $route) . '/';
				if (preg_match($regex, $path, $matches)) {
					$path = preg_replace($regex, $map, $path);
					self::$routed_path = $path;
					$run = explode('?', $path, 2);
					if (count($run) > 1) {
						$args = explode('&', $run[1]);
						foreach ($args as $arg) {
							list($name, $value) = explode('=', $arg);
							$_REQUEST[$name] = $value;
						}
					}
					return $run[0];
				}
			}
		return $path;
	}

	static private function execute($instance, $method) {
		$code = self::invoke($instance, $method);
		$instance->finish($code);
		unset($instance);
	}

	static private function index($index, $method = 'index') {
		@include_once(PHPlainConfig::PATH_BIN . "/$index.php");
		$bin = new $index;
		if (method_exists($bin, $method)) {
			self::execute($bin, $method);
			return TRUE;
		} else return FALSE;
	}

	static private function locate($name, $index = NULL) {
		$base = dirname($_SERVER['SCRIPT_FILENAME']);
		if (PHPlainUtil::download("$base/" . PHPlainConfig::PATH_INC . "/$name")) return;
		foreach (new RecursiveIteratorIterator(new ParentIterator(new RecursiveDirectoryIterator($base)), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD) as $dir)
			if (PHPlainUtil::download("$dir/$name")) return;
		if (!($index && self::index($index, 'unrouted'))) self::fail(PHPlainCodes::UNRESOLVED_URI);
	}

	static private function resolve($index) {
		$path = self::route(self::path_info());
		if (PHPlainUtil::download("$path")) return;
		$segments = explode('/', $path, 2);
		while ($segments && $segments[0] == '') array_shift($segments);
		if ($segments) {
			@include_once(PHPlainConfig::PATH_BIN . "/$segments[0].php");
			if (class_exists($segments[0])) {
				if (count($segments) > 1) {
					if (preg_match('/^(.*)\.(.*?)$/', $segments[1], $matches)) {
						$method = $matches[1];
						$ext = $matches[2];
					} else {
						$method = $segments[1];
						$ext = NULL;
					}
					if (method_exists($segments[0], $method)) {
						$instance = new $segments[0];
						$instance->extension = $ext;
						self::execute($instance, $method);
					} else self::locate($segments[1]);
				} else self::execute(new $segments[0], 'index');
			} else self::locate($path, $index);
		} else self::index($index);
	}

	static public function run($index) {
		if (session_start()) {
			PHPlainSession::$application = $index;
			self::resolve($index);
		} else self::fail(PHPlainCodes::SESSION_FAILS);
	}

}

?>
