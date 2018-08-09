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

/**
	По сути, это фронт-энд внутренних систем. 
	Он периодически проверяет очередь новых ордеров, берет их, проводит все базовые проверки
	Если все оке, перемещает в очередь, с которой ордер уходит уже в трейд-систему



**/

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


//Проверка инструмента
//@return Boolean TRUE if all check passed, FALSE is no
function checkTradingPair($db = null, $order = null){
	if (empty($order)) return false;
	
	$uid = intval($order['uid']);
	$pair = strtoupper( strval( $order['pair'] ) );
	$type = strtoupper( strval( $order['type'] ) );
	$exec = strtoupper( strval( $order['exec'] ) );
	
	if (empty($uid) || empty($pair) || empty($type) || empty($exec)) return false;
	
	//проверим существование торговой пары  
	$pairStatus = $db->fetchOne('SELECT pair_status FROM exchange_pairs_tbl WHERE pair_name = "'.$pair.'" LIMIT 1');
	
	if (empty($pairStatus) || $pairStatus != 'traded')
		return false;
	
	//проверка, какие типы ордеров и исполнения разрешены для этой торговой пары 
	$pairOrderTypeStatus = $db->fetchOne('SELECT order_type_rule FROM exchange_pairs_order_types_tbl WHERE pair_name = "'.$pair.'" AND order_type = "'.$type.'" LIMIT 1');
	
	if (empty($pairOrderTypeStatus) || $pairOrderTypeStatus !== 'ALLOW')
		return false;
	
	//проверка типа исполнения 
	$execOrderTypeStatus = $db->fetchOne('SELECT exec_type_rule FROM exchange_pairs_exec_types_tbl WHERE pair_name = "'.$pair.'" AND exec_type = "'.$exec.'" LIMIT 1');
	
	if (empty($execOrderTypeStatus) || $execOrderTypeStatus !== 'ALLOW')
		return false;
	
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

function addToGlobalOrderList($db, $order, $fee){
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
				order_is_partial_filled	= ?,
				order_market_verification_flag	= ?,
				order_last_status_changed_at	= ?  ';
	
	$db->beginTransaction();
	
	try {
		$db->query($sql, Array(
			$order['uuid'],
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
		
		$db->commit();
		
		return intval($_id);
		
	}catch(Exception $e){
		$db->rollBack();
		
		return $e->getMessage();
	}
}


$z = 0;
$client = initRedis();

while(true){
	$z++;
	
	$tmp = $redis->blpop('INDEXTRDADE_ACCEPTED_ORDERS', 5);
	
	if ($tmp == null){
		echo '.';
		continue;
	}
	
	$t['start'] = microtime(true);
	
	if (!empty($tmp) && count($tmp) == 2 && !empty($tmp[1])){
		//вроде ордер 
		$order = json_decode($tmp[1], true, 16);
		
		if (!empty(json_last_error())){
			echo 'RedisERR:' . json_last_error_msg();
			continue;
		}
		
	echo $z . '| ' . round( (microtime(true) - $t['start'])*1000, 3) . " :: Decode order\n";
		
	//$t['decode'] = round( (microtime(true) - $t['start'])*1000, 3);
		
		//Обработка ордера перед сохранением в БД
		$tz = microtime(true);
		//1. Проверка юзера
		$userCheck = checkUser($db, $order);
		
	echo $z . '| ' . round( (microtime(true) - $tz)*1000, 3) . " :: ";
	
	//$t['userCheck'] = round( (microtime(true) - $t['start'])*1000, 3);
	
		if ($userCheck !== true){
			echo "ERROR: Order with uuid: " . $order['order_uuid'] . " checking FALSE (at user check)\n";
			continue;			
		}
		else
			echo "ORDER: " . $order['order_uuid']  . " user check: OK\n";
		
		$tz = microtime(true);
		//2. Проверка инструмента
		$pairCheck = checkTradingPair($db, $order);
	
	echo $z . '| ' . round( (microtime(true) - $tz)*1000, 3) . " :: ";
	
	//$t['pairCheck'] = round( (microtime(true) - $t['start'])*1000, 3);
	
		if ($pairCheck !== true){
			echo "ERROR: Order with uuid: " . $order['order_uuid'] . " checking FALSE (at pair check)\n";
			continue;			
		}
		else
			echo "ORDER: " . $order['order_uuid']  . " pair check: OK\n";
		
		$tz = microtime(true);
		//3. Проверка границ ордера (размер и т.п.)
		$limitsCheck = checkLimits($db, $order);
	
	echo $z . '| ' . round( (microtime(true) - $tz)*1000, 3) . " :: ";
	//$t['limitsCheck'] = round( (microtime(true) - $t['start'])*1000, 3);
	
		if ($limitsCheck !== true){
			echo "ERROR: Order with uuid: " . $order['order_uuid'] . " limits checking FALSE (".$limitsCheck.")\n";
			continue;			
		}
		else
			echo "ORDER: " . $order['order_uuid']  . " limits check: OK\n";
		
		$tz = microtime(true);
		//3.1 Вычисляем комиссии
		$orderFee = calcOrderFee($db, $order);
		
		echo $z . '| ' . round( (microtime(true) - $tz)*1000, 3) . " :: ";	
		
		if (is_string($orderFee)){
			echo "ERROR: calc fee error - " . $orderFee . "\n";
			continue;
		}
	
		echo "ORDER Fee: " . $orderFee . " :: Price: ".($order['price']/1000000000).", amount: ".($order['amount']/1000000000).", total: ".(($order['price']/1000000000)*($order['amount']/1000000000))." \n";
	
		$tz = microtime(true);
		//4. Запись в глобальный список ордеров 
		$db_id = addToGlobalOrderList($db, $order, $orderFee);
		
		echo $z . '| ' . round( (microtime(true) - $tz)*1000, 3) . " :: ";
		
		if (is_string($db_id)){
			echo "ERROR while save to DB: " . $db_id . "\n";
			continue;
		}
		else{
			echo "ORDER PLACED to BOOK :: id = ".$db_id." with uuid = ".$order['uuid']."\n";
		}
		
		//5. Нотификация - сервиса ордербуков и т.п.
		if (!$client->isConnected()){
			$client->connect();
			
			usleep(1000);
			
			if (!$client->isConnected())
				die('ERROR: No connection to Redis');
		}
		
		$client->publish('INDEXTRDADE_ORDERBOOK_UPDATE', $tmp['pair'] . ':' . $order['uuid']);
	}
	
	//var_dump( $tmp );	
	
	sleep(1);
}














echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
