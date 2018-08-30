<?php
namespace IndexTrade;
 
error_reporting(E_ALL);
date_default_timezone_set('UTC');
clearstatcache(true);


use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Http\Server;




include_once( __DIR__ . '/__bootstrap.php');

echo "\n\n";
echo "     ---| IndexTrade.Exchange Platform via CoinIndex Team with LOVE |--- \n\n";
echo "Starting at: " . date('r') . "\n";
echo "Starting Allocator Master...\n\n";

$log = initLog('idxtAllocator');
$сentrifugo = initCentrifugo();


/***

$socket = new \React\Socket\Server($loop, 
				Array(
					'tcp' => Array(
						'backlog' => 256,
						'so_reuseport' => true,
						'ipv6_v6only' => false
					)
				)
			);

$server = new \React\Http\Server($socket);

$server->on('request', function (\React\Http\Request $request, \React\Http\Response $response) {
    $path = $request->getPath();
	
	echo "Request path: " . $path . "\n";
	
	$redis = initRedis();
	$info = $redis->info();
	
	var_dump( $redis->info() );
	
	
	$response->writeHead(200, array('Content-Type' => 'application/json'));
    
	//throw new \Exception('fuck');
	
	
	$response->end( json_encode( $info ) );
});

$server->on('error', function (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
		
$socket->listen(8099, '127.0.0.1');
***/


$server = new Server(function (ServerRequestInterface $request) use (&$log, &$сentrifugo, &$db, &$redis, &$ssdb) {
	$method = $request->getMethod();
	
	if ($method != 'GET')
		return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Invalid request method', 'data' => null)));
	
	$clientIP = $request->getServerParams()['REMOTE_ADDR'];
	$uri = $request->getUri();
	$path = $uri->getPath();
	$params = $request->getQueryParams();
	
	$log->info('New request from '.$clientIP.' :: ' . $method . ' :: ' . $path, $params);
	
	
	if ($path == '/allocator/allocate'){
		//Аллоцирование средств 
		//в параметрах должно быть: uid, orderID, symbol, amount
		if (!array_key_exists('uid', $params) || !array_key_exists('orderID', $params) || !array_key_exists('symbol', $params) || !array_key_exists('amount', $params)){
			
			return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Missing required params', 'data' => null)));
			
		}
		
		//теперь проверить баланс юзера 
		$userFreeBalance = $db->fetchOne('SELECT currency_balance FROM exchange_users_balances_tbl 
			WHERE 	uid = ? AND currency_symbol = ? AND balance_status = "full_operated" LIMIT 1', 
			Array(intval($params['uid']), strval($params['symbol'])));
		
		if (empty($userFreeBalance)){
			return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Empty user balance', 'data' => null)));
		}
		
		//чтобы точность не терять
		$userFreeBalance = floatval($userFreeBalance);
		
		if ($userFreeBalance < floatval($params['amount'])){
			return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Too low funds on the balance of the user', 'data' => null)));
		}
		else {
			
			$db->query('UPDATE exchange_users_balances_tbl SET amount_at_orders = amount_at_orders + '.floatval($params['amount']).', currency_balance = currency_balance - '.floatval($params['amount']).', last_balance_update = NOW() WHERE uid = ? AND currency_symbol = ? AND balance_status = "full_operated" ', Array(intval($params['uid']), strval($params['symbol'])));
			
			$userBalanceLast = $db->fetchRow('SELECT currency_balance, amount_at_orders, amount_at_guarantee, last_balance_update FROM exchange_users_balances_tbl 
			WHERE 	uid = ? AND currency_symbol = ? AND balance_status = "full_operated" LIMIT 1', 
			Array(intval($params['uid']), strval($params['symbol'])));
			
			
			
			return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'OK', 'error' => null, 'data' => $userBalanceLast)));
		}
		
		
		
		
		
		
	}
	else
	if ($path == '/allocator/refund'){
		//Возврат средств 
	}
	else {
		return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Unknown Allocator mathod', 'data' => null)));		
	}
	

	
	/**
	$headers = $request->getHeaders();
	$protocol = $request->getProtocolVersion();
	
	var_dump( [$uri, $method, $protocol, $headers] );
	**/
	return new Response(
        200,
        array(
            'Content-Type' => 'application/json'
        ),
        json_encode(Array('status' => 'ERROR', 'error' => 'Something going wrong!', 'data' => null))
    );
});

$socket = new \React\Socket\Server(8099, $loop);

$server->on('error', function (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});


$server->listen($socket);

$log->info('Starting HTTP Allocator service...');
$log->info('Usage: just send HTTP request: ');

$log->info('/allocator/allocate - try to allocate currency or asset by order');
$log->info('/allocator/refund - refund asset or currency by order (in responce to reject or cancel execution report');

//в будущем может сюда добавить и другой функционал - депозиты и маржинальность

echo "\n\n";

$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
