var idxt = {};

	idxt.numCodes = [49,50,51,52,53,54,55,56,57,190];
	
idxt.createAndSendOrder = function(el, price, amount, side, symbol){
	if (!price || !amount || !side || !symbol) return;

	$.post('/orders/new', {price: price, amount: amount, side: side, symbol: symbol}, function(d, status, dType){
		//console.log( [d, status, dType]  );
		
		if (d && d.data && d.data.orderID){
			console.log('Order Accepted! ID: ' + d.data.orderID);
			
			$.getJSON('/user/orders/last?symbol=' + curAsset.symbol + '&_' + new Date().getTime(), function(d){
				if (d && !d.error && d.data){
					idxt.renderUserLastOrders( d.data );
					
					idxt.reloadMyPositionStat();
				}
			});
		}
		
		el.removeClass('disabled');					
	});			
};
	
idxt.calcOrder = function(type){
	if (type == 'BUY'){
		var form = $('.idxtBuyForm');
		var fee = curAsset.takerFee;
	}
	else
	if (type == 'SELL'){
		var form = $('.idxtSellForm');
		var fee = curAsset.makerFee;
	}
	else
		return;
	
	var price = parseFloat( form.find('.idxtPrice').val() );
	var amount = parseFloat( form.find('.idxtAmount').val() );
	
	if (!price || !amount) return;
	
	if (!fee)
		fee = 0.2;
	
	var total = price * amount;
	var calcfee = Math.round( (total / 100) * fee, 3);
	
	if (calcfee < 0.001) 
		calcfee = 0.001;
		
	form.find('.idxtTotal').val( Big(total).toPrecision(6) );
	form.find('.idxtTradeFee').html( Big(calcfee).toPrecision(6) );				
};

//рендер текущего рынка
idxt.renderMarketView = function(obj){
	if (!obj) return;
	//снчала по инструменту 
	var ael = $('.idxtAssetOverview'); 
	var oel = $('.idxtAssetBook'); 
		
	if (obj.stat.PRICE.DIFF_PRC > 0){
		ael.find('.idxtAssetChange').html( obj.stat.PRICE.DIFF_PRC ).css({'color':'green'});
		
		oel.find('.idxtMarketMidPrice').css({'background-color':'#caf9b9'}).html('<h3 style="margin-top:10px !important; margin-bottom:10px !important;">' + Big(obj.stat.PRICE.MID).toPrecision(6) + ' <i class="fa fa-long-arrow-up"></i></h3>');
	}
	else 
	if (obj.stat.PRICE.DIFF_PRC < 0){
		ael.find('.idxtAssetChange').html( obj.stat.PRICE.DIFF_PRC ).css({'color':'red'});
		
		oel.find('.idxtMarketMidPrice').css({'background-color':'#ffd8d8'}).html('<h3 style="margin-top:10px !important; margin-bottom:10px !important;">' + Big(obj.stat.PRICE.MID).toPrecision(6) + ' <i class="fa fa-long-arrow-down"></i></h3>');
	}
	else 
	if (obj.stat.PRICE.DIFF_PRC == 0){
		ael.find('.idxtAssetChange').html( obj.stat.PRICE.DIFF_PRC ).css({'color':'grey'});
		
		oel.find('.idxtMarketMidPrice').css({'background-color':'#e9e9e9'}).html('<h3 style="margin-top:10px !important; margin-bottom:10px !important;">' + Big(obj.stat.PRICE.MID).toPrecision(6) + ' &nbsp;</h3>');
	}
		
	ael.find('.idxtAssetPrice').html( obj.stat.PRICE.MID );
	
	if (obj.stat.OHLC && obj.stat.OHLC.CURR){
		ael.find('.idxtAssetLowPrice').html( obj.stat.OHLC.CURR.LOW );
		ael.find('.idxtAssetHighPrice').html( obj.stat.OHLC.CURR.HIGH );
		ael.find('.idxtAsset24hVol').html( obj.stat.OHLC.CURR.VOL24H );
	}
	
	
	//-----
	if (obj.book){
		if (obj.book.BUY){
			var _buyBook = obj.book.BUY;
				//_buyBook.reverse();
			
			if (_buyBook.length > 14)
				_buyBook = _buyBook.slice(0, 13);
				
			_buyBook.reverse();
			
			var _str = ''; 
				
				_.each(_buyBook, function(val, i){
					_str = _str + ' ' + 
						'<div class="row" style="font-weight:normal;height:50% !important;">' + 
							'<div class="col-lg-4 col-md-4 text-center" style="padding-right:0px !important; padding-left:0px !important;color:red;cursor:pointer;" onclick="javascript: idxt.prefillPrice('+val[0]+');">' +
								Big(val[0]).toPrecision(6) + 
							'</div>' +
							'<div class="col-lg-4 col-md-4 text-center" style="padding-right:0px !important; padding-left:0px !important;">' +
								Big(val[1]).toPrecision(6) + 
							'</div>' +
							'<div class="col-lg-4 col-md-4 text-center" style="padding-right:0px !important; padding-left:0px !important;">' +
								Big(val[0] * val[1]).toPrecision(6) + 
							'</div>' +
						'</div>';
				});
				
			oel.find('.idxtAssetBookBUY').html( _str );
		}
		
		
		if (obj.book.SELL){
			var _sellBook = obj.book.SELL;
				//_sellBook.reverse();
			
			if (_sellBook.length > 14)
				_sellBook = _sellBook.slice(0, 13);
				
				_sellBook.reverse();
			
			var _str = '';
				
				_.each(_sellBook, function(val, i){
					_str = _str + ' ' + 
						'<div class="row" style="font-weight:normal;height:50% !important;">' + 
							'<div class="col-lg-4 col-md-4 text-center" style="padding-right:0px !important; padding-left:0px !important;color:green;cursor:pointer;" onclick="javascript: idxt.prefillPrice('+val[0]+');">' +
								Big(val[0]).toPrecision(6) + 
							'</div>' +
							'<div class="col-lg-4 col-md-4 text-center" style="padding-right:0px !important; padding-left:0px !important;">' +
								Big(val[1]).toPrecision(6) + 
							'</div>' +
							'<div class="col-lg-4 col-md-4 text-center" style="padding-right:0px !important; padding-left:0px !important;">' +
								/*Number.parseFloat(val[0] * val[1]).toPrecision(4)*/
								Big(val[0] * val[1]).toPrecision(6) + 
							'</div>' +
						'</div>';
				});
				
			oel.find('.idxtAssetBookSELL').html( _str );
		}
	
	}
	
	if (obj.stat.PRICE){
		var form = $('.idxtBuyForm');
		
		if (_.isEmpty( form.find('.idxtPrice').val() )){
			//для удобства подставим 
			if (obj.stat.PRICE.BUY)
				form.find('.idxtPrice').val( obj.stat.PRICE.BUY );
		}
		
		var form = $('.idxtSellForm');
		
		if (_.isEmpty( form.find('.idxtPrice').val() )){
			//для удобства подставим 
			if (obj.stat.PRICE.SELL)
				form.find('.idxtPrice').val( obj.stat.PRICE.SELL );
		}					
	}

		
}

idxt.renderUserLastOrders = function(data, callBack){
	//console.log('My orders');
	//console.info( data );
	
	var el = $('.idxtMyOrders'); 
	var _str = '';
	
	_.each(data, function(o){
		var dt = o.order_datetime.split(' ');
		var _label = 'label-success';
		
		if (o.order_side == 'SELL')
			_label = 'label-danger';
		
		
		var t = '<tr><th scope="row" class="idxtMyOrderRow idxtOrder'+o.orderID+'" style="text-align:center;cursor:pointer;" title="'+o.order_datetime+'">'+dt[1]+'</th>' + 
					'<td><span class="label '+_label+'">'+o.order_side+'</span></td>' + 
					'<td>'+o.order_price+'</td>' + 
					'<td>'+o.order_amount+'</td>' + 
					'<td>'+o.order_total+'</td>' + 
					'<td><a href="javascript: void idxt.cancelMyOrder(\''+o.orderID+'\');" style="font-size:10px;">cancel</a></td>' + 
				'</tr>';
			_str = _str + t;
	});
	
	el.empty().html( _str );	

	if ($.isFunction(callBack))
		callBack();
};

//предзаполнение цены 
idxt.prefillPrice = function(price){
	if (!price) return;
	
	var form = $('.idxtSellForm');
		form.find('.idxtPrice').val( price );
	
	var form = $('.idxtBuyForm');
		form.find('.idxtPrice').val( price );
		
	return;				
};

idxt.renderMyPosition = function(data, callBack){
	var el = $('.idxtUserPosition');
		el.find('.idxtPositionAssets').html( data.asset.currency_balance );
		el.find('.idxtPositionCurrency').html( data.currency.currency_balance );
		el.find('.idxtPositionMargins').html( data.currency.amount_at_guarantee );
		
		
		//currency_symbol, currency_balance, amount_at_orders, amount_at_guarantee, amount_at_pending_withdraw
}

//запрос на отмену моего ордера 
idxt.cancelMyOrder = function(orderID){
	console.log('Request cancelation of order: ' + orderID);
	
	$.post('/orders/cancel', {orderID: orderID, pair:curAsset.symbol}, function(d, status, dType){
		//console.log( [d, status, dType]  );
		
		if (d && d.data && d.data.orderID){
			console.log('Order cancelation accepted! ID: ' + d.data.orderID);
			
			//var el = $('.idxtMyOrders'); 
			//	el.find('.idxtOrder' + d.data.orderID).parent().remove();
				
			//idxt.reloadMyPositionStat();
		}
		else
		if (d && d.error && d.status && d.status == 'ERROR'){
			console.log('ERROR:' + d.error);
		}						
	});	
	
}

//обновление списка моих ордеров 
idxt.reloadMyOrders = function( symbol, callBack ){
	if (!symbol && curAsset && curAsset.symbol) 
		symbol = curAsset.symbol;
	else
		return;
	
	$.getJSON('/user/orders/last?symbol=' + symbol + '&_' + new Date().getTime(), function(d){
		if (d && !d.error && d.data){
			idxt.renderUserLastOrders( d.data, callBack );
		}
	});
}

//позиция по инструменту 
idxt.reloadMyPositionStat = function( symbol, callBack ){
	if (!symbol && curAsset && curAsset.symbol) 
		symbol = curAsset.symbol;
	
	if (!symbol)
		return;
	
	$.getJSON('/user/position?symbol=' + symbol + '&_' + new Date().getTime(), function(d){
		if (d && !d.error && d.data){
			//console.log( d.data );
			if (!callBack)
				idxt.renderMyPosition( d.data );
			else
				callBack( symbol, d.data );
		}
	});
};
	
//баланс кошелька юзера 
idxt.reloadMyWalletBalance = function( symbol, callBack ){
	if (!symbol)
		return;
	
	$.getJSON('/user/balance?symbol=' + symbol + '&_' + new Date().getTime(), function(d){
		if (d && !d.error && d.data){
			//console.log( d.data );
			if (callBack)
				callBack( symbol, d.data );
		}
	});
};	

//построение графика 
idxt.currentChart = null; 

idxt.createChart = function(symbol){ 
	// create the chart
    idxt.currentChart = Highcharts.stockChart('chartContainer', {

        rangeSelector: {
            selected: 2
        },

        title: {
            text: symbol + ' Market'
        },
		/*
        subtitle: {
            text: 'With SMA and Volume by Price technical indicators'
        },*/

        yAxis: [{
            startOnTick: false,
            endOnTick: false,
            labels: {
                align: 'right',
                x: -3
            },
            title: {
                text: 'Index price'
            },
            height: '60%',
            lineWidth: 2,
            resize: {
                enabled: true
            }
        }, {
            labels: {
                align: 'right',
                x: -3
            },
            title: {
                text: 'Volume'
            },
            top: '65%',
            height: '35%',
            offset: 0,
            lineWidth: 2
        }],

        tooltip: {
			pointFormat: '<span style="color:{series.color}">{series.name}</span>: <b>{point.y}</b> ({point.change}%)<br/>',
			changeDecimals: 2,
			valueDecimals: 2,
			split: true
		},

        plotOptions: {
           series: {
				compare: 'percent',
				//showInNavigator: true,
				compareStart: true
			}
        },

        series: [/**{
            type: 'candlestick',
            name: curAsset.name,
            id: 'eoseth.spot',
            zIndex: 2,
            data: ohlc
        }, {
            type: 'column',
            name: 'Volume',
            id: 'volume',
            data: volume,
            yAxis: 1
        }, {
            type: 'vbp',
            linkedTo: 'eoseth.spot',
            params: {
                volumeSeriesID: 'volume'
            },
            dataLabels: {
                enabled: false
            },
            zoneLines: {
                enabled: false
            }
        }, {
            type: 'sma',
            linkedTo: 'eoseth.spot',
            zIndex: 1,
            marker: {
                enabled: false
            }
        }**/]
    });
}