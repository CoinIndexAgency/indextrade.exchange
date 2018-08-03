$( document ).ready(function() {
    
    // CounterUp Plugin
    $('.counter').counterUp({
        delay: 10,
        time: 1000
    });
    
	/**
    setTimeout(function() {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            showMethod: 'fadeIn',
            hideMethod: 'fadeOut',
            timeOut: 5000
        };
        toastr.success('Checkout settings menu on left!', 'Welcome to Modern!');
    }, 1800);
	**/
    
    $(".live-tile").liveTile();
	
	
	$.getJSON('//static.indextrade.exchange/highstock/aapl-ohlcv.json', function (data) {

    // split the data set into ohlc and volume
    var ohlc = [],
        volume = [],
        dataLength = data.length,
        // set the allowed units for data grouping
        groupingUnits = [[
            'week',                         // unit name
            [1]                             // allowed multiples
        ], [
            'month',
            [1, 2, 3, 4, 6]
        ]],

        i = 0;

    for (i; i < dataLength; i += 1) {
        ohlc.push([
            data[i][0], // the date
            data[i][1], // open
            data[i][2], // high
            data[i][3], // low
            data[i][4] // close
        ]);

        volume.push([
            data[i][0], // the date
            data[i][5] // the volume
        ]);
    }


    // create the chart
    chartMain = Highcharts.stockChart('chartContainer', {

        rangeSelector: {
            selected: 2
        },

        title: {
            text: curAsset.name + ' Market'
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
                text: 'OHLC'
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
            split: true
        },

        plotOptions: {
            series: {
                dataGrouping: {
                    units: groupingUnits
                }
            }
        },

        series: [{
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
        }]
    });
});	
	
	
	
	
	
	
	
	
	

    
});