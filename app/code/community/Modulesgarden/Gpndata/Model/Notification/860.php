<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-06, 11:02:40)
 * 
 *
 *  CREATED BY MODULESGARDEN       ->        http://modulesgarden.com
 *  CONTACT                        ->       contact@modulesgarden.com
 *
 *
 *
 *
 * This software is furnished under a license and may be used and copied
 * only  in  accordance  with  the  terms  of such  license and with the
 * inclusion of the above copyright notice.  This software  or any other
 * copies thereof may not be provided or otherwise made available to any
 * other person.  No title to and  ownership of the  software is  hereby
 * transferred.
 *
 *
 * ******************************************************************** */

/**
 * @author Grzegorz Draganik <grzegorz@modulesgarden.com>
 */

/**
 * Refund Notification.
 * Description: Notifies the merchant about the result of a submitted refund. Direction: From Transaction Server to merchant Server. 
 * 
 * Request:
 * <transaction>
 *	<apiUser></apiUser>
 *	<apiPassword></apiPassword>
 *	<apiCmd></apiCmd>
 *	<gatetransid></gatetransid>
 *	<amount></amount>
 *	<status></status>
 *	<reason></reason>
 *	<checksum></checksum>
 * </transaction>
 * 
 * Response:
 * <transaction>
 *	<result></result>
 *	<gatetransid></gatetransid>
 *	<errorcode></errorcode>
 *	<errormessage></errormessage>
 * </transaction> 
 */
class Modulesgarden_Gpndata_Model_Notification_860 extends Modulesgarden_Gpndata_Model_Notification {
	
	public function isChecksumOk($config){
		if (!$this->_notificationXml)
			Mage::throwException('Notification XML is not valid');
		
		$calculated = sha1(
			$this->getNotificationValue('apiUser') .
			$this->getNotificationValue('apiPassword') .
			$this->getNotificationValue('apiCmd') .
			$this->getNotificationValue('gatetransid') .
			$this->getNotificationValue('amount') .
			$this->getNotificationValue('status') .
			$config['api_key']
		);
		
		return $calculated == $this->getNotificationValue('checksum');
	}
	
	public function process(Modulesgarden_Gpndata_Model_Notification_Response $response){
		$gatetransid = $this->getNotificationValue('gatetransid');
		
		if ($this->getNotificationValue('status') == 'OK' || $this->getNotificationValue('status') == 'SUCCESS'){
			
			$transaction = Mage::getModel('sales/order_payment_transaction');
			$transaction->loadByTxnId($gatetransid);
			
			if (!$transaction->isEmpty()){
				
				$payment		= Mage::getModel('sales/order_payment')->load( $transaction->getPaymentId() );
				$newTransaction = Mage::getModel('sales/order_payment_transaction');
				$newTransaction->setTxnId($gatetransid . '_refund');
				$newTransaction->setParentTxnId($transaction->getTxnId());
				$newTransaction->setPaymentId($transaction->getPaymentId());
				$newTransaction->setOrderId($transaction->getOrderId());
				$newTransaction->setOrderPaymentObject($payment);
				$newTransaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);
				$newTransaction->setIsClosed(1);
				$newTransaction->setAdditionalInformation( Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array(
					'reason' => $this->getNotificationValue('reason')
				));
				$newTransaction->save();
			}
		}
		
		$response
			->setResult('OK')
			->setGatetransid($gatetransid);
	}
	
}