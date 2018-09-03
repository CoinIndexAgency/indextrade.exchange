<?php
namespace IndexTrade;
 
error_reporting(E_ALL);
date_default_timezone_set('UTC');
clearstatcache(true);

include_once( __DIR__ . '/__bootstrap.php');

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Http\Server;


echo "\n\n";
echo "     ---| IndexTrade.Exchange Platform via CoinIndex Team with LOVE |--- \n\n";
echo "Starting at: " . date('r') . "\n";
echo "Starting Allocator Master...\n\n";

$log = initLog('idxtAllocator');
$сentrifugo = initCentrifugo();


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
		
		//$db = initDb();
		//$db->getConnection();		
		$db->beginTransaction();
		
		//теперь проверить баланс юзера 
		$userFreeBalance = $db->fetchOne('SELECT currency_balance FROM exchange_users_balances_tbl 
			WHERE 	uid = ? AND currency_symbol = ? AND balance_status = "full_operated" LIMIT 1', 
			Array(intval($params['uid']), strval($params['symbol'])));
		
		if (empty($userFreeBalance)){
			
			$db->commit();
			
			return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Empty user balance', 'data' => null)));
		}
		
		//чтобы точность не терять
		$userFreeBalance = floatval($userFreeBalance);
		
		if ($userFreeBalance < floatval($params['amount'])){
			
			$db->commit();
			
			return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Too low funds on the balance of the user', 'data' => null)));
		}
		else {
			
			$db->query('UPDATE exchange_users_balances_tbl SET amount_at_orders = amount_at_orders + '.floatval($params['amount']).', currency_balance = currency_balance - '.floatval($params['amount']).', last_balance_update = NOW() WHERE uid = ? AND currency_symbol = ? AND balance_status = "full_operated" ', Array(intval($params['uid']), strval($params['symbol'])));
			
			//специальный лог аллокаций и рефандов, должен сходиться для аудита потом
			$db->query('INSERT INTO exchange_users_funds_allocations_tbl SET uid = ?, symbol = ?, amount = ?, action = ?, order_id = ?, updated_at = UNIX_TIMESTAMP()', Array(
				intval($params['uid']),
				strval($params['symbol']),
				floatval($params['amount']),
				'ALLOCATE',
				$params['orderID']
			));
			
			$userBalanceLast = $db->fetchRow('SELECT currency_symbol, currency_balance, amount_at_orders, amount_at_guarantee, last_balance_update FROM exchange_users_balances_tbl 
			WHERE 	uid = ? AND currency_symbol = ? AND balance_status = "full_operated" LIMIT 1', 
			Array(intval($params['uid']), strval($params['symbol'])));
			
			//обновляем балансы
			$allBalances = $db->fetchAll('SELECT * FROM exchange_users_balances_tbl WHERE uid = ? ', Array(intval($params['uid'])));
			
			$redis->hset('INDEXTRADE_USERS_BALANCES', 'idxt' . intval($params['uid']),  json_encode($allBalances));
			
			//екзекьюшин репорт об аллокации 
			$report = Array('type' => 'ALLOCATE', 'msg' => 'Funds '.$params['symbol'].' allocated by amount ' . $params['amount'], 'orderID' => $params['orderID'], 'ts' => t());
			
			     
			try{ 
				$tmp = json_encode($report); 
				
				if (empty($tmp) || !empty(json_last_error())){
					$log->error( json_last_error_msg() );
					
					$db->rollBack();
					
					return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => json_last_error_msg(), 'data' => null)));
				}
				
				$ssdb->qpush_back('INDEXTRDADE_EXECUTION_REPORTS', $tmp);
				
				$db->commit();
				
				$userBalanceLast['lastAllocate'] = $params['amount'];
				
				return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'OK', 'error' => null, 'data' => $userBalanceLast)));
			
			}catch(\Exception $e){
				$log->error( $e );
				
				$db->rollBack();
				
				return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => $e->getMessage(), 'data' => null)));
			}
		}
				
		$db->rollBack();
		
		return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Something gone wrong', 'data' => null)));
		
	}
	else
	if ($path == '/allocator/refund'){
		//Возврат средств 
	}
	else
	if ($path == '/allocator/time'){
		return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'OK', 'error' => null, 'data' => t())));
	}
	else
	if ($path == '/allocator/health'){
		return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'OK', 'error' => null, 'data' => 'OK')));
	}
	else {
		return new Response(200, Array('Content-Type' => 'application/json'), json_encode(Array('status' => 'ERROR', 'error' => 'Unknown Allocator method', 'data' => null)));		
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


$loop->addPeriodicTimer(3, function() use (&$db, &$log){
	
	if (!$db->isConnected()){
		$log->warning("DB connection not alive. Try to reconnect");
		
		$db->getConnection();
	}
	
	$dbTs = intval( $db->fetchOne('SELECT UNIX_TIMESTAMP() ') );
	$appTs = intval( time() );
		
	if ($appTs != $dbTs){
		$log->warning("DB local time and Application time has difference - " . ($appTs - $dbTs));
	}
	else
		$log->info('Time  app and DB sinked OK');

});

$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
