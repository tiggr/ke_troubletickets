<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA["tx_ketroubletickets_tickets"] = array (
	"ctrl" => $TCA["tx_ketroubletickets_tickets"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "sys_language_uid,l18n_parent,l18n_diffsource,hidden,starttime,endtime,fe_group,title,description,until_date,owner_feuser,responsible_feuser,observers_feuser,externalobservers,category,status,priority,notifications_owner,notifications_responsible,notifications_observer,files,time_used,from_date,time_planned,billing,related_tickets,progress"
	),
	"feInterface" => $TCA["tx_ketroubletickets_tickets"]["feInterface"],
	"columns" => array (
		't3ver_label' => array (
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.versionLabel',
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '30',
			)
		),
		'sys_language_uid' => array (
			'exclude' => 1,
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
			'config' => array (
				'type'                => 'select',
				'foreign_table'       => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.xml:LGL.allLanguages', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.default_value', 0)
				)
			)
		),
		'l18n_parent' => array (
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude'     => 1,
			'label'       => 'LLL:EXT:lang/locallang_general.xml:LGL.l18n_parent',
			'config'      => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
				),
				'foreign_table'       => 'tx_ketroubletickets_tickets',
				'foreign_table_where' => 'AND tx_ketroubletickets_tickets.pid=###CURRENT_PID### AND tx_ketroubletickets_tickets.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => array (
			'config' => array (
				'type' => 'passthrough'
			)
		),
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'starttime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.starttime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'default'  => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.endtime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'checkbox' => '0',
				'default'  => '0',
				'range'    => array (
					'upper' => mktime(0, 0, 0, 12, 31, 2020),
					'lower' => mktime(0, 0, 0, date('m')-1, date('d'), date('Y'))
				)
			)
		),
		'fe_group' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.fe_group',
			'config'  => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
					array('LLL:EXT:lang/locallang_general.xml:LGL.hide_at_login', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.any_login', -2),
					array('LLL:EXT:lang/locallang_general.xml:LGL.usergroups', '--div--')
				),
				'foreign_table' => 'fe_groups'
			)
		),
		"title" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.title",
			"config" => Array (
				"type" => "input",
				"size" => "30",
				"eval" => "required",
			)
		),
		"description" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.description",
			"config" => Array (
				"type" => "text",
				"cols" => "30",
				"rows" => "5",
				"wizards" => Array(
					"_PADDING" => 2,
					"RTE" => array(
						"notNewRecords" => 1,
						"RTEonly" => 1,
						"type" => "script",
						"title" => "Full screen Rich Text Editing|Formatteret redigering i hele vinduet",
						"icon" => "wizard_rte2.gif",
						"script" => "wizard_rte.php",
					),
				),
			)
		),
		"until_date" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.until_date",
			"config" => Array (
				"type"     => "input",
				"size"     => "8",
				"max"      => "20",
				"eval"     => "date",
				"checkbox" => "0",
				"default"  => "0"
			)
		),
		"from_date" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.from_date",
			"config" => Array (
				"type"     => "input",
				"size"     => "8",
				"max"      => "20",
				"eval"     => "date",
				"checkbox" => "0",
				"default"  => "0"
			)
		),
		"owner_feuser" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.owner_feuser",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "fe_users",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"responsible_feuser" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.responsible_feuser",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "fe_users",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"observers_feuser" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.observers_feuser",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "fe_users",
				"size" => 5,
				"minitems" => 0,
				"maxitems" => 50,
			)
		),
		"externalobservers" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.externalobservers",
			"config" => Array (
			    "type" => "text",
			    "cols" => "30",
			    "rows" => "5",
			)
		),
		"category" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.category",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "tx_ketroubletickets_categories",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"status" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.status",
			"config" => Array (
				"type" => "select",
				"items" => Array (
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_OPEN", "open"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_ACKNOWLEDGED", "acknowledged"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_WORKING", "working"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_CLOSED", "closed"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_WAIT", "wait"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_OFFER", "offer"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_ONHOLD", "onhold"),
				),
				"size" => 1,
				"maxitems" => 1,
			)
		),
		"billing" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.billing",
			"config" => Array (
				"type" => "select",
				"items" => Array (
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_OPEN", "open"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_SERVICE", "service"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_INVOICE", "invoice"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_SERVICE_INVOICE", "service_invoice"),
				),
				"size" => 1,
				"maxitems" => 1,
			)
		),
		"priority" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.priority",
			"config" => Array (
				"type" => "select",
				"items" => Array (
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_2", "2"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_1", "1"),
					Array("LLL:EXT:ke_troubletickets/pi1/locallang.xml:SELECTLABEL_3", "3"),
				),
				"size" => 1,
				"maxitems" => 1,
			)
		),
		"notifications_owner" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_owner",
			"config" => Array (
				"type" => "select",
				"items" => Array (
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_owner.I.0", "never"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_owner.I.1", "oneverychange"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_owner.I.2", "onstatuschange"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_owner.I.3", "typoscript"),
				),
				"size" => 1,
				"maxitems" => 1,
			)
		),
		"notifications_responsible" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_responsible",
			"config" => Array (
				"type" => "select",
				"items" => Array (
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_responsible.I.0", "never"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_responsible.I.1", "oneverychange"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_responsible.I.2", "onstatuschange"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_responsible.I.3", "typoscript"),
				),
				"size" => 1,
				"maxitems" => 1,
			)
		),
		"notifications_observer" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_observer",
			"config" => Array (
				"type" => "select",
				"items" => Array (
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_observer.I.0", "never"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_observer.I.1", "oneverychange"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_observer.I.2", "onstatuschange"),
					Array("LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.notifications_observer.I.3", "typoscript"),
				),
				"size" => 1,
				"maxitems" => 1,
			)
		),
		"files" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.files",
			"config" => Array (
				"type" => "group",
				"internal_type" => "file",
				"allowed" => "",
				"disallowed" => "php,php3",
				"max_size" => 20000,
				"uploadfolder" => "uploads/tx_ketroubletickets",
				"size" => 5,
				"minitems" => 0,
				"maxitems" => 200,
				"show_thumbs" => 1,
			)
		),
		"time_used" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.time_used",
			"config" => Array (
				"type" => "input",
				"size" => "5",
				"eval" => "int",
			)
		),
		"time_planned" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.time_planned",
			"config" => Array (
				"type" => "input",
				"size" => "5",
				"eval" => "int",
			)
		),
		"effort" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.effort",
			"config" => Array (
				"type" => "input",
				"size" => "10",
				"eval" => "",
			)
		),
		'close_time' => array (
			'exclude' => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.close_time",
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'datetime',
				'default'  => '0',
				'checkbox' => '0'
			)
		),
		"related_tickets" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.related_tickets",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "tx_ketroubletickets_tickets",
				"size" => 5,
				"minitems" => 0,
				"maxitems" => 100,
			)
		),
		"additional_info" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.additional_info",
			"config" => Array (
				"type" => "text",
				"cols" => "30",
				"rows" => "5",
			)
		),
		"progress" => Array(
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets.progress",
			"config" => Array (
				"type" => "none",
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "sys_language_uid;;;;1-1-1, l18n_parent, l18n_diffsource, hidden;;1, title;;;;2-2-2, description;;;richtext[cut|copy|paste|formatblock|textcolor|bold|italic|underline|left|center|right|orderedlist|unorderedlist|outdent|indent|link|table|image|line|chMode]:rte_transform[mode=ts_css|imgpath=uploads/tx_ketroubletickets/rte/];3-3-3, from_date, until_date, owner_feuser, responsible_feuser, observers_feuser, externalobservers, category, status, billing, priority, notifications_owner, notifications_responsible, notifications_observer, files, related_tickets, time_planned, time_used, close_time, effort, additional_info, progress")
	),
	"palettes" => array (
		"1" => array("showitem" => "starttime, endtime, fe_group")
	)
);



$TCA["tx_ketroubletickets_comments"] = array (
	"ctrl" => $TCA["tx_ketroubletickets_comments"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "sys_language_uid,l18n_parent,l18n_diffsource,hidden,starttime,endtime,fe_group,ticket_uid,feuser_uid,content"
	),
	"feInterface" => $TCA["tx_ketroubletickets_comments"]["feInterface"],
	"columns" => array (
		't3ver_label' => array (
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.versionLabel',
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '30',
			)
		),
		'sys_language_uid' => array (
			'exclude' => 1,
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
			'config' => array (
				'type'                => 'select',
				'foreign_table'       => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.xml:LGL.allLanguages', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.default_value', 0)
				)
			)
		),
		'l18n_parent' => array (
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude'     => 1,
			'label'       => 'LLL:EXT:lang/locallang_general.xml:LGL.l18n_parent',
			'config'      => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
				),
				'foreign_table'       => 'tx_ketroubletickets_comments',
				'foreign_table_where' => 'AND tx_ketroubletickets_comments.pid=###CURRENT_PID### AND tx_ketroubletickets_comments.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => array (
			'config' => array (
				'type' => 'passthrough'
			)
		),
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'starttime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.starttime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'default'  => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.endtime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'checkbox' => '0',
				'default'  => '0',
				'range'    => array (
					'upper' => mktime(0, 0, 0, 12, 31, 2020),
					'lower' => mktime(0, 0, 0, date('m')-1, date('d'), date('Y'))
				)
			)
		),
		'fe_group' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.fe_group',
			'config'  => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
					array('LLL:EXT:lang/locallang_general.xml:LGL.hide_at_login', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.any_login', -2),
					array('LLL:EXT:lang/locallang_general.xml:LGL.usergroups', '--div--')
				),
				'foreign_table' => 'fe_groups'
			)
		),
		"ticket_uid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_comments.ticket_uid",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "tx_ketroubletickets_tickets",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"feuser_uid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_comments.feuser_uid",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "fe_users",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"content" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_comments.content",
			"config" => Array (
				"type" => "text",
				"cols" => "30",
				"rows" => "5",
				"wizards" => Array(
					"_PADDING" => 2,
					"RTE" => array(
						"notNewRecords" => 1,
						"RTEonly" => 1,
						"type" => "script",
						"title" => "Full screen Rich Text Editing|Formatteret redigering i hele vinduet",
						"icon" => "wizard_rte2.gif",
						"script" => "wizard_rte.php",
					),
				),
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "sys_language_uid;;;;1-1-1, l18n_parent, l18n_diffsource, hidden;;1, ticket_uid, feuser_uid, content;;;richtext[cut|copy|paste|formatblock|textcolor|bold|italic|underline|left|center|right|orderedlist|unorderedlist|outdent|indent|link|table|image|line|chMode]:rte_transform[mode=ts_css|imgpath=uploads/tx_ketroubletickets/rte/]")
	),
	"palettes" => array (
		"1" => array("showitem" => "starttime, endtime, fe_group")
	)
);



$TCA["tx_ketroubletickets_categories"] = array (
	"ctrl" => $TCA["tx_ketroubletickets_categories"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "sys_language_uid,l18n_parent,l18n_diffsource,hidden,starttime,endtime,fe_group,title,singleviewpage"
	),
	"feInterface" => $TCA["tx_ketroubletickets_categories"]["feInterface"],
	"columns" => array (
		't3ver_label' => array (
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.versionLabel',
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '30',
			)
		),
		'sys_language_uid' => array (
			'exclude' => 1,
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
			'config' => array (
				'type'                => 'select',
				'foreign_table'       => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.xml:LGL.allLanguages', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.default_value', 0)
				)
			)
		),
		'l18n_parent' => array (
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude'     => 1,
			'label'       => 'LLL:EXT:lang/locallang_general.xml:LGL.l18n_parent',
			'config'      => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
				),
				'foreign_table'       => 'tx_ketroubletickets_categories',
				'foreign_table_where' => 'AND tx_ketroubletickets_categories.pid=###CURRENT_PID### AND tx_ketroubletickets_categories.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => array (
			'config' => array (
				'type' => 'passthrough'
			)
		),
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'starttime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.starttime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'default'  => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.endtime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'checkbox' => '0',
				'default'  => '0',
				'range'    => array (
					'upper' => mktime(0, 0, 0, 12, 31, 2020),
					'lower' => mktime(0, 0, 0, date('m')-1, date('d'), date('Y'))
				)
			)
		),
		'fe_group' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.fe_group',
			'config'  => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
					array('LLL:EXT:lang/locallang_general.xml:LGL.hide_at_login', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.any_login', -2),
					array('LLL:EXT:lang/locallang_general.xml:LGL.usergroups', '--div--')
				),
				'foreign_table' => 'fe_groups'
			)
		),
		"title" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_categories.title",
			"config" => Array (
				"type" => "input",
				"size" => "30",
			)
		),
		"singleviewpage" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_categories.singleviewpage",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "pages",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "sys_language_uid;;;;1-1-1, l18n_parent, l18n_diffsource, hidden;;1, title;;;;2-2-2,singleviewpage")
	),
	"palettes" => array (
		"1" => array("showitem" => "starttime, endtime, fe_group")
	)
);



$TCA["tx_ketroubletickets_history"] = array (
	"ctrl" => $TCA["tx_ketroubletickets_history"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "ticket_uid,feuser_uid,databasefield,feuser_username,value_old,value_new"
	),
	"feInterface" => $TCA["tx_ketroubletickets_history"]["feInterface"],
	"columns" => array (
		"ticket_uid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_history.ticket_uid",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "tx_ketroubletickets_tickets",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"feuser_uid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_history.feuser_uid",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "fe_users",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"databasefield" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_history.databasefield",
			"config" => Array (
				"type" => "none",
			)
		),
		"feuser_username" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_history.feuser_username",
			"config" => Array (
				"type" => "none",
			)
		),
		"value_old" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_history.value_old",
			"config" => Array (
				"type" => "none",
			)
		),
		"value_new" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_history.value_new",
			"config" => Array (
				"type" => "none",
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "ticket_uid;;;;1-1-1, feuser_uid, databasefield, feuser_username, value_old, value_new")
	),
	"palettes" => array (
		"1" => array("showitem" => "")
	)
);

$TCA["tx_ketroubletickets_todo"] = array (
	"ctrl" => $TCA["tx_ketroubletickets_todo"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "ticket_uid,title,done"
	),
	"feInterface" => $TCA["tx_ketroubletickets_todo"]["feInterface"],
	"columns" => array (
		"ticket_uid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_todo.ticket_uid",
			"config" => Array (
				"type" => "group",
				"internal_type" => "db",
				"allowed" => "tx_ketroubletickets_tickets",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"title" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_todo.title",
			"config" => Array (
				"type" => "input",
				"size" => "30",
			)
		),
		"done" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_todo.done",
			"config" => Array (
				"type" => "check",
			)
		
		),
	),
	"types" => array (
		"0" => array("showitem" => "ticket_uid;;;;1-1-1, title, done")
	),
	"palettes" => array (
		"1" => array("showitem" => "")
	)
);


?>
