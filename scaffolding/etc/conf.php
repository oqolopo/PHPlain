<?php

class PHPlainConfig extends PHPlainConfigBase {

	const
		BASE_URI			= '/london',
		DEFAULT_TIMEZONE	= 'Europe/London',
		DEFAULT_LOCALE		= 'en_UK',

		CONSOLE_FIREPHP		= 'lib/FirePHPCore/FirePHP.class.php',
		CONSOLE_CHROMEPHP	= 'lib/ChromePhp.php';

	static public
		$routes				= array(),
		$connections		= array(
			'development'	=> array(
				'dsn'		=> 'mysql:host=localhost;dbname=schema',
				'username'	=> 'user',
				'password'	=> 'password',
				'driver_options'
							=> array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'),
				'pagination'
							=> array('select'	=> 'SQL_CALC_FOUND_ROWS',
									'first'		=> 'LIMIT 1',
									'limit'		=> 'LIMIT ~size OFFSET ~skip',
									'count'		=> 'FOUND_ROWS()')
			)
		);

}

?>
