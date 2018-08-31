<?php
namespace IndexTrade;
 
error_reporting(E_ALL);
date_default_timezone_set('UTC');
clearstatcache(true);

include_once( __DIR__ . '/__bootstrap.php');

echo "\n\n";
echo "     ---| IndexTrade.Exchange Platform via CoinIndex Team with LOVE |--- \n\n";
echo "Starting at: " . date('r') . "\n";
echo "Starting ExecutionReports Master...\n\n";

$log = initLog('idxtReportsMaster');

/**
	$ssdb->qpush_back('INDEXTRDADE_EXECUTION_REPORTS', $tmp);
	
	$report = Array('type' => 'REJECT', 'msg' => 'ParseError: ' . json_last_error(), 'orderID' => null, 'raw' => $tmp, 'ts' => t());

**/

//для ускорения обработки 
$o2uid = Array();
$сentrifugo = initCentrifugo();

$mainTimer = 0.01;
$doSilent = false; //не оповещать центрифугу

if (count($argv) > 1){
	$_argv = array_flip($argv);
	
	if (array_key_exists('-clean-queue', $_argv)){
		$ssdb->qclear('INDEXTRDADE_EXECUTION_REPORTS');
		
		echo "Queue was cleaned\n";
	}
	
	
	if (array_key_exists('-slow-timer', $_argv)){
		$mainTimer = 1;
		
		echo "Main timer change to slow (debug mode)\n";
	}
	
	if (array_key_exists('-silent', $_argv)){
		$doSilent = true;
		
		echo "Do not call Centrifugo - OK\n";
	}
	
	if (array_key_exists('-help', $_argv)){
		echo "Option:\n";
		
		echo "-clean-queue \n";
		echo "-slow-timer \n";
		echo "-silent \n";
		echo "-help \n";
		
		die('');
	}
	
}



//Главный цикл обработки событий 
$loop->addPeriodicTimer(0.20, function() use (&$db, &$redis, &$ssdb, &$log, &$o2uid, &$сentrifugo){
	$_res = $ssdb->qpop_front('INDEXTRDADE_EXECUTION_REPORTS', 1);
	
	if ($_res == null) return;
	
	if (!empty($_res)){
		$res = json_decode($_res, true, 16);
		
		if (empty($res) || json_last_error() != 0){
			$log->error( 'JSON parse error: ' . json_last_error_msg(), Array($res));
			return;
		}
		
		if (empty($res['orderID'])){
			$log->error( 'Invalud Execution report, missing orderID' );
			return;
		}
		
		if (empty($res['ts'])){
			$log->error( 'Invalud Execution report, missing timestamp' );
			return;
		}
		
		$_raw = '';
		
		if (array_key_exists('raw', $res) && !empty($res['raw'])){
			$_raw = json_encode( $res['raw'] );
		}
		
		//var_dump( $ssdb->hgetall('INDEXTRDADE_ORDERS_BY_USER') );
		
		
		//а теперь глянем, чей ордер то? 
		if (!array_key_exists($res['orderID'], $o2uid)){
			$_uid = $order['uid'];
			
			$o2uid[ $res['orderID'] ] = $_uid;
			
			/**
			$_uid = $ssdb->hget('INDEXTRDADE_ORDERS_BY_USER', $res['orderID']);
			
			if (!empty($_uid)){
				$o2uid[ $res['orderID'] ] = $_uid;
			}
			else {
				$log->error( 'Invalud Uid by INDEXTRDADE_ORDERS_BY_USER. OrderID: ' . $res['orderID']);
				return;
			}
			**/
		}
		else
			$_uid = $o2uid[ $res['orderID'] ];
		
		
		
		
		$db->beginTransaction();
			//$log->info('Starting write execution report...');
			try {
			
			$sql = 'INSERT INTO exchange_orders_execution_reports_tbl SET order_id = ?, report_ts = ?, report_type = ?, report_msg = ?, report_data = ? ';
							
			$db->query( $sql, Array($res['orderID'], $res['ts'], $res['type'], $res['msg'], $_raw) );
			
			//а реакции на разные репорты? 
			switch($res['type']){
				
				//ордер не прошел проверки 
				case 'REJECT' : {
					$db->query('UPDATE exchange_real_orders_tbl SET order_status = "reject", order_last_status_changed_at = UNIX_TIMESTAMP(), order_cancel_at = UNIX_TIMESTAMP() WHERE order_uuid = "'.$res['orderID'].'" LIMIT 1');
				}
				//отмена ордера 
				case 'CANCEL' : {
					//он отменяеться уже в OrdersMaster
				}
				//ордер добавлен в бук 
				case 'SAVED' : {
					
				}
				//ордер добавлен в бук 
				case 'PLACED' : {
					
				}
				//этапы проверки
				case 'CHECK' : {
					
				}
				//базово ордер принят и зарегистрирован
				case 'PROPOSED' : {
					
				}
				//другие типы репортов
				default: {
					
				}				
			} 
		
			$db->commit();

		
			}catch(Exception $e){
				$log->error( $e );
				$log->info( $sql );
				$log->info( $_res );
				
				
				$db->rollBack();
				
				return false;
			}
		
		
		
		if ($doSilent != true && !empty($_uid)){
			//отправит в Centrifugu пока что так напрямую
			$_uid = 42;		
			$сentrifugo->publish('public#idxt' . $_uid, Array( 'type' => 'report', 'message' => $res ));
		}
		
		$log->info( $res['msg'] . ' :: ' . $res['orderID'] . ' :: ' . $res['type'] . ' :: ' . date('r', $res['ts']/10000));
	} 	
});


$loop->addPeriodicTimer(5, function() use (&$db, &$log, &$ssdb){
	
	$res = $ssdb->qsize('INDEXTRDADE_EXECUTION_REPORTS');
	
	$log->info("In execution reports queue: " . $res );
	
	
	if (!$db->isConnected()){
		$log->warning("DB connection not alive. Try to reconnect");
		
		$db->getConnection();
		
		$dbTs = intval( $db->fetchOne('SELECT UNIX_TIMESTAMP() ') );
		$appTs = intval( time() );
		
		if ($appTs != $dbTs){
			$log->warning("DB local time and Application time has difference - " . ($appTs - $dbTs));
		}
	}

});




$log->info('Main loop are starting...');
$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
