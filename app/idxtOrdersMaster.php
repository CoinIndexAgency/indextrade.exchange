<?php
namespace IndexTrade;
 
error_reporting(E_ALL);
date_default_timezone_set('UTC');
clearstatcache(true);

include_once( __DIR__ . '/__bootstrap.php');

echo "\n\n";
echo "     ---| IndexTrade.Exchange Platform via CoinIndex Team with LOVE |--- \n\n";
echo "Starting at: " . date('r') . "\n";
echo "Starting Order Master...\n\n";

$log = initLog('idxtOrdersMaster');

/**
	По сути, это фронт-энд внутренних систем. 
	Он периодически проверяет очередь новых ордеров, берет их, проводит все базовые проверки
	Если все оке, перемещает в очередь, с которой ордер уходит уже в трейд-систему



**/

//сохраняет репорт в очереди в SSDB (в JSON-формате)
function sendExecutionReport($ssdb = null, $report = null, $log = null){
	if ($ssdb == null || empty($report))
		return false;
	
	try{ 
		$tmp = json_encode($report); 
		
		if (empty($tmp) || !empty(json_last_error())){
			$log->error( json_last_error() );
			return false;
		}
		
		$ssdb->qpush_back('INDEXTRDADE_EXECUTION_REPORTS', $tmp);
		
		return true;
	
	}catch(Exception $e){
		$log->error( $e );
		
		return false;
	}
}



//Проверка юзера, что он может ставить ордер и т.п. Проверка аккаунта 
//@return Boolean TRUE if all check passed, FALSE is no
function checkUser($db = null, $order = null){
	if (empty($order)) return false;
	
	$uid = intval($order['uid']);
	
	if (empty($uid)) return false;
	
	//проверим существование юзера 
	$userStatus = $db->fetchOne('SELECT user_status FROM exchange_users_tbl WHERE uid = '.$uid.' LIMIT 1');
	
	if (empty($userStatus) || $userStatus == 'blocked')
		return false;
	
	//можно ли ему ставить ордера? 
	$res = $db->fetchOne('SELECT right_value FROM exchange_users_rights_tbl  WHERE uid = '.$uid.' AND right_rule = "new_order_create" LIMIT 1');
	
	if (empty($res) || $res == 'no' || $res == 'false')
		return false;
	
	//ну, базово вроде ОК
	return true;	
}

//проверка валидности что можно отменить ордер 
function checkOrderValidity($db = null, $order = null, $log = null){
	if (empty($order)) return 'Empty order';
	
	$res = $db->fetchRow('SELECT order_id, order_uuid, order_pair_id, order_uid, order_price, order_amount, order_cancel_at, order_status FROM exchange_real_orders_tbl WHERE order_uuid = "'.$order['id'].'" LIMIT 1');
	
	if (empty($res))
		return 'Invalid order';
	
	$log->debug( 'Fetchet from DB: ' . json_encode($res) );
	$log->debug( 'Fetchet from AP: ' . json_encode($order) );
	
	//if ($res['order_uuid'] != $order['id'])
	//	return 'Invalid order id';
	
	$pairId = $db->fetchOne('SELECT _id FROM exchange_pairs_tbl WHERE pair_name = "'.$order['pair'].'" LIMIT 1');
	
	if (empty($pairId))
		return 'Invalid pair';
	
	if ($res['order_pair_id'] != $pairId)
		return 'Invalid pair id';
	
	//for TEST NOW
	//if ($res['order_uid'] != $order['uid'])
	//	return 'Invalid order uid';
	
	if ($res['order_price'] != $order['price'])
		return 'Invalid order price';

	//if ($res['order_amount'] != $order['amount'])
	//	return 'Invalid order amount';
	
	if ($res['order_cancel_at'] < time()){
		//это что ордер должен быть снят раньше 
		return 'Order canceled by timeout';
	}
	
	if ($res['order_status'] != 'live'){
		return 'Order canceled by timeout';
	}
	
	return true;	
}

//отмена ордера 
function cancelOrder($db = null, $redis = null, $ssdb = null, $order = null){
	$db->beginTransaction();
		$t = time();
		
		$db->query('UPDATE exchange_real_orders_tbl SET order_cancel_at = '.$t.', order_status = "canceled", order_last_status_changed_at = '.$t.' WHERE order_uuid = "'.$order['id'].'" LIMIT 1');
		
		//Отмена ордера (уже проверенного)
		$redis->rpush('INDEXTRDADE_CANCEL_ORDERS_' . $order['pair'], $order['id']); 
		
		//удаляем
		$ssdb->hdel('INDEXTRDADE_LIVE_ORDERS_'.$order['pair'], $order['id']);
		
	$db->commit();
	
	return true;
}

//Проверка инструмента
//@return Boolean TRUE if all check passed, FALSE is no
function checkTradingPair($db = null, $order = null){
	if (empty($order)) return 'Empty order';
	
	$uid = intval($order['uid']);
	$pair = strtoupper( strval( $order['pair'] ) );
	$type = strtoupper( strval( $order['type'] ) );
	$exec = strtoupper( strval( $order['exec'] ) );
	
	if (empty($uid) || empty($pair) || empty($type) || empty($exec)) return 'Empty required field (pair, type or exec)';
	
	//проверим существование торговой пары  
	$pairStatus = $db->fetchOne('SELECT pair_status FROM exchange_pairs_tbl WHERE pair_name = "'.$pair.'" LIMIT 1');
	
	if (empty($pairStatus) || $pairStatus != 'traded')
		return 'Pair not traded';
	
	//проверка, какие типы ордеров и исполнения разрешены для этой торговой пары 
	$pairOrderTypeStatus = $db->fetchOne('SELECT order_type_rule FROM exchange_pairs_order_types_tbl WHERE pair_name = "'.$pair.'" AND order_type = "'.$type.'" LIMIT 1');
	
	if (empty($pairOrderTypeStatus) || $pairOrderTypeStatus !== 'ALLOW')
		return 'Order type is disallowed';
	
	//проверка типа исполнения 
	$execOrderTypeStatus = $db->fetchOne('SELECT exec_type_rule FROM exchange_pairs_exec_types_tbl WHERE pair_name = "'.$pair.'" AND exec_type = "'.$exec.'" LIMIT 1');
	
	if (empty($execOrderTypeStatus) || $execOrderTypeStatus !== 'ALLOW')
		return 'Exec type is disallowed';
	
	//ну, базово вроде ОК
	return true;	
}

//Лимиты проверяем (объем, цену, наличие у юзера денег или актива, разрешенное максим ордеров и т.п.)
function checkLimits($db = null, $order = null){
	if (empty($order)) return 'Empty order';
	
	$uid = intval($order['uid']);
	$pair = strtoupper( strval( $order['pair'] ) );
	//IMPORTANT: all values * 1000 000 000 (1 миллиард)
	$price = floatval( $order['price'] );
	$amount = floatval( $order['amount'] );
	
	if (empty($uid))  return 'Empty UID';
	if (empty($pair)) return 'Empty pair'; 
	if (empty($price)) return 'Empty price';
	if (empty($amount)) return 'Empty amount';
	
	//выберем размер ордера, мин и макс для указанной торговой пары 
	$pairLimits = $db->fetchRow('SELECT pair_asset, pair_currency, amount_min, amount_max, fair_price, max_price_deviation_prc FROM exchange_pairs_tbl WHERE pair_name = "'.$pair.'" LIMIT 1');
	
	//проверим по обьему сделки 
	if ($amount < $pairLimits['amount_min'])
		return 'Amount less then pairAmountMin';

	if ($amount > $pairLimits['amount_max'])
		return 'Amount bigger then pairAmountMax';
/** Temporary DISABLED	
	//теперь проверим, чтобы цена укладывалась в диапазон 
	$priceDev = ($pairLimits['fair_price']/100)*50;
	$fairPriceMin = $pairLimits['fair_price'] - $priceDev;
	$fairPriceMax = $pairLimits['fair_price'] + $priceDev;
	
	if ($price < $fairPriceMin)
		return 'Order price less then fairPriceMin ('.$fairPriceMin.')';
	
	if ($price > $fairPriceMax)
		return 'Order price bigger then fairPriceMax ('.$fairPriceMax.')';
**/	
	
	/** вынесено в сервис Allocator
	$checkUserBalanceSymbol = '';
	
	if ($order['side'] == 'SELL')
		$checkUserBalanceSymbol = $pairLimits['pair_asset'];
	else
	if ($order['side'] == 'BUY')
		$checkUserBalanceSymbol = $pairLimits['pair_currency'];
	
	$balance = $db->fetchOne('SELECT currency_balance FROM exchange_users_balances_tbl WHERE uid = '.$uid.' AND currency_symbol = "'.$checkUserBalanceSymbol.'" LIMIT 1');
	
	if (empty($balance))
		return 'Empty user balance';
	
	if ($balance < $amount)
		return 'Amount too much (as user balance)';
	**/
	
	//ну, базово вроде ОК
	return true;	
}

//расчет комиссий
function calcOrderFee($db, $order){
	if (empty($order)) return 'Empty order';

	$pair = strtoupper( strval( $order['pair'] ) );
	//IMPORTANT: all values * 1000 000 000 (1 миллиард)
	$price = floatval( $order['price'] );
	$amount = floatval( $order['amount'] );
	$side = $order['side'];
	
	//пока комиссия только в валюте 
	$fees = $db->fetchRow('SELECT sell_order_fee_prc, buy_order_fee_prc, min_fee_abs_value FROM exchange_pairs_tbl WHERE pair_name = "'.$pair.'" LIMIT 1');
	
	if (empty($fees)) return 'Error while obtain fee from DB';
	
	$fee = 0;
	$feePrc = 0;
	$minFeeAbs = $fees['min_fee_abs_value'];
	
	
	//ITT/ETH - price:: how much ETH for 1 ITT, amount - size of ITT
	
	if ($side == 'BUY'){
		$feePrc = $fees['buy_order_fee_prc'];
	}
	else
	if ($side == 'SELL'){
		$feePrc = $fees['sell_order_fee_prc'];
	}
	
	$fee = round( ((($price/1000000000) * ($amount/1000000000))/100) * floatval($feePrc), 3);
	
	if ($fee < $minFeeAbs)
		$fee = $minFeeAbs;
	
	if (!empty($fee))
		return $fee;
	else
		return 'Empty fee calculated';
}

function addToGlobalOrderList($db, $redis, $ssdb, $order, $fee){
	$pairId = $db->fetchOne('SELECT _id FROM exchange_pairs_tbl WHERE pair_name = "'.$order['pair'].'" LIMIT 1');
	$sql = 'INSERT INTO exchange_real_orders_tbl SET 
				order_uuid	= ?,
				order_pair_id	= ?,
				order_uid	= ?,
				order_type	= ?,
				order_side	= ?,
				order_exec	= ?,
				order_datetime	= NOW(),
				order_real_dtx	= ?,
				order_price	= ?,
				order_amount	= ?,
				order_fee	= ?,
				order_total	= ?,
				order_stoploss_value	= ?,
				order_cancel_at	= ?,
				order_status	= ?,
				order_partial_filled	= ?,
				order_market_verification_flag	= ?,
				order_last_status_changed_at	= ?  ';
	
	$db->beginTransaction();
	
	try {
		$db->query($sql, Array(
			$order['id'],
			$pairId,
			$order['uid'],
			$order['type'],
			$order['side'],
			$order['exec'],
			$order['check_at']*1000,
			$order['price'],
			$order['amount'],
			$fee,
			((($price/1000000000) * ($amount/1000000000))*1000000000),
			0,
			(time() + 90*24*3600),	//90 дней живут ордера 
			'live',
			0,
			0,
			time()	
		));
		
		$_id = $db->lastInsertId();
		
		$jsonOrder = json_encode($order);
		
		if (!empty($jsonOrder)){
			//а теперь в редис 
			$redis->rpush('INDEXTRDADE_NEW_ORDERS_' . $order['pair'], $jsonOrder);
			//$ssdb->qpush_back('INDEXTRDADE_NEW_ORDERS_'.$order['pair'], json_encode($order));
			
			//в SSDB мы для быстрого восстановления запишем в hash-map
			$recoveryList = $ssdb->hset('INDEXTRDADE_LIVE_ORDERS_'.$order['pair'], $order['id'], $jsonOrder);
		}
		
		
		
		$db->commit();
		
		return intval($_id);
		
	}catch(Exception $e){
		$db->rollBack();
		
		return $e->getMessage();
	}
}


$runtimeStats = Array(
	'processingStart'		=> time(),
	'totalOrdersProcessed' 	=> 0,
	'totalOrdersRejected'  	=> 0,
	'totalEmptyLoops'  		=> 0,
	'lastOrdersPipelineProcessingStats' => Array(), //последние 100 обработанных ордеров, время
	'avgOrderProcessedBy'	=> 0
);
//$client = initRedis();

$redis->del('INDEXTRDADE_NEW_ORDERS_CH0');

$httpClient = new \GuzzleHttp\Client([
	'base_uri' => 'http://localhost:8099',
	'exceptions' => false, 
	'allow_redirects' => true, 'connect_timeout' => 3, 'decode_content' => true, 'force_ip_resolve' => 'v4', 'http_errors' => true, 'read_timeout' => 3, 'synchronous' => true, 'timeout' => 3
]);


$loop->addPeriodicTimer(0.25, function() use (&$db, &$redis, &$ssdb, &$log, &$runtimeStats, &$httpClient){
	//пробуем получить один элемент
	$tmp = $redis->lpop('INDEXTRDADE_NEW_ORDERS_CH0'); //'INDEXTRDADE_ACCEPTED_ORDERS');
	
	if (empty($tmp)){
		$runtimeStats['totalEmptyLoops']++;
		return; //нет новых ордеров 
	}
	
	$z = $runtimeStats['totalOrdersProcessed'];
	$z++;
	$t0 = microtime(true);
	
	if (!empty($tmp) && is_string($tmp)){
		//вроде ордер 
		$orderPacket = json_decode($tmp, true, 16);
		
		if (empty($orderPacket) || !empty(json_last_error())){
			/** execReports только по уже orderID
			//создать екзекьюшинРепорт 		
			$report = Array('type' => 'REJECT', 'msg' => 'ParseError: ' . json_last_error(), 'orderID' => null, 'raw' => $tmp, 'ts' => t());
			
			sendExecutionReport($ssdb, $report, $log);
			**/
			$log->error('JSON Parse error: ' . json_last_error_msg(), Array( $tmp ));
			return;
		}
		
		$order = $orderPacket['body'];
		
		if (!$db->isConnected()){
			$log->warning("DB connection not alive. Try to reconnect");
			
			$db->getConnection();
		}
		
		
		//DEBUG 
		$order['uid'] = 1;
		
		//а главное - хеш-мапа глобальная 
		$ssdb->hset('INDEXTRDADE_ORDERS_BY_USER', $order['id'], $order['uid']);
		
		$report = Array('type' => 'CHECK', 'msg' => 'Decoding protocol and parse format', 'orderID' => $order['id'], 'raw' => $tmp, 'ts' => t());
		sendExecutionReport($ssdb, $report, $log);
		
		
		
		$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: ".$orderPacket['act']." :: Decoding order", $order);

		//Обработка ордера перед сохранением в БД
		//1. Проверка юзера
		$userCheck = checkUser($db, $order);
		
	//echo $z . '| ' . round( (microtime(true) - $tz)*1000, 3) . " :: ";
	//$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: " );
	//$t['userCheck'] = round( (microtime(true) - $t['start'])*1000, 3);
	
		if ($userCheck !== true){
			$log->error( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: User checking FAIL");
			
			$report = Array('type' => 'REJECT', 'msg' => 'User checking failure', 'orderID' => $order['id'], 'ts' => t());
			
			sendExecutionReport($ssdb, $report, $log);

			return;		
		}
		else
			$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: User checking OK" );
		
		$report = Array('type' => 'CHECK', 'msg' => 'User rights checking', 'orderID' => $order['id'], 'ts' => t());
		sendExecutionReport($ssdb, $report, $log);
		
		//если это отмена ордера, то проверить валидность
		if ($orderPacket['act'] == 'REM'){
			$orderValidation = checkOrderValidity($db, $order, $log);
			
			if ($orderValidation !== true){
				$log->error( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Order validity checking FAIL. Reason: " . $orderValidation);
			
				$report = Array('type' => 'REJECT', 'msg' => 'Order validity checking failure, reason: ' . $orderValidation, 'orderID' => $order['id'], 'ts' => t());
			
				sendExecutionReport($ssdb, $report, $log);
				
				return;
			}
			else {
				$result = cancelOrder($db, $redis, $ssdb, $order);
				
				if ($result === true){
					$report = Array('type' => 'CANCEL', 'msg' => 'Order canceled by user', 'orderID' => $order['id'], 'ts' => t());
					
					sendExecutionReport($ssdb, $report, $log);
					
					$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: User cancel order OK" );
				}
				
								
				return;
			}
		}
		
		//2. Проверка инструмента
		$pairCheck = checkTradingPair($db, $order);
	
		if ($pairCheck !== true){
			$log->error( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Trading pair checking FAIL :: " . $pairCheck);
			
			$report = Array('type' => 'REJECT', 'msg' => 'Trading pair checking failure', 'orderID' => $order['id'], 'ts' => t());
			
			sendExecutionReport($ssdb, $report, $log);

			return;		
		}
		else
			$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Trading pair checking OK" );
		
		$report = Array('type' => 'CHECK', 'msg' => 'Trading pair and market checking', 'orderID' => $order['id'], 'ts' => t());
		sendExecutionReport($ssdb, $report, $log);
		
		//3. Проверка границ ордера (размер и т.п.)
		$limitsCheck = checkLimits($db, $order);
	
		//echo $z . '| ' . round( (microtime(true) - $tz)*1000, 3) . " :: ";
		//$t['limitsCheck'] = round( (microtime(true) - $t['start'])*1000, 3);
	
		if ($limitsCheck !== true){
			$log->error( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Limits checking FAIL. Reason: " . $limitsCheck);
			
			$report = Array('type' => 'REJECT', 'msg' => 'Limits checking failure - ' . $limitsCheck, 'orderID' => $order['id'], 'ts' => t());
			
			sendExecutionReport($ssdb, $report, $log);

			return;		
		}
		else
			$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Limits checking OK" );
		
		$report = Array('type' => 'CHECK', 'msg' => 'Limits checking', 'orderID' => $order['id'], 'ts' => t());
		sendExecutionReport($ssdb, $report, $log);
		
		$z = $db->fetchRow('SELECT pair_asset, pair_currency FROM exchange_pairs_tbl WHERE pair_name = "'.$order['pair'].'" LIMIT 1');
		
		$_symbol = $z['pair_currency'];
		
		if ($order['side'] == 'SELL')	
			$_symbol = $z['pair_asset'];
		
	try {	
		//3.1 Аллоцируем средства (в синхронном режиме)
		$httpres = $httpClient->get('/allocator/allocate?_='. t() . '&uid=1&orderID='.$order['id'].'&symbol='.$_symbol.'&amount='.floatval( $order['amount'] ));
		
		if ($httpres->getStatusCode() == 200){
			
			$jres = $httpres->getBody()->getContents();
			
			var_dump( $jres );
			
			if (is_string($jres)){
				$jres = json_decode($jres, true);
			}
			
			if ($jres['status'] != 'OK'){
				
				$log->error( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Funds allocation error: " . $jres['error']);
				
				$report = Array('type' => 'REJECT', 'msg' => 'Funds allocation error: ' . $jres['error'], 'orderID' => $order['id'], 'ts' => t());
			
				sendExecutionReport($ssdb, $report, $log);

				return;
			}else {
				
				$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Funds allocated: " . $jres['data']['lastAllocate'] .  ' ' . $jres['data']['currency_symbol'] . " :: Total allocated by user: ". $jres['data']['amount_at_orders']);
							
			}			
			
		} else {
			$log->error( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Network error while funds allocate");
			
			$report = Array('type' => 'REJECT', 'msg' => 'Network error while funds allocate', 'orderID' => $order['id'], 'ts' => t());
			
			sendExecutionReport($ssdb, $report, $log);

			return;	
		}	

	}catch(\Exception $e){
		
		$log->error('Error with connection to Allocate server: ' . $e->getMessage());
		
		$report = Array('type' => 'REJECT', 'msg' => 'Cant funds allocate, system error', 'orderID' => $order['id'], 'ts' => t());
			
		sendExecutionReport($ssdb, $report, $log);
			
		return;
		
	}	
		
		
		
		//3.1 Вычисляем комиссии
		$orderFee = calcOrderFee($db, $order);
		
		if (is_string($orderFee)){
			$log->error( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Fee calc error. Reason: " . $orderFee);
			
			$report = Array('type' => 'REJECT', 'msg' => 'Fee calc error - ' . $orderFee, 'orderID' => $order['id'], 'ts' => t());
			
			sendExecutionReport($ssdb, $report, $log);
			
			return;
		}
		
		$report = Array('type' => 'CHECK', 'msg' => 'Fee calculating', 'orderID' => $order['id'], 'ts' => t());
		sendExecutionReport($ssdb, $report, $log);
		
		$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Trade fee: " . $orderFee . " :: Price: ".($order['price']/1000000000).", amount: ".($order['amount']/1000000000).", total: ".(($order['price']/1000000000)*($order['amount']/1000000000)) );
	
		//4. Запись в глобальный список ордеров 
		$db_id = addToGlobalOrderList($db, $redis, $ssdb, $order, $orderFee);
		
		if (is_string($db_id)){
			$log->error( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Save to main DB error. Reason: " . $db_id);
			
			$report = Array('type' => 'REJECT', 'msg' => 'Save to main DB error - ' . $db_id, 'orderID' => $order['id'], 'ts' => t());
			
			sendExecutionReport($ssdb, $report, $log);

			return;
		}
		else {
			$log->info( $order['id'] . ' | ' . round( (microtime(true) - $t0)*1000, 3) . " :: Order saved to book OK" );
			
			$report = Array('type' => 'SAVED', 'msg' => 'Order successfull saved to orderbook', 'orderID' => $order['id'], 'ts' => t());
			
			sendExecutionReport($ssdb, $report, $log);
		}
		
		/**
		//5. Нотификация - сервиса ордербуков и т.п.
		if (!$client->isConnected()){
			$client->connect();
			
			usleep(1000);
			
			if (!$client->isConnected())
				die('ERROR: No connection to Redis');
		}
		
		$client->publish('INDEXTRDADE_ORDERBOOK_UPDATE', $tmp['pair'] . ':' . $order['uuid']);
		**/
	}
	
	
	
});


$loop->addPeriodicTimer(5, function() use (&$db, &$log, &$redis){
	$tmp = $redis->llen('INDEXTRDADE_NEW_ORDERS_CH0');
	
	if (!empty($tmp))
		$log->info("New orders queue length: " . $tmp);	
	
	
	if (!$db->isConnected()){
		$log->warning("DB connection not alive. Try to reconnect");
		
		$db->getConnection();
	}
	
	$dbTs = intval( $db->fetchOne('SELECT UNIX_TIMESTAMP() ') );
	$appTs = intval( time() );
		
	if ($appTs != $dbTs){
		$log->warning("DB local time and Application time has difference - " . ($appTs - $dbTs));
	}
	//else
	//	$log->info('Time  app and DB sinked OK');
});


$log->info('Main loop are starting...');
$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
