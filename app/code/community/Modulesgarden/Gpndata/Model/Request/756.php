<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-19, 15:09:53)
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
 * 756 - Manual Rebill Request
 * 
 * Request:
 * <transaction>
 *	<apiUser></apiUser>
 *	<apiPassword></apiPassword>
 *	<apiCmd>756</apiCmd>
 *	<gatetransid></gatetransid>
 *	<rebillsecret></rebillsecret>
 *	<transaction>
 *		<amount></amount>
 *		<merchanttransid></merchanttransid>
 *	</transaction>
 *	<checksum></checksum>
 * </transaction>
 * 
 * Response:
 * <transaction>
 *	<result></result>
 *	<merchanttransid></merchanttransid>
 *	<transref></transref>
 *	<errorcode></errorcode>
 *	<errormessage></errormessage>
 * </transaction> 
 */
class Modulesgarden_Gpndata_Model_Request_756 extends Modulesgarden_Gpndata_Model_Request {
	
	protected $_amount;
	protected $_gatetransid;
	protected $_merchanttransid;
	protected $_rebillsecret;
	
	public function setAmount($amount){
		$this->_amount = $amount;
	}
	
	public function setGateTransId($gatetransid){
		$this->_gatetransid = $gatetransid;
	}
	
	public function setRebillsecret($rebillsecret){
		$this->_rebillsecret = $rebillsecret;
	}
	
	public function setMerchanttransid($merchanttransid){
		$this->_merchanttransid = $merchanttransid;
	}
	
	public function post(){
		$this->_xml->addChild('apiCmd', '756');
		
		$this->_xml->addChild('gatetransid', $this->_gatetransid);
		$this->_xml->addChild('rebillsecret', $this->_rebillsecret);
		
		$transaction = $this->_xml->addChild('transaction');
		$transaction->addChild('amount', $this->_amount);
		$transaction->addChild('merchanttransid', $this->_merchanttransid);
		
		$this->_xml->addChild('checksum', $this->calculateChecksum());
		
		return parent::post();
	}
	
	protected function calculateChecksum(){
		return sha1(
			$this->_config['username'] .
			$this->_config['password'] .
			'756' .
			$this->_gatetransid .
			$this->_merchanttransid .
			$this->_config['api_key']
		);
	}
	
}
