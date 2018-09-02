<?php
namespace IndexTrade;
 
error_reporting(E_ALL);
date_default_timezone_set('UTC');
clearstatcache(true);

include_once( __DIR__ . '/__bootstrap.php');

use React\ChildProcess\Process;

echo "\n\n";
echo "     ---| IndexTrade.Exchange Platform via CoinIndex Team with LOVE |--- \n\n";
echo "Starting at: " . date('r') . "\n";
echo "Starting Main Master...\n\n";

$log = initLog('idxtMaster');
$сentrifugo = initCentrifugo();

/*** 
		Порядок запуска 
		- Allocator
		- Reports
		- Trading
		- Order

***/

$idxtComponents = Array(
	'allocator' => Array('exec' => 'idxtAllocatorMaster.php', 'params' => ''),
	'reporter' 	=> Array('exec' => 'idxtExecutionReportsMaster.php', 'params' => ''),
	//'trading'	=> Array('exec' => 'idxtTradeMaster.php', 'params' => ''),
	'order'		=> Array('exec' => 'idxtOrdersMaster.php', 'params' => '')
);

//проверяем, есть ли управляющие команды 
$loop->addPeriodicTimer(1, function() use (&$redis, &$log){
	$comm = $redis->lpop('INDEXTRADE_MAIN_COMMANDER_QUEUE');
	
	if (!empty($comm)){
		$log->info('New command has arrived: ' + $comm);
		
		
	}
});


//теперь запускаем все процессы в строгом порядке 
$log->info('Start running all IDXT processes...');

foreach($idxtComponents as $name => $opts){
	$log->info('Starting: ' . $name . ' ('.$opts['exec'].')');	
	
	$process = new \React\ChildProcess\Process('php -f /opt/www/indextrade.exchange/app/' . $opts['exec']);
	$process->start($loop);
	
	$process->stdout->on('data', function ($chunk) {
		//echo $name . ' :: ' . $chunk;
	});

	$process->stdout->on('end', function () {
		//echo $name . ' :: ' . 'ended';
	});

	$process->stdout->on('error', function (Exception $e) {
		echo $name . ' :: ' . 'error: ' . $e->getMessage();
	});

	$process->stdout->on('close', function () {
		echo $name . ' :: ' . 'closed';
	});
	
	$process->on('exit', function($exitCode, $termSignal) {
		echo $name . ' :: Process exited with code ' . $exitCode . PHP_EOL;
	});
	
	$idxtComponents[ $name ]['process'] = $process;
	
	//
	
	//ждем запуска? правильнее конечно по старту процесса
	sleep(1);
}


$loop->addPeriodicTimer(10, function() use (&$redis, &$log, &$idxtComponents){
	echo("\n");
	
	$log->info('Cheking status...');
	
	foreach($idxtComponents as $n => $x){
		if (is_object($x['process']) && $x['process']->isRunning()){
			$log->info( $n . ' is already run OK');
		}
		else
			$log->error($n . ' Not running! ERROR!');
	}
	
	echo("\n");
});




$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
