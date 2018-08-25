<?php
namespace IndexTrade;

error_reporting(E_ERROR | E_WARNING | E_PARSE);// & ~E_NOTICE);

require_once __DIR__ . '/../app/vendor/autoload.php';
require_once __DIR__ . '/../app/libs/SSDB.php';
require_once __DIR__ . '/../app/libs/idxtLibs.php';

use Zend\Db;
use Zend\Db\Adapter\Mysqli;
use Predis;
use Ramsey\Uuid;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ProcessHandler;
use Monolog\Handler\StdoutHandler;
use Monolog\Handler\ErrorLogHandler;
use Centrifugo\Centrifugo;

$db 	= null;
$redis 	= null;
$ssdb 	= null;
$log 	= null;
	
	function initDb(){
		if (!empty($db))
			return $db;
		
		//подключение к базе данных
		$options = array(
				\Zend_Db::AUTO_QUOTE_IDENTIFIERS => true,
				\Zend_Db::ALLOW_SERIALIZATION => true,
				\Zend_Db::AUTO_RECONNECT_ON_UNSERIALIZE => true
		);

	
		try
		{
			$db = new \Zend_Db_Adapter_Mysqli(array(
					'host'     => 'localhost', //'us1-dfp.agpsource.com',
					'username' => 'indextrade-app',
					'password' => 'MXraGr9FXYGLBxqJ', //'9dvAx6Gh4MZbBqsZ',
					'dbname'   => 'indextrade_exchange_db',
					'port' => 3306,
					'charset'   => 'utf8',
					'options' => $options			
			));
			
			return $db;
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}
	}

	function initRedis(){
		if (!empty($redis))
			return $redis;
		
		$client = new Predis\Client([
			'scheme' => 'tcp',
			'host'   => '127.0.0.1',
			'port'   => 6379,
			'read_write_timeout' => 0
		]);
		
		return $client;
	}
	
	function initSSDB(){
		if (!empty($ssdb))
			return $ssdb;
		
		
		$ssdb = new \SimpleSSDB('localhost', 8888, 5000);	//192.241.194.55   sf3-ssdb.agpsource.com
		
		return $ssdb;
	}
	
	function initLog($name = 'IDXT'){
		$log = new Logger($name);
		$log->pushHandler(new StreamHandler('/var/log/'.strtolower($name).'.log', Logger::DEBUG));
		//$log->pushHandler(new ProcessHandler(), Logger::DEBUG);
		$log->pushHandler(new ErrorLogHandler());
		
		return $log;
	}
	
//$db 	= initDb();
//$redis 	= initRedis(); 
//$ssdb 	= initSSDB();  
