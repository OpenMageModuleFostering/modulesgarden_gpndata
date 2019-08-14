<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-10, 13:12:36)
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

class Modulesgarden_Gpndata_Model_Paymentrecurring extends Mage_Payment_Model_Method_Abstract implements Mage_Payment_Model_Recurring_Profile_MethodInterface {
	
	protected $_code  = 'gpndatarecurring';
	protected $_formBlockType = 'gpndata/form_cc';
	
	/**
     * Availability options
     */
    protected $_isGateway                   = false;
    protected $_canOrder                    = false;
    protected $_canAuthorize                = false;
    protected $_canCapture                  = false;
    protected $_canCapturePartial           = false;
    protected $_canRefund                   = false;
    protected $_canRefundInvoicePartial     = false;
    protected $_canVoid                     = false;
    protected $_canUseInternal              = false;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canCreateBillingAgreement   = false;
    protected $_canReviewPayment            = true;
	
	
	public function canUseCheckout(){
		$quote = Mage::getModel('checkout/cart')->getQuote();
		foreach ($quote->getAllItems() as $item) {
			if (!$item->getProduct()->getIsRecurring())
				return false;
		}
        return true;
    }
	
	public function getOrderPlaceRedirectUrl(){
		if (Mage::getSingleton('core/session')->getAcs())
			return Mage::getUrl('gpndata/redirect/index', array('_secure' => true));
		return null;
	}
	
	/**
     * Validate data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @throws Mage_Core_Exception
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){
		return $this;
	}

    /**
     * Submit to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $paymentInfo Mage_Sales_Model_Quote_Payment -> first transaction -> add initial amount
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $payment){
		
		// recurring = tax + billing + shipment
		$profile->setRecurringAmount( $this->_formatAmount($profile->getTaxAmount() + $profile->getBillingAmount() + $profile->getShippingAmount()) );
		$conf = Mage::getStoreConfig('payment/gpndatarecurring');

		$request = $this->_getRequest(700);
		$request->setType( $conf['payment_action'] == 'authorize' ? Modulesgarden_Gpndata_Model_Payment::TYPE_AUTHORIZE : Modulesgarden_Gpndata_Model_Payment::TYPE_AUTHORIZE_CAPTURE);
		$request->setPayment($payment);
		$request->setAmount( $this->_formatAmount($profile->getInitAmount()) );
		$request->setInfoInstance($payment);
		$request->setRecurringProfile($profile);
		$request->setIs3ds( $conf['payment_3ds'] ? true : false );
		$response = $request->post();

		if ($response->simpleResponseValue('result') == 'SUCCESS' || $response->simpleResponseValue('result') == 'PENDING'){
			
			$payment->setCcTransId( $response->simpleResponseValue('merchanttransid') );
			$payment->setLastTransId ( $response->simpleResponseValue('merchanttransid') );
			$payment->setSkipTransactionCreation(true);
			
			$additionalInfo = $profile->getAdditionalInfo() ? $profile->getAdditionalInfo() : array();
			$additionalInfo['rebillsecret'] = $response->simpleResponseValue('rebillsecret');
			
			if ($response->simpleResponseValue('ACS')){
				Mage::helper('gpndata')->prepare3ds($response);
				$additionalInfo['md'] = (string)$response->getXml()->parameters->MD;
			}
			
			$profile->setReferenceId( $response->simpleResponseValue('merchanttransid') );
			$profile->setAdditionalInfo( serialize($additionalInfo));
			
			if ($response->simpleResponseValue('result') == 'PENDING') {
				$profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_PENDING);
			} else {
				$profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
			}
			
			return $this;
			
		} else {
			
			if ($profile->getInitMayFail()){
				$profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
				$profile->save();
			}
			
			if ($response->getErrorMsg())
				Mage::log($response->getErrorMsg(), null, 'gpndatalog.log');
			Mage::throwException( 'Gateway response: ' . $response->simpleResponseValue('result') );
		}
	}

    /**
     * Fetch details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result){
		return $this;
	}

    /**
     * Check whether can get recurring profile details
     *
     * @return bool
     */
    public function canGetRecurringProfileDetails(){
		return true;
	}

    /**
     * Update data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){
		return $this;
	}

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile){
		$action = null;
		switch ($profile->getNewState()) {
			case Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE:		$action = 'start'; break;
			case Mage_Sales_Model_Recurring_Profile::STATE_CANCELED:	$action = 'cancel'; break;
			case Mage_Sales_Model_Recurring_Profile::STATE_EXPIRED:
			case Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED:	$action = 'stop'; break;
			default: return $this;
		}
		$profileAdditionalInfo = $profile->getAdditionalInfo();
		
		$request = $this->_getRequest(755);
		$request->setAction($action);
		$request->setGateTransId($profileAdditionalInfo['gatetransid']);
		$request->setMerchantTransId($profile->getId());
		$response = $request->post();
	
		if ($response->simpleResponseValue('result') == 'SUCCESS'){
			$profileAdditionalInfo['transref'] = $response->simpleResponseValue('transref');
			// set additional start date if active for cron billing
			if ($action == 'start'){
				$profile->setUpdatedAt(date('Y-m-d H:i:s'));
			}
			$profile->setAdditionalInfo(serialize($profileAdditionalInfo));
			$profile->save();
			return $this;
		}
		Mage::throwException($response->simpleResponseValue('errormessage'));
	}
	
	/**
	 * Cron will call this method for profiles that should be charged
	 * 
	 * @param Mage_Payment_Model_Recurring_Profile $profile
	 */
    public function chargeRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){
		$profileAdditionalInfo = is_string($profile->getAdditionalInfo()) ? unserialize($profile->getAdditionalInfo()) : $profile->getAdditionalInfo();
		
		// send 756 - Manual Rebill Request
		$request = $this->_getRequest(756);
		$request->setAmount( $this->_formatAmount($profile->getTaxAmount() + $profile->getBillingAmount() + $profile->getShippingAmount()) );
		$request->setGateTransId($profileAdditionalInfo['gatetransid']);
		$request->setRebillsecret($profileAdditionalInfo['rebillsecret']);
		$request->setMerchanttransid($profile->getId() . '-' . uniqid() . '-rebill');
		$response = $request->post();
		
		if ($response->simpleResponseValue('result') == 'SUCCESS'){
			// change updated_at to one cycle ahead
			$this->_setUpdateDateToNextPeriod($profile->getId());
			return true;
		}
		
		return false;
	}
	
	
	protected function _setUpdateDateToNextPeriod($profile_id){
		$_resource = Mage::getSingleton('core/resource');
		$sql = '
			UPDATE '.$_resource->getTableName('sales_recurring_profile').'
			SET updated_at = CASE period_unit
				WHEN "day" 			THEN DATE_ADD(updated_at, INTERVAL period_frequency DAY)
				WHEN "week" 		THEN DATE_ADD(updated_at, INTERVAL (period_frequency*7) DAY)
				WHEN "semi_month" 	THEN DATE_ADD(updated_at, INTERVAL (period_frequency*14) DAY)
				WHEN "month" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency MONTH)
				WHEN "year" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency YEAR)
			END
			WHERE profile_id = :pid';
		
		$connection = $_resource->getConnection('core_write');
		$pdoStatement = $connection->prepare($sql);
		$pdoStatement->bindValue(':pid', $profile_id);
		return $pdoStatement->execute();
	}
	
	/**
     * Round up and cast specified amount to float or string
	 * @todo move it to the helper
     *
     * @param string|float $amount
     * @param bool $asFloat
     * @return string|float
     */
    protected function _formatAmount($amount, $asFloat = false){
		$amount = sprintf('%.2F', $amount); // "f" depends on locale, "F" doesn't
		return $asFloat ? (float)$amount : $amount;
	}
	
	protected function _getRequest($code){
		$configValue = Mage::getStoreConfig('payment/gpndatarecurring');
		
		$configValue['password'] = Mage::helper('core')->decrypt($configValue['password']);
		$configValue['api_key'] = Mage::helper('core')->decrypt($configValue['api_key']);
		
		$request = Mage::getModel('gpndata/request_' . $code);
		$request->initRequest($configValue);
		
		return $request;
	}
	
}