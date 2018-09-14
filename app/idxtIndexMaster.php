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
echo "Starting Index Master...\n\n";

$log = initLog('idxtIndex');
$сentrifugo = initCentrifugo();

/**
	200 кредитов в сутки, 1 запрос у нас = 1 кредит 
	
	200 / 24 = 8 = 8 запросов в час максимум. 
	
	запрашиваем раз в 10 минут
**/

$httpClient = new \GuzzleHttp\Client([
	'base_uri' => 'https://pro-api.coinmarketcap.com',
	'defaults' => [
		'exceptions' => false
	],
	'allow_redirects' => true, 'connect_timeout' => 3, 'decode_content' => true, 'force_ip_resolve' => 'v4', 'http_errors' => true, 'read_timeout' => 3, 'synchronous' => true, 'timeout' => 3
]);

$CMC_KEY = 'c3ce21bb-20b0-41f9-85d0-762a38db9d2b';

$log->info('Fetching assets symbols...');

$sql = 'SELECT symbol, name FROM index_assets_info_tbl ';
$res = $db->fetchAll( $sql );

$assets = Array();

foreach($res as $x){
	$assets[ $x['symbol'] ] = $x['name'];
}

$log->info('Found: ' . count($assets) . ' assets fo process');

$log->info('Starting fetch last prices...');

$symbols = array_keys( $assets );

if (empty($symbols))
	die('ERROR: Empty assets list fo fetch');

$url = '/v1/cryptocurrency/quotes/latest?&CMC_PRO_API_KEY=' . $CMC_KEY . '&convert=USD&symbol=' . implode(',', $symbols);

$log->info('URL: ' . $url );

$httpres = $httpClient->get( $url );
			
if ($httpres->getStatusCode() == 200){
				
	$jres = $httpres->getBody()->getContents();
				
	if (is_string($jres)){
		$jres = json_decode($jres, true);
		
		if (!empty($jres['data'])){
			$log->info('Prices fetched OK. Start to process them...');
			
			$log->info('Process: curculation and mcap stat...');
			
			$db->beginTransaction();
			
			foreach($jres['data'] as $z){
				$_max_supply = 0;
				$_circulating_supply = 0;
				$_market_cap = 0;
				$_total_supply = 0;
				
				if (!empty($z['max_supply']) && is_numeric($z['max_supply'])){
					$_max_supply = floatval( $z['max_supply'] );
				}
				
				if (!empty($z['circulating_supply']) && is_numeric($z['circulating_supply'])){
					$_circulating_supply = floatval( $z['circulating_supply'] );
				}
				
				if (!empty($z['total_supply']) && is_numeric($z['total_supply'])){
					$_total_supply = floatval( $z['total_supply'] );
				}
				
				if (!empty($z['quote']['USD']['market_cap']) && is_numeric($z['quote']['USD']['market_cap'])){
					$_market_cap = floatval( $z['quote']['USD']['market_cap'] );
				}
				
				if (!empty($_market_cap) && !empty($_circulating_supply)){
					
					$db->query('INSERT INTO index_assets_market_stats SET 
						symbol = ?, 
						circulating_supply = ?, 
						total_supply = ?,
						max_supply = ?,
						market_cap_usd = ?,
						updated_at = UNIX_TIMESTAMP(),
						updated_date = NOW()', 
					Array($z['symbol'], $_circulating_supply, $_total_supply, $_max_supply, $_market_cap));
				}			
			}
			
			$db->commit();
			
			
			$log->info('Process: latest prices...');
			
			$db->beginTransaction();
			
			foreach($jres['data'] as $z){
				$_price_usd = 0;
				$_volume_24h_usd = 0;
				$_percent_change_1h = 0;
				$_percent_change_24h = 0;
				$_percent_change_7d = 0;
				
				if (!empty($z['quote']['USD']['price']) && is_numeric($z['quote']['USD']['price'])){
					$_price_usd = floatval( $z['quote']['USD']['price'] );
				}
				
				if (!empty($z['quote']['USD']['volume_24h']) && is_numeric($z['quote']['USD']['volume_24h'])){
					$_volume_24h = floatval( $z['quote']['USD']['volume_24h'] );
				}
				
				if (!empty($z['quote']['USD']['percent_change_1h']) && is_numeric($z['quote']['USD']['percent_change_1h'])){
					$_percent_change_1h = floatval( $z['quote']['USD']['percent_change_1h'] );
				}
				
				if (!empty($z['quote']['USD']['percent_change_24h']) && is_numeric($z['quote']['USD']['percent_change_24h'])){
					$_percent_change_24h = floatval( $z['quote']['USD']['percent_change_24h'] );
				}
				
				if (!empty($z['quote']['USD']['percent_change_7d']) && is_numeric($z['quote']['USD']['percent_change_7d'])){
					$_percent_change_7d = floatval( $z['quote']['USD']['percent_change_7d'] );
				}
				
				
				if (!empty($_price_usd) && !empty($_volume_24h)){
					
					$db->query('INSERT INTO index_assets_quotes_tbl SET 
						symbol = ?, 
						price_usd = ?, 
						volume_24h_usd = ?,
						percent_change_1h = ?,
						percent_change_24h = ?,
						percent_change_7d = ?, 
						updated_at = UNIX_TIMESTAMP(),
						updated_date = NOW()', 
					Array($z['symbol'], $_price_usd, $_volume_24h, $_percent_change_1h, $_percent_change_24h, $_percent_change_7d));
				}			
			}
			
			$db->commit();
			
			$log->info('Finish fetch and process base assets quotes');
			
			//$log->info('Starting calculating indices...');
			
			
			
			
		}
		else
			$log->error('No data at DATA block of json responce');
	}
	else
		$log->error('HTTP Responce (content) not a valid String');
}
else
	$log->error('HTTP Error code: ' . $httpres->getStatusCode());

echo "\n\n";
die( "Finish him! " . date('r') . "\n" );
