<?php
/**
 * @package DPSPaymentExpress
 * @class   DPSPaymentExpressTemplateFetchFunctions
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    30 May 2012
 **/

class DPSPaymentExpressTemplateFetchFunctions
{
	public function fetchTransaction( $orderID ) {
		$transaction = eZPersistentObject::fetchObject(
			DPSPaymentExpressTransaction::definition(),
			null,
			array( 'order_id' => $orderID ),
			true
		);
		return array(
			'result' => $transaction
		);
	}
}