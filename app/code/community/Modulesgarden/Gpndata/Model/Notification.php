<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-04, 09:32:27)
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

abstract class Modulesgarden_Gpndata_Model_Notification {

	protected $_notificationXml;
	
	abstract public function isChecksumOk($config);
	abstract public function process(Modulesgarden_Gpndata_Model_Notification_Response $response);
	
	public function setXml(SimpleXMLElement $xml){
		$this->_notificationXml = $xml;
	}
	
	public function getNotificationValue($key, $default = null){
		if (!$this->_notificationXml)
			Mage::throwException('Notification XML is not valid');
		
		return isset($this->_notificationXml->$key) ? (string)$this->_notificationXml->$key : $default;
	}
	
	public static function factory($xml_string){
		$notificationXml = simplexml_load_string($xml_string);
		if (!$notificationXml)
			Mage::throwException('Notification XML is not valid');
		
		$obj = Mage::getModel('gpndata/notification_' . (string)$notificationXml->apiCmd);
		if (!$obj)
			Mage::throwException('Notification type is not implemented yet');
		
		$obj->setXml($notificationXml);
		return $obj;
	}
	
	public static function isProperIp($clientIp, $gateway_url){
		$parse = parse_url($gateway_url);
		$gateway_ip = gethostbyname($parse['host']);
		
		return $clientIp == $gateway_ip;
	}
	
	protected function _parseMerchantTransId($merchanttransid){
		$sub_merchanttransid = substr($merchanttransid, 8);
		$fp = strpos($sub_merchanttransid, '_');
		$lp = strrpos($sub_merchanttransid, '_');
		if (!$fp || !$lp)
			Mage::throwException('Merchant Transaction Id is not valid');
		
		return array(
			'order_id'	=> substr($sub_merchanttransid, 0, $fp),
			'payment_id'=> substr($sub_merchanttransid, $fp+1, $lp-$fp-1),
		);
	}
	
}