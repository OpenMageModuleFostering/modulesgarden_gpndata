<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-01-27, 14:14:51)
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

class Modulesgarden_Gpndata_Helper_Data extends Mage_Core_Helper_Abstract {

	public function prepare3ds(Modulesgarden_Gpndata_Model_Response $response){
		$session = Mage::getSingleton('core/session');
		$session->setAcs( $response->simpleResponseValue('ACS') );
		if ($response->getXml() && $response->getXml()->parameters){
			foreach ($response->getXml()->parameters->children() as $k => $v){
				$session->setData( 'gpn3ds_' . (string)$k, (string)$v );
			}
		}
	}
	
}