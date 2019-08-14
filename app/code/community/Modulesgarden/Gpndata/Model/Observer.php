<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-19, 09:27:40)
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

class Modulesgarden_Gpndata_Model_Observer {

	/**
     * Cron job method to charge recurring profiles
     *
     * @param Mage_Cron_Model_Schedule $schedule
     */
	public function chargeRecurringProfiles(Mage_Cron_Model_Schedule $schedule){
		if (!Mage::getStoreConfig('payment/gpndatarecurring/activecron'))
			return false;
		
		$_resource = Mage::getSingleton('core/resource');
		$sql = '
			SELECT
				CASE srp.period_unit
					WHEN "day" 			THEN FLOOR(DATEDIFF(NOW(), srp.updated_at) / srp.period_frequency)
					WHEN "week" 		THEN FLOOR(FLOOR(DATEDIFF(NOW(), srp.updated_at) / 7) / srp.period_frequency)
					WHEN "semi_month" 	THEN FLOOR(FLOOR(DATEDIFF(NOW(), srp.updated_at) / 14) / srp.period_frequency)
					WHEN "month" 		THEN FLOOR(PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(srp.updated_at, "%Y%m")) - (DATE_FORMAT(NOW(), "%d") < DATE_FORMAT(srp.updated_at, "%d")) / srp.period_frequency)
					WHEN "year" 		THEN FLOOR(YEAR(NOW()) - YEAR(srp.updated_at) - (DATE_FORMAT(NOW(), "%m%d") < DATE_FORMAT(srp.updated_at, "%m%d")) / srp.period_frequency)
				END
				AS billing_count,
				srp.*
			FROM '.$_resource->getTableName('sales_recurring_profile').' AS srp
			WHERE
				srp.method_code = "gpndatarecurring" AND
				srp.state = "active" AND
				srp.updated_at <= NOW() AND
				srp.start_datetime <= NOW() AND
				(
					(
						srp.start_datetime > srp.updated_at AND
						srp.start_datetime <= NOW()
					)
					OR
					(
						srp.start_datetime <= srp.updated_at AND
						NOW() >= CASE srp.period_unit
							WHEN "day" 			THEN DATE_ADD(srp.updated_at, INTERVAL srp.period_frequency DAY)
							WHEN "week" 		THEN DATE_ADD(srp.updated_at, INTERVAL srp.period_frequency WEEK)
							WHEN "semi_month" 	THEN DATE_ADD(srp.updated_at, INTERVAL (srp.period_frequency * 2) WEEK)
							WHEN "month" 		THEN DATE_ADD(srp.updated_at, INTERVAL srp.period_frequency MONTH)
							WHEN "year" 		THEN DATE_ADD(srp.updated_at, INTERVAL srp.period_frequency YEAR)
						END
					)
				)
		';
		
		$connection = $_resource->getConnection('core_read');
		$recurring = Mage::getModel('gpndata/paymentrecurring');
		
		foreach ($connection->fetchAll($sql) as $profileArr) {
			
			$profile = Mage::getModel('sales/recurring_profile')->addData($profileArr);
			$orders = $profile->getResource()->getChildOrderIds($profile);
			$countBillingCycling = count($orders);
			if ($profile->getInitAmount())
				$countBillingCycling--;
			
			if ($profile->getBillFailedLater()){ // Auto Bill on Next Cycle
				// multi charges
				for ($i = 0; $i < $profile->getBillingCount(); $i++){
					if ($recurring->chargeRecurringProfile($profile)){
						$countBillingCycling++;
					} else {
						break;
					}
					
					if ($profile->getPeriodMaxCycles() && $countBillingCycling >= $profile->getPeriodMaxCycles()){
						$profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
						break;
					}
				}
				
			} else {
				// single charge
				if ($recurring->chargeRecurringProfile($profile))
					$countBillingCycling++;
				
				if ($profile->getPeriodMaxCycles() && $countBillingCycling >= $profile->getPeriodMaxCycles())
					$profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
			}
		}
    }
	
	/**
	 * before admin/sales_recurring_profile/view/profile/PROFILE_ID
	 * @param Varien_Event_Observer $observer
	 */
	public function manualRebill(Varien_Event_Observer $observer){
		$r				= Mage::app()->getRequest();
		$isManualRebill = (bool)$r->getParam('manual_rebill', false);
		$profile_id		= (int)$r->getParam('profile');
		$session		= Mage::getSingleton('adminhtml/session');
		$helper			= Mage::helper('gpndata');
		
		if ($isManualRebill && $profile_id){
			$recurring = Mage::getModel('gpndata/paymentrecurring');
			$profile = Mage::getModel('sales/recurring_profile')->load($profile_id);
			
			if ($profile->getMethodCode() == 'gpndatarecurring'){
				
				$orders = $profile->getResource()->getChildOrderIds($profile);
				$countBillingCycling = count($orders);
				if ($profile->getInitAmount())
					$countBillingCycling--;
				
				if ($recurring->chargeRecurringProfile($profile)){
					$countBillingCycling++;
					
					if ($profile->getPeriodMaxCycles() && $countBillingCycling >= $profile->getPeriodMaxCycles()){
						$profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
						$profile->save();
					}
					$session->addSuccess($helper->__('Recurring Profile has been charged.'));
					
				} else {
					$session->addError($helper->__('Error during charging recurring profile.'));
				}
				
				
			} else {
				$session->addError($helper->__('This is not GPN DATA gateway. Unable to proceed.'));
			}
			
			Mage::app()->getFrontController()->getResponse()->setRedirect(
				Mage::helper('adminhtml')->getUrl('adminhtml/sales_recurring_profile/view', array('profile' => $profile_id))
			);
			Mage::app()->getResponse()->sendResponse();
			exit;
		}
	}
	
	/**
	 * This is fix for magento bug: in Mage_Checkout_Model_Type_Onepage::saveOrder it does not redirect to any url (3DS)
	 * @param Varien_Event_Observer $observer
	 */
	public function checkout_submit_all_after($observer){
		$session = Mage::getSingleton('checkout/session');
		$url = Mage::getModel('gpndata/paymentrecurring')->getOrderPlaceRedirectUrl();
		if ($session->getLastRecurringProfileIds() && $url){
			$session->setRedirectUrl($url);
		}
	}
	
}