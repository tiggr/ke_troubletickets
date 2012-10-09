<?php

/***************************************************************
* Extension Manager/Repository config file for ext "ke_troubletickets".
*
* Auto generated 24-09-2012 17:08
*
* Manual updates:
* Only the data in the array - everything else is removed by next
* writing. "version" and "dependencies" must not be touched!
***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Troubletickets',
	'description' => 'Manage your troubletickets, tasks or todo-lists or use the extension as a bugtracker, helpdesk or project management tool. Assign tickets to users, define observers, receive email notifications and group tickets into categories and projects.',
	'category' => 'fe',
	'shy' => 0,
	'version' => '1.4.0',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'mod1',
	'state' => 'stable',
	'uploadfolder' => 1,
	'createDirs' => 'uploads/tx_ketroubletickets/rte/',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'C. Buelter, C. Keramitsis, A. Kiefer (kennziffer.com)',
	'author_email' => 'buelter@kennziffer.com',
	'author_company' => 'www.kennziffer.com GmbH',
	'CGLcompliance' => NULL,
	'CGLcompliance_note' => NULL,
	'constraints' => 
	array (
		'depends' => 
		array (
			'cms' => '',
			'rtehtmlarea' => '',
		),
		'conflicts' => '',
		'suggests' => 
		array (
		),
	),
);

?>