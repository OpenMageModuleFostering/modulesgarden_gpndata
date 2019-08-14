<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-03, 12:45:10)
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
 * 701 - Request Capture Authorization
 */
class Modulesgarden_Gpndata_Model_Request_701 extends Modulesgarden_Gpndata_Model_Request {
	
	protected $_amount;
	protected $_gatetransid;
	
	public function setAmount($amount){
		$this->_amount = $amount;
	}
	
	public function setGateTransId($gatetransid){
		$this->_gatetransid = $gatetransid;
	}
	
	public function post(){
		$this->_xml->addChild('apiCmd', '701');
		
		$this->_xml->addChild('gatetransid', $this->_gatetransid);
		$this->_xml->addChild('amount', $this->_amount);
		$this->_xml->addChild('carrier', '');
		$this->_xml->addChild('trackingnumber', '');
		$this->_xml->addChild('checksum', $this->calculateChecksum());
		
		return parent::post();
	}
	
	protected function calculateChecksum(){
		return sha1(
			$this->_config['username'] .
			$this->_config['password'] .
			'701' .
			$this->_gatetransid .
			$this->_config['api_key']
		);
	}
	
}

//<transaction>
//	<apiUser></apiUser>
//	<apiPassword></apiPassword>
//	<apiCmd></apiCmd>
//	<gatetransid></gatetransid>
//	<amount></amount>
//	<carrier></carrier>
//	<trackingnumber></trackingnumber>
//	<checksum></checksum>
//</transaction>
