<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-04, 10:15:51)
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

class Modulesgarden_Gpndata_Model_Notification_Response extends Varien_Object {

	protected $_responseXml;
	
	public function __construct(){
		$this->_responseXml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><transaction/>');
	}
	
	public function getXml(){
		foreach ($this->getData() as $k => $v)
			$this->_responseXml->addChild($k, $v);
		
		return $this->_responseXml->asXML();
	}
	
}