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
	'allocator' => Array('exec' => 'idxtAllocatorMaster.php', 'params' => '', 'started' => false),
	'reporter' 	=> Array('exec' => 'idxtExecutionReportsMaster.php', 'params' => '', 'started' => false),
	'trading'	=> Array('exec' => 'idxtTradeMaster.php', 'params' => ' > /var/log/idxttrademaster.log 2>&1', 'started' => false), 
	'order'		=> Array('exec' => 'idxtOrdersMaster.php', 'params' => '', 'started' => false)
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
	//проверить процесс 
	$__outp = Array();	
	exec('exec ps ax|grep "'.$opts['exec'].'"', $__outp);
	
	if (count($__outp) > 2){
		$log->error('Another process for component: '.$name.' is RUN');
		//var_dump( $__outp );
		
		if (count($__outp) == 3){
			$px = explode(' ', $__outp[0]);
			
			$pid = intval($px[0]);
			
			if (!empty($pid)){
				$log->error('Process pid: ' . $pid . ', try to terminate by kill -9');
				exec('kill -9 ' . $pid);
				
				sleep(3);
			}
		}
		else {
			$idxtComponents[ $name ]['started'] = false;
			
			$log->error('Cant terminate process automaticly.');
		
			continue;
		}
	}
	
	//exit();
	
	$log->info('Starting: ' . $name . ' ('.$opts['exec'].')');	
	
	$process = new \React\ChildProcess\Process('exec php -f /opt/www/indextrade.exchange/app/' . $opts['exec'] . $opts['params']);
	$process->start($loop);
	
	/**
	$process->stdout->on('data', function ($chunk) use ($name, $opts, &$process) {
		//echo $name . ' :: ' . $chunk;
	});

	$process->stdout->on('end', function () use ($name, $opts, &$process) {
		//echo $name . ' :: ' . 'ended';
	});

	$process->stdout->on('error', function (Exception $e) use ($name, $opts, &$process) {
		echo $name . ' :: ' . 'error: ' . $e->getMessage();
	});

	$process->stdout->on('close', function () use ($name, $opts, &$process) {
		echo $name . ' :: ' . 'closed';
	});
	
	$process->on('exit', function($exitCode, $termSignal) use ($name, $opts, &$process) {
		echo $name . ' :: Process exited with code ' . $exitCode . PHP_EOL;
	});
	**/
	$idxtComponents[ $name ]['started'] = true;
	$idxtComponents[ $name ]['process'] = $process;
	
	//получаем инфу о файле 
	clearstatcache();
	$idxtComponents[ $name ]['lastChanged'] = filemtime('/opt/www/indextrade.exchange/app/' . $opts['exec']);
	
	//
	
	//ждем запуска? правильнее конечно по старту процесса
	sleep(1);
}


$zTimer = $loop->addPeriodicTimer(10, function() use (&$redis, &$log, &$idxtComponents, &$loop){
	$log->info('Cheсking status...');
	
	clearstatcache();
	
	foreach($idxtComponents as $n => $x){
		//проверим, не было ли изменений
		$lx = filemtime('/opt/www/indextrade.exchange/app/' . $x['exec']);
		//$log->info('Last modify: ' . date ("F d Y H:i:s.", $lx) . ', base: ' . date ("F d Y H:i:s.", $x['lastChanged']));
		
		if ($lx != $x['lastChanged']){
			$log->info('Component are changed (at '.date ("F d Y H:i:s.", $lx).'). Try to terminate old and restart');
			
			if (is_object($x['process']) && $x['process']->isRunning()){
				$x['process']->terminate(SIGINT);
				
				//sleep(1);
			}
		}
		
		
		if (is_object($x['process']) && $x['process']->isRunning()){
			$log->info( $n . ' is already run OK');
		}
		else 
		if ($x['started'] == true){
			$log->error($n . ' Not running! ERROR!');
			$log->error($n . ' Try to restart after 5 sec waiting!');
			
			$loop->addTimer(2, function() use (&$log, &$idxtComponents, $n, &$loop) {
				
				if (!empty($idxtComponents[ $n ])){
					$p = $idxtComponents[ $n ];
					
					if (!$p['process']->isRunning()){
						$log->info('Try to restart component: ' . $n);
						
						$process = new \React\ChildProcess\Process('exec php -f /opt/www/indextrade.exchange/app/' . $p['exec'] . $p['params']);
						$process->start($loop);
						
						/*
						$process->on('exit', function($exitCode, $termSignal) use ($n, $p, &$process) {
							echo $n . ' :: Process exited with code ' . $exitCode . PHP_EOL;
						});
						*/
						
						$idxtComponents[ $n ]['started'] = true;
						$idxtComponents[ $n ]['process'] = $process;
						
						clearstatcache();
						$idxtComponents[ $n ]['lastChanged'] = filemtime('/opt/www/indextrade.exchange/app/' . $p['exec']);
					}
				}			
			});			
		}
	}
	
	echo("\n");
});

$loop->addSignal(SIGINT, $func = function (int $signal) use (&$idxtComponents, $loop, &$log, $zTimer, &$func) {
    $loop->removeSignal(SIGINT, $func);
	
	if (!empty($zTimer))
		$loop->cancelTimer( $zTimer );
	
	//возможная гонка, если таймер перезапуска процесса будет в это время запущен
	
	$log->warn('Running aborted by SIGINT');
	
	foreach($idxtComponents as $n => $x){
		if (is_object($x['process']) && $x['process']->isRunning()){
			
			$log->info('Service: ' . $n . ' - running');
		}
		else
			$log->info('Service: ' . $n . ' - NOT run');
	}
	
	//sleep(1);	
	
	foreach($idxtComponents as $n => $x){
		if (is_object($x['process']) && $x['process']->isRunning()){
			
			$log->info('Running service: ' . $n . '. Try to terminate it...');
			
			$x['process']->terminate(SIGINT);		
			
			//sleep(1);
		}
		else
			$log->warn('Service: ' . $n . ' not runnable');
	}
	
	$loop->stop();	
});


$loop->addPeriodicTimer(3, function() use (&$redis, &$log, &$idxtComponents, &$сentrifugo, &$loop){
	$platformRun = true; 
	
	foreach($idxtComponents as $z){
		if ($z['started'] == false){
			$platformRun = false; 
			break;
		}
	}
	
	if ($platformRun === false){
		//что-то не так, оповещаем клиентов 
		$сentrifugo->publish('public', Array( 'type' => 'system', 'message' => 'Warning! Trading system temporary unavailable'));
	}
});



$loop->run();

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
