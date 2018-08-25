<?php
namespace IndexTrade;
 
error_reporting(E_ALL);
date_default_timezone_set('UTC');
clearstatcache(true);

include_once( __DIR__ . '/__bootstrap.php');

echo "\n\n";
echo "     ---| IndexTrade.Exchange Platform via CoinIndex Team with LOVE |--- \n\n";
echo "Starting at: " . date('r') . "\n";
echo "Starting Trade Master...\n";

$ch = 0;
$pair = 'XXX/USDT';

if (!empty($argv[1])){
	$ch = trim($argv[1]); //какой канал слушаем  
}

if (!empty($argv[2])){
	$pair = strtoupper( trim($argv[2]) ); //какой инструмент
}

echo "\nChannel: " . $ch . ", Pair: ".$pair."\n\n";

//$redis = initRedis();

//базовая проверка ордера
function checkOrder($json){

	if (empty($json)) return 'ERR:Empty message';
	/*
	$json = json_decode( $msg, true, 16);*/
	
	if (!empty(json_last_error()))
		return 'ERR:' . json_last_error_msg();
	
	if (empty($json['id'])) return 'ERR:Invalid Order ID';
	
	if (empty($json['uid']) || !is_int($json['uid'])) return 'ERR:Invalid uid';
	if (empty($json['pair'])) return 'ERR:Empty pair';
	if (count( explode('/', $json['pair']) ) != 2) return 'ERR:Invalid pair';
	if (empty($json['type'])) return 'ERR:Empty type';
		
	//$json['type'] = strtoupper( $json['type'] );
		
	if (!in_array($json['type'], Array('LIMIT','MARKET')))
		return 'ERR:Invalid type';
		
	if (empty($json['side'])) return 'ERR:Empty side';
		
	//$json['side'] = strtoupper( $json['side'] );
		
	if ($json['side'] != 'BUY' && $json['side'] != 'SELL') return 'ERR:Invalid side';	
		
	if (empty($json['exec'])) return 'ERR:Empty execution type';
		
	//$json['exec'] = strtoupper( $json['exec'] );
		
	if (!in_array($json['exec'], Array('DAY','FOK','IOC','GTC','AON','LOO','OPG','MOC','MOO')))
		return 'ERR:Invalid execution type (value: '.$json['exec'].')';
		
	if ($json['type'] != 'MARKET'){		
		if (empty($json['price']) || !is_numeric($json['price'])) return 'ERR:Empty price';
			
		if ($json['price'] < 0 || ($json['price']/1000000000) > 999999999999)
			return 'ERR:Price out of range';
	}
		
	if (empty($json['amount']) || !is_numeric($json['amount'])) return 'ERR:Empty amount';
		
	if ($json['amount'] < 0.000000001 || ($json['amount']/1000000000) > 999999999999)
		return 'ERR:Amount out of range';
		
	if (empty($json['ts'])) return 'ERR:Empty created ts';
		
	return true;
}

//обработка ордеров 
function procOrder($redis, $order, &$book, &$reportsQueue){
	//printMarketView($book, $reportsQueue); 
	
	/**
		рыночный ордер пробуем исполнить сразу
		лимитный - ставим в стакан
		проверяем, можно ли исполнить топ-оф-буук (не перекрываються ли они)	
		отправляем репорты, если они есть 
	**/
	if ($order['type'] == 'MARKET'){
		executeMarketOrder($order, $book, $reportsQueue);
	}
	else
	if ($order['type'] == 'LIMIT'){
		updateBook($order, 'ADD', $book, $reportsQueue);
	}
	
	while(checkTopBook($book) === true){
		$res = executeTopBook($book, $reportsQueue);
			
		if ($res === true){
			//после этого перестроить весь бук
			//!TODO: оптимизировать или убрать вообще потом :) 
			rebuildBook($book, $reportsQueue);
		}
			
		usleep(10);
	}

	return true;
}

//обрабатывает репорты 
function procReports(&$ssdb, &$reportsQueue){
	if (empty($reportsQueue))
		return false;
	
	$_qq = Array();
	
	foreach($reportsQueue as $q){
		$tmp = json_encode($q); 
		
		if (empty($tmp) || !empty(json_last_error())){
			continue; //пропускаем?
		}
		
		$_qq[] = $tmp;
	}
	
	if (!empty($_qq) && !empty($ssdb)){
		try{
			
			$ssdb->qpush_back('INDEXTRDADE_EXECUTION_REPORTS', $_qq);
			
			$reportsQueue = Array();
			
			return true;
			
		}catch(Exception $e){
			var_dump( $e );
			
			return false;
		}
	}
	
	return false;
}

//удаляет ордер с бука
function remOrder(&$redis, $order, &$book, &$reportsQueue){
	$id = $order['id'];
	$pair = $order['pair'];
	
	if (empty($id) || empty($pair)) return false; 
	
	updateBook($order, 'REM', $book, $reportsQueue);
	
	return true;
}

//обновляет локальную копию ордербука
function updateBook($order, $action, &$book, &$reportsQueue, $mode = 'normal'){
	$id = $order['id'];
	$ts = t();
	
	if ($action == 'ADD'){
		//статистика
		$book['STAT'][ $order['side'] ]['orders']++;
		$book['STAT'][ $order['side'] ]['volume'] = $book['STAT'][ $order['side'] ]['volume'] + $order['amount'];
			
		$book['BOOK'][ $order['side'] ][ $order['price'] ][ $ts ][] = $id;
				
		$book['ORDERS'][ $id ] = $order;
		
		if ($mode == 'normal'){
			$report = Array('type' => 'PLACED', 'msg' => 'Order placed to trade system', 'orderID' => $id, 'ts' => $ts);
			$reportsQueue[] = $report;	
		}
	}
	else 
	if ($action == 'REM') {
		if (array_key_exists($id, $book['ORDERS'])){
			$r = $book['ORDERS'][ $id ];
			$ts = t(); // intval($r['ts']*10000);
			
			unset( $book['ORDERS'][ $id ] );
			
			
				$tmp = $book['BOOK'][ $r['side'] ][ $r['price'] ][ $ts ];
				
				if (is_array($tmp)){					
					$book['BOOK'][ $r['side'] ][ $r['price'] ][ $ts ] = array_filter($tmp, function($i, $v){
						if ($v === $id)
							return false;
						else
							return true;
					}, ARRAY_FILTER_USE_BOTH);				
				}
				
				if (empty($book['BOOK'][ $r['side'] ][ $r['price'] ][ $ts ])){
					unset( $book['BOOK'][ $r['side'] ][ $r['price'] ][ $ts ] );
				}

				if (empty($book['BOOK'][ $r['side'] ][ $r['price'] ])){
					unset( $book['BOOK'][ $r['side'] ][ $r['price'] ] );
				}				
						
			$book['STAT'][ $r['side'] ]['orders']--;
			$book['STAT'][ $r['side'] ]['volume'] = $book['STAT'][ $r['side'] ]['volume'] - $r['amount'];
		
			if ($mode == 'normal'){
				$report = Array('type' => 'REMOVE', 'msg' => 'Order removed from trade system', 'orderID' => $id, 'ts' => $ts);
				$reportsQueue[] = $report;
			}
		}
	}
	
	if ($mode !== 'rebuild'){
		//сортировка 
		//SELL - от минимальной до максимальной цены 
		//поправка - так же как и бай 
		krsort( $book['BOOK'][ 'SELL' ], SORT_NUMERIC );
		
		//BUY - от максимальной до минимальной 
		krsort( $book['BOOK'][ 'BUY' ], SORT_NUMERIC );
		
		//построение BookView, топ-10 стакана в обе стороны
		//procMarketView($book, 10);
			
		$z = t() - $ts;
		
		/**
		if ($action === 'ADD'){
			$report = Array('type' => 'PLACED', 'msg' => 'Order booked', 'orderID' => $id, 'ts' => $ts);
			$reportsQueue[] = $report;
				
//t			echo $z . " mcs. :: ADD " . $order['side'] . " :: " . $order['type'] ." " . ($order['amount']/1000000000) . "@" . ($order['price']/1000000000) . " :: OrderID: " . $id . "\n"; 
		}
		else
		if ($action === 'REM'){
			$report = Array('type' => 'CANCEL', 'msg' => 'Order canceled', 'orderID' => $id, 'ts' => $ts);
			$reportsQueue[] = $report;
		}
		**/
	}
	
	
	return true;	
}

//упрощенная версия, обирает ордер по id
function cancelOrderById($orderId, &$book, &$reportsQueue){
	if (array_key_exists($orderId, $book['ORDERS'])){
		$r = $book['ORDERS'][ $orderId ];
		$ts = t(); // intval($r['ts']*10000);
		
		unset( $book['ORDERS'][ $orderId ] );
		
		$tmp = $book['BOOK'][ $r['side'] ][ $r['price'] ][ $ts ];
			
			if (is_array($tmp)){					
				$book['BOOK'][ $r['side'] ][ $r['price'] ][ $ts ] = array_filter($tmp, function($i, $v){
					if ($v === $orderId)
						return false;
					else
						return true;
				}, ARRAY_FILTER_USE_BOTH);				
			}
			
			if (empty($book['BOOK'][ $r['side'] ][ $r['price'] ][ $ts ])){
				unset( $book['BOOK'][ $r['side'] ][ $r['price'] ][ $ts ] );
			}

			if (empty($book['BOOK'][ $r['side'] ][ $r['price'] ])){
				unset( $book['BOOK'][ $r['side'] ][ $r['price'] ] );
			}				
					
		$book['STAT'][ $r['side'] ]['orders']--;
		$book['STAT'][ $r['side'] ]['volume'] = $book['STAT'][ $r['side'] ]['volume'] - $r['amount'];
		
		$report = Array('type' => 'CANCEL', 'msg' => 'Order canceled', 'orderID' => $orderId, 'ts' => $ts);
		$reportsQueue[] = $report;	
	}
	
	return true;
}

function procMarketView(&$book, $countItems = 10){
	$b = array_chunk( array_keys($book['BOOK'][ 'BUY' ]), $countItems)[0];
	$s = array_chunk( array_keys($book['BOOK'][ 'SELL' ]), $countItems)[0];
		
	$buy = Array();
	$sell = Array();
	
	if (!empty($b)){
		foreach($b as $z){
			$t = $book['BOOK'][ 'BUY' ][ $z ];
		
			$orders = count( $t );
			$volume = 0;
			$price = $z;
			
			foreach($t as $x){
				//var_dump( $x );
				
				$o = $book['ORDERS'][$x[0]];
				$volume = $volume + $o['amount'];
			}
			
			$buy[] = Array($price / 1000000000, $volume / 1000000000, $orders);
		}
	}
	
	$book['MARKET_VIEW']['BUY'] = $buy;
	
	if (!empty($s)){
		foreach($s as $z){
			$t = $book['BOOK'][ 'SELL' ][ $z ];
		
			$orders = count( $t );
			$volume = 0;
			$price = $z;
			
			foreach($t as $x){
				$o = $book['ORDERS'][$x[0]];
				$volume = $volume + $o['amount'];
			}
			
			$sell[] = Array($price / 1000000000, $volume / 1000000000, $orders);
		}
	}
	
	$book['MARKET_VIEW']['SELL'] = $sell;

	return true;
}

//выполнение рыночного ордера (сразу)
function executeMarketOrder($order, &$book, &$reportsQueue){
	/*
	echo t() . " :: ADD " . $order['side'] . " :: " . $order['type'] ." " . ($order['amount']/1000000000) . "@" . ($order['price']/1000000000) . " :: OrderID: " . $order['id'] . "\n"; 
	*/
	
	if (empty($book['BOOK']['BUY']) && empty($book['BOOK']['SELL'])){
		//пока еще книга пустая, генерируем reportRejectOrder
		$report = Array('type' => 'REJECT', 'msg' => 'Book empty', 'orderID' => $order['id'], 'ts' => t());
		
		$reportsQueue[] = $report;
		
		return false;
	}
	
	if ($order['side'] == 'BUY'){
		if (empty($book['BOOK']['SELL'])){
			$report = Array('type' => 'REJECT', 'msg' => 'Sell book empty', 'orderID' => $order['id'], 'ts' => t());
		
			$reportsQueue[] = $report;
			
			return false;
		}
		
		//получим топ-цену
		$price = getTopBook($book, 'SELL');
		
		if (empty($price) || $price === false){
			$report = Array('type' => 'REJECT', 'msg' => 'Sell price empty', 'orderID' => $order['id'], 'ts' => t());
		
			$reportsQueue[] = $report;
			
			return false;
		}
	}
	else
	if ($order['side'] == 'SELL'){
		if (empty($book['BOOK']['BUY'])){
			$report = Array('type' => 'REJECT', 'msg' => 'Buy book empty', 'orderID' => $order['id'], 'ts' => t());
		
			$reportsQueue[] = $report;
			
			return false;
		}
		
		//получим топ-цену
		$price = getTopBook($book, 'BUY');
		
		if (empty($price) || $price === false){
			$report = Array('type' => 'REJECT', 'msg' => 'Buy price empty', 'orderID' => $order['id'], 'ts' => t());
		
			$reportsQueue[] = $report;
			
			return false;
		}
	}
	
	$order['price'] = $price;
	
	//добавим в бук (хотя неоптимально так)
	updateBook($order, 'ADD', $book, $reportsQueue);
	
	return true;	
}

//метчит лимитные ордера по топовой цене 
function executeTopBook(&$book, &$reportsQueue){
	$b = $book['BOOK']['BUY'];
	$s = $book['BOOK']['SELL'];
	
	if (empty($b) || empty($s))
		return false;
	
	//выполняем первую заявку бай и дальше смотрим по циклу 
	$bTop = array_keys($b)[0];
	$sTop = array_keys($s)[0];
	
	//в здесь уже сортируем по времени 
	$bb = $book['BOOK']['BUY'][ $bTop ];
	krsort($bb, SORT_NUMERIC );
	
	$ss = $book['BOOK']['SELL'][ $sTop ];
	krsort($ss, SORT_NUMERIC );
	
	//но приоритет будет ММО ордеру, попробуем найти 
	$firstMMO = null;
	$firstOrder = null;
	
	foreach($bb as $ts => $oarr){
		foreach($oarr as $z){
			$o = $book['ORDERS'][ $z ];
			
			if (in_array('MMO', $o['tags']) === true){
				$firstMMO = $o['id'];
				break 2;
			}
			else
			if ($firstOrder === null){
				$firstOrder = $o['id'];
			}
		}
	}
	
	$realExecuteOrderBuy = null;
	
	if (!empty($firstMMO)){
		//echo "EXECUTE MMO ORDER BUY: " . $firstMMO . "\n";
		$realExecuteOrderBuy = $firstMMO;		
	}
	else 
	if (!empty($firstOrder)) {
		//echo "EXECUTE USER ORDER BUY: " . $firstOrder . "\n";
		$realExecuteOrderBuy = $firstOrder;		
	}
	
	//а теперь какой будет селл-ордер? 
	//так же, сначала MMO потом другие 
	$secondMMO = null;
	$secondOrder = null;
	
	foreach($ss as $ts => $oarr){
		foreach($oarr as $z){
			$o = $book['ORDERS'][ $z ];
			
			if (in_array('MMO', $o['tags']) === true){
				$secondMMO = $o['id'];
				break 2;
			}
			else
			if ($secondOrder === null){
				$secondOrder = $o['id'];
			}
		}
	}
	
	$realExecuteOrderSell = null;
	
	if (!empty($secondMMO)){
		//echo "EXECUTE MMO ORDER SELL: " . $secondMMO . "\n";
		$realExecuteOrderSell = $secondMMO;		
	}
	else 
	if (!empty($secondOrder)) {
		//echo "EXECUTE USER ORDER SELL: " . $secondOrder . "\n";
		$realExecuteOrderSell = $secondOrder;		
	}
	
	//echo "\n==================================\n";
	if (!empty($realExecuteOrderBuy) && !empty($realExecuteOrderSell)){
		
		//var_dump( $book['ORDERS'][ $realExecuteOrderBuy ] );
		//var_dump( $book['ORDERS'][ $realExecuteOrderSell ] );
		
		//непосредственно выполняет ордер (пару взаимную)
		realExecuteOrders($book, $realExecuteOrderBuy, $realExecuteOrderSell, $reportsQueue);
		
		return true;
	}
	
	//var_dump( $bb );
	
	return false;	
}

//выполняет взаимную пару ордеров (BUY >> SELL), создает репорты, если нужно - partial fill или убирает с ордербука
function realExecuteOrders(&$book, $buyOrderId, $sellOrderId, &$reportsQueue){
	//echo "Real Executing Orders!\n";
	//echo "BUY: " . $buyOrderId . ", SELL: " . $sellOrderId . "\n\n";
	
	$buy = $book['ORDERS'][ $buyOrderId ];
	$sell = $book['ORDERS'][ $sellOrderId ];
	
	//рассчитаем комиссию 
	//$buyFee = calcFee(($buy['price'] * $buy['amount']), 'TRADER', 'BUY', $book);
	//$sellFee = calcFee(($sell['price'] * $buy['amount']), 'TRADER', 'BUY', $book);
	
	//тип исполнения - FOC (Fill-or-Kill) - если не может быть полностью исполнен по цене 
	if ($buy['exec'] === 'FOC'){
		if ($buy['amount'] > $sell['amount']){
			//отменяем ордер BUY 
			$reportClose = Array( 'type' => 'CANCEL', 
							'msg' => 'Canceled by execution (No full amount)', 
							'orderID' => $buyOrderId, 
							'ts' => t()	);
			$reportsQueue[] = $reportClose;
			
			unset( $book['ORDERS'][ $buyOrderId ] );
			
//t			echo "BUY order ".$buyOrderId." :: Cancel :: Reason: No full amount for FOC exec type. Need: ".$buy['amount'].", available: ".$sell['amount']."\n";
			
			return true;			
		}
	}
	
	if ($sell['exec'] === 'FOC'){
		if ($buy['amount'] < $sell['amount']){
			//отменяем ордер BUY 
			$reportClose = Array( 'type' => 'CANCEL', 
							'msg' => 'Canceled by execution (No full amount)', 
							'orderID' => $sellOrderId, 
							'ts' => t()	);
			$reportsQueue[] = $reportClose;
			
			unset( $book['ORDERS'][ $sellOrderId ] );
			
//t			echo "SELL order ".$sellOrderId." :: Cancel :: Reason: No full amount for FOC exec type. Need: ".$sell['amount'].", available: ".$buy['amount']."\n";
			
			return true;			
		}
	}
	
	//конечная цена сделки будет минимальной от покупки или продажи 
	$realPrice = $buy['price'];
	
	if ($sell['price'] < $buy['price'])
		$realPrice = $sell['price'];
	
	//а ММ или нет 
	$buyUser = 'TRADER';
	$sellUser = 'TRADER';
	
	if (is_array($buy['tags']) && in_array('MMO', $buy['tags']))
		$buyUser = 'MM';
	
	if (is_array($sell['tags']) && in_array('MMO', $sell['tags']))
		$sellUser = 'MM';
	
	//если обьем на продажу больше покупки, то закрываем весь ордер покупки, оставляем partial-fill на продажу
	if ($sell['amount'] > $buy['amount']){
//t		echo "DEAL: ".($realPrice/1000000000)." real price BUY (".$buyOrderId.") vol " . ($buy['amount']/1000000000) . "@" . ($buy['price']/1000000000) . " from SELL (".$sellOrderId.") vol.: ".($sell['amount']/1000000000)."@".($sell['price']/1000000000)."\n";
		
		//расчет идет по сумме и цене продажи 
		$buyFee = calcFee(($realPrice/1000000000 * $buy['amount']/1000000000)*1000000000, $buyUser, 'BUY', $book);
		$sellFee = calcFee(($realPrice/1000000000 * $buy['amount']/1000000000)*1000000000, $sellUser, 'SELL', $book);

		//для облегчения теста 
		$buyFee = $buyFee/1000000000;
		$sellFee = $sellFee/1000000000;
		
		//1. Buy ордер полностью выполнен и убираеться вообще 
		//2. Sell ордер partial fill и остаеться 
		$reportBuy = Array( 'type' => 'FILL', 
							'msg' => 'Executing', 
							'orderID' => $buyOrderId, 
							'ts' => t(), 
							'raw' => Array(
								'price' => $realPrice, //$buy['price'],
								'volume' => $buy['amount'],
								'fee'	 => $buyFee,
								'cPartyOrderId' => $sellOrderId
							)
					);
		$reportsQueue[] = $reportBuy;
		
		$reportClose = Array( 'type' => 'CLOSE', 
							'msg' => 'Closed by full filled', 
							'orderID' => $buyOrderId, 
							'ts' => t()
					);
		$reportsQueue[] = $reportClose;
		
		$reportSell = Array( 'type' => 'PFILL', 
							'msg' => 'Executing', 
							'orderID' => $sellOrderId, 
							'ts' => t(), 
							'raw' => Array(
								'price' => $realPrice, //$buy['price'],
								'volume' => $buy['amount'],
								'fee'	=> $sellFee,
								'cPartyOrderId' => $buyOrderId
							)
					);
		$reportsQueue[] = $reportSell;
		
		//селл ордер остаеться
		$book['ORDERS'][ $sellOrderId ]['amount'] = $sell['amount'] - $buy['amount'];
		
		//выставим флаг что он частично уже исполнен
		if (!in_array( 'PFILL', $book['ORDERS'][ $sellOrderId ]['tags'] )){
			$book['ORDERS'][ $sellOrderId ]['tags'][] = 'PFILL'; 
		}
		
		//уберем полностью бай ордер 
		unset( $book['ORDERS'][ $buyOrderId ] );
		
		//echo "EXECUTION OK! See reports to details\n";		
	}
	else
	if ($sell['amount'] == $buy['amount']){
//t		echo "DEAL: ".($realPrice/1000000000)." real price BUY (".$buyOrderId.") vol " . ($buy['amount']/1000000000) . "@" . ($buy['price']/1000000000) . " from SELL (".$sellOrderId.") vol.: ".($sell['amount']/1000000000)."@".($sell['price']/1000000000)."\n";
		
		//расчет идет по сумме и цене продажи 
		$buyFee = calcFee(($realPrice/1000000000 * $buy['amount']/1000000000)*1000000000, $buyUser, 'BUY', $book);
		$sellFee = calcFee(($realPrice/1000000000 * $buy['amount']/1000000000)*1000000000, $sellUser, 'SELL', $book);
		
		//для облегчения теста 
		$buyFee = $buyFee/1000000000;
		$sellFee = $sellFee/1000000000;
		
		//1. Buy ордер полностью выполнен и убираеться вообще 
		//2. Sell ордер partial fill и остаеться 
		$reportBuy = Array( 'type' => 'FILL', 
							'msg' => 'Executing', 
							'orderID' => $buyOrderId,
							'ts' => t(), 
							'raw' => Array(
								'price' => $realPrice, //$buy['price'],
								'volume' => $buy['amount'],
								'fee'	=> $buyFee,
								'cPartyOrderId' => $sellOrderId
							)
					);
		$reportsQueue[] = $reportBuy;
		
		$reportClose = Array( 'type' => 'CLOSE', 
							'msg' => 'Closed by full filled', 
							'orderID' => $buyOrderId, 
							'ts' => t()
					);
		$reportsQueue[] = $reportClose;
		
		$reportSell = Array( 'type' => 'FILL', 
							'msg' => 'Executing', 
							'orderID' => $sellOrderId, 
							'ts' => t(), 
							'raw' => Array(
								'price' => $realPrice, //$buy['price'],
								'volume' => $buy['amount'],
								'fee' => $sellFee,
								'cPartyOrderId' => $buyOrderId
							)
					);
		$reportsQueue[] = $reportSell;
		
		$reportClose = Array( 'type' => 'CLOSE', 
							'msg' => 'Closed by full filled', 
							'orderID' => $sellOrderId, 
							'ts' => t()
					);
		$reportsQueue[] = $reportClose;
		
		//уберем полностью оба ордера  
		unset( $book['ORDERS'][ $buyOrderId ] );
		unset( $book['ORDERS'][ $sellOrderId ] );
		
		//echo "EXECUTION OK! See reports to details\n";			
	}
	else
	if ($sell['amount'] < $buy['amount']){
//t		echo "DEAL: ".($realPrice/1000000000)." real price BUY (".$buyOrderId.") vol " . ($sell['amount']/1000000000) . " (from ".($buy['amount']/1000000000).")@" . ($buy['price']/1000000000) . " from SELL (".$sellOrderId.") vol.: ".($sell['amount']/1000000000)."@".($sell['price']/1000000000)."\n";
		
		//расчет идет по сумме и цене продажи 
		$buyFee = calcFee(($realPrice/1000000000 * $sell['amount']/1000000000)*1000000000, $buyUser, 'BUY', $book);
		$sellFee = calcFee(($realPrice/1000000000 * $sell['amount']/1000000000)*1000000000, $sellUser, 'SELL', $book);
		
		//для облегчения теста 
		$buyFee = $buyFee/1000000000;
		$sellFee = $sellFee/1000000000;
		//1. Sell ордер полностью выполнен и убираеться вообще 
		//2. Buy ордер partial fill и остаеться 
		$reportBuy = Array( 'type' => 'PFILL', 
							'msg' => 'Executing', 
							'orderID' => $buyOrderId, 
							'ts' => t(), 
							'raw' => Array(
								'price' => $realPrice, //$buy['price'],
								'volume' => $sell['amount'],
								'fee' => $buyFee,
								'cPartyOrderId' => $sellOrderId
							)
					);
		$reportsQueue[] = $reportBuy;
		
		$reportSell = Array('type' => 'FILL', 
							'msg' => 'Executing', 
							'orderID' => $sellOrderId, 
							'ts' => t(), 
							'raw' => Array(
								'price' => $realPrice, //$buy['price'],
								'volume' => $sell['amount'],
								'fee' => $sellFee,
								'cPartyOrderId' => $buyOrderId
							)
					);
		$reportsQueue[] = $reportSell;
		
		$reportClose = Array( 'type' => 'CLOSE', 
							'msg' => 'Closed by full filled', 
							'orderID' => $sellOrderId, 
							'ts' => t()
					);
		$reportsQueue[] = $reportClose;
		
		//уберем полностью sell ордер 
		unset( $book['ORDERS'][ $sellOrderId ] );
		
		//buy ордер остаеться
		$book['ORDERS'][ $buyOrderId ]['amount'] = $buy['amount'] - $sell['amount'];
		
		//выставим флаг что он частично уже исполнен
		if (!in_array( 'PFILL', $book['ORDERS'][ $buyOrderId ]['tags'] )){
			$book['ORDERS'][ $buyOrderId ]['tags'][] = 'PFILL'; 
		}
		
		//echo "EXECUTION OK! See reports to details\n";	
	}
	
	return true;
}

//получает топ-цену для указанной стороны в буке 
function getTopBook(&$book, $side = 'BUY'){
	$b = $book['BOOK'][$side];
	
	if (empty($b))
		return false;
	
	$bTop = intval( array_keys($b)[0] );
	
	if (empty($bTop)) 
		return false;
	
	return $bTop;	
}


//проверка, можно ли закрыть два лимитных ордера в топ-оф-бук
function checkTopBook(&$book){
	$b = $book['BOOK']['BUY'];
	$s = $book['BOOK']['SELL'];
	
	if (empty($b) || empty($s))
		return false;
	
	$bTop = intval( array_keys($b)[0] );
	$sTop = intval( array_keys($s)[0] );
	
	if (empty($bTop) || empty($sTop))
		return false;
	
	//echo "Best BUY: " . $bTop . "\n";
	//echo "Best SELL: " . $sTop . "\n";
	
	//покупают за цену продажи или больше
	if ($bTop >= $sTop) { 
		return true;
	}
	
	return false;	
}

//полная перестройка ордербука 
function rebuildBook(&$book, &$reportsQueue){
	$orders = $book['ORDERS'];
	
	$book = buildBook();
	
	foreach($orders as $o){
		updateBook($o, 'ADD', $book, $reportsQueue, 'rebuild');
	}
	
	//сортировка 
	//SELL - от минимальной до максимальной цены 
	//поправка - так же как и бай 
	krsort( $book['BOOK'][ 'SELL' ], SORT_NUMERIC );
		
	//BUY - от максимальной до минимальной 
	krsort( $book['BOOK'][ 'BUY' ], SORT_NUMERIC );
		
	//построение BookView, топ-10 стакана в обе стороны
	//procMarketView($book, 10);
	
	
	return true;
}

function buildBook(){
	return Array(
		'STAT' => Array(
			'BUY' => Array('orders' => 0, 'volume' => 0),
			'SELL' => Array('orders' => 0, 'volume' => 0),
			'LAST_TRADE' => null,
			'PRICE' => Array('BUY' => 0, 'SELL' => 0, 'MID' => 0)
		),
		'BOOK' => Array('BUY' => Array(), 'SELL' => Array()),
		
		'MARKET_VIEW' => Array(),
		
		'FEE' => Array(
			'TRADER' => Array(
				'BUY' =>  Array('prc' => 0.1, 'min' => 0.01, 'max' => 10), //% от суммы
				'SELL' => Array('prc' => 0.1, 'min' => 0.01, 'max' => 10) //% от суммы
			),
			'MM' => Array(
				'BUY'	=> Array('prc' => 0.05, 'min' => 0.01, 'max' => 5),
				'SELL'	=> Array('prc' => 0.05, 'min' => 0.01, 'max' => 5)
			)
		),
		
		//общая таблица ордеров 
		'ORDERS' => Array()
	);
}

//возвращает описание комиссий 
function getFee($type = 'TRADER', $side = 'BUY', &$book){
	return $book['FEE'][ $type ][ $side ];
}

//расчет комиссии 
function calcFee($sum = 0, $type = 'TRADER', $side = 'BUY', &$book){
	if (empty($sum))
		return 0;
	
	$fee = getFee($type, $side, $book);
	
	//return Array($type, $side, $fee);
	
	if (empty($fee))
		return 0;
	
	if (!empty($fee) && empty($fee['prc']))
		return 0;
	
	//считаем prc а потом сравниваем с мин/макс 
	$prcFee = ($sum / 100) * $fee['prc'];
	
	if ($prcFee >= ($fee['min']*1000000000) && $prcFee <=  ($fee['max']*1000000000))
		return $prcFee;
	else
	if ($prcFee < ($fee['min']*1000000000))
		return $fee['min']*1000000000;
	else
	if ($prcFee > ($fee['max']*1000000000))
		return $fee['max']*1000000000;	
}

function printMarketView(&$book, &$reportsQueue){
	$x = $book['MARKET_VIEW'];
	
	if (!empty($x)){
		echo "\n";
		//рендерим визуально как выглядит стакан 
		echo str_pad('BUY', 13, ' ', STR_PAD_LEFT) . '|' . str_pad('SELL', 13, ' ', STR_PAD_RIGHT);
		echo "\n";
		
		for($i = 0; $i < 9; $i++){
			$b = $x['BUY'][ $i ];
			$s = $x['SELL'][ $i ];
			
			if (empty($b) && empty($s)) break;
			
			//var_dump( $b ); 
			//var_dump( $s );
			
			if (!empty($b)) echo str_pad($b[1] . ' : ' . str_pad($b[0], 5, '0',STR_PAD_RIGHT),  13, ' ', STR_PAD_LEFT);
			else 
				echo str_pad(' ', 13, ' ', STR_PAD_LEFT);
			
			echo "|";
			
			if (!empty($s)) echo str_pad(str_pad($s[0],5, '0',STR_PAD_RIGHT) . ' : ' . $s[1], 13, ' ', STR_PAD_RIGHT);
			else 
				echo str_pad(' ', 13, ' ', STR_PAD_RIGHT);
			
			echo "\n"; 
		}
		
		echo "\n";		
	}
	//echo "\n";
	echo "Reports: " . count($reportsQueue) . "\n";
	echo "Orders: " . count($book['ORDERS']) . "\n";

	return true;
}

//статус пострения бука - open - значит открыт, freeze - заморожен
$bookStatus = 'open';
$reportsQueue = Array(); //массив репортов, которые нужно отправить 
//сколко всего в буке ордеров, сумма и обьемы
$book = buildBook();
$marketView = Array();

$ssdb->hclear('INDEXTRDADE_LIVE_ORDERS_'.$pair);
//начальная загрузка данных 
$res = $ssdb->hgetall('INDEXTRDADE_LIVE_ORDERS_'.$pair);

echo "Found: " . count($res) . " orders at snapshot. Try to restore orderbook\n\n";

if (!empty($res)){
	foreach($res as $x){
		$z = json_decode($x, true, 16);
		
		if (!empty($z)){
			$bookQueueOrders[ $z['id'] ] = $z;

			updateBook($z, 'ADD', $book, $reportsQueue, 'restore');
		}
	}
}
echo "Book snapshot restored: " . count($book['ORDERS']) . " orders\n";

$сentrifugo = initCentrifugo();

//таймер берет с очереди новую задачу и обрабатывает ее (добавление или удаление ордера или команда)
$loop->addPeriodicTimer(0.1, function() use (&$redis, &$bookStatus, &$pair, &$ch, &$reportsQueue, &$book){
	if ($bookStatus === 'freez') return;
	
	//нужно подписаться на парралельно два канала - с отменами и новыми ордерами 	
	// INDEXTRDADE_CANCEL_ORDERS_' . $order['pair']
	$cancelQueue = $redis->llen( 'INDEXTRDADE_CANCEL_ORDERS_' . $pair );
	
	if (!empty($cancelQueue)){
		echo "Cancel order queue length: " . $cancelQueue . "\n";
		
		$cq = $redis->lrange( 'INDEXTRDADE_CANCEL_ORDERS_' . $pair, 0, $cancelQueue );
		
		if (!empty($cq)){
			foreach($cq as $orderId){
				//перебираем все ордера которые нужно удалить 
				cancelOrderById($orderId, $book);
			}
		}
	}
	
	$tmp = $redis->lpop('INDEXTRDADE_NEW_ORDERS_'.$pair); // .'_CH' . $ch
	
	if (!empty($tmp)){
	
		$cmd = json_decode($tmp, true, 16);
		
		$checkResult = checkOrder( $cmd );
			
		if ($checkResult !== true){
			//echo $checkResult . " :: ";
				
			$report = Array('type' => 'REJECT', 'msg' => $checkResult, 'orderID' => $cmd['id'], 'ts' => t());
			$reportsQueue[] = $report;
		}
		else {	
			echo $tmp . "\n";
			
			procOrder($redis, $cmd, $book, $reportsQueue);	
			
		}
	}
	
});

/*
$loop->addPeriodicTimer(1, function() use (&$book){
	procMarketView($book, 10);
});
*/

//фризим очередь, формируем бук 
$loop->addPeriodicTimer(10, function() use (&$redis, &$сentrifugo, $pair, &$book, &$reportsQueue){
	//return;
	
	//Todo: считать спред, лучший бид/аск, последнюю сделку, обьемы на покупку и продажу общие	
	procMarketView($book, 10);
	
	$_data = json_encode( $book['MARKET_VIEW'] );
	
	//обновляем в Redis-е
	$redis->hset('INDEXTRDADE_MARKET_VIEW', $pair, $_data);
	
	//отослать в паблик канал центрифуги 
	$сentrifugo->publish('public:' . $pair, Array( 'message' => $_data ));
	
	printMarketView($book, $reportsQueue); 
	return;
});

//отправляем репорты асинхронно 
$loop->addPeriodicTimer(1, function() use (&$ssdb, &$reportsQueue){
	if (!empty($reportsQueue)){
		procReports($ssdb, $reportsQueue);
	}
});


$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
