<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-04, 10:59:27)
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
 * Command Notification
 * Description: Notifies the merchant about the result of a submitted charge as well as most other commands except for Refunds (760).
 * The Notification will be sent to the Notification URL supplied by the merchant.
 * 
 * RESPONSE 850
 * <transaction>
 *		<result></result>
 *		<gatetransid></gatetransid>
 *		<merchanttransid></merchanttransid>
 *		<errorcode></errorcode>
 *		<errormessage></errormessage>
 * </transaction>
 */
class Modulesgarden_Gpndata_Model_Notification_850 extends Modulesgarden_Gpndata_Model_Notification {
	
	public function isChecksumOk($config){
		if (!$this->_notificationXml)
			Mage::throwException('Notification XML is not valid');
		
		$calculated = sha1(
			$this->getNotificationValue('apiUser') .
			$this->getNotificationValue('apiPassword') .
			$this->getNotificationValue('apiCmd') .
			$this->getNotificationValue('merchanttransid') .
			$this->getNotificationValue('amount') .
			$this->getNotificationValue('curcode') .
			$config['api_key']
		);
		
		return $calculated == $this->getNotificationValue('checksum');
	}
	
	/**
	 * possible state:
	 * - AUTHORIZED: Funds from Customers Credit Card account
	 * - CAPTURED: Custo charged with the amount of the transaction.
	 * - DECLINED: transaction was declined.
	 * - CHARGEBACK: means that a charge back for the transaction was received by the gateway. Charge back notifications can be sent to the merchant at any time after the transaction was initially APPROVED. 
	 * - PENDING: in time of 3DS?
	 * @param Modulesgarden_Gpndata_Model_Notification_Response $response
	 */
	public function process(Modulesgarden_Gpndata_Model_Notification_Response $response){
		
		// notification is asynchronous and order could not exist yet
		
		$gatetransid		= $this->getNotificationValue('gatetransid');
		$merchanttransid	= $this->getNotificationValue('merchanttransid');
		$state				= $this->getNotificationValue('state');
		$transaction_details= $this->_parseMerchantTransId($merchanttransid);
		
		// no success -> no transaction
		if (in_array($state, array('PARTIAL_SUCCESS','PENDING','CANCELED','CANCELED_AUTO','ERROR','REFUNDED','DECLINED'))){
			return $response
				->setResult('OK')
				->setGatetransid($gatetransid)
				->setMerchanttransid($merchanttransid);
		}
		
		if ($this->isRebill()){
			
			$recurringProfile = Mage::getModel('sales/recurring_profile')->load( $transaction_details['order_id'] );
			if ($recurringProfile->getMethodCode()){
				$additionalInfo = $recurringProfile->getAdditionalInfo() ? $recurringProfile->getAdditionalInfo() : array();
				$additionalInfo['gatetransid'] = $gatetransid;
				$recurringProfile->setAdditionalInfo( serialize($additionalInfo));
				$recurringProfile->save();
				
				// add order assigned to the recurring profile with initial fee
				if ($recurringProfile->getInitAmount()){
					$productItemInfo = new Varien_Object;
					$productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_INITIAL);
					$productItemInfo->setPrice($recurringProfile->getInitAmount());
					
					$order = $recurringProfile->createOrder($productItemInfo);

					$payment = $order->getPayment();
					$payment->setTransactionId($gatetransid . '-initial')->setIsTransactionClosed(1);
					$order->save();
					$recurringProfile->addOrderRelation($order->getId());
					$order->save();
					$payment->save();

					$transaction= Mage::getModel('sales/order_payment_transaction');
					$transaction->setTxnId($gatetransid . '-initial');
					$transaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
					$transaction->setPaymentId($payment->getId());
					$transaction->setOrderId($order->getId());
					$transaction->setOrderPaymentObject($payment);
					$transaction->setIsClosed( 1 );
					$transaction->save();
				}
				
				return $response
					->setResult('OK')
					->setGatetransid($gatetransid)
					->setMerchanttransid($merchanttransid);
				
			}
			return $response
				->setResult('ERROR')
				->setGatetransid($gatetransid)
				->setMerchanttransid($merchanttransid)
				->setErrorcode(403)
				->setErrormessage('Recurring Profile #'.$transaction_details['order_id'].' is not created yet');
		}
		
		
		
		$order = Mage::getModel('sales/order')->load($transaction_details['order_id']);
		
		if ($order->isEmpty()){
			return $response
				->setResult('ERROR')
				->setGatetransid($gatetransid)
				->setMerchanttransid($merchanttransid)
				->setErrorcode(403)
				->setErrormessage('Order '.$transaction_details['order_id'].' is not created yet');
		}
		
		// PARTIAL_SUCCESS, DECLINED, CANCELED, CANCELED_AUTO, ERROR, REFUNDED
		if (in_array($state, array('AUTHORIZED','CAPTURED','CHARGEBACK'))){
			
			switch ($state){
				case 'AUTHORIZED':	$transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH; break;
				case 'CAPTURED':	$transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE; break;
				case 'CHARGEBACK':	$transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID; break;
				default: Mage::throwException('Unable to add transaction');
			}
			
			$payment	= Mage::getModel('sales/order_payment')->load($transaction_details['payment_id']);
			$transaction= Mage::getModel('sales/order_payment_transaction');
			$transaction->setTxnId($merchanttransid);
			$transaction->setPaymentId($transaction_details['payment_id']);
			$transaction->setOrderId($transaction_details['order_id']);
			$transaction->setOrderPaymentObject($payment);
			$transaction->setTxnType($transactionType);
			$transaction->setIsClosed( $transactionType == Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE ? 1 : 0 );
			$transaction->setAdditionalInformation( Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array(
				'gatetransid' => $gatetransid
			));
			
			$parentTransaction = $payment->getTransaction($merchanttransid);
			if ($parentTransaction){
				$transaction->setParentTxnId($merchanttransid);
				$transaction->setTxnId($gatetransid);
			}
			
			$transaction->save();
		}
		
		$response
			->setResult('OK')
			->setGatetransid($gatetransid)
			->setMerchanttransid($merchanttransid);
	}
	
	public function isRebill(){
		return $this->getNotificationValue('merchantspecific3') == 'rebill';
	}
	
}