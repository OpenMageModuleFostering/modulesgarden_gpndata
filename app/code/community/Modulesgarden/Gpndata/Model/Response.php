<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-01-27, 15:29:57)
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

class Modulesgarden_Gpndata_Model_Response {

	protected $_response;
	protected $_xml;
	
	public function setResponse(Zend_Http_Response $response){
		$this->_response = $response;
		
		if ($this->_response->getStatus() != 200)
			Mage::throwException('Response is not valid: HTTP ' . $this->_response->getStatus());
		
		$this->_xml = new SimpleXMLElement( $this->_response->getBody() );
		
		return $this;
	}
	
	public function getXml(){
		return $this->_xml;
	}
	
	public function isNotError(){
		$re = $this->simpleResponseValue('result');
		return isset($re) && $re != 'ERROR';
	}
	
	public function getResult(){			return $this->simpleResponseValue('result'); }
	public function getMerchantTransId(){	return $this->simpleResponseValue('merchanttransid'); }
	public function getErrorCode(){			return $this->simpleResponseValue('errorcode'); }
	public function getErrorMsg(){			return $this->simpleResponseValue('errormessage'); }
	public function getDescription(){		return $this->simpleResponseValue('description'); }
	public function getGateTransId(){		return $this->simpleResponseValue('gatetransid'); }
	
	
	public function simpleResponseValue($key, $default = null){
		if (!$this->_xml)
			Mage::throwException('Response XML is not valid');
		
		return isset($this->_xml->$key) ? (string)$this->_xml->$key : $default;
	}
	
}