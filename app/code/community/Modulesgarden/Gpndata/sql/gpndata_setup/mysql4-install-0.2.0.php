<?php

/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-03-31, 09:54:46)
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


$installer = $this;
$installer->startSetup();

$installer->addAttribute("order", "gpndata_md", array(
	'type'          => 'varchar',
	'backend_type'  => 'text',
	'is_user_defined' => false,
	'label'         => 'GPN Data MD',
	'visible'       => false,
	'required'      => false,
	'user_defined'  => false,
	'searchable'    => false,
	'filterable'    => false,
	'comparable'    => false,
	'default'       => ''
));

$installer->endSetup();