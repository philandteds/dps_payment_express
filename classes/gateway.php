<?php
/**
 * @package DPSPaymentExpress
 * @class   DPSPaymentExpressRedirectGateway
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    08 Nov 2012
 **/

class DPSPaymentExpressRedirectGateway extends eZRedirectGateway
{
	const AUTOMATIC_STATUS = false;
	const TYPE_DPS_EXPAY = 'dbsexpay';

	public function __construct() {
		$this->logger = self::getLogHandler();
	}

	public static function getLogHandler() {
		return eZPaymentLogger::CreateForAdd( 'var/log/dps_payment_express.log' );
	}

	public function createPaymentObject( $processID, $orderID ) {
		$this->logger->writeTimedString( 'DPSPaymentExpressRedirectGateway::createPaymentObject' );
        return eZPaymentObject::createNew( $processID, $orderID, self::TYPE_DPS_EXPAY );
	}

	public function createRedirectionUrl( $process ) {
		$this->logger->writeTimedString( 'DPSPaymentExpressRedirectGateway::createRedirectionUrl' );

		$processParams = $process->attribute( 'parameter_list' );
		$order         = eZOrder::fetch( $processParams['order_id'] );

		$shopName = null;
		$xrowIni  = eZINI::instance( 'xrowecommerce.ini' );
		$shopIni  = eZINI::instance( 'shop.ini' );
		if( $xrowIni->hasVariable( 'Settings', 'Shop' ) ) {
			$shopName = $xrowIni->variable( 'Settings', 'Shop' );
		}
		if(
			$shopName === null
			&& $shopIni->hasVariable( 'Settings', 'Shop' )
		) {
			$shopName = $shopIni->variable( 'Settings', 'Shop' );
		}

		$description = ezpI18n::tr(
			'extension/dps_payment_express',
			'Order #%order_id',
			null,
			array( '%order_id' => $shopName . $order->attribute( 'id' ) )
		);
		$transaction = new DPSPaymentExpressTransaction(
			array(
				'amount_input'       => $order->attribute( 'total_inc_vat' ),
				'order_id'           => $order->attribute( 'id' ),
				'currency_input'     => $order->attribute( 'productcollection' )->attribute( 'currency_code' ),
				'txn_id'             => $shopName . $order->attribute( 'id' ),
				'merchant_reference' => $description
			)
		);
		$transaction->store();

		$redirectURL = '/dps_payment_express/redirect/' . $transaction->attribute( 'id' );
		eZURI::transformURI( $redirectURL, false, 'full' );
		return $redirectURL;
	}

	public static function sendRequest( $xml ) {
		$log = fopen( 'var/log/dps_requests.log', 'a' );

		fwrite( $log, str_repeat( '=', 80 ) . "\n" . 'Request:' . "\n" . $xml . "\n" );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://sec.paymentexpress.com/pxpay/pxaccess.aspx' );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$r = curl_exec( $ch );
		if( $r === false ) {
			fwrite( $log, 'Error:' . "\n" . curl_error( $ch ) . "\n" );
		} else {
			fwrite( $log, 'Response:' . "\n" . $r . "\n" );
		}
		fclose( $log );
		curl_close( $ch );
		return (array) new SimpleXMLElement( $r );
	}

	public static function name() {
		return 'DPS Payment Express';
	}

	public static function costs() {
		return 0.00;
	}
}
?>
