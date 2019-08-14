<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-11, 12:15:52)
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
 * Rebill Notification
 * Description: Notifies the merchant about the result of a submitted Rebill transaction.
 * 
 * Request:
 * <transaction>
 *	<apiUser></apiUser>
 *	<apiPassword></apiPassword>
 *	<apiCmd></apiCmd>
 *	<gatetransid></gatetransid>
 *	<gaterebillid></gaterebillid>
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
class Modulesgarden_Gpndata_Model_Notification_870 extends Modulesgarden_Gpndata_Model_Notification {
	
	public function isChecksumOk($config){
		if (!$this->_notificationXml)
			Mage::throwException('Notification XML is not valid');
		
		$calculated = sha1(
			$this->getNotificationValue('apiUser') .
			$this->getNotificationValue('apiPassword') .
			$this->getNotificationValue('apiCmd') .
			$this->getNotificationValue('gaterebillid') .
			$this->getNotificationValue('gatetransid') .
			$this->getNotificationValue('status') .
			$config['api_key']
		);
		return $calculated == $this->getNotificationValue('checksum');
	}
	
	public function process(Modulesgarden_Gpndata_Model_Notification_Response $response){
		
		sleep(10);
		
		$recurringProfileCollection = Mage::getModel('sales/recurring_profile')
			->getCollection()
			->addFieldToFilter('additional_info', array(
				array('like' => '%'.$this->getNotificationValue('gaterebillid').'%'),
			));
		
		$profile = $recurringProfileCollection->getFirstItem();
		
		if ($profile->isEmpty()){
			return $response
				->setResult('ERROR')
				->setErrorcode(403)
				->setErrormessage('Recurring Profile is not created yet');
		}
		
		if ($this->getNotificationValue('status') == 'CANCELED'){
			
			$profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
			$profile->save();
			
		} elseif ($this->getNotificationValue('status') == 'SUCCESS'){
			
			$gatetransid = $this->getNotificationValue('gatetransid');

			$order = $this->_createOrder($profile);

			$payment = $order->getPayment();
			$payment->setTransactionId($gatetransid . '-rebill')->setIsTransactionClosed(1);
			$order->save();
			$profile->addOrderRelation($order->getId());
			//$payment->save();

			$transaction= Mage::getModel('sales/order_payment_transaction');
			$transaction->setTxnId($gatetransid . '-rebill');
			$transaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
			$transaction->setPaymentId($payment->getId());
			$transaction->setOrderId($order->getId());
			$transaction->setOrderPaymentObject($payment);
			$transaction->setIsClosed( 1 );
			$transaction->save();
			
		} elseif ($this->getNotificationValue('status') == 'DECLINED'){
			
			// failure -> save it
			$additionalInfo = $profile->getAdditionalInfo() ? $profile->getAdditionalInfo() : array();
			$additionalInfo['failures'] = isset($additionalInfo['failures']) ? ++$additionalInfo['failures'] : 1;
			
			// suspend if failures limit occured
			if ($profile->getSuspensionThreshold() && $additionalInfo['failures'] >= $profile->getSuspensionThreshold())
				$profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
			
			$profile->setAdditionalInfo( serialize($additionalInfo));
			$profile->save();
		}
		
		return $response
			->setResult('OK')
			->setGaterebillid( $this->getNotificationValue('gaterebillid') );
	}
	
	protected function _createOrder(Mage_Sales_Model_Recurring_Profile $profile){
		
		$orderInfo			= is_string($profile->getOrderInfo())			? unserialize($profile->getOrderInfo()) : $profile->getOrderInfo();
		$orderItemInfo		= is_string($profile->getOrderItemInfo())		? unserialize($profile->getOrderItemInfo()) : $profile->getOrderItemInfo();
		$billingAddressInfo = is_string($profile->getBillingAddressInfo())	? unserialize($profile->getBillingAddressInfo()) : $profile->getBillingAddressInfo();
		$shippingAddressInfo= is_string($profile->getShippingAddressInfo()) ? unserialize($profile->getShippingAddressInfo()) : $profile->getShippingAddressInfo();
		
		$item = Mage::getModel('sales/order_item')
			->setName(			'Rebill for Recurring Profile #' . $profile->getId())
			->setQtyOrdered(	$orderItemInfo['qty'] )
			->setBaseOriginalPrice($profile->getBillingAmount() )
			->setPrice(			$profile->getBillingAmount() )
			->setBasePrice(		$profile->getBillingAmount() )
			->setRowTotal(		$profile->getBillingAmount() )
			->setBaseRowTotal(	$profile->getBillingAmount() )
			->setTaxAmount(		$profile->getTaxAmount() )
			->setShippingAmount($profile->getShippingAmount() )
			->setPaymentType(	Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR)
			->setIsVirtual(		$orderItemInfo['is_virtual'] )
			->setWeight(		$orderItemInfo['weight'] )
			->setId(null);
		
        $grandTotal = $profile->getBillingAmount() + $profile->getShippingAmount() + $profile->getTaxAmount();

        $order = Mage::getModel('sales/order');

        $billingAddress = Mage::getModel('sales/order_address')
            ->setData($billingAddressInfo)
            ->setId(null);

        $shippingAddress = Mage::getModel('sales/order_address')
            ->setData($shippingAddressInfo)
            ->setId(null);

        $payment = Mage::getModel('sales/order_payment')
            ->setMethod($profile->getMethodCode());

        $transferDataKays = array(
            'store_id',             'store_name',           'customer_id',          'customer_email',
            'customer_firstname',   'customer_lastname',    'customer_middlename',  'customer_prefix',
            'customer_suffix',      'customer_taxvat',      'customer_gender',      'customer_is_guest',
            'customer_note_notify', 'customer_group_id',    'customer_note',        'shipping_method',
            'shipping_description', 'base_currency_code',   'global_currency_code', 'order_currency_code',
            'store_currency_code',  'base_to_global_rate',  'base_to_order_rate',   'store_to_base_rate',
            'store_to_order_rate'
        );

        foreach ($transferDataKays as $key) {
            if (isset($orderInfo[$key])) {
                $order->setData($key, $orderInfo[$key]);
            } elseif (isset($shippingAddressInfo[$key])) {
                $order->setData($key, $shippingAddressInfo[$key]);
            }
        }

        $order
            ->setState(				Mage_Sales_Model_Order::STATE_NEW )
            ->setBaseToOrderRate(	$orderInfo['base_to_quote_rate'])
            ->setStoreToOrderRate(	$orderInfo['store_to_quote_rate'])
            ->setOrderCurrencyCode(	$orderInfo['quote_currency_code'])
            ->setBaseSubtotal(		$profile->getBillingAmount() )
            ->setSubtotal(			$profile->getBillingAmount() )
            ->setBaseShippingAmount($profile->getShippingAmount() )
            ->setShippingAmount(	$profile->getShippingAmount() )
            ->setBaseTaxAmount(		$profile->getTaxAmount() )
            ->setTaxAmount(			$profile->getTaxAmount() )
            ->setBaseGrandTotal(	$grandTotal)
            ->setGrandTotal(		$grandTotal)
            ->setIsVirtual(			$orderItemInfo['is_virtual'] )
            ->setWeight(			$orderItemInfo['weight'] )
            ->setTotalQtyOrdered(	$orderItemInfo['qty'] )
            ->setBillingAddress(	$billingAddress )
            ->setShippingAddress(	$shippingAddress )
            ->setPayment(			$payment );
		
		$order->addItem($item);

        return $order;
    }
	
}