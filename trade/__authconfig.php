<?php 
	
	$config = [
		'callback' => 'https://trade.indextrade.exchange/callback.html',
		'providers' => [

			'Google' => [
				'enabled' => true,
				'keys' => [
					'id'     => '48005449054-v0nofamta8bf2g5t1v7gbrt34ctoto6f.apps.googleusercontent.com',
					'secret' => 'nhC3cRvOQDaiX0kLi43ai9r9'
				],
				'scope' => 'profile'
			],
			
			'Facebook'  => ['enabled' => true, 'keys' => [ 'id'   => '559145107849951', 'secret' => '1751bf0cac6c19b73a8f09037cbc9eae']],
			
			'GitHub'	=> ['enabled' => true, 'keys' => [ 'key'  => 'd9470c6ff08a0ca23932', 'secret' => 'd3e0971f0aed08cb754f55fc6b86d92949e82320']],
			
			//'Twitter'   => ['enabled' => true, 'keys' => [ 'key'  => '...', 'secret' => '...']],
			
			// 'Yahoo'     => ['enabled' => true, 'keys' => [ 'key'  => '...', 'secret' => '...']],
			//
			//'Twitter'   => ['enabled' => true, 'keys' => [ 'key'  => '...', 'secret' => '...']],
			// 'Instagram' => ['enabled' => true, 'keys' => [ 'id'   => '...', 'secret' => '...']],
			//'GitHub'	=> ['enabled' => true, 'keys' => [ 'key'  => '...', 'secret' => '...']],
			//'WindowsLive' => ['enabled' => true, 'keys' => [ 'key'  => '...', 'secret' => '...']],
			//'LinkedIn' => ['enabled' => true, 'keys' => [ 'key'  => '...', 'secret' => '...']],
			//'Vkontakte' => ['enabled' => true, 'keys' => [ 'key'  => '...', 'secret' => '...']],
			//'Mailru' => ['enabled' => true, 'keys' => [ 'key'  => '...', 'secret' => '...']]
			
		]
	];