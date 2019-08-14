<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-01-27, 12:48:54)
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

class Modulesgarden_Gpndata_Model_Request {

	protected $_client;
	protected $_xml;
	protected $_config;
	
	public function initRequest(array $config){
		
		$this->_config = $config;
		
		$this->_client = new Zend_Http_Client($this->_getApiUrl(), array(
			'maxredirects' => 0,
			'timeout'      => 30
		));
		
		$this->_xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><transaction/>');
		$this->_xml->addChild('apiUser', $config['username']);
		$this->_xml->addChild('apiPassword', $config['password']);
	}
	
	public function post(){
		$this->_client->setParameterPost(array(
			'strrequest' => $this->_xml->asXML()
		));
		$response = $this->_client->request('POST');
		$responseGpndata = Mage::getModel('gpndata/response')->setResponse($response);
		
//		Mage::log('REQUEST: ' . $this->getXml()->asXML(), null, 'gpndata.log');
//		Mage::log('RESPONSE: ' . $responseGpndata->getXml()->asXML(), null, 'gpndata.log');
		
		return $responseGpndata;
	}
	
	public function getXml(){
		return $this->_xml;
	}
	
	public function getHttpClient(){
		return $this->_client;
	}
	
	
	protected function _getApiUrl(){
		if (!isset($this->_config['submit_url']) || !$this->_config['submit_url']){
			Mage::throwException( Mage::helper('gpndata')->__('Payment method is not fully configured') );
		}
		
		if (strpos($this->_config['submit_url'], 'http') !== 0)
			$this->_config['submit_url'] = 'https://' . $this->_config['submit_url'];
		
		return $this->_config['submit_url'];
	}
	
}