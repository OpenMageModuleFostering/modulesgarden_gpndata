<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-11, 11:52:44)
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
 * 755 - Update Rebill Instructions
 */
class Modulesgarden_Gpndata_Model_Request_755 extends Modulesgarden_Gpndata_Model_Request {
	
	protected $_amount = '';
	protected $_merchanttransid;
	protected $_gatetransid;
	protected $_action;
	
	public function setAction($action){
		$this->_action = $action;
	}
	
	public function setAmount($amount){
		$this->_amount = $amount;
	}
	
	public function setGateTransId($gatetransid){
		$this->_gatetransid = $gatetransid;
	}
	
	public function setMerchantTransId ($merchanttransid){
		$this->_merchanttransid = $merchanttransid;
	}
	
	public function getGateTransId(){
		return $this->_gatetransid;
	}
	
	public function post(){
		$this->_xml->addChild('apiCmd', '755');
		
		$this->_xml->addChild('merchanttransid', $this->_merchanttransid);
		$this->_xml->addChild('gatetransid', $this->_gatetransid);
		$this->_xml->addChild('action', $this->_action); // stop|start|changeamount|changecount
		$this->_xml->addChild('amount', $this->_amount);
		$this->_xml->addChild('checksum', $this->calculateChecksum());
		
		return parent::post();
	}
	
	protected function calculateChecksum(){
		return sha1(
			$this->_config['username'] .
			$this->_config['password'] .
			'755' .
			$this->_merchanttransid .
			$this->_gatetransid .
			$this->_config['api_key']
		);
	}
	
}

//<transaction>
//	<apiUser></apiUser>
//	<apiPassword></apiPassword>
//	<apiCmd>755</apiCmd>
//	<merchanttransid></merchanttransid>
//	<gatetransid></gatetransid>
//	<action></action>
//	<amount></amount>
//	<checksum></checksum>
//</transaction>
