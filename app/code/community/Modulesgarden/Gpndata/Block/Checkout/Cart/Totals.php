<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-09-02, 07:56:11)
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
 * Template: frontend/base/default/template/checkout/total/nominal.phtml
 */
class Modulesgarden_Gpndata_Block_Checkout_Cart_Totals extends Mage_Checkout_Block_Cart_Totals {
	
	public function getTotals() {
		if (is_null($this->_totals))
			parent::getTotals();
		
		if (Mage::getStoreConfig('payment/gpndatarecurring/hidecheckoutemptytotals')){
			foreach ($this->getQuote()->getAllItems() as $item) {
				if ($item->getProduct()->getIsRecurring()){
					foreach ($this->_totals as $k => $total) {
						if (in_array($total->getCode(), array('subtotal', 'grand_total', 'shipping')))
							unset($this->_totals[$k]);
					}
				}
				// magento allow to have only one recurring product at once in the cart
				break;
			}
		}
		return $this->_totals;
	}

}