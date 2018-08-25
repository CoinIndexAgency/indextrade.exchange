<?php 
	date_default_timezone_set('UTC');
	clearstatcache(true);
	
	include_once('Predis.php');
	
	$redis = new Predis\Client([
		'scheme' => 'tcp',
		'host'   => '127.0.0.1',
		'port'   => 6379,
	]);
	
	//получим с коинмаркеткапа текущие курсы 
	$xcurUSD = Array();
	$xcurETH = Array();
	$xcurXRP = Array();
	$xcurIDXT = Array();
	$xcurNANO = Array();
	
	$t = time();
	
	$tmp = $redis->get('INDEXTRADE.EXCHAGE.COINMARKETCAP.ETH');
	
	if (empty($tmp)){
		$tmp = json_decode(file_get_contents('https://api.coinmarketcap.com/v2/ticker/?limit=100&convert=ETH'), true);
	}
	
	if (($t - $tmp['metadata']['timestamp']) > 420){
		$tmp = json_decode(file_get_contents('https://api.coinmarketcap.com/v2/ticker/?limit=100&convert=ETH'), true);
		
		$tmp['metadata']['timestamp'] = $t;		
		$redis->set('INDEXTRADE.EXCHAGE.COINMARKETCAP.ETH', json_encode($tmp));
	}
	
	//$tmp = json_decode(file_get_contents('https://api.coinmarketcap.com/v2/ticker/?limit=100&convert=ETH'), true);
	
	foreach($tmp['data'] as $z){
		$xx = $z['quotes'];
		
		$z['quotes'] = $xx['USD'];
		$xcurUSD[ $z['symbol'] ] = $z;
		
		$z['quotes'] = $xx['ETH'];
		$xcurETH[ $z['symbol'] ] = $z;
	}
	
	
	
	$tmp = $redis->get('INDEXTRADE.EXCHAGE.COINMARKETCAP.XRP');
	
	if (empty($tmp)){
		$tmp = json_decode(file_get_contents('https://api.coinmarketcap.com/v2/ticker/?limit=100&convert=XRP'), true);
	}
	
	if (($t - $tmp['metadata']['timestamp']) > 420){
		$tmp = json_decode(file_get_contents('https://api.coinmarketcap.com/v2/ticker/?limit=100&convert=XRP'), true);
		$tmp['metadata']['timestamp'] = $t;
		
		$redis->set('INDEXTRADE.EXCHAGE.COINMARKETCAP.ETH', json_encode($tmp));
	}
	
	//$tmp = json_decode(file_get_contents('https://api.coinmarketcap.com/v2/ticker/?limit=100&convert=XRP'), true);
	
	foreach($tmp['data'] as $z){
		$xx = $z['quotes'];
		
		$z['quotes'] = $xx['XRP'];
		$xcurXRP[ $z['symbol'] ] = $z;
		
		$xcurNANO[ $z['symbol'] ] = $z;
		$xcurIDXT[ $z['symbol'] ] = $z;
	}

//Демо данные для загрузки в интерфейс
$__symbols = Array(
	'USDT.FUT/IDXT' => Array(
		'name' => "Tether.FUT/IDXT",
		'type' => 'FUT',
		'index' => "USDT/IDXT"
		),
	'EOS.FUT/TUSD' => Array(
		'name' => "EOS.FUT/TUSD",
		'type' => 'FUT',
		'index' => "EOS/USD"
		),
	'ETH.FUT/TUSD' => Array(
		'name' => "Ethereum.FUT/TUSD",
		'type' => 'FUT',
		'index' => "ETH/USD"
		),
	'BCH.FUT/TUSD' => Array(
		'name' => "BitcoinCash.FUT/TUSD",
		'type' => 'FUT',
		'index' => "BCH/USD"
		),
	'LTC.FUT/TUSD' => Array(
		'name' => "Litecoin.FUT/TUSD",
		'type' => 'FUT',
		'index' => "LTC/USD"),
	'TRX.FUT/ETH' => Array(
		'name' => "Tron.FUT/ETH",
		'type' => 'FUT',
		'index' => "TRX/ETH"),
	'BNC.FUT/ETH' => Array(
		'name' => "BinanceCoin.FUT/ETH",
		'type' => 'FUT',
		'index' => "BNC/ETH"),
	'TRUE.FUT/ETH' => Array(
		'name' => "TrueChain.FUT/ETH",
		'type' => 'FUT',
		'index' => "TRUE/ETH"),
	'BIX.FUT/TUSD' => Array(
		'name' => "BiboxToken.FUT/TUSD",
		'type' => 'FUT',
		'index' => "BIX/USD"),
	'ZRX.FUT/NANO' => Array(
		'name' => "0x.FUT/NANO",
		'type' => 'FUT',
		'index' => "ZRX/NANO"),
	'HT.FUT/NANO' => Array(
		'name' => "HuobiToken.FUT/NANO",
		'type' => 'FUT',
		'index' => "HT/NANO"),
	'DMT.FUT/NANO' => Array(
		'name' => "DMarket.FUT/NANO",
		'type' => 'FUT',
		'index' => "DMT/NANO"),
	'REP.FUT/NANO' => Array(
		'name' => "Augur.FUT/NANO",
		'type' => 'FUT',
		'index' => "REP/NANO"),
	'PAY.IDX/IDXT' => Array(
		'name' => "Payments.IDX/IDXT",
		'type' => 'FUT',
		'index' => "PAY.IDX/IDXT"),
	'ADS.IDX/IDXT' => Array(
		'name' => "Ads.IDX/IDXT",
		'type' => 'FUT',
		'index' => "ADS.IDX/IDXT"),
	'CASINO.IDX/IDXT' => Array(
		'name' => "Casino.IDX/IDXT",
		'type' => 'FUT',
		'index' => "CASINO.IDX/IDXT"),
	'ANON.IDX/IDXT' => Array(
		'name' => "Anonim.IDX/IDXT",
		'type' => 'FUT',
		'index' => "ANON.IDX/IDXT"),
	'GAME.IDX/IDXT' => Array(
		'name' => "Gaming.IDX/IDXT",
		'type' => 'FUT',
		'index' => "GAME.IDX/IDXT"),
	'SOC.IDX/IDXT' => Array(
		'name' => "Social.IDX/IDXT",
		'type' => 'FUT',
		'index' => "SOC.IDX/IDXT"),
	'SRV.IDX/IDXT' => Array(
		'name' => "Service.IDX/IDXT",
		'type' => 'FUT',
		'index' => "SRV.IDX/IDXT"),
	'CANNAB.IDX/IDXT' => Array(
		'name' => "Cannabis.IDX/IDXT",
		'type' => 'FUT',
		'index' => "CANNAB.IDX/IDXT"),
	'MED.IDX/IDXT' => Array(
		'name' => "Medical.IDX/IDXT",
		'type' => 'FUT',
		'index' => "MED.IDX/IDXT"),
	'EXC.IDX/IDXT' => Array(
		'name' => "Exchanges.IDX/IDXT",
		'type' => 'FUT',
		'index' => "EXC.IDX/IDXT"),
	'DEX.IDX/IDXT' => Array(
		'name' => "DEX.IDX/IDXT",
		'type' => 'FUT',
		'index' => "DEX.IDX/IDXT"),
	'FTECH.IDX/IDXT' => Array(
		'name' => "FinTech.IDX/IDXT",
		'type' => 'FUT',
		'index' => "FTECH.IDX/IDXT"),
	'MOB.IDX/IDXT' => Array(
		'name' => "Mobile.IDX/IDXT",
		'type' => 'FUT',
		'index' => "MOB.IDX/IDXT"),
	'FUNDS.IDX/IDXT' => Array(
		'name' => "TokenizedFunds.IDX/IDXT",
		'type' => 'FUT',
		'index' => "FUNDS.IDX/IDXT"),
	'COINIDX.IDX/IDXT' => Array(
		'name' => "COINIDX.IDX/IDXT",
		'type' => 'FUT',
		'index' => "COINIDX.IDX/IDXT"),
	'TCAP10.IDX/IDXT' => Array(
		'name' => "TCAP10.IDX/IDXT",
		'type' => 'FUT',
		'index' => "TCAP10.IDX/IDXT"),
	'TCAP25.IDX/IDXT' => Array(
		'name' => "TCAP25.IDX/IDXT",
		'type' => 'FUT',
		'index' => "TCAP25.IDX/IDXT"),
	'TCAP50.IDX/IDXT' => Array(
		'name' => "TCAP50.IDX/IDXT",
		'type' => 'FUT',
		'index' => "TCAP50.IDX/IDXT"),
	'TCAP100.IDX/IDXT' => Array(
		'name' => "TCAP100.IDX/IDXT",
		'type' => 'FUT',
		'index' => "TCAP100.IDX/IDXT"),
	'TCAP500.IDX/IDXT' => Array(
		'name' => "TCAP500.IDX/IDXT",
		'type' => 'FUT',
		'index' => "TCAP500.IDX/IDXT"),
	'TCAP1000.IDX/IDXT' => Array(
		'name' => "TCAP1000.IDX/IDXT",
		'type' => 'FUT',
		'index' => "TCAP1000.IDX/IDXT"),
	'MCAP10B.IDX/IDXT' => Array(
		'name' => "MCAP10B.IDX/IDXT",
		'type' => 'FUT',
		'index' => "MCAP10B.IDX/IDXT"),
	'MCAP1B.IDX/IDXT' => Array(
		'name' => "MCAP1B.IDX/IDXT",
		'type' => 'FUT',
		'index' => "MCAP1B.IDX/IDXT"),
	'MCAP500M.IDX/IDXT' => Array(
		'name' => "MCAP500M.IDX/IDXT",
		'type' => 'FUT',
		'index' => "MCAP500M.IDX/IDXT"),
	'MCAP100M.IDX/IDXT' => Array(
		'name' => "MCAP100M.IDX/IDXT",
		'type' => 'FUT',
		'index' => "MCAP100M.IDX/IDXT"),
	'LowCap.IDX/IDXT' => Array(
		'name' => "LowCap.IDX/IDXT",
		'type' => 'FUT',
		'index' => "LowCap.IDX/IDXT"),
	'ETH.IDX/IDXT' => Array(
		'name' => "Ethreum.IDX/IDXT",
		'type' => 'FUT',
		'index' => "ETH.IDX/IDXT"),
	'EOS.IDX/IDXT' => Array(
		'name' => "EOS.IDX/IDXT",
		'type' => 'FUT',
		'index' => "EOS.IDX/IDXT"),
	'WAVES.IDX/IDXT' => Array(
		'name' => "WAVES.IDX/IDXT",
		'type' => 'FUT',
		'index' => "WAVES.IDX/IDXT"),
	'BTS.IDX/IDXT' => Array(
		'name' => "BitShares.IDX/IDXT",
		'type' => 'FUT',
		'index' => "BTS.IDX/IDXT"),
	'NEO.IDX/IDXT' => Array(
		'name' => "NEO.IDX/IDXT",
		'type' => 'FUT',
		'index' => "NEO.IDX/IDXT"),
	'NEM.IDX/IDXT' => Array(
		'name' => "NEM.IDX/IDXT",
		'type' => 'FUT',
		'index' => "NEM.IDX/IDXT"),
	'OMNI.IDX/IDXT' => Array(
		'name' => "OMNI.IDX/IDXT",
		'type' => 'FUT',
		'index' => "OMNI.IDX/IDXT"),
	'QTUM.IDX/IDXT' => Array(
		'name' => "Qtum.IDX/IDXT",
		'type' => 'FUT',
		'index' => "QTUM.IDX/IDXT"),
	'XLM.IDX/IDXT' => Array(
		'name' => "Stellar.IDX/IDXT",
		'type' => 'FUT',
		'index' => "XLM.IDX/IDXT"),
	'UBIQ.IDX/IDXT' => Array(
		'name' => "Ubiq.IDX/IDXT",
		'type' => 'FUT',
		'index' => "UBIQ.IDX/IDXT"),
	'ARDR.IDX/IDXT' => Array(
		'name' => "Ardor.IDX/IDXT",
		'type' => 'FUT',
		'index' => "ARDR.IDX/IDXT"),
	'ACT.IDX/IDXT' => Array(
		'name' => "Achain.IDX/IDXT",
		'type' => 'FUT',
		'index' => "ACT.IDX/IDXT"),
	'COPARTY.IDX/IDXT' => Array(
		'name' => "Counterparty.IDX/IDXT",
		'type' => 'FUT',
		'index' => "COPARTY.IDX/IDXT"),
	'BTC/TUSD' => Array(
		'name' => "BTC/TUSD",
		'type' => 'SPOT',
		'index' => "BTC/TUSD"),
	'EOS/TUSD' => Array(
		'name' => "EOS/TUSD",
		'type' => 'SPOT',
		'index' => "EOS/TUSD"),
	'EMC/TUSD' => Array(
		'name' => "EMC/TUSD",
		'type' => 'SPOT',
		'index' => "EMC/TUSD"),
	'BCH/TUSD' => Array(
		'name' => "BCH/TUSD",
		'type' => 'SPOT',
		'index' => "BCH/TUSD"),
	'ETH/TUSD' => Array(
		'name' => "ETH/TUSD",
		'type' => 'SPOT',
		'index' => "ETH/TUSD"),
	'LTC/TUSD' => Array(
		'name' => "LTC/TUSD",
		'type' => 'SPOT',
		'index' => "LTC/TUSD"),
	'XMR/TUSD' => Array(
		'name' => "XMR/TUSD",
		'type' => 'SPOT',
		'index' => "XMR/TUSD"),
	'ZEC/TUSD' => Array(
		'name' => "ZEC/TUSD",
		'type' => 'SPOT',
		'index' => "ZEC/TUSD"),	
	'XRP/TUSD' => Array(
		'name' => "XRP/TUSD",
		'type' => 'SPOT',
		'index' => "XRP/TUSD"),
	'DTR/TUSD' => Array(
		'name' => "DTR/TUSD",
		'type' => 'SPOT',
		'index' => "DTR/TUSD"),
	'NANO/TUSD' => Array(
		'name' => "NANO/TUSD",
		'type' => 'SPOT',
		'index' => "NANO/TUSD"),
	'LTC/IDXT' => Array(
		'name' => "LTC/IDXT",
		'type' => 'SPOT',
		'index' => "LTC/IDXT"),	
	'XLM/IDXT' => Array(
		'name' => "XLM/IDXT",
		'type' => 'SPOT',
		'index' => "XLM/IDXT"),	
	'BCH/IDXT' => Array(
		'name' => "BCH/IDXT",
		'type' => 'SPOT',
		'index' => "BCH/IDXT"),	
	'EMC/IDXT' => Array(
		'name' => "EMC/IDXT",
		'type' => 'SPOT',
		'index' => "EMC/IDXT"),	
	'EOS/IDXT' => Array(
		'name' => "EOS/IDXT",
		'type' => 'SPOT',
		'index' => "EOS/IDXT"),	
	'ETC/IDXT' => Array(
		'name' => "ETC/IDXT",
		'type' => 'SPOT',
		'index' => "ETC/IDXT"),
	'ETH/IDXT' => Array(
		'name' => "ETH/IDXT",
		'type' => 'SPOT',
		'index' => "ETH/IDXT"),	
	'BTC/IDXT' => Array(
		'name' => "BTC/IDXT",
		'type' => 'SPOT',
		'index' => "BTC/IDXT"),	
	'XMR/IDXT' => Array(
		'name' => "XMR/IDXT",
		'type' => 'SPOT',
		'index' => "XMR/IDXT"),
	'ZEC/IDXT' => Array(
		'name' => "ZEC/IDXT",
		'type' => 'SPOT',
		'index' => "ZEC/IDXT"),	
	'XRP/IDXT' => Array(
		'name' => "XRP/IDXT",
		'type' => 'SPOT',
		'index' => "XRP/IDXT"),	
	'DTR/IDXT' => Array(
		'name' => "DTR/IDXT",
		'type' => 'SPOT',
		'index' => "DTR/IDXT"),
	'USDT/IDXT' => Array(
		'name' => "USDT/IDXT",
		'type' => 'SPOT',
		'index' => "USDT/IDXT"),
	'TUSD/IDXT' => Array(
		'name' => "TUSD/IDXT",
		'type' => 'SPOT',
		'index' => "TUSD/IDXT"),
	'NANO/IDXT' => Array(
		'name' => "NANO/IDXT",
		'type' => 'SPOT',
		'index' => "NANO/IDXT"),
	'BTC/ETH' => Array(
		'name' => "BTC/ETH",
		'type' => 'SPOT',
		'index' => "BTC/ETH"),
	'EOS/ETH' => Array(
		'name' => "EOS/ETH",
		'type' => 'SPOT',
		'index' => "EOS/ETH"),
	'EMC/ETH' => Array(
		'name' => "EMC/ETH",
		'type' => 'SPOT',
		'index' => "EMC/ETH"),
	'BCH/ETH' => Array(
		'name' => "BCH/ETH",
		'type' => 'SPOT',
		'index' => "BCH/ETH"),
	'XLM/ETH' => Array(
		'name' => "XLM/ETH",
		'type' => 'SPOT',
		'index' => "XLM/ETH"),
	'LTC/ETH' => Array(
		'name' => "LTC/ETH",
		'type' => 'SPOT',
		'index' => "LTC/ETH"),
	'XMR/ETH' => Array(
		'name' => "XMR/ETH",
		'type' => 'SPOT',
		'index' => "XMR/ETH"),
	'ZEC/ETH' => Array(
		'name' => "ZEC/ETH",
		'type' => 'SPOT',
		'index' => "ZEC/ETH"),
	'XRP/ETH' => Array(
		'name' => "XRP/ETH",
		'type' => 'SPOT',
		'index' => "XRP/ETH"),
	'' => Array(
		'name' => "",
		'type' => 'SPOT',
		'index' => ""),
	'USDT/ETH' => Array(
		'name' => "USDT/ETH",
		'type' => 'SPOT',
		'index' => "USDT/ETH"),
	'TUSD/ETH' => Array(
		'name' => "TUSD/ETH",
		'type' => 'SPOT',
		'index' => "TUSD/ETH")
);	


//а теперь процессим, заполняя реальными и случайными данными 
foreach($__symbols as $s => $v){
	$cur = array_reverse(explode('/', $v['index']))[0];
	$ass = array_reverse(explode('/', $v['index']))[1];
	
	$v['cur'] = $cur;
	$v['ass'] = $ass;
	
	if ($v['type'] == 'FUT')
		$v['contract'] = array_reverse(explode('/', $s))[1];
	
	if (!array_key_exists($ass, $xcurUSD)){
		//это индексы, поэтому подменим на что-то что точно есть 
		//на рандом = XMR, LTC, DASH, ZEC, DGD
		$_z = Array('XMR', 'LTC', 'DASH', 'ZEC', 'DGD');
		$ass = $_z[ array_rand($_z, 1) ];
	}
	
	
	if ($cur == 'ETH')
		$val = $xcurETH[ $ass ];
	else
	if ($cur == 'USD' || $cur == 'TUSD' || $cur == 'USDT')
		$val = $xcurUSD[ $ass ];	
	else
	if ($cur == 'XRP' || $cur == 'NANO' || $cur == 'IDXT')
		$val = $xcurXRP[ $ass ];
	
	
	
		
	$v['lastIndex'] = round($val['quotes']['price'], 4);
	$v['24hVol'] = intval($val['quotes']['volume_24h'] / 1000000);
	$v['change'] = $val['quotes']['percent_change_1h'];
	
	if ($v['24hVol'] < 1000)
		$v['24hVol'] = random_int(999, 300000);
		
	$v['low'] = $v['lastIndex'] - ($v['lastIndex']/100)*random_int(1, 15);
	$v['high'] = $v['low'] + ($v['lastIndex']/100)*random_int(3, 20);
		
	if (random_int(0, 100) > 70){
		$v['last'] = $v['lastIndex'] + ($v['lastIndex']/100)*random_int(1, 10);
	}
	else
		$v['last'] = $v['lastIndex'] - ($v['lastIndex']/100)*random_int(1, 10);
	
	
	
	$v['symbol'] = $s;
	$__symbols[$s] = $v;
}
