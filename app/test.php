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

$indices = Array();

$totalMCap = 205749061324; //08.09.2018 10:30 UTC

/*
$indices['GeometricalAvg_Top10'] = Array('BTC', 'ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA', 'DASH', 'TRX', 'NEO', 'ETC', 'BNB', 'XEM', 'VET', 'XTZ', 'ZEC', 'DOGE', 'OMG', 'LSK', 'BCN', 'ONT', 'QTUM');





*/
/**
$indices['GeometricalAvg_Top10'] = Array('BTC', 'ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR');
$indices['GeometricalAvg_Top10wBTC'] = Array('ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA');

$indices['CapWeight_Top10'] = Array('BTC', 'ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR');
$indices['CapWeight_Top10wBTC'] = Array('ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA');
**/
$indices['PriceWeight_Top10'] = Array('BTC', 'ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR');
$indices['PriceWeight_Top10wBTC'] = Array('XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA', 'DASH');


$indices['PriceWeight_Top25'] = Array('BTC', 'ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA', 'DASH', 'TRX', 'NEO', 'ETC', 'BNB', 'XEM', 'VET', 'XTZ', 'ZEC', 'DOGE', 'OMG', 'LSK', 'BCN', 'ONT');
$indices['PriceWeight_Top25wBTC'] = Array('XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA', 'DASH', 'TRX', 'NEO', 'ETC', 'BNB', 'XEM', 'VET', 'XTZ', 'ZEC', 'DOGE', 'OMG', 'LSK', 'BCN', 'ONT', 'QTUM', 'ETH');

//=======
/**
$indices['GeometricalAvg_Top25'] = Array('BTC', 'ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA', 'DASH', 'TRX', 'NEO', 'ETC', 'BNB', 'XEM', 'VET', 'XTZ', 'ZEC', 'DOGE', 'OMG', 'LSK', 'BCN', 'ONT');
$indices['GeometricalAvg_Top25wBTC'] = Array('ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA', 'DASH', 'TRX', 'NEO', 'ETC', 'BNB', 'XEM', 'VET', 'XTZ', 'ZEC', 'DOGE', 'OMG', 'LSK', 'BCN', 'ONT', 'QTUM');

$indices['CapWeight_Top25'] = Array('BTC', 'ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA', 'DASH', 'TRX', 'NEO', 'ETC', 'BNB', 'XEM', 'VET', 'XTZ', 'ZEC', 'DOGE', 'OMG', 'LSK', 'BCN', 'ONT');
$indices['CapWeight_Top25wBTC'] = Array('ETH', 'XRP', 'BCH', 'EOS', 'XLM', 'LTC', 'USDT', 'ADA', 'XMR', 'MIOTA', 'DASH', 'TRX', 'NEO', 'ETC', 'BNB', 'XEM', 'VET', 'XTZ', 'ZEC', 'DOGE', 'OMG', 'LSK', 'BCN', 'ONT', 'QTUM');

$indices['GeometricalAvg_Privacy'] = Array('DASH', 'XMR', 'ZEC', 'BBR', 'XDN', 'XVG', 'ZCL', 'BTCP', 'PIVX', 'ZEN', 'NAV', 'AEON', 'BLK');
$indices['CapWeight_Privacy'] = Array('DASH', 'XMR', 'ZEC', 'BBR', 'XDN', 'XVG', 'ZCL', 'BTCP', 'PIVX', 'ZEN', 'NAV', 'AEON', 'BLK');

$indices['GeometricalAvg_Trading'] = Array('USDT', 'ZRX', 'NANO', 'KCS', 'HT', 'TUSD', 'BNT', 'ICN', 'BLOCK', 'SAFEX', 'C20', 'BFT', 'TEN', 'KNC','DTR');
$indices['CapWeight_Trading'] = Array('USDT', 'ZRX', 'NANO', 'KCS', 'HT', 'TUSD', 'BNT', 'ICN', 'BLOCK', 'SAFEX', 'C20', 'BFT', 'TEN', 'KNC','DTR');

**/

$indices['PriceWeight_Privacy'] = Array('DASH', 'XMR', 'ZEC', 'BBR', 'XDN', 'XVG', 'ZCL', 'BTCP', 'PIVX', 'ZEN', 'NAV', 'AEON', 'BLK');
$indices['PriceWeight_Trading'] = Array('USDT', 'BNB', 'ZRX', 'NANO', 'KCS', 'HT', 'TUSD', 'BNT', 'ICN', 'BLOCK', 'SAFEX', 'C20', 'BFT', 'TEN', 'KNC','DTR');

//начальная точка 
$basePoint = 1536336002;
$nowPoint = time();
$step = 15 * 60; //расчет каждые 15 мин


foreach($indices as $n => $aa){
	$indexPoint = $basePoint;
	$d = 1;
	
	if ($n == 'PriceWeight_Top10wBTC' || $n == 'PriceWeight_Top10' || $n == 'PriceWeight_Privacy' || $n == 'PriceWeight_Top25' || $n == 'PriceWeight_Top25wBTC' || $n == 'PriceWeight_Trading'){
		
		$dt = $db->fetchOne('SELECT SUM(price_usd) FROM index_assets_quotes_tbl WHERE updated_at = 1536336002 AND symbol IN ("'.implode('","', $aa) .'") ');
		
		echo "Total summ: " . $dt . "\n";
		
		$d = round( ($dt / 100 /*count( $aa )*/), 9);
		
		echo "Divisor: " . $d . "";
	}
	else
	if ($n == 'GeometricalAvg_Top10' || $n == 'GeometricalAvg_Top10wBTC' || $n == 'GeometricalAvg_Top25' || $n == 'GeometricalAvg_Top25wBTC' || $n == 'GeometricalAvg_Privacy' || $n == 'GeometricalAvg_Trading'){
		
	}
	else
		continue;
	
	echo "\n\n\n";
	echo $n . "\n";
	

	while( $indexPoint < $nowPoint ){
		$_p = Array();
		$indx = 0;
		
		foreach($aa as $a){
			$px = $db->fetchOne('SELECT ROUND(price_usd, 2) FROM index_assets_quotes_tbl WHERE symbol = "'.$a.'" AND updated_at < '.$indexPoint.' ORDER BY updated_at DESC LIMIT 1');
			
			if (!empty($px))
				$_p[] = $px;
		}
		
		if (!empty($_p)){
		
		
			if ($n == 'PriceWeight_Top10wBTC' || $n == 'PriceWeight_Top10' || $n == 'PriceWeight_Privacy' || $n == 'PriceWeight_Top25' || $n == 'PriceWeight_Top25wBTC' || $n == 'PriceWeight_Trading'){
				$z = array_sum( $_p );
				
				$indx = round( $z / $d, 2 );
			}
			else
			if ($n == 'GeometricalAvg_Top10' || $n == 'GeometricalAvg_Top10wBTC' || $n == 'GeometricalAvg_Top25' || $n == 'GeometricalAvg_Top25wBTC' || $n == 'GeometricalAvg_Privacy' || $n == 'GeometricalAvg_Trading'){
				
				$z = 1;
				
				foreach($_p as $pz){
					$z = $z * $pz;
				}
				
				$indx = round(  pow( $z, round(1/count($_p), 6)), 2 );		

			}
			
			$btcPrice = $db->fetchOne('SELECT ROUND(price_usd, 2) FROM index_assets_quotes_tbl WHERE symbol = "BTC" AND updated_at < '.$indexPoint.' ORDER BY updated_at DESC LIMIT 1');
			
			$ethPrice = $db->fetchOne('SELECT ROUND(price_usd, 2) FROM index_assets_quotes_tbl WHERE symbol = "ETH" AND updated_at < '.$indexPoint.' ORDER BY updated_at DESC LIMIT 1');
			
			
			echo date('[d.m.Y] h:i', $indexPoint) . ' index value ' . $indx . ', BTC: ' . $btcPrice . ', ETH: ' . $ethPrice . "\n";	
			
		}
		
		$indexPoint = $indexPoint + $step;
	}

}

//foreach($indices['GeometricalAvg_Top10']




















/***
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
***/

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
