<?php
/**
 * @package DPSPaymentExpress
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    10 Nov 2012
 **/

$ini    = eZINI::instance( 'dpspaymentexpress.ini' );
$time   = microtime( true );
$logger = DPSPaymentExpressRedirectGateway::getLogHandler();

$transaction = DPSPaymentExpressTransaction::fetch( (int) $Params['TransactionID'] );
if( $transaction instanceof DPSPaymentExpressTransaction === false ) {
	return $Params['Module']->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

$logger->writeTimedString( 'Callback for order "' . $transaction->attribute( 'order_id' ) . '"' );
// This order is already processed by ezp
$URL = $ini->variable( 'LocalShopSettings', 'PaymentCompleteRedirectURL' );
$URL = str_replace( 'ORDER_ID', $transaction->attribute( 'order_id' ), $URL );
$URL = str_replace( 'TRANSACTION_ID', $transaction->attribute( 'id' ), $URL );

if( (bool) $transaction->attribute( 'success' ) ) {
	$logger->writeTimedString( 'Redirecting user to order view page' );
	return $Params['Module']->redirectTo( $URL );
}

$currentUserId = eZUser::currentUserID();
$transactionUserId = $transaction->attribute( 'user_id' );
$logger->writeTimedString("Verifying currently logged in matches expected user ID. Actual user: $currentUserId; Expected User: $transactionUserId");

if( eZUser::currentUserID() != $transaction->attribute( 'user_id' ) ) {
	if (eZUser::currentUser()->isAnonymous()) {
        $logger->writeTimedString("Users do not match, but the current user is logged in as 'anonymous'. Allowing, based on the fact that the user ID may have been lost in redirection.");
    } else {
        $logger->writeTimedString("Users do not match. Denying.");
        return $Params['Module']->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
	}
}

$http = eZHTTPTool::instance();
if( $http->hasGetVariable( 'result' ) === false ) {
	$logger->writeTimedString("Callback is missing 'result' GET parameter. Aborting.");
	return $Params['Module']->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

$dom = new DOMDocument( '1.0', 'utf-8' );
$dom->formatOutput = true;
$root = $dom->createElement( 'ProcessResponse' );
$dom->appendChild( $root );
$root->appendChild( $dom->createElement( 'PxPayUserId', $ini->variable( 'LocalShopSettings', 'PxPayUserID' ) ) );
$root->appendChild( $dom->createElement( 'PxPayKey', $ini->variable( 'LocalShopSettings', 'PxPayKey' ) ) );
$root->appendChild( $dom->createElement( 'Response', $http->getVariable( 'result' ) ) );

$execTime = round( microtime( true ) - $time, 6 );
$time     = time();
$logger->writeTimedString( 'Processing input params: ' . $execTime );

$result = DPSPaymentExpressRedirectGateway::sendRequest( $dom->saveXML() );
$transaction->setAttribute( 'status', DPSPaymentExpressTransaction::STATUS_PROCESSED );
$transaction->store();

$execTime = round( microtime( true ) - $time, 6 );
$time     = time();
$logger->writeTimedString( 'Sending validation request to DPS: ' . $execTime );

if( (bool) $result['@attributes']['valid'] === false ) {
	eZDebug::writeError( $result['ResponseText'], 'DPS Payment Express' );
	return $Params['Module']->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

$transaction->setAttribute( 'auth_code', $result['AuthCode'] );
$transaction->setAttribute( 'card_name', $result['CardName'] );
$transaction->setAttribute( 'card_number', $result['CardNumber'] );
$transaction->setAttribute( 'date_expiry', $result['DateExpiry'] );
$transaction->setAttribute( 'dps_txn_ref', $result['DpsTxnRef'] );
$transaction->setAttribute( 'success', $result['Success'] );
$transaction->setAttribute( 'response_text', $result['ResponseText'] );
$transaction->setAttribute( 'dps_billing_id', $result['DpsBillingId'] );
$transaction->setAttribute( 'card_holder_name', $result['CardHolderName'] );
$transaction->setAttribute( 'client_info', inet_pton( $result['ClientInfo'] ) );
$transaction->setAttribute( 'txn_mac', $result['TxnMac'] );
$transaction->setAttribute( 'card_number_2', $result['CardNumber2'] );
$transaction->setAttribute( 'cvc2_result_code', $result['Cvc2ResultCode'] );
$transaction->store();

$execTime = round( microtime( true ) - $time, 6 );
$time     = time();
$logger->writeTimedString( 'Updating DPS in DB: ' . $execTime );

if( (bool) $transaction->attribute( 'success' ) ) {
	$order             = eZOrder::fetch( $transaction->attribute( 'order_id' ) );
	$paymentObject     = eZPaymentObject::fetchByOrderID( $transaction->attribute( 'order_id' ) );
	$xrowPaymentObject = xrowPaymentObject::fetchByOrderID( $transaction->attribute( 'order_id' ) );
	if( $xrowPaymentObject instanceof xrowPaymentObject === false ) {
		if( $order instanceof eZOrder ) {
			$accountInfo       = $order->accountInformation();
			$xrowPaymentObject = xrowPaymentObject::createNew(
				$paymentObject instanceof eZPaymentObject ? $paymentObject->attribute( 'workflowprocess_id' ) : 0,
				$transaction->attribute( 'order_id' ),
				'DPSPaymentExpressRedirect'
			);
		}
	} else {
		$xrowPaymentObject->setAttribute( 'payment_string', 'DPSPaymentExpressRedirect' );
	}
	if( $xrowPaymentObject instanceof xrowPaymentObject ) {
		$xrowPaymentObject->approve();
		$xrowPaymentObject->store();
	}
	$execTime = round( microtime( true ) - $time, 6 );
	$time     = time();
	$logger->writeTimedString( 'xRow payment approving: ' . $execTime );

	if( $order instanceof eZOrder ) {
		$xmlString = $order->attribute( 'data_text_1' );
		if( $xmlString !== null ) {
			$doc = new DOMDocument();
			$doc->loadXML( $xmlString );

			$root    = $doc->documentElement;
			$invoice = $doc->createElement(
				xrowECommerce::ACCOUNT_KEY_PAYMENTMETHOD,
				DPSPaymentExpressRedirectGateway::TYPE_DPS_EXPAY
			);
			$root->appendChild( $invoice );
			$order->setAttribute( 'data_text_1', $doc->saveXML() );
			$order->store();
		}
	}

	$execTime = round( microtime( true ) - $time, 6 );
	$time     = time();
	$logger->writeTimedString( 'Updating order XML: ' . $execTime );

	if( $paymentObject instanceof eZPaymentObject ) {
		$paymentObject->approve();
		$paymentObject->store();
		eZPaymentObject::continueWorkflow( $paymentObject->attribute( 'workflowprocess_id' ) );

		$execTime = round( microtime( true ) - $time, 6 );
		$time     = time();
		$logger->writeTimedString( 'eZ Payment approving: ' . $execTime );
	} else {
		$logger->writeTimedString( 'eZ Payment not found' );
	}

	return $Params['Module']->redirectTo( $URL );
} else {
    $order = eZOrder::fetch( $transaction->attribute( 'order_id' ) );
    $tpl = eZTemplate::factory();
    $tpl->setVariable( 'order', $order );
	$tpl->setVariable( 'transaction', $transaction );

    return $Params['Module']->redirectTo( 'shop/basket' );

	/*$Result = array();
	$Result['content'] = $tpl->fetch( 'design:dps_payment_express/fail.tpl' );
	$Result['path']    = array(
		array(
			'text' => ezpI18n::tr( 'extension/dps_payment_express', 'DPS Payment Express Transaction' ),
			'url'  => false
		)
	);*/
}
?>
