<?php
namespace IndexTrade;

error_reporting(E_ERROR | E_WARNING | E_PARSE);// & ~E_NOTICE);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/SSDB.php';

use Zend\Db;
use Zend\Db\Adapter\Mysqli;
use Predis;
use Ramsey\Uuid;

$db 	= null;
$redis 	= null;
$ssdb 	= null;
	
	function initDb(){
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
		$client = new Predis\Client([
			'scheme' => 'tcp',
			'host'   => '127.0.0.1',
			'port'   => 6379,
			'read_write_timeout' => 0
		]);
		
		return $client;
	}
	
	function initSSDB(){
		$ssdb = new \SimpleSSDB('localhost', 8888, 5000);	//192.241.194.55   sf3-ssdb.agpsource.com
		
		return $ssdb;
	}
	
	
$db 	= initDb();
$redis 	= initRedis(); 
$ssdb 	= initSSDB();  

$loop = \React\EventLoop\Factory::create();
