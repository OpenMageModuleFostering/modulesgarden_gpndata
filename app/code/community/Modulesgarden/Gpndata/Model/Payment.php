<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-01-24, 15:46:03)
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

class Modulesgarden_Gpndata_Model_Payment extends Mage_Payment_Model_Method_Cc {
	
	const TYPE_AUTHORIZE = 'authorize';
	const TYPE_AUTHORIZE_CAPTURE = 'authorize_capture';
	
	protected $_code = 'gpndata';

	protected $_isGateway			= true; // Is this payment method a gateway (online auth/charge) ?
	protected $_canAuthorize		= true; // Can authorize online?
	protected $_canCapture			= true; // Can capture funds online?
	protected $_canUseInternal		= true; // Can use this payment method in administration panel?
	protected $_canUseCheckout		= true; // Can show this payment method as an option on checkout payment page?
	protected $_canRefund			= true;
	protected $_canVoid				= true;
    protected $_canRefundInvoicePartial = true;
	protected $_isInitializeNeeded	= false;

	protected $_formBlockType = 'gpndata/form_cc';
	
	
	public function getOrderPlaceRedirectUrl(){
		if (Mage::getSingleton('core/session')->getAcs())
			return Mage::getUrl('gpndata/redirect/index', array('_secure' => true));
		return null;
	}
	
	public function authorize(Varien_Object $payment, $amount){
		
		$conf = Mage::getStoreConfig('payment/gpndata');
		$order = $payment->getOrder();
		
		$request = $this->_getRequest(700);
		$request->setType(self::TYPE_AUTHORIZE);
		$request->setIs3ds( $conf['payment_3ds'] ? true : false );
		$request->setOrder($order);
		$request->setPayment($payment);
		$request->setAmount($this->_formatAmount($amount));
		$request->setInfoInstance($this->getInfoInstance());
		$response = $request->post();

		if ($response->simpleResponseValue('result') == 'SUCCESS' || $response->simpleResponseValue('result') == 'PENDING'){
			
			$payment->setCcTransId( $request->getMerchantTransId() );
			$payment->setLastTransId ( $request->getMerchantTransId() );
			$payment->setSkipTransactionCreation(true);
			$payment->setIsTransactionClosed(false);
			
			if ($response->simpleResponseValue('ACS')){
				Mage::helper('gpndata')->prepare3ds($response);
				$order->setGpndataMd((string)$response->getXml()->parameters->MD);
			}
			
			return $this;
			
		} else {
			if ($response->getErrorMsg())
				Mage::log($response->getErrorMsg(), null, 'gpndatalog.log');
			Mage::throwException( 'Gateway response: ' . $response->simpleResponseValue('result') );
		}
	}

	/**
	 * Third-party API stuff would go here, with exceptions being thrown if the gateway determines they've provided an invalid card, etc.
	 * @param Varien_Object $payment
	 * @param decimal $amount
	 */
	public function capture(Varien_Object $payment, $amount){
		$order = Mage::getModel('sales/order')->load( $payment->getOrder()->getId() );
		
		if ($payment->getCcTransId()){
			$trans = $payment->getTransaction($payment->getCcTransId());
			if (!$trans)
				Mage::throwException('Unable to find transaction '.$payment->getCcTransId().' for this order.');
			$additional = $trans->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
			
			$request = $this->_getRequest(701);
			$request->setGateTransId( $additional['gatetransid'] );
			$request->setAmount($this->_formatAmount($amount));
			
		} else {
			$conf = Mage::getStoreConfig('payment/gpndata');
			
			$request = $this->_getRequest(700);
			$request->setType(self::TYPE_AUTHORIZE_CAPTURE);
			$request->setOrder($order);
			$request->setAmount($this->_formatAmount($amount));
			$request->setInfoInstance($this->getInfoInstance());
			$request->setPayment($payment);
			$request->setIs3ds( $conf['payment_3ds'] ? true : false );
			
		}
		$response = $request->post();

		if ($response->simpleResponseValue('result') == 'SUCCESS' || $response->simpleResponseValue('result') == 'PENDING'){
			
			$payment->setCcTransId( $response->simpleResponseValue('merchanttransid') );
			$payment->setLastTransId ( $response->simpleResponseValue('merchanttransid') );
			$payment->setSkipTransactionCreation(true);
			
			if ($response->simpleResponseValue('ACS')){
				Mage::helper('gpndata')->prepare3ds($response);
				$order->setGpndataMd((string)$response->getXml()->parameters->MD);
				$order->save();
			}
			
			return $this;
			
		} else {
			if ($response->getErrorMsg())
				Mage::log($response->getErrorMsg(), null, 'gpndatalog.log');
			Mage::throwException( 'Gateway response: ' . $response->simpleResponseValue('result') );
		}
	}
	
	public function refund(Varien_Object $payment, $amount){
		
		$trans = $payment->getTransaction($payment->getCcTransId());
		if (!$trans)
			Mage::throwException('Unable to find transaction '.$payment->getCcTransId().' for this order.');
		$additional = $trans->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
		
		$request = $this->_getRequest(760);
		$request->setGateTransId( $additional['gatetransid'] );
		$request->setAmount($this->_formatAmount($amount));
		$request->setReason('Refund');
		$response = $request->post();
		
		if ($response->simpleResponseValue('result') == 'SUCCESS'){
			
			return $this;
		} else {
			if ($response->getErrorMsg())
				Mage::log($response->getErrorMsg(), null, 'gpndatalog.log');
			Mage::throwException( 'Gateway response: ' . $response->simpleResponseValue('result') );
		}
	}
	
	public function void(Varien_Object $payment){
		
		$trans = $payment->getTransaction($payment->getCcTransId());
		if (!$trans)
			Mage::throwException('Unable to find transaction '.$payment->getCcTransId().' for this order.');
		$additional = $trans->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
		
		$request = $this->_getRequest(702);
		$request->setGateTransId( $additional['gatetransid'] );
		$response = $request->post();
		
		if ($response->simpleResponseValue('result') == 'CANCELED'){

			$transaction = Mage::getModel('sales/order_payment_transaction');
			$transaction->setTxnId($trans->getTxnId() . '_void');
			$transaction->setParentTxnId($trans->getTxnId());
			$transaction->setPaymentId($trans->getPaymentId());
			$transaction->setOrderId($trans->getOrderId());
			$transaction->setOrderPaymentObject($payment);
			$transaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID);
			$transaction->setIsClosed( 1 );
			$transaction->setAdditionalInformation( Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array(
				'gatetransid' => $additional['gatetransid']
			));
			$transaction->save();
			
			$payment->setSkipTransactionCreation(true);
			return $this;
		} else {
			if ($response->getErrorMsg())
				Mage::log($response->getErrorMsg(), null, 'gpndatalog.log');
			Mage::throwException( 'Gateway response: ' . $response->simpleResponseValue('result') );
		}
	}
	
	
	
	
	protected function _getRequest($code){
		$configValue = Mage::getStoreConfig('payment/gpndata');
		
		$configValue['password'] = Mage::helper('core')->decrypt($configValue['password']);
		$configValue['api_key'] = Mage::helper('core')->decrypt($configValue['api_key']);
		
		$request = Mage::getModel('gpndata/request_' . $code);
		$request->initRequest($configValue);
		
		return $request;
	}
	
	/**
     * Round up and cast specified amount to float or string
     *
     * @param string|float $amount
     * @param bool $asFloat
     * @return string|float
     */
    protected function _formatAmount($amount, $asFloat = false){
		$amount = sprintf('%.2F', $amount); // "f" depends on locale, "F" doesn't
		return $asFloat ? (float)$amount : $amount;
	}
	
	/**
     * Add payment transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string $transactionId
     * @param string $transactionType
     * @param array $transactionDetails
     * @param array $transactionAdditionalInfo
     * @return null|Mage_Sales_Model_Order_Payment_Transaction
     */
    public function addTransaction(Mage_Sales_Model_Order_Payment $payment, $transactionId, $transactionType,
        array $transactionDetails = array(), array $transactionAdditionalInfo = array(), $message = false
    ) {
        $payment->setTransactionId($transactionId);
        $payment->resetTransactionAdditionalInfo();
        foreach ($transactionDetails as $key => $value) {
            $payment->setData($key, $value);
        }
        $transaction = $payment->addTransaction($transactionType, null, false , $message);
		$transaction->setAdditionalInformation( Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $transactionAdditionalInfo );
		$transaction->save();
		
        foreach ($transactionDetails as $key => $value) {
            $payment->unsetData($key);
        }
        $payment->unsLastTransId();

        /**
         * It for self using
         */
        $transaction->setMessage($message);

        return $transaction;
    }
	
	protected function _getIncrementOrderId(){
		$info = $this->getInfoInstance();

		if (!($info instanceof Mage_Sales_Model_Quote_Payment) && $info instanceof Mage_Sales_Model_Order_Payment){ // isPlaceOrder
			return $info->getOrder()->getIncrementId();
		} else {
			if (!$info->getQuote()->getReservedOrderId())
				$info->getQuote()->reserveOrderId();
			return $info->getQuote()->getReservedOrderId();
		}
    }
	
	protected function getGateTransIdByMerchantTransId($merchantid){
		$collection = Mage::getModel('core/config_data')->getCollection()
			->addFieldToFilter('path', 'gpndata/gatetransid/' . $merchantid);
		
		foreach ($collection as $config){
			$v = $config->getData('value');
			$config->delete();
			return $v;
		}
		
		return null;
	}
	
}
