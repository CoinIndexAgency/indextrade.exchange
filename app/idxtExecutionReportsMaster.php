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



//Главный цикл обработки событий 
$loop->addPeriodicTimer(0.1, function() use (&$db, &$redis, &$ssdb, &$log){
	$res = $ssdb->qpop_front('INDEXTRDADE_EXECUTION_REPORTS', 1);
	
	if (!empty($res)){
		$res = json_decode($res, true, 16);
		
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
		
		$db->beginTransaction();
			//$log->info('Starting write execution report...');
			try {
			
			$sql = 'INSERT INTO exchange_orders_execution_reports_tbl SET 
							order_id = "'.$res['orderID'].'", 
							report_ts = '.$res['ts'].', 
							report_type = "'.$res['type'].'", 
							report_msg = "'.$res['msg'].'", 
							report_data = '.$db->quote($_raw).' ';
							
			//if ($res['type'] == 'FILL' || $res['type'] == 'CLOSE'){
			//	echo "\n\n" . $sql . "\n\n";
			//}
							
							
			$db->query( $sql );
			
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
				//другие типы репортов
				default: {
					
				}				
			} 
			
			}catch(Exception $e){
				$log->error( $e );
				$log->info( $sql );
				
				$db->rollBack();
				
				return false;
			}
		
		$db->commit();
		
		
		$log->info( $res['msg'] . ' :: ' . $res['orderID'] . ' :: ' . $res['type'] . ' :: ' . date('r', $res['ts']/10000));
	} 	
});


$log->info('Main loop are starting...');
$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
