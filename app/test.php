<?php
namespace IndexTrade;
 
error_reporting(E_ALL);
date_default_timezone_set('UTC');
clearstatcache(true);

include_once( __DIR__ . '/__bootstrap.php');

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;

echo "\n\n";
echo "     ---| IndexTrade.Exchange Platform via CoinIndex Team with LOVE |--- \n\n";
echo "Starting at: " . date('r') . "\n";
echo "Starting Test Master...\n\n";

$log = initLog('idxtTest');
$сentrifugo = initCentrifugo();

//в будущем может сюда добавить и другой функционал - депозиты и маржинальность

echo "\n\n";


$loop->addPeriodicTimer(3, function(){
	echo "Start test...\n";
	
	
	$client = new \GuzzleHttp\Client([
		'base_uri' => 'http://localhost:8099',
		'defaults' => [
			'exceptions' => false
		],
		'allow_redirects' => true, 'connect_timeout' => 3, 'decode_content' => true, 'force_ip_resolve' => 'v4', 'http_errors' => true, 'read_timeout' => 3, 'synchronous' => true, 'timeout' => 3
	]);
	
	//var_dump($client);
	
	
	$res = $client->get('GET', '/allocator/time');
	
	var_dump( $res );
	
	echo $res->getStatusCode() . "\n";
	// 200
	echo $res->getBody() . "\n\n--------------\n\n";

});



$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
