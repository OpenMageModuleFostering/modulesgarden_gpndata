<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-04, 09:20:57)
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

class Modulesgarden_Gpndata_NotificationsController extends Mage_Core_Controller_Front_Action {
	
	public function indexAction(){
		
		$response		= Mage::getModel('gpndata/notification_response');
		$xml_string		= $this->getRequest()->getPost('strdata');
//		Mage::log('NOTIFICATION REQUEST: '.$xml_string, null, 'gpndata.log');
		
		try {
			$notification = Modulesgarden_Gpndata_Model_Notification::factory($xml_string);

			// rebill
			if ($notification instanceof Modulesgarden_Gpndata_Model_Notification_870 || ($notification instanceof Modulesgarden_Gpndata_Model_Notification_850 && $notification->isRebill())){
				$conf = Mage::getStoreConfig('payment/gpndatarecurring');
			} else { // single
				$conf = Mage::getStoreConfig('payment/gpndata');
			}
			
			$conf['password']	= Mage::helper('core')->decrypt($conf['password']);
			$conf['api_key']	= Mage::helper('core')->decrypt($conf['api_key']);
			$valid = $notification->isChecksumOk($conf);
			
			if (!$valid){
				$response
					->setResult('ERROR')
					->setErrorcode(403)
					->setErrormessage('Checksum is not valid');
				
			} else { // notification ok
				$notification->process($response);
				
			}
			
		} catch (Exception $e){
			$response
				->setResult('ERROR')
				->setErrorcode(500)
				->setErrormessage($e->getMessage());
		}
		
		$xml = $response->getXml();
//		Mage::log('NOTIFICATION RESPONSE: '.$xml, null, 'gpndata.log');
		
		header('Content-type: application/xml; charset="utf-8"');
		echo $xml;
		die();
	}
	
	public function acsAction(){
		$inputPost = base64_encode(file_get_contents('php://input'));
		
		if (!$inputPost){
			Mage::app()->getFrontController()->getResponse()->setRedirect( Mage::getUrl('/') );
			Mage::app()->getResponse()->sendResponse();
			exit;
		}
		
//		Mage::log('ACS INCOMING: '.$inputPost, null, 'gpndata.log');
		
		$sid = $this->getRequest()->getParam('sid');
		if (is_numeric($sid)){ // order
			$md = Mage::getModel('sales/order')->load($sid)->getGpndataMd();
			$configValue = Mage::getStoreConfig('payment/gpndata');
			
		} else { // recurring profile
			$profile = Mage::getModel('sales/recurring_profile')->load(substr($sid,1));
			$additionalInfo = $profile->getAdditionalInfo() ? $profile->getAdditionalInfo() : array();
			$md = isset($additionalInfo['md']) ? $additionalInfo['md'] : '';
			$configValue = Mage::getStoreConfig('payment/gpndatarecurring');
		}
		
		$configValue['password']= Mage::helper('core')->decrypt($configValue['password']);
		$configValue['api_key']	= Mage::helper('core')->decrypt($configValue['api_key']);
		
		try {
			$request = Mage::getModel('gpndata/request_705');
			$request->initRequest($configValue);

			$request->setType( $configValue['payment_action'] == 'authorize' ? 'Auth3DS' : '3DS' );
			$request->setACSRes( $inputPost );
			$request->setMD( $md );

			$response = $request->post();
			
			$session = Mage::getSingleton('core/session');
			switch ($response->getResult()){
				case 'SUCCESS':	$session->addSuccess($this->__('Payment Successful! Credit Card account has been charged with the amount of the transaction.')); break;
				case 'PENDING':	$session->addSuccess($this->__('Payment Approved!')); break;
				case 'DECLINED':$session->addError($this->__('Payment declined! Transaction has been declined.')); break;
				case 'ERROR':	$session->addError($this->__('Payment error! The transaction has NOT been accepted by the gateway and thus no processing has taken place.')); break;
			}
			
		} catch (Exception $e){
			
		}
		
		$this->_redirect('/');
	}
	
}