<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-03-25, 13:47:34)
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
 * 705 - Request Capture Authorization
 */
class Modulesgarden_Gpndata_Model_Request_705 extends Modulesgarden_Gpndata_Model_Request {
	
	protected $_type;
	protected $_ACSRes;
	protected $_MD;
	
	/**
	 * This tag Must be present and the value for type must be: 3DS or Auth3DS
	 * @param string $type
	 */
	public function setType($type){
		$this->_type = $type;
	}
	
	/**
	 * This is the complete, raw POST data returned by the Issuing Bank ACS to the URL provided
	 * by the merchant in the redirect screen POST variable (TermUrl). It must be packed base64
	 * (example: if POST input data is “test=value123”, ACSRes will contain “dGVzdD12YWx1ZTEyMw==”). Raw POST
	 * data can be obtained in PHP with function file_get_contents('php://input')
	 * 
	 * @param type $ACSRes
	 */
	public function setACSRes($ACSRes){
		$this->_ACSRes = $ACSRes;
	}
	
	/**
	 * Authorization process ID as received from ACS server in POST "MD" field. Must be
	 * included in the request. It is equivalent to MD received in response to the 700 request which started the transaction.
	 * 
	 * @param type $MD
	 */
	public function setMD($MD){
		$this->_MD = $MD;
	}
	
	
	public function post(){
		$this->_xml->addChild('apiCmd', '705');
		
		$auth = $this->_xml->addChild('auth');
		$auth->addChild('type', $this->_type);
		$auth->addChild('ACSRes', $this->_ACSRes);
		$auth->addChild('MD', $this->_MD);
		
		$this->_xml->addChild('checksum', $this->calculateChecksum());
		
		return parent::post();
	}
	
	protected function calculateChecksum(){
		return sha1(
			$this->_config['username'] .
			$this->_config['password'] .
			'705' .
			$this->_type .
			$this->_MD .
			$this->_config['api_key']
		);
	}
	
}

//<transaction>
//	<apiUser></apiUser>
//	<apiPassword></apiPassword>
//	<apiCmd></apiCmd>
//	<auth>
//		<type></type>
//		<ACSRes></ACSRes>
//		<MD></MD>
//	</auth>
//	<checksum></checksum>
//</transaction>
