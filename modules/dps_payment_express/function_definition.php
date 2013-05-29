<?php
/**
 * @package DPSPaymentExpress
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    30 May 2012
 **/

$FunctionList = array();
$FunctionList['fetch_transaction'] = array(
	'name'             => 'fetch_transaction',
	'call_method'      => array(
		'class'  => 'DPSPaymentExpressTemplateFetchFunctions',
		'method' => 'fetchTransaction'
	),
	'parameter_type'   => 'standard',
	'parameters'       => array(
		array(
			'name'     => 'order_id',
			'type'     => 'int',
			'required' => true
		)
	)
);
