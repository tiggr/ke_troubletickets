<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key';
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi2']='layout,select_key';

t3lib_extMgm::addPlugin(array('LLL:EXT:ke_troubletickets/locallang_db.xml:tt_content.list_type_pi1', $_EXTKEY.'_pi1'),'list_type');
t3lib_extMgm::addPlugin(array('LLL:EXT:ke_troubletickets/locallang_db.xml:tt_content.list_type_pi2', $_EXTKEY.'_pi2'),'list_type');

t3lib_extMgm::addStaticFile($_EXTKEY,'pi1/static/','Trouble Ticket System');

if (TYPO3_MODE == 'BE')	{
	$TBE_MODULES_EXT["xMOD_db_new_content_el"]["addElClasses"]["tx_ketroubletickets_pi1_wizicon"] = t3lib_extMgm::extPath($_EXTKEY).'pi1/class.tx_ketroubletickets_pi1_wizicon.php';
	t3lib_extMgm::addModule('web','txketroubleticketsM1','',t3lib_extMgm::extPath($_EXTKEY).'mod1/');
}

t3lib_extMgm::addToInsertRecords('tx_ketroubletickets_tickets');

	// Show FlexForm field in plugin configuration
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY . '_pi1'] = 'pi_flexform';

	// Configure FlexForm field
t3lib_extMgm::addPiFlexFormValue($_EXTKEY . '_pi1', 'FILE:EXT:' . $_EXTKEY . '/flexform_ds.xml');

	// add flexform field to support ke_ukb
if (t3lib_extMgm::isLoaded('ke_ukb')) {
	$flexformConfig = file_get_contents(t3lib_extMgm::extPath($_EXTKEY) . '/flexform_ds.xml');
	$additionalFlexformFields = '
		<sheetAdditional>
			<ROOT>
				<TCEforms>
					<sheetTitle>LLL:EXT:ke_troubletickets/pi1/locallang.xml:tt_content.pi_ketroubletickets.sheet_title_ukb</sheetTitle>
				</TCEforms>
				<type>array</type>
				<el>
					<drwikisingleview>
						<TCEforms>
							<label>LLL:EXT:ke_troubletickets/pi1/locallang.xml:tt_content.pi_ketroubletickets.drwikisingleview</label>
							<config>
								<type>group</type>
								<internal_type>db</internal_type>
								<allowed>pages</allowed>
								<size>1</size>
								<maxitems>1</maxitems>
								<minitems>0</minitems>
								<show_thumbs>0</show_thumbs>
							</config>
						</TCEforms>
					</drwikisingleview>
					<drwikistorage>
						<TCEforms>
							<label>LLL:EXT:ke_troubletickets/pi1/locallang.xml:tt_content.pi_ketroubletickets.drwikistorage</label>
							<config>
								<type>group</type>
								<internal_type>db</internal_type>
								<allowed>pages</allowed>
								<size>1</size>
								<maxitems>1</maxitems>
								<minitems>0</minitems>
								<show_thumbs>0</show_thumbs>
							</config>
						</TCEforms>
					</drwikistorage>
				</el>
			</ROOT>
		</sheetAdditional>
	';

	$TCA['tt_content']['columns']['pi_flexform']['config']['ds'][$_EXTKEY . '_pi1' . ',list'] =
		str_replace(
			'</sheets>',
			$additionalFlexformFields . "\n" . '</sheets>',
			$flexformConfig
		);
}

$TCA["tx_ketroubletickets_tickets"] = array (
	"ctrl" => array (
		'title'     => 'LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_tickets',
		'label'     => 'title',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'versioningWS' => TRUE,
		'origUid' => 't3_origuid',
		'languageField'            => 'sys_language_uid',
		'transOrigPointerField'    => 'l18n_parent',
		'transOrigDiffSourceField' => 'l18n_diffsource',
		'sortby' => 'sorting',
		'delete' => 'deleted',
		'enablecolumns' => array (
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
			'fe_group' => 'fe_group',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_ketroubletickets_tickets.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "sys_language_uid, l18n_parent, l18n_diffsource, hidden, starttime, endtime, fe_group, title, description, until_date, owner_feuser, responsible_feuser, observers_feuser, category, status, priority, notifications_owner, notifications_responsible, notifications_observer, files, time_used, connected_addresses, connected_feusers, connected_custom",
	)
);


t3lib_extMgm::addToInsertRecords('tx_ketroubletickets_comments');

$TCA["tx_ketroubletickets_comments"] = array (
	"ctrl" => array (
		'title'     => 'LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_comments',
		'label'     => 'content',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'versioningWS' => TRUE,
		'origUid' => 't3_origuid',
		'languageField'            => 'sys_language_uid',
		'transOrigPointerField'    => 'l18n_parent',
		'transOrigDiffSourceField' => 'l18n_diffsource',
		'sortby' => 'sorting',
		'delete' => 'deleted',
		'enablecolumns' => array (
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
			'fe_group' => 'fe_group',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_ketroubletickets_comments.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "sys_language_uid, l18n_parent, l18n_diffsource, hidden, starttime, endtime, fe_group, ticket_uid, feuser_uid, content",
	)
);


t3lib_extMgm::addToInsertRecords('tx_ketroubletickets_categories');

$TCA["tx_ketroubletickets_categories"] = array (
	"ctrl" => array (
		'title'     => 'LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_categories',
		'label'     => 'title',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'versioningWS' => TRUE,
		'origUid' => 't3_origuid',
		'languageField'            => 'sys_language_uid',
		'transOrigPointerField'    => 'l18n_parent',
		'transOrigDiffSourceField' => 'l18n_diffsource',
		'sortby' => 'sorting',
		'delete' => 'deleted',
		'enablecolumns' => array (
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
			'fe_group' => 'fe_group',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_ketroubletickets_categories.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "sys_language_uid, l18n_parent, l18n_diffsource, hidden, starttime, endtime, fe_group, title",
	)
);

$TCA["tx_ketroubletickets_history"] = array (
	"ctrl" => array (
		'title'     => 'LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_history',
		'label'     => 'uid',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => "ORDER BY crdate",
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_ketroubletickets_history.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "ticket_uid, feuser_uid, databasefield, feuser_username, value_old, value_new",
	)
);

$TCA["tx_ketroubletickets_todo"] = array (
	"ctrl" => array (
		'title'     => 'LLL:EXT:ke_troubletickets/locallang_db.xml:tx_ketroubletickets_todo',
		'label'     => 'uid',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'sortby' => 'sorting',
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_ketroubletickets_todo.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "ticket_uid, title, done",
	)
);


	// allow the data to be stored on normal pages
t3lib_extMgm::allowTableOnStandardPages('tx_ketroubletickets_tickets');
t3lib_extMgm::allowTableOnStandardPages('tx_ketroubletickets_categories');
t3lib_extMgm::allowTableOnStandardPages('tx_ketroubletickets_comments');
t3lib_extMgm::allowTableOnStandardPages('tx_ketroubletickets_history');
t3lib_extMgm::allowTableOnStandardPages('tx_ketroubletickets_todo');
?>
