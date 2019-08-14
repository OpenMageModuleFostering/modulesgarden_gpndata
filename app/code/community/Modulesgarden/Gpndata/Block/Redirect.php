<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-02-07, 14:51:04)
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

class Modulesgarden_Gpndata_Block_Redirect extends Mage_Core_Block_Abstract {
	
	protected function _toHtml(){
		
		$session= Mage::getSingleton('core/session');
		$ACS	= $session->getAcs();
		
		$inputs = '';
		foreach ($session->getData() as $k => $value){
			$key = $this->_getValidParamKey($k);
			if ($key){
				$inputs .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
				$session->unsetData($k);
			}
		}
		
		$html = '
			<!DOCTYPE html>
			<html>
				<head>
					<meta http-equiv="Content-Type" content="text/html; Charset=UTF-8">
					<title>'.$this->__('3D-Secure Payment Transaction').'</title>
				</head>
				<body>
					'.$this->__('You will be redirected to the bank website in a few seconds.').'
					<form method="post" action="'.$ACS.'" id="gpndata_form">
						'.$inputs.'
						<noscript>
							<p>'.$this->__('JavaScript is currently disabled or is not supported by your browser. Please click Submit to continue the processing of your 3D-Secure Payment transaction.').'</p>
							<input type="submit" value="'.$this->__('Submit').'" />
						</noscript>
					</form>
					
					<script type="text/javascript">document.getElementById("gpndata_form").submit();</script>
				</body>
			</html>
		';
		
		$session->unsAcs();
		
		return $html;
	}
	
	protected function _getValidParamKey($key){
		if (substr($key, 0, 7) !== 'gpn3ds_')
			return null;
		
		return substr($key, 7);
	}
	
}