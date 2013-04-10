<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2012 Christian Bülter <buelter@kennziffer.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/*
 * This class is the main class of ke_troubletickets, a trouble ticket extension for TYPO3.
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(t3lib_extMgm::extPath('ke_troubletickets').'lib/class.tx_ketroubletickets_lib.php');

	// Constants
define('CONST_NEWTICKET', 'NEWTICKET');
define('CONST_NEWCOMMENT', 'NEWCOMMENT');
define('CONST_REOPENANDCOMMENT', 'REOPENANDNEWCOMMENT');
define('CONST_ONEVERYCHANGE', 'oneverychange');
define('CONST_NEVER', 'never');
define('CONST_ONSTATUSCHANGE', 'onstatuschange');
define('CONST_TYPOSCRIPT', 'typoscript');
define('CONST_STATUS_OPEN', 'open');
define('CONST_STATUS_CLOSED', 'closed');
define('CONST_STATUS_CLOSED_LOCKED', 'closed_locked');
define('CONST_STATUS_WAIT', 'wait');
define('CONST_RENDER_TYPE_EMAIL', 'email');
define('CONST_RENDER_TYPE_CSV', 'csv');
define('CONST_SHOW_ALL_FOR_ADMINS', 'all_for_admins');
define('CONST_SHOW_ALL_ALWAYS', 'all_always');
define('DEFAULT_SORT', 'until_date,crdate-1');
define('RENDER_EMPTY_DRODOWN_ELEMENT', true);
define('DONT_RENDER_EMPTY_DRODOWN_ELEMENT', false);
define('CONST_KEEP_TAGS_YES', 'keeptags');
define('CONST_RENDER_ALL_INTERNAL_FIELDS', 'render_all_internal_fields');
define('NOT_FULLY_CHARGED_FILTER', 'not_fully_charged');

	// RTE
require_once(t3lib_extMgm::extPath('rtehtmlarea').'pi2/class.tx_rtehtmlarea_pi2.php');

	// Basic file func, needed for checking filenames when uploading files
require_once(PATH_t3lib.'class.t3lib_basicfilefunc.php');

if (class_exists(VersionNumberUtility)) {
    $numeric_typo3_version = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
} else if (class_exists('t3lib_utility_VersionNumber')) {
    $numeric_typo3_version = t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version);
} else {
    $numeric_typo3_version = t3lib_div::int_from_ver(TYPO3_version);
}
	// Mail functions
if ($numeric_typo3_version >= 4005000)
	require_once(PATH_t3lib.'mail/class.t3lib_mail_message.php');
else
	require_once(PATH_t3lib.'class.t3lib_htmlmail.php');

/**
 * Plugin 'Trouble Ticket System' for the 'ke_troubletickets' extension.
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_ketroubletickets
 */
class tx_ketroubletickets_pi1 extends tslib_pibase {
	var $prefixId 			= 'tx_ketroubletickets_pi1';		// Same as class name
	var $scriptRelPath 		= 'pi1/class.tx_ketroubletickets_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey 			= 'ke_troubletickets';	// The extension key.
	var $tablename 		 	= 'tx_ketroubletickets_tickets';
	var $categoryTablename 	= 'tx_ketroubletickets_categories';
	var $historyTablename 	= 'tx_ketroubletickets_history';
	var $commentsTablename 	= 'tx_ketroubletickets_comments';
	var $hiddenFormFields 	= array();
	var $ticketFormName		= 'ketroubletickets_ticketform';
	var $commentFormName	= 'ketroubletickets_commentform';
	var $formErrors 		= array();
	var $fileUploadDir 		= 'uploads/tx_ketroubletickets/';
	var $insertFields 		= array();
	var $markerArray 		= array();
	var $ticketHistory		= array();
	var $csv_filename 		= 'troubletickets_###DATE###.csv';
	var $defaultCSS 		= 'res/css/ke_troubletickets.css';
	var $timetracking		= array();
	var $listViewConf		= '';

		// remember to which users a notification has been sent so
		// that no double notifications are sent
	var $alreadySentTo = array();


    /* RTE vars */
	var $RTEObj;
    var $strEntryField;
    var $docLarge = 0;
    var $RTEcounter = 0;
    var $formName;
    var $additionalJS_initial = '';		// Initial JavaScript to be printed before the form (should be in head, but cannot due to IE6 timing bug)
	var $additionalJS_pre = array();	// Additional JavaScript to be printed before the form
	var $additionalJS_post = array();	// Additional JavaScript to be printed after the form
	var $additionalJS_submit = array();	// Additional JavaScript to be executed on submit
    var $PA = array(
		'itemFormElName' =>  '',
		'itemFormElValue' => '',
	);
    var $specConf = array(
        'rte_transform' => array(
        'parameters' => array('mode' => 'ts_css')
        )
    );
    var $thisConfig = array();
    var $RTEtypeVal = 'text';
    var $thePidValue;

	/**
	 * Plugin Main Method
	 *
	 * @param	string	$content: The content of the PlugIn
	 * @param	array		$conf: The PlugIn Configuration
	 * @return	The content that should be displayed on the website
	 */
	public function main($content,$conf) {/*{{{*/
		$this->trackTime('start');

		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

			// path to this extension
		$this->extPath = t3lib_extMgm::siteRelPath($this->extKey);

			// Include default CSS?
 		if ($this->conf['includeDefaultCSS']) {
            if ($this->getNumericTYPO3versionNumber() >= 6000000) {
                $GLOBALS['TSFE']->getPageRenderer()->addCssFile($this->extPath . $this->defaultCSS);
            } else {
                $GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_css'] = '<link rel="stylesheet" type="text/css" href="' . $this->extPath . $this->defaultCSS . '" />';
            }
        }

			// create instance of the extension library
		$this->lib = t3lib_div::makeInstance('tx_ketroubletickets_lib');

			// include ke_ukb if installed
		if (t3lib_extMgm::isLoaded('ke_ukb')) {
			require_once(t3lib_extMgm::extPath('ke_ukb').'class.ke_ukb.php');
			$this->ukb = t3lib_div::makeInstance('ke_ukb');
		}

			// Configuring so caching is not expected. This value means that no
			// cHash params are ever set. We do this, because it's a USER_INT
			// object!
		$this->pi_USER_INT_obj = 1;

			// get the pidList and the recursive flag from the content object
			// if this plugin has been inserted into a content column (and has not
			// been included via typoscript)
		if (strstr($this->cObj->currentRecord,'tt_content'))	{
			$conf['pidList'] = $this->cObj->data['pages'] ? $this->cObj->data['pages'] : $conf['pidList'];
			$conf['recursive'] = $this->cObj->data['recursive'] ? $this->cObj->data['recursive']  : $conf['recursive'];
		}

			// make the configurationen class-wide available
		$this->conf = $conf;

			// use date2cal if the extension is installed
		if (t3lib_extMgm::isLoaded('date2cal')) {
			$this->initDate2Cal();
		}

			// a local content object (with clear configuration)
		$lcObj=t3lib_div::makeInstance('tslib_cObj');

			// start with empty content
		$content = '';

			// Init and get the flexform data of the plugin
		$this->pi_initPIflexForm();

			// Assign the flexform data to a local variable for easier access
		$piFlexForm = $this->cObj->data['pi_flexform'];

			// Traverse the entire flexform array based on the language
			// and write the content to an array
		if (is_array($piFlexForm['data'])) {
			foreach ( $piFlexForm['data'] as $sheet => $data ) {
				foreach ( $data as $lang => $value ) {
					foreach ( $value as $key => $val ) {
						$this->ffdata[$key] = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
					}
				}
			}
		}

			// get the list of internal users
		if ($this->ffdata['internal_users']) {
			$this->internalUserList = t3lib_div::trimExplode(',',$this->ffdata['internal_users']);
		}

			// get the template
		$templateFile = $this->conf['templateFile'];
		$this->templateCode = $lcObj->fileResource($templateFile);
		if (!$this->templateCode) {
			return '<p class="error">' . $this->pi_getLL('error_no_template') . '</p>';
		}

			// add the "are you sure"-function to the header.
        $jsCode = '
<script type="text/javascript">
	function areYouSure(ziel) {
		if ( confirm("' . $this->pi_getLL('are_you_sure_delete', 'Are you sure?') . '") ) {
			window.location.href = ziel;
		}
	}
</script>
';
		
        if ($this->getNumericTYPO3versionNumber() >= 6000000) {
            $GLOBALS['TSFE']->getPageRenderer()->addHeaderData($jsCode);
        } else {
            $GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_areyousure'] = $jsCode;
        }
			// General permission check: This plugin only makes sense if a user is logged in
		if (!$GLOBALS['TSFE']->loginUser) {
			return $this->pi_wrapInBaseClass($this->pi_getLL('error_not_logged_in', 'Please log in.'));
		}

		/******************************************
		 * Actions
		 *****************************************/

			// show single view if searchword is ticket UID
		if ($this->isValidTicketUid($this->piVars['sword'])) {
			$this->piVars['showUid'] = intval($this->piVars['sword']);
			$this->piVars['sword'] = '';
		}

			// single / update / print view
			// get the database entry for the single or print view / the entry that will be updated.
		if ( $this->piVars['showUid'] || $this->piVars['updateUid'] || $this->piVars['printview'] )	{
			$uid = $this->piVars['showUid'] ? $this->piVars['showUid'] : ( $this->piVars['updateUid'] ? $this->piVars['updateUid'] : $this->piVars['printview'] );
			$this->internal['currentTable'] = $this->tablename;
			$this->internal['currentRow'] = $this->pi_getRecord($this->tablename, $uid);

				// save the ticket before the data changes for later use
			$this->oldTicket = $this->internal['currentRow'];

				// PERMISSION CHECKS
				// only the owner, the responsible user and the observers may view or update ticket
			if (!$this->checkPermissionForCurrentTicket()) {
				return $this->pi_wrapInBaseClass('<div class="error">' . $this->pi_getLL('error_no_permission', 'Permission denied.') . '</div>');
			}
		}

			// check, if this is a follow-up ticket
			// if yes, get the data and save it for later use
		$followUpTicketUid = intval($this->piVars['followup']);
		if ($followUpTicketUid && $this->isValidTicketUid($followUpTicketUid) && $this->checkPermissionForTicket($followUpTicketUid)) {
			$this->parentTicket = $this->pi_getRecord($this->tablename, $followUpTicketUid);
		} else {
			$this->parentTicket = array();
		}

			// a new ticket has been submitted / a ticket should be updated
		if ($this->piVars['newticket'] || $this->piVars['updateUid']) {
			$this->handleSubmittedForm();
		}

			// a ticket should be deleted
		if ($this->piVars['deleteUid']) {
			$this->deleteTicket($this->piVars['deleteUid']);
		}

			// a ticket should be closed
		if ($this->piVars['closeUid']) {
			$this->closeTicket($this->piVars['closeUid']);
		}

			// a file should be deleted
		if ($this->piVars['deleteFile']) {
			$this->removeFileFromTicket($this->piVars['deleteFile']);
		}

			// a related ticket link should be deleted
		if ($this->piVars['deleteRelatedTicket']) {
			$this->removeRelatedTicketFromCurrentTicket($this->piVars['deleteRelatedTicket']);
		}
		
			// Render the main content
		if (
			($this->piVars['do'] == 'new')
			|| $this->piVars['showUid']
			|| ($this->piVars['updateUid'] && count($this->formErrors))
			|| ($this->piVars['newticket'] && count($this->formErrors))
			) {
			$content .= $this->renderTicketForm();
		} else if ($this->piVars['printview']) {
			$content .= $this->printview();
		} else {
			$this->setListviewConfiguration();
			$this->cleanUpPiVars();
			$content .= $this->listView();
		}

		return $this->pi_wrapInBaseClass($content);
	}/*}}}*/

	/**
	 * sets the configuration for the listview: sorting, filter, viewtype
	 * entries per page, search
	 *
	 * @access public
	 * @return void
	 */
	public function setListviewConfiguration() {

			// read session data
		$sessionVars = $GLOBALS['TSFE']->fe_user->getKey('ses',$this->prefixId);

			// viewtype - get value from form or from session
		if (!$this->piVars['viewtype'] && $sessionVars[$GLOBALS['TSFE']->id]['viewtype']) {
			$this->piVars['viewtype'] = $sessionVars[$GLOBALS['TSFE']->id]['viewtype'];
		}

			// accept only allowed values
		if (!in_array($this->piVars['viewtype'], t3lib_div::trimExplode(',', $this->conf['viewtypeList']))) {
			unset($this->piVars['viewtype']);
		}

			// store chosen viewtype in session - if set
		if ($this->piVars['viewtype']) {
			$sessionVars[$GLOBALS['TSFE']->id]['viewtype'] = $this->piVars['viewtype'];
		}

			// set listview typoscript configuration
			// "view" is defined in the backend (teaser / normal listview)
			// "viewtype" is selected by the user in the frontend
		switch ($this->ffdata['view']) {
				// tickets the user is responsible for
			case 'TEASER_OWN':
				$this->listViewConf = $this->conf['teaserViewOwn.'];
				break;

			case 'TEASER_OWNSHORT':
				$this->listViewConf = $this->conf['teaserViewOwnShort.'];
				break;

				// tickets the user delegated to other users
			case 'TEASER_DEL':
				$this->listViewConf = $this->conf['teaserViewDelegated.'];
				break;

			// teaser view
			case 'TEASER_NORMAL':
				$this->listViewConf = $this->conf['teaserView.'];
				break;

			default:
				if ($this->piVars['viewtype'] == 'short') {
					$this->listViewConf = $this->conf['listViewShort.'];
				} else {
					$this->listViewConf = $this->conf['listView.'];
				}
			break;
		}

			// get sorting from typoscript configuration if not set by piVars or in Session
		if (!$this->piVars['sort'] && $this->listViewConf['sort'] && empty($sessionVars[$GLOBALS['TSFE']->id]['sort'])) {
			$this->piVars['sort'] = $this->listViewConf['sort'];
		}

			// Initialize sorting
			// set default sort when no sorting chosen and no sorting set in session data
		if (empty($this->piVars['sort']) && empty($sessionVars[$GLOBALS['TSFE']->id]['sort'])) {
			$this->piVars['sort'] = DEFAULT_SORT;
		} else if (empty($this->piVars['sort']) && !empty($sessionVars[$GLOBALS['TSFE']->id]['sort'])) {
				// use sorting from session data
			$this->piVars['sort'] = $sessionVars[$GLOBALS['TSFE']->id]['sort'];
		}

			// store chosen sorting in session
		$sessionVars[$GLOBALS['TSFE']->id]['sort'] = $this->piVars['sort'];

			// keep existing filter
		if ($this->piVars['filter']) {
			$this->filter = unserialize(base64_decode($this->piVars['filter']));
		} else {
				// if no filter is set use stored data from session, ONLY
				// if we are not in delegated teaser view or in own teaser view
				// since in this views there is no possibility to clear the filter
			if (!strstr($this->ffdata['view'], 'TEASER_')) {
				$this->filter = unserialize(base64_decode($sessionVars[$GLOBALS['TSFE']->id]['filter']));
			}
		}

			// a new filter for listview is set
		if ($this->piVars['filter_submit']) {
			$this->filter = array();
			foreach (explode(',',$this->conf['listView.']['filterList']) as $filterName) {
				$filterName = trim($filterName);
				if (!empty($this->piVars[$filterName])) {
					$this->filter[$filterName] = $this->piVars[$filterName];
				}
			}
				// go to page 1 if a new filter has been set
			$this->piVars['pointer'] = 0;
		}

			// set some default values for the filter
			// TODO: Should be configurable in Typoscript in future versions
		$this->filter['status'] = $this->filter['status'] ? $this->filter['status'] : 'all_not_closed';
		if (strstr($this->ffdata['view'], 'TEASER_OWN')) {
			$this->filter['responsible_feuser'] = $GLOBALS['TSFE']->fe_user->user['uid'];
		}
		if (strstr($this->ffdata['view'], 'TEASER_OWNSHORT')) {
			$this->filter['status'] = 'open_and_working';
		}
		if (strstr($this->ffdata['view'], 'TEASER_DEL')) {
			$this->filter['owner_feuser'] = $GLOBALS['TSFE']->fe_user->user['uid'];
		}

			// save the filter in piVars
			// Use base64 because the serialized value contains quotes
		$this->piVars['filter'] = base64_encode(serialize($this->filter));

			// store chosen filter in session
		$sessionVars[$GLOBALS['TSFE']->id]['filter'] = $this->piVars['filter'];

			// Entries per page - get value from form or from session
		if (!$this->piVars['entries_per_page'] && $sessionVars[$GLOBALS['TSFE']->id]['entries_per_page']) {
			$this->piVars['entries_per_page'] = $sessionVars[$GLOBALS['TSFE']->id]['entries_per_page'];
		}

			// clean value
		$this->piVars['entries_per_page'] = intval($this->piVars['entries_per_page']);

			// store entries per page in session
		$sessionVars[$GLOBALS['TSFE']->id]['entries_per_page'] = $this->piVars['entries_per_page'];

			// store session data
		$GLOBALS['TSFE']->fe_user->setKey('ses', $this->prefixId, $sessionVars);
		$GLOBALS['TSFE']->storeSessionData();
	}

	/**
	 * checkPermissionForCurrentTicket
	 *
	 * Checks if the current logged in user has any rights for the current ticket
	 * which is stored in $this->internal['currentRow']
	 *
	 * @access public
	 * @return void
	 */
	public function checkPermissionForCurrentTicket() {/*{{{*/
		return $this->checkPermissionForTicket($this->internal['currentRow']['uid']);
	}/*}}}*/

	/**
	 * checkPermissionForTicket
	 *
	 * checks if the current user has access to a given ticket
	 * see lib/class.tx_ketroubletickets_lib for documentation
	 *
	 * @param int $ticketUid
	 * @access public
	 * @return integer
	 */
	public function checkPermissionForTicket($ticketUid=0) {/*{{{*/
		return  $this->lib->checkPermissionForTicket($ticketUid, $this);
	}/*}}}*/

	/**
	 * cleanUpPiVars
	 * unset the piVars we don't need anymore
	 * otherwise every formvar would stay in the piVars
	 * and would be integrated into any link generated by the pi_-functions
	 * (for example a new ticket would be submitted over and over)
	 *
	 * @access public
	 * @return void
	 */
	public function cleanUpPiVars() {/*{{{*/

			// some vars form the ticket form
		unset($this->piVars['newticket']);
		unset($this->piVars['updateUid']);
		unset($this->piVars['filter_submit']);
		foreach ($this->conf['formFieldList.'] as $fieldConf) {
			unset($this->piVars[$fieldConf['name']]);
			if ($fieldConf['type'] == 'textareaRTE') {
				unset($this->piVars['_TRANSFORM_' . $fieldConf['name']]);
			}
		}

			// vars from the listview
		unset($this->piVars['filter']);

			// and some vars from the comment form
		unset($this->piVars['content']);
		unset($this->piVars['comment_submit']);
		unset($this->piVars['0']);

			// some more piVars
		unset($this->piVars['deleteUid']);
		unset($this->piVars['deleteFile']);
		unset($this->piVars['closeUid']);

	}/*}}}*/

	/**
	 * handleSubmittedForm
	 *
	 * handles the submitted values of a ticket form
	 *
	 * @access public
	 * @return void
	 */
	public function handleSubmittedForm() {/*{{{*/

			// set some values for NEW tickets
			// and UPDATED tickets
		if ($this->piVars['newticket']) {

				// set the crdate
			$this->insertFields['crdate'] = time();

				// use the first pid of the pid list or, if not set, the current page
			if ($this->conf['pidList']) {
				$pidListArray = explode(',', $this->conf['pidList']);
				$this->insertFields['pid'] = $pidListArray[0];
			} else {
				$this->insertFields['pid'] = $GLOBALS['TSFE']->id;
			}

				// set the owner to the current user
				// if no user is logged in, set it to an empty value
			if ($GLOBALS['TSFE']->loginUser) {
				$this->insertFields['owner_feuser'] = $GLOBALS['TSFE']->fe_user->user['uid'];
			} else {
				$this->insertFields['owner_feuser'] = '';
			}

		} else {

				// We need to initalize the "file" type fields, because they get combined from
				// the different upload fields in the form
			foreach ($this->conf['formFieldList.'] as $fieldConf) {
				if ($fieldConf['type'] == 'files' && strlen($this->internal['currentRow'][$fieldConf['name']])) {
					$this->insertFields[$fieldConf['name']] = $this->internal['currentRow'][$fieldConf['name']];
				}
			}
		}

			// handle each of the submitted fields as defined in the typoscript setup
		foreach ($this->conf['formFieldList.'] as $fieldConf) {

				// ignore fields:
				// - the submit-field
				// - internal fields if the current user is not an internal user
				// - fields that are configured as "doNotSaveInDB = 1"
				// - fields the user does not have access to
			if (
				$fieldConf['type'] != 'submit'
				&& (!$fieldConf['internal'] || $this->isCurrentUserInternalUser())
				&& !$fieldConf['doNotSaveInDB']
				&& $this->fieldIsWritableForCurrentUser($fieldConf)
			) {

					// required-check
				if ($fieldConf['required'] && empty($this->piVars[$fieldConf['name']])) {
					$this->formErrors[] = $this->pi_getLL('formerror_required_start')
						. '"' . $this->pi_getLL('LABEL_' . strtoupper(trim($fieldConf['name'])))
						. '"' . $this->pi_getLL('formerror_required_end');
				}

					// validate
				if ($fieldConf['validate'] && !empty($this->piVars[$fieldConf['name']])) {
					$validationParams = t3lib_div::trimExplode(':', $fieldConf['validate']);
					switch ($validationParams[0]) {
						case 'float':
								// replace dot with comma in order to check
								// for a correct float value
							$value = str_replace(',', '.', $this->piVars[$fieldConf['name']]);
							if (filter_var($value, FILTER_VALIDATE_FLOAT) === FALSE) {
								$this->formErrors[] = $this->pi_getLL('formerror_float_begin')
									. '"' . $this->pi_getLL('LABEL_' . strtoupper(trim($fieldConf['name'])))
									. '"' . $this->pi_getLL('formerror_float_end');
							}
						break;

						case 'notAllowedWhenClosing':
							if (stristr($this->piVars['status'], CONST_STATUS_CLOSED)
								&& $this->piVars[$fieldConf['name']] == $validationParams[1]) {
								$this->formErrors[] = $this->pi_getLL('formerror_not_allowed_on_close_begin')
									. '"' . $this->pi_getLL('LABEL_' . strtoupper(trim($fieldConf['name'])))
									. '"' . $this->pi_getLL('formerror_not_allowed_on_close_end');

							}
						break;
					}
				}

					// check the "requiredForInternalUsersOnClose" property
					// This means: If the current user is an "internal" user and
					// the current field has the
					// requiredForInternalUsersOnClose-Flag set, he has to fill the
					// field in case he want's to close the ticket
					// This is useful for the "time used"-field.
				if ($fieldConf['requiredForInternalUsersOnClose']
					&& stristr($this->piVars['status'], CONST_STATUS_CLOSED)
					&& empty($this->piVars[$fieldConf['name']])
					&& $this->isCurrentUserInternalUser()
					) {
					$this->formErrors[] = $this->pi_getLL('formerror_required_start') . '"' . $this->pi_getLL('LABEL_' . strtoupper(trim($fieldConf['name']))) . '"' . $this->pi_getLL('formerror_required_end');
				}

					// generate the db-insert values

					// combine the "file" type fields
					// use the already set value as default value, since files must be
					// deletedy by clicking on the "delete"-icon, not by submitting
					// an empty "files"-field
				if ($fieldConf['type'] == 'files') {
					if (strlen($this->insertFields[$fieldConf['name']])) {
						$defaultValue = $this->insertFields[$fieldConf['name']];
					} else {
						$defaultValue = '';
					}
				} else {
					$defaultValue = '';
				}

					// parse and clean up the submitted value
				$this->insertFields[$fieldConf['name']] = $this->generateDBInsertValue($fieldConf, $defaultValue);
							
			}
		}
			// if there are errors, delete the uploaded files
			// don't delete when UPDATING a ticket
		if (!$this->piVars['updateUid']) {
			if (count($this->formErrors) && strlen($this->insertFields['files'])) {
				$this->deleteFiles($this->insertFields['files']);
				$this->insertFields['files'] = '';
				$this->formErrors[] = $this->pi_getLL('formerror_upload_again');
			}
		}

			// the "project leader function": If a user is the responsible user of
			// a ticket and delegates that ticket to another user, automatically add
			// the former responsible user to the list of observers. That only happens
			// if the responsible user himself changes the responsibility.
		if ($this->piVars['updateUid']
			&& $this->conf['addResponsibleUserAsObserverAfterDelegation']
			&& $this->internal['currentRow']['responsible_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']
			&& $this->insertFields['responsible_feuser'] != $GLOBALS['TSFE']->fe_user->user['uid']
			&& !t3lib_div::inList($GLOBALS['TSFE']->fe_user->user['uid'], $this->insertFields['observers_feuser'])
			) {
			$this->insertFields['observers_feuser'] = $this->addToCommaList($this->insertFields['observers_feuser'], $GLOBALS['TSFE']->fe_user->user['uid']);
		}

			// if everything is OK, insert the ticket into the database or update it
		if (!count($this->formErrors)) {
			if (!$this->piVars['updateUid']) { // new ticket
					// set the "close_time" field, if the ticket is directy
					// set to "closed" the moment it's created.
				if (stristr($this->insertFields['status'], CONST_STATUS_CLOSED)) {
					$this->insertFields['close_time'] = time();
				}

				$saveFieldsStatus = $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->tablename, $this->insertFields) ? true : false;
				$new_uid = $GLOBALS['TYPO3_DB']->sql_insert_id();

					// hook: after inserting a new ticket
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['afterNewTicketInsert'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['afterNewTicketInsert'] as $_classRef) {
						$_procObj = & t3lib_div::getUserObj($_classRef);
						$_procObj->afterNewTicketInsert($new_uid, $this);
					}
				}

				$this->addHistoryEntry( array(
					'ticket_uid' => $new_uid,
					'databasefield' => '',
					'value_old' => '',
					'value_new' => $this->pi_getLL('history_new_ticket', 'new')
					));

					// send the notification emails
				$this->checkChangesAndSendNotificationEmails($new_uid, CONST_NEWTICKET);

					// status message after saving new ticket
				if ($saveFieldsStatus) {
					$this->markerArray['STATUS_CSS_CLASS'] = 'status_ok';
					$this->markerArray['STATUS_MESSAGE_TEXT'] = $this->pi_getLL('status_new_ticket');
				}

					// process comment form if allowed for new tickets and data available
				if ($this->conf['allowCommentsInNewTicketForm'] && !empty($this->piVars['content'])) $this->handleSubmittedCommentForm();

			} else { // update ticket

					// go through the form fields and check what has changend
					// add a history entry for every change
				$changedFields = '';
				$changedInternalFields = '';

				foreach ($this->conf['formFieldList.'] as $fieldConf) {
					$value_old = $this->internal['currentRow'][$fieldConf['name']];
					$value_new = $this->insertFields[$fieldConf['name']];
					if ( !empty($value_new) && ($value_old != $value_new)) {
						$this->addHistoryEntry( array(
									'ticket_uid' => $this->internal['currentRow']['uid'],
									'databasefield' => $fieldConf['name'],
									'value_old' => $value_old,
									'value_new' => $value_new
									));

							// update the "close_time" field, if the ticket is
							// now closed and was NEVER closed before
							// That means, the close_time is always the time
							// the ticket has been closed for the first time.
						if ($fieldConf['name'] == 'status'
								&& stristr($value_new, CONST_STATUS_CLOSED)
								&& !$this->internal['currentRow']['close_time'])
						{
							$this->insertFields['close_time'] = time();
						}

							// Remember the fields that have changed for the notification mail.
							// Do this only for fields that are not internal!
						if (empty($fieldConf['internal'])) {
							if (strlen($changedFields)) {
								$changedFields .= ',';
							}
							$changedFields .= $fieldConf['name'];
						}

							// Remember the internal fields that have changed
						if (!empty($fieldConf['internal'])) {
							if (strlen($changedInternalFields)) {
								$changedInternalFields .= ',';
							}
							$changedInternalFields .= $fieldConf['name'];
						}
					}
				}

					// If a comment has been submitted, process it now.
					// Comments are not normal fields but have an own table, so we
					// cannot process them like the ticket fields.
					// Remember the fields that have changed for the notification mail.
				if (isset($this->piVars['content']) && !empty($this->piVars['content'])) {
					$saveCommentStatus = $this->handleSubmittedCommentForm();

						// if the ticket currently is closed, re-open it.
					if (stristr($this->internal['currentRow']['status'], CONST_STATUS_CLOSED)) {

							// change the status
						$this->insertFields['status'] = CONST_STATUS_OPEN;
							
							// add the information to changedFields list
						$changedFields = $this->addToCommaList($changedFields, CONST_REOPENANDCOMMENT);

							// add a history entry
						$this->addHistoryEntry( array(
							'ticket_uid' => $this->internal['currentRow']['uid'],
							'databasefield' => 'status',
							'value_old' => $this->internal['currentRow']['status'],
							'value_new' => CONST_STATUS_OPEN
						));
					} else {

							// if the status is currentyl "wait", set the status to the value
							// defined in typoscript
							// only do so if the status has not been changed by the user.
						if ($this->conf['changeWaitStatusOnNewComment']
							&& $this->internal['currentRow']['status'] == CONST_STATUS_WAIT
							&& $this->insertFields['status'] == $this->internal['currentRow']['status']
							&& t3lib_div::inList($this->conf['statusList'], $this->conf['changeWaitStatusOnNewComment'])) {

								// change the status
							$this->insertFields['status'] = $this->conf['changeWaitStatusOnNewComment'];

								// add the information to changedFields list
							$changedFields = $this->addToCommaList($changedFields, 'status');

								// add a history entry
							$this->addHistoryEntry( array(
										'ticket_uid' => $this->internal['currentRow']['uid'],
										'databasefield' => 'status',
										'value_old' => $this->internal['currentRow']['status'],
										'value_new' => $this->conf['changeWaitStatusOnNewComment']
										));
						}

						$changedFields = $this->addToCommaList($changedFields, CONST_NEWCOMMENT);
					}

				}
				
				
					// get ticket progress
				$newStatus = $this->insertFields['status'];
				if ($newStatus == CONST_STATUS_CLOSED || $newStatus == CONST_STATUS_CLOSED_LOCKED) {
					$this->insertFields['progress'] = 100;
				} else {
					$this->insertFields['progress'] = $this->lib->getTicketProgressFromToDo($this->internal['currentRow']['uid']);
				}
				if ($this->insertFields['progress'] != $this->internal['currentRow']['progress']) {
					$changedFields = $this->addToCommaList($changedFields, 'progress');
				}

					// exec update database query
				$saveFieldsStatus = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tablename, 'uid=' . $this->internal['currentRow']['uid'], $this->insertFields) ? true : false;


					// hook: after updating a ticket
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['afterTicketUpdate'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['afterTicketUpdate'] as $_classRef) {
						$_procObj = & t3lib_div::getUserObj($_classRef);
						$_procObj->afterTicketUpdate($this->internal['currentRow']['uid'], $this);
					}
				}

					// send the notification emails
				$this->checkChangesAndSendNotificationEmails($this->internal['currentRow']['uid'], $changedFields, $changedInternalFields);

					// check if saving of fields and comments went fine
					// and set status texts
					// fields changed and new comment
				if (( !empty($changedFields) && strstr($changedFields,CONST_NEWCOMMENT) && trim($changedFields) != CONST_NEWCOMMENT )
					|| ( !empty($changedInternalFields) && (strstr($changedFields,CONST_NEWCOMMENT)))) {
					if ($saveFieldsStatus && $saveCommentStatus) {
						$this->markerArray['STATUS_CSS_CLASS'] = 'status_ok';
						$this->markerArray['STATUS_MESSAGE_TEXT'] = $this->pi_getLL('status_fields_and_comment');
					}
				}
					// new comment only
				else if (empty($changedInternalFields) && trim($changedFields) == CONST_NEWCOMMENT) {
					if ($saveCommentStatus) {
						$this->markerArray['STATUS_CSS_CLASS'] = 'status_ok';
						$this->markerArray['STATUS_MESSAGE_TEXT'] = $this->pi_getLL('status_comment_only');
					}
				}
					// fields changed
				else if ((!empty($changedFields) && !strstr($changedFields,CONST_NEWCOMMENT)) || !empty($changedInternalFields)) {
					if ($saveFieldsStatus) {
						$this->markerArray['STATUS_CSS_CLASS'] = 'status_ok';
						$this->markerArray['STATUS_MESSAGE_TEXT'] = $this->pi_getLL('status_fields_only');
					}
				}

			}


			// process redirect if activated and back pid set
			// AK, 13.08.2010
			if ($this->conf['listView.']['backPidRedirect.']['activated'] && $this->piVars['backPid']) {

				// extend the status message with redirection notice
				$this->markerArray['STATUS_MESSAGE_TEXT'] .= ' '.sprintf($this->pi_getLL('status_additional_redirect'),$this->conf['listView.']['backPidRedirect.']['wait']);

				// generate redirect link
				unset($linkconf);
				$linkconf['parameter'] = $this->piVars['backPid'];
				$linkconf['useCacheHash'] = false;
				$redirectLink = $this->cObj->typoLink_URL($linkconf);

				// generate location header url
				$redirectUrl = t3lib_div::locationHeaderUrl($redirectLink);

				// process redirect
				header("Refresh: ".$this->conf['listView.']['backPidRedirect.']['wait']."; ".$redirectUrl);

			}

		}
	}/*}}}*/


	 /*
	 * addToCommaList
	 *
	 * adds an element to an comma separated list
	 *
	 * @param string $list List to add the element to
	 * @param string $element elemenet to add
	 * @access public
	 * @return string
	 */
	public function addToCommaList($list, $element) {/*{{{*/
		if ($element) {
			if (strlen($list)) {
				$list .= ',';
			}
			$list .= strval($element);
		}
		return $list;
	}/*}}}*/

	/**
	 * removeFileFromTicket
	 *
	 * Removes a single file from a ticket.
	 * Deletes it physically on the server and removes the databse connection.
	 * Only ticket-owners are allowed to delete files,
	 * therefore, this function checks if a file belongs
	 * to a ticket the logged-in user is the owner of.
	 *
	 * @param mixed $filename
	 * @access public
	 * @return void
	 */
	public function removeFileFromTicket($filename) { /*{{{*/
		$deleteAllowed = false;
		$lcObj = t3lib_div::makeInstance('tslib_cObj');

		if (file_exists($this->fileUploadDir . $filename)) {
			$listQuery = $GLOBALS['TYPO3_DB']->listQuery('files', $filename, $this->tablename);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid, owner_feuser, files', $this->tablename, $listQuery . $lcObj->enableFields($this->tablename));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($GLOBALS['TSFE']->loginUser && $GLOBALS['TSFE']->fe_user->user['uid'] == $row['owner_feuser']) {
					$deleteAllowed = true;
				}
			}
		}

		if ($deleteAllowed) {

			// delete the file
			$this->deleteFiles($filename);

			// remove it from the database
			$new_filelist = t3lib_div::rmFromList($filename, $row['files']);
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tablename, 'uid=' . $row['uid'], array('files' => $new_filelist));

			// and add a history entry
			$this->addHistoryEntry( array(
						'ticket_uid' => $row['uid'],
						'databasefield' => 'files',
						'value_old' => $row['files'],
						'value_new' => $new_filelist
						));

		}
	}/*}}}*/

	/**
	 * removeRelatedTicketFromCurrentTicket
	 *
	 * Removes a relation to an other ticket.
	 *
	 * @param mixed $filename
	 * @access public
	 * @return void
	 */
	public function removeRelatedTicketFromCurrentTicket($ticketUid) {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');

		// case 1: It's a relation from the current to an other ticket
		if (t3lib_div::inList($this->internal['currentRow']['related_tickets'], $ticketUid)) {
			// remove it from the list
			$new_related_ticket_list = t3lib_div::rmFromList($ticketUid, $this->internal['currentRow']['related_tickets']);

			// update the ticket entry in the database
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tablename, 'uid=' . $this->internal['currentRow']['uid'], array('related_tickets' => $new_related_ticket_list));

			// and add a history entry
			$this->addHistoryEntry( array(
						'ticket_uid' => $this->internal['currentRow']['uid'],
						'databasefield' => 'related_tickets',
						'value_old' => $this->internal['currentRow']['related_tickets'],
						'value_new' => $new_related_ticket_list
						));

			// Update the currentRow
			$this->internal['currentRow']['related_tickets'] = $new_related_ticket_list;
		}

		// case 2: It's a relation from the other ticket to the current ticket
		// find the ticket
		// remove it from the list
		// update the ticket entry in the database
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, title, related_tickets',
			$this->tablename,
			'uid=' . $ticketUid
			//$GLOBALS['TYPO3_DB']->listQuery('related_tickets', $this->internal['currentRow']['uid'], $this->tablename)
			. $lcObj->enableFields($this->tablename)
		);

		if (count($rows)) {
			$row = $rows[0];

			// remove it from the list
			$new_related_ticket_list = t3lib_div::rmFromList($this->internal['currentRow']['uid'], $row['related_tickets']);

			// update the ticket entry in the database
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tablename, 'uid=' . $ticketUid, array('related_tickets' => $new_related_ticket_list));

			// and add a history entry
			$this->addHistoryEntry( array(
						'ticket_uid' => $ticketUid,
						'databasefield' => 'related_tickets',
						'value_old' => $row['related_tickets'],
						'value_new' => $new_related_ticket_list
						));
		}
	}/*}}}*/

	/**
	 * deleteFiles
	 *
	 * Deletes files from the extension upload folder
	 * expects a commalist of files as parameter.
	 * Does not check for permissions!
	 *
	 * @param string $filelist
	 * @access public
	 * @return void
	 */
	public function deleteFiles($filelist) {/*{{{*/
		$content = '';

		if (strlen($filelist)) {
			foreach (explode(',', $filelist) as $filename) {
				if (file_exists($this->fileUploadDir . $filename)) {
					if (!unlink ( $this->fileUploadDir . $filename ) ) {
						// error message
						$content .= $filename . $this->pi_getLL('formerror_could_not_be_deleted') . ' ';
					} else {
						// success message
						//$content .= $filename . $this->pi_getLL('formerror_has_been_deleted', ' deleted') . ' ';
					}
				}
			}
		}

		return $content;
	}/*}}}*/

	/**
	 * handleSubmittedCommentForm
	 *
	 * handle the incoming post data of a submitted comment form
	 *
	 * @access public
	 * @return bool $status
	 */
	public function handleSubmittedCommentForm() {/*{{{*/
		// set the crdate
		$commentInsertFields['crdate'] = time();

		// use the first pid of the pid list or, if not set, the current page
		if ($this->conf['pidList']) {
			$pidListArray = explode(',', $this->conf['pidList']);
			$commentInsertFields['pid'] = $pidListArray[0];
		} else {
			$commentInsertFields['pid'] = $GLOBALS['TSFE']->id;
		}

		// set the owner to the current user
		// if no user is logged in, set it to an empty value
		if ($GLOBALS['TSFE']->loginUser) {
			$commentInsertFields['feuser_uid'] = $GLOBALS['TSFE']->fe_user->user['uid'];
		}

		// set the ticket_uid
		$commentInsertFields['ticket_uid'] = $this->internal['currentRow']['uid'];

		// set the content
		// the user that committed the comment may be deleted later, so we write the
		// username into the comment content
		$commentInsertFields['content'] =
			$this->renderNamesFromFeUserUids($commentInsertFields['feuser_uid'])
			. ': ' . $this->sanitizeData($this->piVars['content']);

		// insert the comment
		$status = $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->commentsTablename, $commentInsertFields) ? true : false;

		// add a history entry
		$this->addHistoryEntry( array(
					'ticket_uid' => $commentInsertFields['ticket_uid'],
					'databasefield' => '',
					'value_old' => '',
					'value_new' => $this->pi_getLL('history_new_comment', 'new comment')
					));

		return $status;
	}/*}}}*/

	/**
	 * sanitizeData
	 *
	 * sanitizeData
	 *
	 * @param string $data
	 * @access public
	 * @return string
	 */
	public function sanitizeData($data='') {/*{{{*/
		return htmlspecialchars($data, ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
	}/*}}}*/

	/**
	 * cleanUpHtmlOutput
	 *
	 * Cleanes up HTML-Output:
	 * removes double html entities but still outputs htmlspecialchars
	 *
	 * @param string $content
	 * @param string $param
	 * @access public
	 * @return void
	 */
	public function cleanUpHtmlOutput($content='', $param='') {/*{{{*/
		$content = html_entity_decode(t3lib_div::deHSCentities($content), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
		$content = htmlentities($content, ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);

		// Keep Tags
		if (stristr($param, CONST_KEEP_TAGS_YES)) {
			$content = str_replace(htmlentities('<'), '<', $content);
			$content = str_replace(htmlentities('>'), '>', $content);
			$content = str_replace(htmlentities('"'), '"', $content);
		}

		return $content;
	}/*}}}*/

	/**
	 * getTicketData
	 *
	 * Returns the whole ticket as an array.
	 * If the ticket uid does not belong to a valid ticket (or the user as no
	 * access), returns false (uses function "checkPermissionForTicket").
	 *
	 * @param integer $ticket_uid
	 * @access public
	 * @return array
	 */
	public function getTicketData($ticket_uid) {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');
		if ($this->checkPermissionForTicket($ticket_uid)) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->tablename, 'uid=' . $ticket_uid . $lcObj->enableFields($this->tablename));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				return $row;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}/*}}}*/

	/**
	 * deleteTicket
	 *
	 * deletes a ticket
	 *
	 * only the owner is allowed to delete the ticket.
	 *
	 * @param mixed $ticket_uid
	 * @access public
	 * @return void
	 */
	public function deleteTicket($ticket_uid) {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');
		$deleteAllowed = false;

		if ($ticket_uid) {

			// get the ticket data
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('owner_feuser, files', $this->tablename, 'uid=' . $ticket_uid . $lcObj->enableFields($this->tablename));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($row['owner_feuser']) {
					if ($GLOBALS['TSFE']->loginUser && $GLOBALS['TSFE']->fe_user->user['uid'] == $row['owner_feuser']) {
						$deleteAllowed = true;
					}
				}
			}
		}

		if ($deleteAllowed) {
			// dont't really delete the ticket, just set the "deleted" flag
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tablename, 'uid=' . $ticket_uid, array('deleted' => 1));

			// do the same for the comments
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->commentsTablename, 'ticket_uid=' . $ticket_uid, array('deleted' => 1));

			// really delete the ticket
			//$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->tablename, 'uid=' . $ticket_uid);

			// delete the comments
			//$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->commentsTablename, 'ticket_uid=' . $ticket_uid);

			// delete the history
			//$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->historyTablename, 'ticket_uid=' . $ticket_uid);

			// delete the files
			//$this->deleteFiles($row['files']);
		}
	}/*}}}*/

	/**
	 * closeTicket
	 *
	 * closes a ticket (changes the status to "closed")
	 *
	 * only the owner and the responsible user is allowed to close the ticket.
	 *
	 * @param mixed $ticket_uid
	 * @access public
	 * @return void
	 */
	public function closeTicket($ticket_uid) {/*{{{*/
		$closeAllowed = false;

		if ($ticket_uid) {

			// get the ticket data
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('owner_feuser, responsible_feuser, status', $this->tablename, 'uid=' . $ticket_uid);
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($row['owner_feuser']) {
					if ($GLOBALS['TSFE']->loginUser && $GLOBALS['TSFE']->fe_user->user['uid'] == $row['owner_feuser']) {
						$closeAllowed = true;
					}
				}
				if ($row['responsible_feuser']) {
					if ($GLOBALS['TSFE']->loginUser && $GLOBALS['TSFE']->fe_user->user['uid'] == $row['responsible_feuser']) {
						$closeAllowed = true;
					}
				}
			}
		}


		if ($closeAllowed) {
			$updateFields = array('status' => CONST_STATUS_CLOSED);

				// update the "close_time" field, if the ticket
				// was NEVER closed before.
				// That means, the close_time is always the time
				// the ticket has been closed for the first time.
			if (!$row['close_time']) {
				$updateFields['close_time'] = time();
			}

			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tablename, 'uid=' . $ticket_uid, $updateFields);

				// send the notification emails
			$this->checkChangesAndSendNotificationEmails($ticket_uid, CONST_STATUS_CLOSED);

				// add history entry
			$this->addHistoryEntry( array(
						'ticket_uid' => $ticket_uid,
						'databasefield' => 'status',
						'value_old' => $row['status'],
						'value_new' => CONST_STATUS_CLOSED
						));
		}
	}/*}}}*/

	/**
	 * addHistoryEntry
	 *
	 * Adds an entry to the history table
	 * expects in the array:
	 * 'pid' => INTEGER,
	 * 'ticket_uid' => INTEGER,
	 * 'databasefield' => STRING,
	 * 'value_old' => STRING,
	 * 'value_new' => STRING
	 *
	 * @param array $historyInsertFields
	 * @access public
	 * @return void
	 */
	public function addHistoryEntry($historyInsertFields = array()) {/*{{{*/

		if ($historyInsertFields['ticket_uid']) {

			// initialize the data
			$historyInsertFields['crdate'] = time();

			// for certain fields, get the cleartext value
			foreach ($this->conf['formFieldList.'] as $fieldConf) {

				// real names / usernames of fe_users instead of uids
				if ($historyInsertFields['databasefield'] == $fieldConf['name'] && $fieldConf['type'] == 'feuserSelect') {
					$historyInsertFields['value_old'] = $this->lib->getNameListFromUidList($historyInsertFields['value_old'], 'fe_users', 'name,username');
					$historyInsertFields['value_new'] = $this->lib->getNameListFromUidList($historyInsertFields['value_new'], 'fe_users', 'name,username');
				}

				// names / company ... of tt_address instead of uids
				if ($historyInsertFields['databasefield'] == $fieldConf['name'] && $fieldConf['type'] == 'addressSelect') {
					$historyInsertFields['value_old'] = $this->lib->getNameListFromUidList($historyInsertFields['value_old'], 'tt_address', 'name,company,email');
					$historyInsertFields['value_new'] = $this->lib->getNameListFromUidList($historyInsertFields['value_new'], 'tt_address', 'name,company,email');
				}

				// nice formatted date instead of timestamp
				if ($historyInsertFields['databasefield'] == $fieldConf['name'] && $fieldConf['type'] == 'date') {
					if (!$historyInsertFields['value_old']) {
						$historyInsertFields['value_old'] = '';
					} else {
						$historyInsertFields['value_old'] = date ($this->conf['datefield_dateformat'], $historyInsertFields['value_old']);
					}
					if (!$historyInsertFields['value_new']) {
						$historyInsertFields['value_new'] = '';
					} else {
						$historyInsertFields['value_new'] = date ($this->conf['datefield_dateformat'], $historyInsertFields['value_new']);
					}
				}

				// add spaces to filelist
				if ($historyInsertFields['databasefield'] == $fieldConf['name'] && $fieldConf['type'] == 'files') {
					$historyInsertFields['value_old'] = str_replace(',', ', ' , $historyInsertFields['value_old']);
					$historyInsertFields['value_new'] = str_replace(',', ', ' , $historyInsertFields['value_new']);
				}

				// localized values for select fields
				if ($historyInsertFields['databasefield'] == $fieldConf['name'] && $fieldConf['type'] == 'select') {
					$historyInsertFields['value_old'] = $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($historyInsertFields['value_old'])));
					$historyInsertFields['value_new'] = $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($historyInsertFields['value_new'])));
				}

				// convert minutes to hours
				if ($historyInsertFields['databasefield'] == $fieldConf['name'] && $fieldConf['type'] == 'inputHoursToMinutes') {
					$historyInsertFields['value_old'] = $this->lib->m2h($historyInsertFields['value_old']);
					$historyInsertFields['value_new'] = $this->lib->m2h($historyInsertFields['value_new']);
				}
			}

			// set the pid to the same value as the ticket
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pid', $this->tablename, 'uid=' . $historyInsertFields['ticket_uid']);
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$historyInsertFields['pid'] = $row['pid'];
			}

			// set the user data
			if ($GLOBALS['TSFE']->loginUser) {
				$historyInsertFields['feuser_uid'] = $GLOBALS['TSFE']->fe_user->user['uid'];
				$historyInsertFields['feuser_username'] = $GLOBALS['TSFE']->fe_user->user['username'];
			} else {
				$historyInsertFields['feuser_uid'] = '';
				$historyInsertFields['feuser_username'] = '';
			}

			$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->historyTablename, $historyInsertFields);
		}
	}/*}}}*/

	/**
	 * checkChangesAndSendNotificationEmails
	 *
	 * TODO: Add "send overdue tickets" functionality and make this function
	 * work standalone in order to use it with a cronjob.
	 *
	 * $changedFields and $changedInternalFields are comma-separated lists of the fields that have changed.
	 *
	 * @param integer $ticket_uid
	 * @param string $changedFields
	 * @param string $changedInternalFields
	 * @access public
	 * @return void
	 */
	public function checkChangesAndSendNotificationEmails($ticket_uid, $changedFields, $changedInternalFields='') {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');

			// a notification will be sent if a ticket has been updated and
			// 1. notification setting is "always"
			// 2. notification setting is "onstatuschange" and the "status" field changed
			// 3. notification setting is "defined in typoscript" and the typoscript
			// options match the list of changed fields
			// The notification setting is done in the plugin via flexform.

			// does this ticket exist?
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->tablename, 'uid=' . $ticket_uid . $lcObj->enableFields($this->tablename));
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {

				// get the ticket data
			$this->internal['currentTable'] = $this->tablename;
			$this->internal['currentRow'] = $this->pi_getRecord($this->tablename, $ticket_uid);

				// render the mailbody for standard mails to non-internal users
				// the second parameter has to be empty, because we don't want any
				// changes of internal fields sent to non-internal users!
			$emailbody = $this->renderNotificationMail($changedFields,'');

				// render the mailbody for internal mails
				// on new tickets, render all internal fields, on updated tickets, render
				// only fields which have changed
			if (stristr($changedFields, CONST_NEWTICKET)) {
				$emailbody_internal = $this->renderNotificationMail($changedFields, CONST_RENDER_ALL_INTERNAL_FIELDS);
			} else {
				$emailbody_internal = $this->renderNotificationMail($changedFields, $changedInternalFields);
			}

				// render the subject
			if ($this->conf['email_notifications.']['subject_prefix']) {
				$subject = $this->conf['email_notifications.']['subject_prefix'] . ' ';
			} else {
				$subject = '';
			}

				// add ticket uid to subject if set in TS
			if ($this->conf['email_notifications.']['add_uid_to_subject']) {
				$subject .= sprintf($this->conf['ticket_uid_formatstring'], $ticket_uid) . ' ';
			}

				// add the status to the subject if it has changed
				// otherwise just add the word "changed"
			if (stristr($changedFields, CONST_NEWTICKET)) {
				$subject .= $this->pi_getLL('email_subject_type_new', 'new') . ': ';
			} else if (stristr($changedFields, CONST_REOPENANDCOMMENT)) {
				$subject .= $this->pi_getLL('email_subject_type_newcomment_reopen', 'new comment and re-open') . ': ';
			} else if (stristr($changedFields, CONST_ONSTATUSCHANGE)) {
				$subject .= $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($this->internal['currentRow']['status'])), $this->internal['currentRow']['status']) . ': ';
			} else {
				$subject .= $this->pi_getLL('email_subject_type_changed', 'changed') . ': ';
			}
			$subject .= $this->internal['currentRow']['title'];


				// Andreas Kiefer, kiefer@kennziffer.com, 15:15 15.05.2009
				// don't send if only internal fields are changed,
				// that are configured as "don't send notification" in TS
			$sendNotification = false;

				// always send notification if "normal" fields have been changed
			if ($changedFields != "" ) {
				$sendNotification = true;
			} else {
					// check if only fields have been changed where
					// no notification is wanted. this applies only to
					// internal fields
				$internalFieldsWithoutNotification = t3lib_div::trimExplode(',', $this->conf['email_notifications.']['internalFieldsWithoutNotification']);
				$changedInternalFieldsArray = explode(',',$changedInternalFields);
				foreach ($changedInternalFieldsArray as $internalField) {
					if (!in_array($internalField, $internalFieldsWithoutNotification)) {
						$sendNotification = true;
					}
				}
			}

				// send notifications to owner
			if ($this->internal['currentRow']['owner_feuser']
					&& ($this->internal['currentRow']['notifications_owner'] == CONST_ONEVERYCHANGE
						|| (
							$this->internal['currentRow']['notifications_owner'] == CONST_ONSTATUSCHANGE
							&& t3lib_div::inList($changedFields, 'status')
						)
						|| (
							$this->internal['currentRow']['notifications_owner'] == CONST_TYPOSCRIPT
							&& $this->checkCustomNotificationCondition($changedFields, $this->conf['email_notifications.']['ownerNotificationOnChangedFields'])
						)
					)
					&& $sendNotification) {

					// get the user data of the owner
				$fe_user_data = $this->getFeUserData($this->internal['currentRow']['owner_feuser']);

					// send standard mail
				if (is_array($fe_user_data) && !empty($changedFields) && !$this->isUserInternalUser($fe_user_data['uid'])) {
					$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody);
				}

					// send internal mail
				if (is_array($fe_user_data) && (!empty($changedFields) || !empty($changedInternalFields)) && $this->isUserInternalUser($fe_user_data['uid'])) {
					$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody_internal);
				}
			}

				// send notifications to responsible user
			if ($this->internal['currentRow']['responsible_feuser']
					&& ($this->internal['currentRow']['notifications_responsible'] == CONST_ONEVERYCHANGE
						|| (
							$this->internal['currentRow']['notifications_responsible'] == CONST_ONSTATUSCHANGE
							&& t3lib_div::inList($changedFields, 'status')
						)
						|| (
							$this->internal['currentRow']['notifications_responsible'] == CONST_TYPOSCRIPT
							&& $this->checkCustomNotificationCondition($changedFields, $this->conf['email_notifications.']['responsibleNotificationOnChangedFields'])
						)
					)
					&& $sendNotification) {

					// get the user data of the responsible user
				$fe_user_data = $this->getFeUserData($this->internal['currentRow']['responsible_feuser']);

					// send standard mail
				if (is_array($fe_user_data) && !empty($changedFields) && !$this->isUserInternalUser($fe_user_data['uid'])) {
					$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody);
				}

					// send internal mail
				if (is_array($fe_user_data) && (!empty($changedFields) || !empty($changedInternalFields)) && $this->isUserInternalUser($fe_user_data['uid'])) {
					$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody_internal);
				}
			}

				// list of observers
			if ($this->internal['currentRow']['observers_feuser']) {
				$observers = t3lib_div::trimExplode(',', $this->internal['currentRow']['observers_feuser']);
			} else {
				$observers = array();
			}

				// if the owner, the responsible user or the observers have changed,
				// treat the former owner, former responsible user and former observers
				// temporarily as observers (for this change only). For example, This notifies former responsible
				// users that they are not responsible anymore.
			if ($this->internal['currentRow']['owner_feuser'] != $this->oldTicket['owner_feuser']) {
				$observers[] = $this->oldTicket['owner_feuser'];
			}
			if ($this->internal['currentRow']['responsible_feuser'] != $this->oldTicket['responsible_feuser']) {
				$observers[] = $this->oldTicket['responsible_feuser'];
			}
			if ($this->oldTicket['observers_feuser']) {
				$observers_old = t3lib_div::trimExplode(',', $this->oldTicket['observers_feuser']);
				foreach ($observers_old as $observer_old) {
					if (!in_array($observer_old, $observers)) {
						$observers[] = $observer_old;
					}
				}
			}

				// send notifications to observers
			if (count($observers)) {
				foreach ($observers as $observer_uid) {
					if (($this->internal['currentRow']['notifications_observer'] == CONST_ONEVERYCHANGE
						|| (
							$this->internal['currentRow']['notifications_observer'] == CONST_ONSTATUSCHANGE
							&& t3lib_div::inList($changedFields, 'status')
						)
						|| (
							$this->internal['currentRow']['notifications_observer'] == CONST_TYPOSCRIPT
							&& $this->checkCustomNotificationCondition($changedFields, $this->conf['email_notifications.']['observersNotificationOnChangedFields'])
						)
					)
					&& $sendNotification) {

							// get the user data of the observer
						$fe_user_data = $this->getFeUserData($observer_uid);

							// send standard mail
						if (is_array($fe_user_data) && !empty($changedFields) && !$this->isUserInternalUser($fe_user_data['uid'])) {
							$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody);
						}

							// send internal mail
						if (is_array($fe_user_data) && (!empty($changedFields) || !empty($changedInternalFields)) && $this->isUserInternalUser($fe_user_data['uid'])) {
							$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody_internal);
						}
					}
				}
			}
		}
	}/*}}}*/

	/**
 	* checks the changed fields of a ticket against the options set in
 	* typoscript and decides, if a notification should be sent.
 	*
 	* @param   string $changedFields comma separated list of changed fields or keywords (e.g. on a new ticket)
 	* @param   string $options comma list of field names or keywors, see setup.txt for explanation
 	* @return  boolean
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Thu May 06 2010 14:50:46 GMT+0200
 	*/
	function checkCustomNotificationCondition($changedFields, $options) {
		$sendNotification = false;

			// comment?
		if (stristr($changedFields, CONST_NEWCOMMENT) && t3lib_div::inList($options, 'comment')) {
			$sendNotification = true;
		}

			// new ticket?
		if (stristr($changedFields, CONST_NEWTICKET) && t3lib_div::inList($options, 'newticket')) {
			$sendNotification = true;
		}

			// closed ticket?
		if (t3lib_div::inList($changedFields, 'status')
			&& stristr($this->internal['currentRow']['status'], CONST_STATUS_CLOSED)
			&& t3lib_div::inList($options, 'closed')) {
			$sendNotification = true;
		}

			// is one of the changedFields in the options-List?
		$changedFieldsArray = t3lib_div::trimExplode(',', $changedFields);
		foreach ($changedFieldsArray as $changedField) {
			if (t3lib_div::inList($options, $changedField)) {
				$sendNotification = true;
			}
		}

		return $sendNotification;
	}

	/**
	 * getFeUserData
	 *
	 * returns the fe_user data as an array
	 *
	 * @param int $fe_user_uid
	 * @access public
	 * @return void
	 */
	public function getFeUserData($fe_user_uid=0) {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');
		if ($fe_user_uid) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'uid=' . $fe_user_uid . $lcObj->enableFields('fe_users'));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				return $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			} else {
				return false;
			}
		}
	}/*}}}*/

	/**
	 * renderNotificationMail
	 *
	 * Renders a html page to be sent via email.
	 * Uses $this->internal['currentRow'].
	 *
	 * changedFields is the commalist of dabase fields that have changed.
	 * Also possible values in changedFields:
	 * NEWTICKET
	 * NEWCOMMENT
	 *
	 * @param string $changedFields
	 * @access public
	 * @return void
	 */
	function renderNotificationMail($changedFields='', $changedInternalFields='') {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');
		$content = $this->cObj->getSubpart($this->templateCode,'###EMAIL_NOTIFICATION###');
		$localMarkerArray = array();
		$lConf = $this->conf['email_notifications.'];
		$fieldsArray = t3lib_div::trimExplode(',', $lConf['fieldList']);
		$changedFieldsArray = t3lib_div::trimExplode(',', $changedFields);

		// get the markers
		foreach ($fieldsArray as $fieldName) {
			if (strtolower(trim($fieldName)) == 'comments') {
				$markerContent = '<strong>' . $this->pi_getLL('LABEL_COMMENT_HEADER') . '</strong><br />';

					// check if a comment has been submitted.
				if (strstr($changedFields, CONST_NEWCOMMENT) || strstr($changedFields, CONST_REOPENANDCOMMENT)) {
					$markLatestComment = 1;
				} else {
					$markLatestComment = 0;
				}
				$markerContent .= $this->renderCommentList($this->internal['currentRow']['uid'], '', 0, $markLatestComment);
			} else {
				$markerContent = $this->getFieldContent(strtolower(trim($fieldName)), CONST_RENDER_TYPE_EMAIL);
			}
			$localMarkerArray['EMAIL_FIELD_' . strtoupper(trim($fieldName))] = $markerContent;
		}

		// find out what type of change
		if (stristr($changedFields, CONST_NEWTICKET)) {
			$type = 'new';
		} else if (stristr($this->internal['currentRow']['status'], CONST_STATUS_CLOSED)) {
			$type = 'closed';
		} else {
			$type = 'changed';
		}

			// what has happened?
		if ($GLOBALS['TSFE']->loginUser) {
				// this is too much
			//$localMarkerArray['WHAT_HAS_HAPPENED'] = $this->pi_getLL('email_text_user', 'user:');

				// add name
			$localMarkerArray['WHAT_HAS_HAPPENED'] .= ' ' . $this->renderNameFromFeUserUid($GLOBALS['TSFE']->fe_user->user['uid']) . ' ';

			$localMarkerArray['WHAT_HAS_HAPPENED'] .= $this->pi_getLL('email_text_type_' . $type . '_with_user', $type);
		}  else {
			$localMarkerArray['WHAT_HAS_HAPPENED'] = $this->pi_getLL('email_text_type_' . $type, $type);
		}

		$localMarkerArray['WHAT_HAS_HAPPENED'] = $this->cleanUpHtmlOutput($localMarkerArray['WHAT_HAS_HAPPENED']);

			// clear the internal changes marker
		$localMarkerArray['INTERNAL_CHANGES'] = '';

			// which fields have changed?
			// on new tickets, nothing has changed, so we don't render the changed fields
		$firstField = true;
		if (!stristr($changedFields, CONST_NEWTICKET)) {
			$localMarkerArray['WHAT_HAS_HAPPENED'] .= '<br />';
			$localMarkerArray['WHAT_HAS_HAPPENED'] .= $this->cleanUpHtmlOutput($this->pi_getLL('email_text_fields_have_changed')) . ' ';

				// internal fields
			if (strlen($changedInternalFields)) {
				foreach (explode(',', $changedInternalFields) as $fieldName) {
					if (!$firstField) {
						if ($fieldName == CONST_REOPENANDCOMMENT || $fieldName == CONST_NEWCOMMENT) {
							$localMarkerArray['WHAT_HAS_HAPPENED'] .= '<br />';
						} else {
							$localMarkerArray['WHAT_HAS_HAPPENED'] .= ', ';
						}
					}
					$localMarkerArray['WHAT_HAS_HAPPENED'] .= $this->cleanUpHtmlOutput($this->pi_getLL('LABEL_' . strtoupper(trim($fieldName)), $fieldName));
					$firstField = false;
				}

					// render the changes in the internal fields (if there are any)
				$localMarkerArray['INTERNAL_CHANGES'] = $this->renderChangedInternalFields($changedInternalFields);
			}

				// standard fields
			if (strlen($changedFields)) {
				foreach ($changedFieldsArray as $fieldName) {
					if (!$firstField) {
						if ($fieldName == CONST_REOPENANDCOMMENT || $fieldName == CONST_NEWCOMMENT) {
							$localMarkerArray['WHAT_HAS_HAPPENED'] .= '<br />';
						} else {
							$localMarkerArray['WHAT_HAS_HAPPENED'] .= ', ';
						}
					}
					$localMarkerArray['WHAT_HAS_HAPPENED'] .= $this->cleanUpHtmlOutput($this->pi_getLL('LABEL_' . strtoupper(trim($fieldName)), $fieldName));
					$firstField = false;
				}
			}

		}

			// render styles for cells. Styles depend on wether the
			// field changed or not.
		foreach ($fieldsArray as $fieldName) {
			if (in_array($fieldName, $changedFieldsArray)) {
					// if a configuration for this field exists, use it. Otherwise,
					// use the default
				if ($lConf['cellStyleFieldHasChanged_' . strtolower($fieldName)]) {
					$localMarkerArray['CELLSTYLE_' . strtoupper($fieldName)] = $lConf['cellStyleFieldHasChanged_' . strtolower($fieldName)];
				} else {
					$localMarkerArray['CELLSTYLE_' . strtoupper($fieldName)] = $lConf['cellStyleFieldHasChanged'];
				}
			} else {
				if ($lConf['cellStyleDefault_' . strtolower($fieldName)]) {
					$localMarkerArray['CELLSTYLE_' . strtoupper($fieldName)] = $lConf['cellStyleDefault_' . strtolower($fieldName)];
				} else {
					$localMarkerArray['CELLSTYLE_' . strtoupper($fieldName)] = $lConf['cellStyleDefault'];
				}
			}
		}

			// for internal users (only!) we render all internal fields on a new ticket
		if (stristr($changedFields, CONST_NEWTICKET) && $changedInternalFields == CONST_RENDER_ALL_INTERNAL_FIELDS) {
			$localMarkerArray['INTERNAL_CHANGES'] = $this->renderChangedInternalFields(CONST_NEWTICKET);
		}

			// generate a link to the ticket

			// get the category data
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('singleviewpage',$this->categoryTablename,'uid="' . $this->internal['currentRow']['category'].'" ' . $lcObj->enableFields($this->categoryTablename));
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$categoryData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}

			// find out the singleview pid
			// the singleviewpage must be set in the category
			// If the category of the current ticket contains a single view page,
			// use that. Otherwise use the current page.
		if (is_array($categoryData) && !empty($categoryData['singleviewpage'])) {
			$singleViewPage = $categoryData['singleviewpage'];
		} else {
			$singleViewPage = $GLOBALS['TSFE']->id;
		}

			// get the template subpart and render the link
		$linkToTicketSubpart = $this->cObj->getSubpart($this->templateCode,'###EMAIL_NOTIFICATION_LINKTOTICKET###');
		$linkToTicketURL = $this->pi_getPageLink($singleViewPage, '_blank', array(
				'tx_ketroubletickets_pi1[showUid]' => $this->internal['currentRow']['uid']
			)
		);
		if (!empty($linkToTicketURL)) {
			$localSubpartMarkerArray = array(
				//'URL_GO_TO_TICKET' => t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $linkToTicketURL,
				'URL_GO_TO_TICKET' => $linkToTicketURL,
				'LINKTEXT_GO_TO_TICKET' => $this->pi_getLL('LABEL_GO_TO_TICKET')
			);
			$linkToTicketSubpart = $this->cObj->substituteMarkerArray($linkToTicketSubpart,$localSubpartMarkerArray,'###|###',true);
		} else {
			$linkToTicketSubpart = '';
		}
		$localMarkerArray['LINK_TO_SINGLEVIEW_FROM_EMAIL'] = $linkToTicketSubpart;

		// hook for additional notification marker
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['additionalNotificationMarker'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['additionalNotificationMarker'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->additionalNotificationMarker($localMarkerArray, $this);
			}
		}

			// get some more markers
		$localMarkerArray = $this->getAdditionalMarkers($localMarkerArray, CONST_RENDER_TYPE_EMAIL);

			// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$localMarkerArray,'###|###',true);

		return $content;
	}/*}}}*/

	/**
	 * renderChangedInternalFields
	 *
	 * @param string $changedInternalFields
	 * @access public
	 * @return void
	 */
	public function renderChangedInternalFields($changedInternalFields='') {/*{{{*/
		if (strlen($changedInternalFields)) {
			$content = $this->cObj->getSubpart($this->templateCode,'###INTERNAL_CHANGES_SUBPART###');
			$localMarkerArray = array();
			$localMarkerArray['INTERNAL_CHANGES'] = '';

			// on new tickets, render all internal fields
			if ($changedInternalFields == CONST_NEWTICKET) {
				$changedInternalFields = '';
				foreach ($this->conf['formFieldList.'] as $fieldConf) {
					if ($fieldConf['internal']) {
						$changedInternalFields .= $fieldConf['name'] . ',';
					}
				}
			}
			$changedInternalFields = rtrim($changedInternalFields,',');

			foreach (explode(',', $changedInternalFields) as $fieldName) {
				$localMarkerArray['INTERNAL_CHANGES'] .= $this->cleanUpHtmlOutput($this->pi_getLL('LABEL_' . strtoupper(trim($fieldName)), $fieldName));
				$localMarkerArray['INTERNAL_CHANGES'] .= ': ';
				$localMarkerArray['INTERNAL_CHANGES'] .= $this->getFieldContent($fieldName);
				$localMarkerArray['INTERNAL_CHANGES'] .= '<br />';
			}
			$content = $this->cObj->substituteMarkerArray($content,$localMarkerArray,'###|###',true);
		} else {
			$content = '';
		}
		return $content;
	}/*}}}*/

	/**
	 * sendNotificationEmail
	 *
	 * sends the notification email, uses the TYPO3 mail functions
	 *
	 * @param string $toEMail
	 * @param string $subject
	 * @param string $html_body
	 * @param int $sendAsHTML
	 * @access public
	 * @return void
	 */
	public function sendNotificationEmail($toEMail, $subject, $html_body, $sendAsHTML = 1) {/*{{{*/
			// don't send a mail twice to the same user (for example if he is
			// responsible AND observer)
		if (in_array($toEMail, $this->alreadySentTo)) {
			return ;
		}
		$this->alreadySentTo[] = $toEMail;

			// only send a mail to the current user (who actually made the changes)
			// if configured so
		if ($GLOBALS['TSFE']->fe_user->user['email'] == $toEMail && !$this->conf['sendNotificationsToSelf']) {
			return ;
		}

		// Only ASCII is allowed in the header
		$subject = html_entity_decode(t3lib_div::deHSCentities($subject), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
		$subject = t3lib_div::encodeHeader($subject, 'base64', $GLOBALS['TSFE']->renderCharset);

		// create the plain message body
		$message = html_entity_decode(strip_tags($html_body), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);

		// inspired by code from tt_products, thanks
		if ($this->getNumericTYPO3versionNumber() >= 4005000){
			$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_mail_Message');
			$Typo3_htmlmail->setSubject($subject);
			$Typo3_htmlmail->setFrom(array($this->conf['email_notifications.']['from_email'] => $this->conf['email_notifications.']['from_name']));
	
			// add Attachments
			if (is_array($files) && count($files)>0) {
				foreach ($files as $attachment) {
					$Typo3_htmlmail->attach(Swift_Attachment::fromPath($uploadPath.$attachment));
				}
			}
	
			if ($sendAsHTML)  {
				$Typo3_htmlmail->setBody($html_body, 'text/html');
				if ($message && $this->conf['email_notifications.']['addPlainTextPart'])	{
					$Typo3_htmlmail->addPart($message, 'text/plain');
				}
			} else {
				$Typo3_htmlmail->addPart($message, 'text/plain');
			}
			$Typo3_htmlmail->setTo(explode(',', $toEMail));
			$Typo3_htmlmail->send();
		}else{
			$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_htmlmail');
			$Typo3_htmlmail->start();

			$Typo3_htmlmail->subject = $subject;
			$Typo3_htmlmail->from_email = $this->conf['email_notifications.']['from_email'];
			$Typo3_htmlmail->from_name = $this->conf['email_notifications.']['from_name'];
			$Typo3_htmlmail->replyto_email = $Typo3_htmlmail->from_email;
			$Typo3_htmlmail->replyto_name = $Typo3_htmlmail->from_name;
			$Typo3_htmlmail->organisation = '';

			// add Attachments
			if (is_array($files) && count($files)>0) {
				foreach ($files as $attachment) {
					$Typo3_htmlmail->addAttachment($uploadPath.$attachment);
				}
			}

			if ($sendAsHTML)  {
				$Typo3_htmlmail->theParts['html']['content'] = $html_body;
				$Typo3_htmlmail->theParts['html']['path'] = t3lib_div::getIndpEnv('TYPO3_REQUEST_HOST') . '/';

				$Typo3_htmlmail->extractMediaLinks();
				$Typo3_htmlmail->extractHyperLinks();
				$Typo3_htmlmail->fetchHTMLMedia();
				$Typo3_htmlmail->substMediaNamesInHTML(0);	// 0 = relative
				$Typo3_htmlmail->substHREFsInHTML();
				$Typo3_htmlmail->setHTML($Typo3_htmlmail->encodeMsg($Typo3_htmlmail->theParts['html']['content']));
				if ($message && $this->conf['email_notifications.']['addPlainTextPart'])	{
					$Typo3_htmlmail->addPlain($message);
				}
			} else {
				$Typo3_htmlmail->addPlain($message);
			}
			$Typo3_htmlmail->setHeaders();
			$Typo3_htmlmail->setContent();
			$Typo3_htmlmail->setRecipient(explode(',', $toEMail));
			$Typo3_htmlmail->sendTheMail();
		}
	}/*}}}*/

	/**
	 * renderCommentForm
	 *
	 * render the form for a new comment
	 *
	 * @access public
	 * @return void
	 */
	public function renderCommentForm($ticket_uid) {/*{{{*/
		$content = $this->cObj->getSubpart($this->templateCode,'###TICKET_COMMENT###');

			// render the form only if allowed for the current user
		if ($this->fieldIsWritableForCurrentUser(array('name' => 'comment'))) {

				// get the prefillValue
				// For example: If we wanted to update a ticket, but errors occurred.
				// Then the comment has not been written, but we don't want to loose
				// it, so we prefill the form with it.
			if (($this->piVars['newticket'] || $this->piVars['updateUid']) && $this->piVars['content']) {
				$prefillValue = $this->sanitizeData($this->piVars['content']);
			} else {
				$prefillValue = '';
			}

				// the form fields
			$localMarkerArray['FIELD_CONTENT'] = '<textarea name="' . $this->prefixId . '[content]" cols="' . $this->conf['comment_cols'] . '" rows="' . $this->conf['comment_rows'] . '">' . $prefillValue . '</textarea>';
			$localMarkerArray['FIELD_SUBMIT'] = '<input type="submit" name="' . $this->prefixId . '[comment_submit]' . '" value="'.$this->pi_getLL('LABEL_COMMENT_SUBMIT').'">';

		} else {

				// empty the form markers
			$localMarkerArray['FIELD_CONTENT'] = '';
			$localMarkerArray['FIELD_SUBMIT'] = '';

		}

			// show the existing comments
		$localMarkerArray['COMMENTLIST'] = $this->renderCommentList($this->internal['currentRow']['uid']);

			// get some more markers
		$localMarkerArray = $this->getAdditionalMarkers($localMarkerArray);

			// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content, $localMarkerArray, '###|###',true);

		return $content;
	}/*}}}*/

	/**
	 * renderCommentList
	 *
	 * renders a html list of all comments for a ticket
	 *
	 * @param integer $ticket_uid
	 * @param string $renderType defines if the commentlist should be rendered in text or html
	 * @param integer $latest if set to 1, give only the latest comment back
	 * @param integer $markNewestComment if set to 1, mark the newest comment with a background color set in typoscript (for email notifications).
	 * @access public
	 * @return string
	 */
	public function renderCommentList($ticket_uid, $renderType='', $latest=0, $markNewestComment=0) {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');
		$content = '';

			// if the newest comment should be marked, find out which one it is
		if ($markNewestComment) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', $this->commentsTablename, $where, '', 'uid desc', 1);
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$latestCommentUid = $row['uid'];
			} else {
				$latestCommentUid = 0;
			}
		}

			// build query
		$where = 'ticket_uid=' . $ticket_uid . $lcObj->enableFields($this->commentsTablename);
		if (!$latest) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->commentsTablename, $where, '', $this->conf['commentListOrderBy']);
		} else {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->commentsTablename, $where, '', 'uid desc', 1);
		}

		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
			if ($renderType != CONST_RENDER_TYPE_CSV) {
				$content .= '<div class="commentlist">';
				$commentrow_odd_even = 0;
			}
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				if ($renderType != CONST_RENDER_TYPE_CSV) {
					$commentline = $this->cObj->getSubpart($this->templateCode,'###OPTIONAL_COMMENT_ROW_SUBPART###');
					$localMarkerArray = array();
					$localMarkerArray['COMMENT_DATE'] = date($this->conf['comment_dateformat'], $row['crdate']);

						// Split up content into author and content
					$contentParts = explode(':', $row['content'], 2);
					$localMarkerArray['COMMENT_AUTHOR'] = $this->cleanUpHtmlOutput($contentParts[0]);
					$localMarkerArray['COMMENT_CONTENT'] = nl2br($this->cleanUpHtmlOutput($contentParts[1]));
					$localMarkerArray['COMMENTROW_ODD_EVEN'] = $commentrow_odd_even;
					$commentrow_odd_even = 1 - $commentrow_odd_even;

						// mark the newest comment in email notifications
					$localMarkerArray['COMMENTSTYLE'] = '';
					if ($markNewestComment && $row['uid'] == $latestCommentUid) {
						$localMarkerArray['COMMENTSTYLE'] = $this->conf['email_notifications.']['newCommentStyle'];
					}

						// substitute the markers
					$commentline = $this->cObj->substituteMarkerArray($commentline,$localMarkerArray,'###|###',true);
					unset($localMarkerArray);
					$content .= $commentline;
				} else {
					$content = date($this->conf['comment_dateformat'], $row['crdate']);
					$content .= ', ';
					$commentline = ' ' . strip_tags($row['content']);
					str_replace("\n", '', $commentline);
					str_replace("\r", '', $commentline);
					$content .= $commentline;
				}
			}
			if ($renderType != CONST_RENDER_TYPE_CSV) {
				$content .= '</div>';
			}
		}

		return $content;
	}/*}}}*/

	/**
	 * generateDBInsertValue
	 *
	 * Parses and cleans up the submitted form values found in piVars in order
	 * to insert them into the database.
	 *
	 * @param array $fieldConf
	 * @access public
	 * @return mixed
	 */
	public function generateDBInsertValue($fieldConf, $returnValue = '') {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');

		switch ($fieldConf['type']) {

			case 'textareaRTE':
				$returnValue .= $this->piVars[$fieldConf['name']];
			break;

            case 'checkbox':
                $returnValue .= intval($this->piVars[$fieldConf['name']]);
            break;

			case 'input':
			case 'textarea':
				$returnValue .= $this->sanitizeData($this->piVars[$fieldConf['name']]);
			break;

			case 'inputHoursToMinutes':
				if (empty($this->piVars[$fieldConf['name']])) return '';

					// convert the hours to minutes
				$hours = floatval(str_replace(',', '.', $this->piVars[$fieldConf['name']]));
				$returnValue = round( $hours * 60 );

					// validate value
				if (!intval($returnValue)) {
					$this->formErrors[] = $this->pi_getLL('formerror_hours');
				}
			break;

			case 'select':
			case 'categorySelect':
			case 'feuserSelect':
			case 'notificationsSelect':
			case 'addressSelect':
				// if this is a "multiple" field, implode the values to a comma-separated list
				if ($fieldConf['multiple'] && is_array($this->piVars[$fieldConf['name']])) {
					$returnValue .= implode(',',$this->piVars[$fieldConf['name']]);
				} else {
					$returnValue .= $this->piVars[$fieldConf['name']];
				}
			break;

			case 'input_related_tickets':
				$ticketUid = intval($this->piVars[$fieldConf['name']]);
				if ($ticketUid) {
					// check if the ticket exists
					// check if user the has access to the ticket
					if ($this->checkPermissionForTicket($ticketUid)) {
						// add it to the list:
						// first get the current value
						// then (if we are updating a ticket)
						// check if the relation already exists
						// then add it to the list
						// and update the ticket
						// or put the value into the insertFields (if it is a new ticket)
						if ($this->piVars['updateUid']) {
							if (!t3lib_div::inList($this->piVars['related_tickets_old'], $ticketUid)) {
								$returnValue = $this->piVars['related_tickets_old'];
								if ($returnValue) {
									$returnValue .= ',';
								}
								$returnValue .= $ticketUid;
							}
						} else {
							$returnValue = $ticketUid;
						}
					} else {
						$this->formErrors[] = $this->pi_getLL('formerror_could_not_create_related_ticket');
					}
				} else {
					// bugfix version 1.1.3, CB, 27.11.09
					// if there is no ticket uid given (so the input field ist empty),
					// make sure, that at least the already given tickets will not be deleted!
					// relations to other tickets must be deletedy by clicking on the
					// "delete"-icon, not by submitting an empty "related tickets"-field
					if ($this->piVars['updateUid']) {
						$returnValue = $this->piVars['related_tickets_old'];
					}
				}
				break;

			case 'date':
				// parse the date to a timestamp
				$timestamp = strtotime($this->piVars[$fieldConf['name']]);
				if (!$timestamp && $fieldConf['required']) {
					$this->formErrors[] = $this->pi_getLL('formerror_date');
				} else {
					$returnValue = $timestamp;
				}
			break;

			case 'files':
				for ($i = 1; $i<=$fieldConf['maxFiles']; $i++) {
					$attachmentName = $this->prefixId . '_' . $fieldConf['name'] . '_' . $i;
					if (strlen($_FILES[$attachmentName]['name'])) {
						$uploadedFile = $this->handleUpload($attachmentName);
						if (strlen($uploadedFile)) {
							if (strlen($returnValue)) {
								$returnValue .= ',';
							}
							$returnValue .= $uploadedFile;
						}
					}
				}
			break;

			default:

			break;
		}
		return $returnValue;
	}/*}}}*/

	/**
	 * Uploads the file given in the form-field $attachmentName to the server
	 *
	 * success: returns the new filename
	 * no success: returns false
	 *
	 * @param string $attachmentName
	 * @return array
	 */
	public function handleUpload($attachmentName='attachment') {/*{{{*/
		$success = true;

		// does the directory exist?
		if (!is_dir($this->fileUploadDir)) {
			$this->formErrors[] = $this->pi_getLL('error_no_upload_directory','Upload directory does not exist.');
		}

		// set deault values
		$this->conf['maxFileSize'] = $this->conf['maxFileSize'] ? $this->conf['maxFileSize'] : 20000000;

		// get the destination filename
		$filefuncs = new t3lib_basicFilefunctions();
		$uploadfile = $filefuncs->getUniqueName($filefuncs->cleanFileName($_FILES[$attachmentName]['name']), $this->fileUploadDir);

		// Filesize OK?
		if($_FILES[$attachmentName]['size'] > $this->conf['maxFileSize']){
			$this->formErrors[] = $this->pi_getLL('error_file_too_big','Error: File is too big.');
			$success=false;
		}

		// File extension allowed?
		if(!$this->extAllowed($_FILES[$attachmentName]['name'])){
			$this->formErrors[] = $this->pi_getLL('error_filetype_not_allowed','Error: This Filetype is not allowed.');
			$success=false;
		}

		if($success && move_uploaded_file($_FILES[$attachmentName]['tmp_name'], $uploadfile)) {
			// success
			// change rights so that everyone can read the file
			chmod($uploadfile,octdec('0644'));
 		} else {
			$this->formErrors[] = $this->pi_getLL('error_file_upload_not_successful','Error: File upload was not successfull.');
			$success=false;
		}

		if ($success) {
			return basename($uploadfile);
		} else {
			return false;
		}
	}/*}}}*/

	/**
	 * renderTicketForm / SingleView
	 *
	 * renders the form for a new ticket
	 * or for updating an existing ticket
	 *
	 * @access public
	 * @return void
	 */
	public function renderTicketForm() {/*{{{*/
		$lcObj=t3lib_div::makeInstance('tslib_cObj');

			// clear markers which won't be filled if the corresponding files
			// are marked as "not writable", e.g. RTE Javascript
		$this->markerArray['ADDITIONALJS_PRE'] = '';
		$this->markerArray['ADDITIONALJS_POST'] = '';
		$this->markerArray['UKB_FORM'] = '';
		
		
			// set css and js files that have to be included
		$cssFiles = array();
		$jsFiles = array();
		
			// Include default CSS?
 		if ($this->conf['includeDefaultCSS']) $cssFiles['css'] = $this->defaultCSS;
			// Include jQuery?
		if ($this->conf['includeJQuery']) $jsFiles['jquery'] = 'js/jquery-1.9.1.min.js';
			// Include jQueryUI?
		if ($this->conf['includeJQueryUI']) {
			$jsFiles['jqueryui'] = 'js/jquery-ui/jquery-ui-1.10.2.custom.min.js';
			$cssFiles['jqueryuicss'] = 'js/jquery-ui/ui-lightness/jquery-ui-1.10.2.custom.min.css';
		}
			// Include Todo functions?
		if ($this->conf['includeToDoFunctions']) $jsFiles['todo'] = 'js/todo.js';

			// Include configured CSS and JS files regarding to 
			// current TYPO3 version
		if ($this->getNumericTYPO3versionNumber() >= 6000000) {
			foreach($cssFiles as $cssFile) {
				$GLOBALS['TSFE']->getPageRenderer()->addCssFile($this->extPath . $cssFile);
			}
			foreach ($jsFiles as $jsFile) {
				$GLOBALS['TSFE']->getPageRenderer()->addJsFile($this->extPath . $jsFile);
			}
		} else {
			foreach($cssFiles as $key => $cssFile) {
				$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_' . $key] = '<link rel="stylesheet" type="text/css" href="' . $this->extPath . $cssFile . '" />';
			}
			foreach ($jsFiles as $key => $jsFile) {
				$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_' . $key] = '<script type="text/javascript" src="' . $this->extPath . $jsFile . '"></script>';
			}
		}
				
			// get additional markers (locallang, ...)
		$this->markerArray = $this->getAdditionalMarkers($this->markerArray);

			// remember some of the piVars when we go back the list view
		foreach (explode(',',$this->conf['keepPiVars']) as $piVarName) {
			$this->hiddenFormFields['piVar_'.$piVarName] = '<input type="hidden" name="' . $this->prefixId . '[' . $piVarName . ']" value="' . $this->piVars[$piVarName] . '">';
		}

			// if no UID is set, we create a new ticket
			// otherwise, we update a ticket
		if (empty($this->piVars['showUid']) && empty($this->piVars['updateUid'])) {
			$this->hiddenFormFields['newticket'] = '<input type="hidden" name="' . $this->prefixId . '[newticket]" value="1">';
			$this->markerArray['LABEL_EDIT_TICKET'] = '';

				// if we are creating a new ticket, we have to have at least one category
			$where_clause = 'pid IN (' . $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']) . ')';
			if (!$this->ffdata['all_categories'] && $this->ffdata['categories']) {
				$where_clause .= ' AND uid IN (' . $this->ffdata['categories'] . ')';
			}
			$where_clause .= $lcObj->enableFields($this->categoryTablename);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$this->categoryTablename,$where_clause,'','sorting');
			$num_rows = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			if (!$num_rows) {
				return '<p class="error">' . $this->pi_getLL('error_no_category') . '</p>';
			}

				// if we create a new ticket we have to have responsible users to
				// select from or a prefilled responsible user
			foreach ($this->conf['formFieldList.'] as $fieldConf) {
				if ($fieldConf['name'] == 'responsible_feuser') {
					$prefillValue = $this->getPrefillValue($fieldConf);
					if (!$this->fieldIsWritableForCurrentUser($fieldConf) && empty($prefillValue)) {
						return '<p class="error">' . $this->pi_getLL('error_no_responsible_user') . '</p>';
					}
				}
			}

				// set marker for uid
			$this->markerArray['UID'] = $this->pi_getLL('LABEL_NOT_AVAILABLE','n/a');
		} else {
			$this->hiddenFormFields['updateUid'] = '<input type="hidden" name="' . $this->prefixId . '[updateUid]" value="' . $this->internal['currentRow']['uid'] . '">';

				// remember the related tickets
			$this->hiddenFormFields['related_tickets_old'] = '<input type="hidden" name="' . $this->prefixId . '[related_tickets_old]" value="' . $this->internal['currentRow']['related_tickets'] . '">';
			$this->markerArray['LABEL_OPEN_NEW_TICKET'] = '';
		}

            // add date picker javascript to the header and configure it
		if (!$this->useDate2Cal) {
            $jsCode =
'<script type="text/javascript">
	var DateSeparator="' . $this->conf['datepicker.']['separator'] . '";
</script>';
            if ($this->getNumericTYPO3versionNumber() >= 6000000) {
                $GLOBALS['TSFE']->getPageRenderer()->addHeaderData('<script type="text/javascript" src="'.$this->extPath.'js/datetimepicker.js"></script>');
                $GLOBALS['TSFE']->getPageRenderer()->addHeaderData($jsCode);
            } else {
                $GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_datetimepicker'] = '<script type="text/javascript" src="'.$this->extPath.'js/datetimepicker.js"></script>';
                $GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_datetimepicker_config'] .= $jsCode;
            }
		}

			// get the template subpart
		$content = $this->cObj->getSubpart($this->templateCode,'###TICKET_FORM###');

			// if the form already has been submitted and errors occured, render
			// the error messages into the template
		$this->markerArray['ERRORS'] = '';
		if ( ($this->piVars['newticket'] || $this->piVars['updateUid']) && count($this->formErrors) > 0) {
			foreach ($this->formErrors as $error) {
				$this->markerArray['ERRORS'] .= '<div class="error">' . $error . '</div>';
			}
		}

			// get the field markers (render the form fields)
		foreach ($this->conf['formFieldList.'] as $fieldConf) {
				
			// decide wether the field is editable and output either the
				// form field or just the content of that field.
			if ($this->fieldIsWritableForCurrentUser($fieldConf)) {
					// current user has write access
				$this->markerArray['FIELD_' . strtoupper(trim($fieldConf['name']))] = $this->renderFormField($fieldConf, $fieldConf['renderEmptyDropdownField']);
			} else {
				// current user has no write access
					// render the field
				$this->markerArray['FIELD_' . strtoupper(trim($fieldConf['name']))] = $this->getFieldContent($fieldConf['name'], 'default', $fieldConf);

					// In order not to loose the value, we add a hidden field
					// to the form.
				$prefillValue = $this->getPrefillValue($fieldConf);
				$prefillValue = $this->parsePrefillValue($fieldConf, $prefillValue);
				if (!empty($prefillValue)) {
					$this->hiddenFormFields[$fieldConf['name']] = '<input type="hidden" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="'. $prefillValue .'">';
					$this->internal['currentRow'][$fieldConf['name']] = $prefillValue;
				}
			}

				// make the values of the ticket available without the need to
				// put them into a form field (static markers)
				// (only if we are editing an existing ticket)
			if ( ($this->piVars['showUid'] || $this->piVars['updateUid'])) {

				$this->markerArray['VALUE_' . strtoupper(trim($fieldConf['name']))] = $this->getFieldContent($fieldConf['name'], 'default', $fieldConf);

			} else {

					// clear the markers if we open a new ticket (no ticket-uid is given)
				$this->markerArray['VALUE_' . strtoupper(trim($fieldConf['name']))] = '';

					// fill in the current user for "owner" (the user who opens a ticket is always the owner)
				if ($fieldConf['name'] == 'owner_feuser') {
					$this->markerArray['VALUE_' . strtoupper(trim($fieldConf['name']))] =
						$this->renderNameFromFeUserUid($GLOBALS['TSFE']->fe_user->user['uid']);
				}
			}

				// If this is an internal field:
				// If the current user may view internal fields, get the
				// corresponding subpart and copy it into the template in order to
				// replace the correct markers.
				// Otherwise clear the marker for the internal field.
			if ($fieldConf['internal']) {
				if ($this->isCurrentUserInternalUser()) {
					$internalFieldContent = $this->cObj->getSubpart($this->templateCode,'###INTERNAL_' . strtoupper($fieldConf['name']) . '_SUBPART###');
				} else {
					$internalFieldContent = '';
				}
				$content = $this->cObj->substituteMarker($content, '###INTERNAL_' . strtoupper($fieldConf['name']) . '###', $internalFieldContent);
			}

				// If this is an optional field:
				// If there is content, get the corresponding subpart and copy it into the template in order to replace the correct markers.
				// Otherwise clear the marker for the optional field.
			if ($fieldConf['optional']) {
				if (!empty($this->markerArray['FIELD_' . strtoupper(trim($fieldConf['name']))])) {
					$optionalFieldContent = $this->cObj->getSubpart($this->templateCode,'###OPTIONAL_' . strtoupper($fieldConf['name']) . '_SUBPART###');
				} else {
					$optionalFieldContent = '';
				}
				$content = $this->cObj->substituteMarker($content, '###OPTIONAL_' . strtoupper($fieldConf['name']) . '###', $optionalFieldContent);
			}
		}

			// add the crdate
		$this->markerArray['CRDATE'] = $this->getFieldContent('crdate');

			// add the hidden fields
		$this->markerArray['hidden_fields'] = implode("\n",$this->hiddenFormFields);

			// add the form markers
		$this->markerArray['TICKETFORM_NAME'] = $this->ticketFormName;
		$this->markerArray['TICKETFORM_ACTION'] = $this->cObj->typoLink_URL(array('parameter' => $GLOBALS['TSFE']->id));

			// add the ticket history
		$this->markerArray['OPTIONAL_TICKET_HISTORY'] = $this->renderTicketHistory($this->internal['currentRow']['uid']);

			// add the comment form and the list of comments
			// (only if we are editing an existing ticket or comments are allowed for new ticket form)
		if (($this->piVars['showUid'] || $this->piVars['updateUid']) || ($this->piVars['do'] == 'new' && $this->conf['allowCommentsInNewTicketForm'])) {
			$this->markerArray['OPTIONAL_TICKET_COMMENT'] = $this->renderCommentForm($this->internal['currentRow']['uid']);
		} else {
			$this->markerArray['OPTIONAL_TICKET_COMMENT'] = '';
		}

			// add a link to open a follow-up ticket (makes only sense for
			// existing tickets)
		if ( ($this->piVars['showUid'] || $this->piVars['updateUid'])) {
			$linkConf = array(
						'parameter' => $GLOBALS['TSFE']->id,
						'additionalParams' => $this->getAdditionalParamsFromKeepPiVars()
							. '&' . $this->prefixId . '[do]=new'
							. '&' . $this->prefixId . '[followup]=' . $this->internal['currentRow']['uid']
						);
			$this->markerArray['FOLLOWUPLINK'] = '<span class="followuplink">' . $this->cObj->typoLink($this->pi_getLL('link_followup', 'Follow-up ticket.'), $linkConf) . '</span>';
		} else {
			$this->markerArray['FOLLOWUPLINK'] = '';
		}



		// hook for additional ticketForm Markers
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['additionalTicketFormMarker'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['additionalTicketFormMarker'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->additionalTicketFormMarker($this->markerArray, $this);
			}
		}


			// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);

			// overwrite subpart in toolbar with empty ocntent if we create a new ticket
		if (empty($this->piVars['showUid']) && empty($this->piVars['updateUid'])) {
			$content = $this->cObj->substituteSubpart ($content, '###TOOLBAR_EDITONLY###', '');
		}

		return $content;
	}/*}}}*/

	/**
 	* checks, if the given field of the current ticket ist writeable for
 	* the current user.
 	*
	* fields may be edited if
	*- if no restriction is set via typoscript
	* - a restriction is set but the user has the right usergroup
	* - the ticket is not locked (does not apply to field "status") or the
	* field is in the list of fields which may be edited in locked tickets also
 	*
 	* @param   array $fieldConf Configuration Array (defined in typoscript) of the field to check.
 	* @return  boolean
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Tue May 18 2010 10:00:47 GMT+0200
 	*/
	public function fieldIsWritableForCurrentUser($fieldConf) {
		if (empty($fieldConf['writeAccessOnlyForUserGroup']) || t3lib_div::inList($GLOBALS['TSFE']->fe_user->user['usergroup'], $fieldConf['writeAccessOnlyForUserGroup'])) {
			$returnValue = true;
		} else {
			$returnValue = false;
		}

			// Is the current ticket locked, allow only the fields defined
			// in typoscript to be edited. The fields differ for internal and
			// normal users.
		if ($this->internal['currentRow']['status'] == CONST_STATUS_CLOSED_LOCKED) {
			if ($this->isCurrentUserInternalUser()) {
				$allowedFields = $this->conf['allowFieldsInLockedTicketsForInternalUsers'];
			} else {
				$allowedFields = $this->conf['allowFieldsInLockedTickets'];
			}
			if (!t3lib_div::inList($allowedFields, $fieldConf['name'])) {
				$returnValue = false;
			}
		}

			// If a new ticket has been created, some values may be
			// defined in hidden fields (set in the backend via flexform).
			// We have to allow that here.
		$allowedFieldsInNewTickets = 'responsible_feuser,observers_feuser,notifications_owner,notifications_responsible,notifications_observer';
		if ($this->piVars['newticket'] && t3lib_div::inList($allowedFieldsInNewTickets, $fieldConf['name'])) {
			$returnValue = true;
		}

		return $returnValue;
	}

	/**
	 * printview
	 *
	 * Render print optimized view of single ticket
	 * Author: Andreas Kiefer (kiefer@kennziffer.com)
	 *
	 * @access public
	 * @return string
	 */
	public function printview() {

        // include Javascript for printview
        // include special css for printview
        $jsCode = '<script type="text/javascript">window.print();</script>';

		$cssfile = t3lib_extMgm::siteRelPath($this->extKey).'res/css/ke_troubletickets_printview.css';
        if ($this->getNumericTYPO3versionNumber() >= 6000000) {
            $GLOBALS['TSFE']->getPageRenderer()->addHeaderData('<link rel="stylesheet" type="text/css" href="'.$cssfile.'" />');
            $GLOBALS['TSFE']->getPageRenderer()->addHeaderData($jsCode);
        } else {
            $GLOBALS['TSFE']->additionalHeaderData[$this->prefixId] .= '<link rel="stylesheet" type="text/css" href="'.$cssfile.'" />';
            $GLOBALS['TSFE']->additionalHeaderData[$this->prefixId] .=  $jsCode;
        }

		// get the template subpart
		$content = $this->cObj->getSubpart($this->templateCode,'###PRINTVIEW###');

		// get additional markers (locallang, ...)
		$this->markerArray = $this->getAdditionalMarkers($this->markerArray);

		// get the field markers (render the form fields)
		foreach ($this->conf['formFieldList.'] as $fieldConf) {
			// show internal fields  if internal user is logged in
			if ($fieldConf['internal'] && $this->isCurrentUserInternalUser()) {
				$this->markerArray['FIELD_' . strtoupper(trim($fieldConf['name']))] = $this->getFieldContent($fieldConf['name']);
			}
			// clear subpart if internal fields are not shown
			else if ($fieldConf['internal'] && !$this->isCurrentUserInternalUser()) {
				$content = $this->cObj->substituteSubpart ($content, '###INTERNAL_' . strtoupper(trim($fieldConf['name'])), '');
			}
			// improved rendering of field "description"
			else if ($fieldConf['name'] == 'description') {
				$fieldContent = $this->internal['currentRow'][$fieldConf['name']];
				// strip some tags
				$fieldContent = strip_tags($fieldContent, '<p>,<br>,<h1>,<h2>,<h3>,<h4>,<h5>,<h6>,<div>,<a>,<img>,<table><th>,<tr>,<td>,<li>,<ul>,<ol>');
				$fieldContent = $this->pi_RTEcssText($fieldContent);
				// strip ms office tags
				$fieldContent = str_replace('&lt;o:p&gt;&nbsp;&lt;/o:p&gt;','',$fieldContent);
				$fieldContent = str_replace('<p class="MsoNormal"></p>','',$fieldContent);
				// clear empty paragraphs
				$fieldContent = str_replace('<p class="bodytext">&nbsp;</p>','',$fieldContent);
				$fieldContent = str_replace('<p class="bodytext">&nbsp;</p>
','',$fieldContent);
				$fieldContent = str_replace('<p class="bodytext">&nbsp;
</p>','',$fieldContent);
				$this->markerArray['FIELD_' . strtoupper(trim($fieldConf['name']))] = $fieldContent;
			}
			// get content for other fields
			else $this->markerArray['FIELD_' . strtoupper(trim($fieldConf['name']))] = $this->getFieldContent($fieldConf['name']);
		}

		// get comments
		$this->markerArray['FIELD_TICKET_COMMENT'] = $this->renderCommentList($this->internal['currentRow']['uid'], $renderType);

		// hook for additional printview markers
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['additionalPrintviewMarker'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['additionalPrintviewMarker'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->additionalPrintviewMarker($this->markerArray, $this);
			}
		}

		// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);

		return $content;
	}

	/**
	 * isCurrentUserInternalUser
	 *
	 * Checks, if the current fe_user is an "internal" user.
	 *
	 * @access public
	 * @return void
	 */
	public function isCurrentUserInternalUser() {/*{{{*/
		return $this->isUserInternalUser($GLOBALS['TSFE']->fe_user->user['uid']);
	}/*}}}*/

	/**
	 * isUserInternalUser
	 *
	 * Checks, if a given user uid belongs to an internal user.
	 * Internal users are defined in the flexform configuration of the plugin.
	 *
	 * @param integer $user_uid
	 * @access public
	 * @return void
	 */
	public function isUserInternalUser($user_uid) {/*{{{*/
		if (is_array($this->internalUserList) && in_array($user_uid, $this->internalUserList)) {
			$result = true;
		} else {
			$result = false;
		}
		return $result;
	}/*}}}*/

	/**
	 * renderTicketHistory
	 *
	 * renders the history elements for a given ticket uid
	 *
	 * @param mixed $ticket_uid
	 * @access public
	 * @return void
	 */
	public function renderTicketHistory($ticket_uid) {/*{{{*/
		if ($ticket_uid) {
			// get the template
			$content = $this->cObj->getSubpart($this->templateCode,'###TICKET_HISTORY###');

			// render the rows
			$rowsContent = '';

			// get the history entries for this ticket
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->historyTablename, 'ticket_uid=' . $ticket_uid, '', $this->conf['history_order_by']);
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					// check, if this is an internal field, then render it only to internal members
					$is_internal = 0;
					foreach ($this->conf['formFieldList.'] as $fieldConf) {
						if ($fieldConf['name'] == $row['databasefield'] && $fieldConf['internal']) {
							$is_internal = 1;
						}
					}
					if (!$is_internal || $this->isCurrentUserInternalUser()) {
						$singleRowContent = $this->cObj->getSubpart($this->templateCode,'###TICKET_HISTORY_ROW_TEMPLATE###');
						$this->markerArray['HISTORY_DATE'] = date ($this->conf['history_dateformat'], $row['crdate']);
						$this->markerArray['HISTORY_USER'] = $this->lib->getNameListFromUidList($row['feuser_uid'], 'fe_users', 'username');
						$this->markerArray['HISTORY_FIELD'] = $this->pi_getLL('LABEL_' . strtoupper(trim($row['databasefield'])), $row['databasefield']);
						$this->markerArray['HISTORY_OLD_VALUE'] = strip_tags($row['value_old']) ? strip_tags($row['value_old']) : '---';
						$this->markerArray['HISTORY_NEW_VALUE'] = strip_tags($row['value_new']) ? strip_tags($row['value_new']) : '---';
						$singleRowContent = $this->cObj->substituteMarkerArray($singleRowContent, $this->markerArray,'###|###',true);
						$rowsContent .= $singleRowContent;
					}
				}
			}

			// put the rows into the ticket history template
			$content = $this->cObj->getSubpart($this->templateCode,'###TICKET_HISTORY###');
			$this->markerArray['TICKET_HISTORY_ROWS'] = $rowsContent;
			$content = $this->cObj->substituteMarkerArray($content, $this->markerArray,'###|###',true);
		} else {
			$content = '';
		}

		return $content;
	}/*}}}*/

	/**
	 * getAdditionalMarkers
	 *
	 * get markers from locallang and some markers useful for different functions in this class
	 *
	 * $markerArray array
	 * $renderType string
	 *
	 * @access public
	 * @return void
	 */
	public function getAdditionalMarkers($markerArray=array(), $renderType='') {/*{{{*/
		$lcObj=t3lib_div::makeInstance('tslib_cObj');

			// UID
		if (is_array($this->internal['currentRow']) && !empty($this->internal['currentRow']['uid'])) {
			$markerArray['UID'] = sprintf($this->conf['ticket_uid_formatstring'],$this->internal['currentRow']['uid']);
		} else {
			$markerArray['UID'] = '';
		}

			// "CLEAN" UID WITHOUT FORMATTING -- AK 17:18 25.05.2009
		if (is_array($this->internal['currentRow']) && !empty($this->internal['currentRow']['uid'])) {
			$markerArray['CLEANUID'] = $this->internal['currentRow']['uid'];
		} else {
			$markerArray['CLEANUID'] = '';
		}
		
			// STORAGE PID
		$markerArray['STORAGEPID'] = $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']);
		
			// COBJ UID
		$markerArray['COBJID'] = $this->cObj->data['uid'];
		
			// get the label markers from locallang
		foreach (explode(',', $this->conf['locallangLabelList']) as $labelName) {
			$markerArray['LABEL_' . trim($labelName)] = $this->pi_getLL('LABEL_' . trim($labelName));
			if ($renderType == CONST_RENDER_TYPE_EMAIL) {
				$markerArray['LABEL_' . trim($labelName)] = $this->cleanUpHtmlOutput($markerArray['LABEL_' . trim($labelName)]);
			}
		}

			// generate the "back" link marker
		$markerArray['BACK_TO_LISTVIEW'] = $this->cObj->typoLink(
				$this->pi_getLL('back_to_listview', 'Back to listview.'),
				array(
					'parameter' => $GLOBALS['TSFE']->id,
					'additionalParams' => $this->getAdditionalParamsFromKeepPiVars()
					)
				);

			// generate the "back" link marker - only url
		$markerArray['BACK_TO_LISTVIEW_URL'] = $this->cObj->typoLink_URL(
				array(
					'parameter' => $GLOBALS['TSFE']->id,
					'additionalParams' => $this->getAdditionalParamsFromKeepPiVars()
					)
				);

			// current url
		$markerArray['CURRENT_URL'] = htmlspecialchars(t3lib_div::getIndpEnv('REQUEST_URI'));

			// TODO: link to the current ticket singleview
			// The single-view of this ticket is defined in the category this ticket belongs to.
		// $mainPage = ...
		// $markerArray['LINK_TO_SINGLEVIEW'] $this->pi_list_linkSingle($this->pi_getLL('open_this_ticket','Open this ticket'),$this->internal['currentRow']['uid'],0,0,0,$mainPage);

			// generate the "open new ticket" link marker
		$linkConf = array(
					'parameter' => $GLOBALS['TSFE']->id,
					'additionalParams' => $this->getAdditionalParamsFromKeepPiVars() . '&' . $this->prefixId . '[do]=new'
					);
		$markerArray['LINK_TO_NEW_TICKET_FORM'] = $this->cObj->typoLink($this->pi_getLL('link_to_new_ticket', 'New ticket.'), $linkConf);
		$markerArray['LINK_TO_NEW_TICKET_FORM_URL'] = $this->cObj->typoLink_URL($linkConf);

			// generate the link to csv export
		$markerArray['LINK_URL_CSV_EXPORT'] = $this->cObj->typoLink_URL(
				array(
					'parameter' => $GLOBALS['TSFE']->id,
					'additionalParams' => $this->getAdditionalParamsFromKeepPiVars() . '&' . $this->prefixId . '[export]=csv'
					)
				);

			// Addtional Icons
		if ($this->conf['additionalIconList']) {
			foreach (t3lib_div::trimExplode(',', $this->conf['additionalIconList']) as $iconName) {
				$imageConf = $this->conf[$iconName . '.'];
				$imageConf['file'] = $this->getFilePath($imageConf['file']);
				$markerArray[strtoupper($iconName)] = $lcObj->IMAGE($imageConf);
			}
		}

			// Marker for date2cal
			// obsolete with date2cal version 7.3.1
			// just keep to empty the marker in order to make it compatible
			// with older templates which still containt ther marker DATE2CAL_JS
		// $markerArray['DATE2CAL_JS'] = $this->date2cal->getMainJS();
		$markerArray['DATE2CAL_JS'] = '';

			// Permalink URL
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$linkconf['additionalParams'] = '&tx_ketroubletickets_pi1[showUid]='.$this->piVars['showUid'];
		$markerArray['PERMALINK_URL'] = $this->cObj->typoLink_URL($linkconf);

			// printview URL
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id.' 800x600';
		$linkconf['additionalParams'] = '&tx_ketroubletickets_pi1[printview]='.$this->piVars['showUid'];
		$linkconf['JSwindow'] = 1;
		$linkconf['JSwindow_params'] = 'status=0,menubar=0,scrollbars=1,resizable=1,location=0,directories=0,toolbar=0';
		$markerArray['PRINTLINK'] = $this->cObj->typoLink($this->pi_getLL('LABEL_PRINTLINK'),$linkconf);

			// ke_ukb label markers
		if (t3lib_extMgm::isLoaded('ke_ukb')) {
			$markerArray['LABEL_RELATED_TICKETS'] = $this->cleanUpHtmlOutput($this->pi_getLL('LABEL_RELATED_TICKETS_UKB'));
		}

			// max. Filesize
		$markerArray['LABEL_MAXFILESIZE'] = str_replace(
			'###FILESIZE###', 
			$this->lib->filesize_format($this->conf['maxFileSize']), 
			$markerArray['LABEL_MAXFILESIZE']
		);

	return $markerArray;

	}/*}}}*/

	/**
	 * getAdditionalParamsFromKeepPiVars
	 *
	 * generate a "additional Params String" for the piVars which are set and
	 * in the list of piVars to keep
	 * We need this function, because we don't want to keep all of the piVars as
	 * the posted form vars are also handled as piVars
	 *
	 * @access public
	 * @return string
	 */
	public function getAdditionalParamsFromKeepPiVars() {/*{{{*/
		$additionalParams = '';

		foreach (explode(',',$this->conf['keepPiVars']) as $piVarName) {
			if ($this->piVars[$piVarName]) {
				$additionalParams .= '&' . $this->prefixId . '[' . $piVarName . ']=' . $this->piVars[$piVarName];
			}
		}
		return $additionalParams;
	}/*}}}*/


	/**
 	* getPrefillValue
 	*
 	* Finds the prefill-value for
 	* 1. follow-up ticketes
 	* 2. existing tickets which the user is about to edit
 	* 3. fields which have predefined values in the plugin flexform
 	* 4. on updating a ticket: If an error occured, get prefill the form fields
 	* with the submitted values. IMPORTANT: The value in piVars is the raw value from the
 	* form, not the value that would have been written into the database. So we
 	* have to use generateDBInsertValue().
 	*
 	* @param   array $fieldConf Field configuration as set in typoscript
 	* @return  string
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Wed Jun 02 2010 16:28:09 GMT+0200
 	*/
	public function getPrefillValue($fieldConf) {
		$prefillValue = '';

		if (count($this->parentTicket)) {
			$prefillValue = $this->getPrefillValueFromParentTicket($fieldConf);
		} else {
			if ($this->piVars['updateUid'] && count($this->formErrors)) {
				$prefillValue = $this->generateDBInsertValue($fieldConf);
			} else if (!empty($this->internal['currentRow'][$fieldConf['name']])) {
				$prefillValue = $this->internal['currentRow'][$fieldConf['name']];
			} else if ($fieldConf['name'] == 'responsible_feuser' && $this->ffdata['responsible_singleuser_preselected']) {
				$prefillValue = $this->ffdata['responsible_singleuser_preselected'];
			}
		}

		return $prefillValue;
	}

	/**
 	* Parses a value coming from the Database for the use in either a form
 	* field or in a hidden field.
 	*
 	* @param   array $fieldConf configuration for the field
 	* @param   mixed $value value of the field
 	* @return  mixed
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	*/
	public function parsePrefillValue($fieldConf, $prefillValue) {
		if ($prefillValue) {
			switch ($fieldConf['type']) {
				case 'inputHoursToMinutes':
					$prefillValue = $this->lib->m2h($prefillValue);
				break;

				case 'input':
					$prefillValue = $this->cleanUpHtmlOutput($prefillValue);
				break;

				case 'date':
					$prefillValue = intval($prefillValue);
					$prefillValue = date($this->conf['datefield_dateformat'], $prefillValue);
				break;

				case 'filelist':
					$prefillValue = $this->internal['currentRow']['files'];
				break;
			}
		}
		return $prefillValue;
	}

	/**
	 * renderFormField
	 *
	 * renders a form field depending on the given configuration $fieldConf
	 * if $renderEmptyDropdownFields is set, and empty value in dropdown fields is added (useful for rendering the filter fields)
	 *
	 * @param array $fieldConf
	 * @param integer $renderEmptyDropdownFields
	 * @param string $addJS if filled, content is added to the generated input/select tag. Useful to add javascript, e. g. "onchange".
	 * @access public
	 * @return void
	 */
	public function renderFormField($fieldConf, $renderEmptyDropdownFields=0, $addJS='') {/*{{{*/

		$lcObj=t3lib_div::makeInstance('tslib_cObj');
		$content = '';

			// Get the prefillValue:
			// 1. New ticket with form errors, Existing ticket the user want's to edit.
			// 2. If the form just has been submitted (new ticket),
			// prefill the form fields with the already parsed submitted values
			// ($this->insertFields).
			// 3. If we are rendering fields for the listview filter, we find the values
			// in $this->filter.
		if (($this->piVars['do'] == 'new' && !$this->piVars['newticket']) || $this->piVars['showUid'] || $this->piVars['updateUid']) {
			$prefillValue = $this->getPrefillValue($fieldConf);
		} else if ($this->piVars['newticket'] && strlen($this->insertFields[$fieldConf['name']])) {
			$prefillValue = $this->insertFields[$fieldConf['name']];
		} else if (is_array($this->filter)) {
			$prefillValue = $this->filter[$fieldConf['name']];
		} else {
			$prefillValue = '';
		}

		if ($addJS) {
			$addJS .= ' ';
		}

			// render the form fields according to their type
		switch ($fieldConf['type']) {

			case 'submit':
					$content .= '<input ' . $addJS . 'type="submit" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . '" value="'.$this->pi_getLL('LABEL_' . strtoupper($fieldConf['name'])).'">';
			break;

			case 'input':
			case 'inputHoursToMinutes':
				$prefillValue = $this->parsePrefillValue($fieldConf, $prefillValue);
				$content .= '<input ' . $addJS . 'type="text" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="' . $prefillValue . '" size="' . $fieldConf['size'] . '" maxlength="' . $fieldConf['maxlength'] . '">';
			break;

            case 'checkbox':
                $class = $fieldConf['css_class'] ? ' class="' . $fieldConf['css_class'] . '"' : '';
                $checked = $prefillValue ? ' checked="checked"' : '';
                $content ='<input type="checkbox" value="1" ' . $checked . $addJS . $class . 'name="' . $this->prefixId . '[' . $fieldConf['name'] . ']"' . '>';
            break;

			case 'select':
				$class = $fieldConf['css_class'] ? ' class="' . $fieldConf['css_class'] . '"' : '';
				$content ='<select ' . $addJS . $class . 'name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '')  . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';

					// render empty option
				if ($renderEmptyDropdownFields) {
					if (!$prefillValue) {
						$selected = ' selected';
					} else {
						$selected = '';
					}
					$content .= '<option value=""' . $selected . '>';
					$content .= $this->conf['emptyDropdownElement'];
					$content .= '</option>';
				}

				$valueList = $this->conf[$fieldConf['valueList']];

					// this is a HACK for the filter option "status". We want to
					// have a filter for "open" and "working" in one option.
					// TODO: Should be configurable in Typoscript in future versions
					// $renderEmptyDropdownFields is only set when rendering the filter dropdown,
					// so we use this as a condition.
				if ($fieldConf['name'] == 'charged' && $renderEmptyDropdownFields) {
					$valueList = NOT_FULLY_CHARGED_FILTER . ',' . $valueList;
				}

					// this is a HACK for the filter option "status". We want to
					// have a filter for "open" and "working" in one option.
					// TODO: Should be configurable in Typoscript in future versions
					// $renderEmptyDropdownFields is only set when rendering the filter dropdown,
					// so we use this as a condition.
				if ($fieldConf['name'] == 'status' && $renderEmptyDropdownFields) {
					$valueList = 'open_and_working,all_not_closed,all,' . $valueList;
				}

					// this is a HACK for the viewtype selector dropdown
					// since the previll value is not in the insertFields or in the
					// filter var, we get it from piVars
				if ($fieldConf['name'] == 'viewtype' && $this->piVars['viewtype']) {
					$prefillValue = $this->piVars['viewtype'];
				}

					// Generate the valueList for the closed_in_month-Filter.
					// This filter gives you the possibility to filter the tickets
					// according to the month they were closed in. So wie first get
					// the month in which a ticket has been closed for the first
					// time and add all months until today.
				if ($fieldConf['name'] == 'closed_in_month') {

						// Get the first closed ticket the user has access to.
						// IMPORTANT: Tickets may be re-opened, but the closing time remains in the ticket!
					$where_clause = 'close_time != 0';
					$where_clause .= $this->getUserAccessibleTicketsWhereClause($GLOBALS['TSFE']->fe_user->user['uid']);
					$where_clause .= ' AND pid IN (' . $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']) . ')';
					$where_clause .= $lcObj->enableFields($this->tablename);
					$res_month = $GLOBALS['TYPO3_DB']->exec_SELECTquery('close_time', $this->tablename, $where_clause, '', 'close_time ASC', 1);
					$valueList = '';

					if ($GLOBALS['TYPO3_DB']->sql_num_rows($res_month)) {
						$row_month = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_month);
						$year = date('Y', $row_month['close_time']);
						$month = date('m', $row_month['close_time']);
						$now = time();

							// render a list from the starting month until now, the
							// timestamp is the beginning of each month
						while ($year < date('Y', $now) || ($year == date('Y', $now) && $month <= date('m', $now))) {
							if ($valueList) {
								$valueList .= ',';
							}
							$valueList .= mktime(0, 0, 0, $month, 1, $year);
							$month++;
							if ($month>12) {
								$month = 1;
								$year++;
							}
						}
					}

						// render newest on top
					$valueListArray = explode(',', $valueList);
					arsort($valueListArray);
					$valueList = implode(',', $valueListArray);
					unset($valueListArray);
				}

					// render the list
				foreach (explode(',', $valueList) as $value) {
					if (strlen($value)) {
						$selected = $prefillValue == $value ? ' selected' : '';
						if ($fieldConf['name'] == 'closed_in_month') {
							$content .= '<option value="' . $value . '"' . $selected . '>' . date(($this->conf['listView.']['closed_in_month_dateformat'] ? $this->conf['listView.']['closed_in_month_dateformat'] : 'm-Y'), intval($value)) . '</option>';
						} else {
							$value = trim($value);
							$content .= '<option value="' . $value . '"' . $selected . '>' . $this->pi_getLL('SELECTLABEL_' . strtoupper($value), $value) . '</option>';
						}
					}
				}
				$content .= '</select>';
			break;

			case 'input_related_tickets':

				// AK 08.04.2010
				// universal keworks browser
				if (t3lib_extMgm::isLoaded('ke_ukb')) {
					// show ukb only in edit view, not when creating a ticket
					if ($this->piVars['showUid'] || $this->piVars['updateUid']) {
						// use showUid or updateUid ?
						$ukbTicketUid = $this->piVars['updateUid'] ? $this->piVars['updateUid'] : $this->piVars['showUid'];
						// pid list
						$storagePids = $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']);
						// get content
						$content = $this->ukb->renderContent('tx_ketroubletickets_tickets', $ukbTicketUid, $storagePids, $this);
						// fill markers
						$this->markerArray['UKB_FORM'] = $this->ukb->renderForm();
					} else {
						$content = $this->pi_getLL('ukb_after_saving');
						$content .= '<input type="hidden" name="'.$this->prefixId.'[ukb_followup]" value="'.$this->piVars['followup'].'" >';
						$this->markerArray['UKB_FORM'] = '';
					}

				} else {
					// usual "related tickets" handling if ke_ukb is not loaded
					$content = $this->renderRelatedTicketListForCurrentTicket();

					if ($this->piVars['newticket']) {
						$prefillValue = $this->piVars['related_tickets'];
					} else if (!$this->piVars['followup']) {
						$prefillValue = '';
					}
					$content .= $this->pi_getLL('LABEL_RELATED_TICKETS_ADD');
					$content .= ' <input ' . $addJS . 'type="text" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="' . $prefillValue . '" size="' . $fieldConf['size'] . '" maxlength="' . $fieldConf['maxlength'] . '">';
					$this->markerArray['UKB_FORM'] = '';
				}
				break;

			case 'textareaRTE':
                if (!$this->RTEObj) $this->RTEObj = t3lib_div::makeInstance('tx_rtehtmlarea_pi2');
                if ($this->RTEObj->isAvailable()) {
                    $this->RTEcounter++;
                    $this->formName = $this->ticketFormName;
                    $this->PA['itemFormElName'] = $this->prefixId . '[' . $fieldConf['name'] . ']';
                    $this->PA['itemFormElValue'] = $prefillValue;
                    $this->thePidValue = $GLOBALS['TSFE']->id;
                    $this->strEntryField = $fieldConf['name'];
                    // add 150px to the RTE width if configured in typoscript
                    if ($fieldConf['largeRTE']) {
                        $this->docLarge = true;
                    }
                    $RTEItem = $this->RTEObj->drawRTE(
                        $this,
                        '',
                        $this->strEntryField,
                        $row=array(),
                        $this->PA,
                        $this->specConf,
                        $this->thisConfig,
                        $this->RTEtypeVal,
                        '',
                        $this->thePidValue
                    );
                    $this->markerArray['ADDITIONALJS_PRE'] =
                        $this->additionalJS_initial
                        . '<script type="text/javascript">'
                        . implode(chr(10), $this->additionalJS_pre)
                        . '</script>';
                    $this->markerArray['ADDITIONALJS_POST'] =
                        '<script type="text/javascript">'
                        . implode(chr(10), $this->additionalJS_post)
                        . '</script>';
                    $this->markerArray['ADDITIONALJS_SUBMIT'] =
                        implode(';', $this->additionalJS_submit);
                    $content = $RTEItem;
                }
 			break;


			case 'textarea':
				$content .= '<textarea name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" cols="' . $fieldConf['cols'] . '" rows="' . $fieldConf['rows'] . '"  maxlength="' . $fieldConf['maxlength'] . '">';
				$content .= $prefillValue;
				$content .= '</textarea>';
			break;

			case 'date':
				$fieldName = $this->prefixId . '[' . $fieldConf['name'] . ']';
				$prefillValue = $this->parsePrefillValue($fieldConf, $prefillValue);

				if ($this->useDate2Cal) {
					// render the datefield using the date2cal extension
					$this->JSCalendar->setInputField($fieldName);
					$fieldContent = $this->JSCalendar->render($prefillValue);
				} else {
					// render the datefield using the datepicker javascript
					$fieldContent .= '<input '
						. $addJS
						. 'type="text" name="' . $fieldName
						. '" id="' . $fieldName
						. '" value="' . $prefillValue
						. '" size="' . $fieldConf['size']
						. '" maxlength="' . $fieldConf['maxlength'] . '"> ';
					$fieldContent .= '<a href="javascript:NewCal(\'' . $fieldName . '\',\'' . $this->conf['datepicker.']['dateformat'] . '\')">';
					$fieldContent .= '<img src="' . $this->extPath . 'res/images/cal.gif" width="16" height="16" border="0"></a>';
				}

				$content .= $fieldContent;
			break;

			case 'files':
					// show the form elements for the new files
				$content .= '<table border="0" cellpadding="0" cellspacing="0">';
				for ($i = 1; $i<=$fieldConf['maxFiles']; $i++) {
					$content .= '<tr id="' . $fieldConf['name'] . '_' . $i . '_row' . '"';
					if ($i>1) {
						$content .= ' style="display:none;"';
					}
					$content .= '><td>';
					$content .= '<input type="file" id="' . $fieldConf['name'] . '_' . $i .'" name="' . $this->prefixId . '_' . $fieldConf['name'] . '_' . $i . '" value="" size="' . $fieldConf['size'] . '" maxlength="' . $fieldConf['maxlength'] . '">';
					$j = $i + 1;
					if ($i < $fieldConf['maxFiles']) {
						$content .= ' <a href="javascript:document.getElementById(\'' . $fieldConf['name'] . '_' . $j . '_row' . '\').style.display = \'inline\'; this.style.visibility = \'hidden\';">' . $this->pi_getLL('more_files') . '</a>';
					}
					$content .= '</td></tr>';
				}
				$content .= '</table>';
			break;

			case 'categorySelect':
				// don't display any category if no categores are selected in the backend
				// and the field "display all categories is not selected"
				if (!$this->ffdata['all_categories'] && !$this->ffdata['categories']) {
					break;
				}

				// get the categories
				$where_clause = 'pid IN (' . $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']) . ')';
				if (!$this->ffdata['all_categories'] && $this->ffdata['categories']) {
					$where_clause .= ' AND uid IN (' . $this->ffdata['categories'] . ')';
				}
				$where_clause .= $lcObj->enableFields($this->categoryTablename);

				// if special sorting is set in TYPOScript: use this instead of default value "sorting"
				$sorting = !empty($this->conf['filter.']['category.']['sortField']) ? $this->conf['filter.']['category.']['sortField'] : 'sorting';
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->categoryTablename, $where_clause, '', $sorting);
				$num_rows = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

					// render the dropdown
					// if there is only one category possible, preselect that using a hidden form field
					// and don't display the dropdown
				if ($num_rows > 1) {
						// start select tag
					$content .= '<select ' . $addJS . 'name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '')  . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';

						// render empty option
						// preselect it, if no prefillValue is given
					if (!$prefillValue) {
						$selected = ' selected';
					} else {
						$selected = '';
					}

					if ($renderEmptyDropdownFields) {
						$content .= '<option value=""' . $selected . '>';
						$content .= $this->conf['emptyDropdownElement'];
						$content .= '</option>';
					}

						// render the options
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						if (t3lib_div::inList($prefillValue, $row['uid'])) {
							$selected = ' selected';
						} else {
							$selected = '';
						}
						$content .= '<option value="' . $row['uid'] . '"' . $selected . '>' . $row['title'] . '</option>';
					}

						// close select tag
					$content .= '</select>';
				} else if ($num_rows == 1) {
					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					$this->hiddenFormFields['category'] = '<input type="hidden" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="'. $row['uid'] .'">';
				}
			break;

			case 'feuserSelect':
					// if there is a single responsible user (or a user list) given in the flexform,
					// preselect that using a hidden form field
				if (!empty($this->ffdata[$fieldConf['flexformFieldForPreselectedUser']]) && !$filterMode) {
					$this->hiddenFormFields[$fieldConf['name']] = '<input type="hidden" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="'. $this->ffdata[$fieldConf['flexformFieldForPreselectedUser']] .'">';
				} else {
					if ($this->ffdata[$fieldConf['flexformFieldForUsergroupToChoseFrom']] || $fieldConf['addCurrentUserToList'] || $filterMode) {

						$where_clause = '';
						$orderBy = $fieldConf['orderBy'] ? $fieldConf['orderBy'] : 'username';
						$groupBy = '';
						$limit = '';

							// special query for "responsible"-filter
						if ($filterMode && $fieldConf['name'] == 'responsible_feuser') {
							$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
									'*',
									$this->tablename,
									'pid IN (' . $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']) . ')'
										. ' AND status NOT LIKE "' . CONST_STATUS_CLOSED . '%"'
										. $lcObj->enableFields($this->tablename),'responsible_feuser'
									);


							if (count($rows)) {
								$first_element = true;
								foreach ($rows as $row) {
									if ($row['responsible_feuser']) {
										if (!$first_element) {
											$where_clause .= ',';
										}
										$where_clause .= $row['responsible_feuser'];
										$first_element = false;
									}
								}
								if (strlen($where_clause)) {
									$where_clause = 'uid IN (' . $where_clause . ')';
								}
							}
						} else {
							if ($this->ffdata[$fieldConf['flexformFieldForUsergroupToChoseFrom']]) {

									// get all feusers from the given group(s)
								foreach (explode(',',$this->ffdata[$fieldConf['flexformFieldForUsergroupToChoseFrom']]) as $group) {
									if (!empty($where_clause)) {
										$where_clause .= ' OR ';
									}
									$where_clause .= $GLOBALS['TYPO3_DB']->listQuery('usergroup', $group, 'fe_users');
								}

							}
						}

							// add the current fe-user if configured so
						if ($fieldConf['prefillWithCurrentUserIfEmpty'] && $GLOBALS['TSFE']->fe_user->user['uid']) {
							if (!empty($where_clause)) {
								$where_clause .= ' OR ';
							}
							$where_clause .= 'fe_users.uid=' . $GLOBALS['TSFE']->fe_user->user['uid'];
						}

							// if there is no $where_clause, we don't render the filter
						if (strlen($where_clause)) {

								// add brackets (maybye there is more than one group or a group and the current user)
							$where_clause = '(' . $where_clause . ')';

								// add enableFields
							$where_clause .= $lcObj->enableFields('fe_users');

							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', $where_clause, $groupBy, $orderBy, $limit);
							$num_rows = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

								// if there is no prefill value, we choose the current user to be pre-selected
							if ($fieldConf['prefillWithCurrentUserIfEmpty'] && !$prefillValue && $GLOBALS['TSFE']->fe_user->user['uid']) {
								$prefillValue = $GLOBALS['TSFE']->fe_user->user['uid'];
							}

								// render the dropdown
							if ($num_rows > 0) {
								$content .= '<select ' . $addJS . 'name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '') . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';

									// render empty option
								if (!$prefillValue) {
									$selected = ' selected';
								} else {
									$selected = '';
								}
								if ($renderEmptyDropdownFields) {
									$content .= '<option value=""' . $selected . '>';
									$content .= $this->conf['emptyDropdownElement'];
									$content .= '</option>';
								}
								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									if (t3lib_div::inList($prefillValue, $row['uid'])) {
										$selected = ' selected';
									} else {
										$selected = '';
									}
									$content .= '<option value="' . $row['uid'] . '"' . $selected . '>';

										// render name
									$content .= $this->renderNameFromFeUserUid($row['uid'], $fieldConf['lastNameFirst']);

									$content .= '</option>';
								}
								$content .= '</select>';
							} else {
									// render an error message if this is a required
									// field but we don't have any values to select
									// from
								if ($fieldConf['required']) {
									$content .= '<p class="error">' . $this->pi_getLL('error_no_values') . $this->pi_getLL('LABEL_' . strtoupper($fieldConf['name'])) . '</p>';
								}
							}
						}
					} else {
							// render an error message if no group is selected
						if ($fieldConf['required']) {
							$content .= '<p class="error">' . $this->pi_getLL('error_no_values') . $this->pi_getLL('LABEL_' . strtoupper($fieldConf['name'])) . '</p>';
						}
					}
				}
			break;

			case 'notificationsSelect':

				// if the notification behavior is preselected in the flexform field, preselect it using a hidden form field
				if ($this->ffdata[$fieldConf['flexformFieldForNotificationsSelect']] != 'frontendselection') {
					$this->hiddenFormFields[$fieldConf['name']] = '<input type="hidden" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="'. $this->ffdata[$fieldConf['flexformFieldForNotificationsSelect']] .'">';
				} else {

					// render a drodown and give the user the opportunity to select the notification behavior
					$content .= '<select ' . $addJS . 'name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '') . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';
					foreach (explode(',',$this->conf[$fieldConf['valueList']]) as $value) {
						if (t3lib_div::inList($prefillValue, $value)) {
							$selected = ' selected';
						} else {
							$selected = '';
						}
						$content .= '<option value="' . $value . '"' . $selected . '>' . $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($value)), $value) . '</option>';
					}
					$content .= '</select>';
				}
			break;

			case 'addressSelect':

				// if there is a single address given in the flexform, preselect that using a hidden form field
				if (!empty($this->ffdata[$fieldConf['flexformFieldForPreselectedAddress']])) {
					$this->hiddenFormFields[$fieldConf['name']] = '<input type="hidden" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="'. $this->ffdata[$fieldConf['flexformFieldForPreselectedAddress']] .'">';
				} else {
					if ($this->ffdata[$fieldConf['flexformFieldForSysfolderToChoseFrom']]) {

						// get all addresses from a given sysfolder
						$where_clause = '';
						foreach (explode(',',$this->ffdata[$fieldConf['flexformFieldForSysfolderToChoseFrom']]) as $pid) {
							if (!empty($where_clause)) {
								$where_clause .= ' OR ';
							}
							$where_clause .= 'pid='.$pid;
						}
						$where_clause .= $lcObj->enableFields('tt_address');
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_address', $where_clause);
						$num_rows = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

						// render the dropdown
						if ($num_rows > 0) {
							$content .= '<select ' . $addJS . 'name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '') . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';
							while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
								if (t3lib_div::inList($prefillValue, $row['uid'])) {
									$selected = ' selected';
								} else {
									$selected = '';
								}
								$content .= '<option value="' . $row['uid'] . '"' . $selected . '>';
								$label = '';
								foreach (explode(',',$fieldConf['ttaddressFieldsToShowInDropdown']) as $fieldName) {
									if (!empty($label)) {
										$label .= ' ';
									}
									// if the fieldName exists, add it to the label
									// if not, add the fieldName itself to the label (this way, you can add characters like "(" ")" or "-" to the option label)
									if (isset($row[$fieldName])) {
										$label .= $row[$fieldName];
									} else {
										$label .= $fieldName;
									}
								}
								$content .= $label . '</option>';
							}
							$content .= '</select>';
						}
					}
				}
			break;	
			
			case 'todo_list':
				if ($this->piVars['showUid'] || $this->piVars['updateUid']) {
						$content = '
							<ul id="kett_todo_list"></ul>
							<div class="kett_float_clean">&nbsp;</div>
							<input type="text" id="kett_todo_new" />
							<div id="addToDo" /></div>';
					} else {
						$content = $this->pi_getLL('SAVETICKETFIRST');
					}
			break;
		
			case 'progress':
				$content = '
					<div id="kett_sv_progress_outer"><div id="kett_sv_progress"></div></div>
					<div id="kett_sv_progress_percent"></div>';
			break;
		
			default:

			break;
		}

			// hook for changing the rendered form field, overwrites the rendered content
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['afterRenderFormField'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_troubletickets']['afterRenderFormField'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$content = $_procObj->afterRenderFormField($content, $fieldConf, $this);
			}
		}

		return $content;
	}/*}}}*/

	/**
 	* Returns a prefill value for the given field.
 	* Uses the parent ticket as source, but modifies the data where it makes
 	* sense.
 	*
 	* @param   array $fieldConf Field configuration (set in typoscript).
 	* @return  mixed
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Wed May 19 2010 16:51:30 GMT+0200
 	*/
	public function getPrefillValueFromParentTicket($fieldConf) {
		$value = '';
		switch ($fieldConf['name']) {
			case 'title':
				$value = $this->pi_getLL('followuptitleprefix') . $this->parentTicket['title'];
			break;
			case 'category':
			case 'billing':
			case 'priority':
			case 'owner_feuser':
			case 'responsible_feuser':
				$value = $this->parentTicket[$fieldConf['name']];
			break;
			case 'observers_feuser':
				$value = $this->parentTicket[$fieldConf['name']];

					// The current user will be the owner of the ticket (normally,
					// because by default the owner can't be changed in the
					// frontend), so add the former owner as observer of the
					// follow-up ticket (only if he isn't already an observer).
				if (!t3lib_div::inList($value, $this->parentTicket['owner_feuser'])) {
					$value = $this->addToCommaList($value, $this->parentTicket['owner_feuser']);
				}
			break;
			case 'related_tickets':
				$value = $this->parentTicket['uid'];
			break;
		}

		return $value;
	}

	/**
	 * renderNameFromFeUserUid
	 *
	 * returns the name of a fe_user
	 * 1. name (if set)
	 * 2. first_name + last_name (if set)
	 * 3. username (always set ...)
	 *
	 * @param integer $uid
	 * @access public
	 * @return string
	 */
	public function renderNameFromFeUserUid($uid=0, $lastNameFirst = 0) {
		$name = '';

		$feUserData = $this->getFeUserData($uid);

		if ($feUserData['name']) {
			$name = $feUserData['name'];
			if ($this->conf['addUsername']) {
				$name .= ' (' . $feUserData['username'] . ')';
			}
		} else if ($feUserData['last_name']) {
			if ($lastNameFirst) {
				$name .= $feUserData['last_name'];
			} else {
				$name .= $feUserData['first_name'];
			}
			if ($name && $lastNameFirst) {
				$name .= ', ';
			} else if ($name) {
				$name .= ' ';
			}
			if ($lastNameFirst) {
				$name .= $feUserData['first_name'];
			} else {
				$name .= $feUserData['last_name'];
			}
			if ($this->conf['addUsername']) {
				$name .= ' (' . $feUserData['username'] . ')';
			}
		} else {
			$name .= $feUserData['username'];
		}

		return $name;
	}

	/**
	 * renderNamesFromFeUserUids
	 *
	 * renders names from a comma separated list of fe-users
	 *
	 * @param string $uidList
	 * @access public
	 * @return string
	 */
	public function renderNamesFromFeUserUids($uidList) {
		$retval = '';

		foreach (t3lib_div::trimExplode(',', $uidList) as $feuserUid) {
			if ($retval) {
				$retval .= ', ';
			}
			$retval .= $this->renderNameFromFeUserUid($feuserUid);
		}

		return $retval;
	}

	/**
	 * getFilePath
	 *
	 * returns the correct path for a file, parses "EXT:", returns path relative to PATH_site
	 *
	 * @param string $filePath
	 * @access public
	 * @return string
	 */
	public function getFilePath($filePath) {/*{{{*/
		// Parse EXT: in $filePath into real path
		$filePath = t3lib_div::getFileAbsFileName($filePath);
		// Returns relative filename
		if (t3lib_div::isAbsPath($filePath)) {
			$filePath = substr($filePath,strlen(PATH_site));
		}
		return $filePath;
	}/*}}}*/

	/**
	 * renderRelatedTicketListForCurrentTicket
	 *
	 * @param int $ticketUid
	 * @param mixed $renderLinks
	 * @param mixed $renderDeleteButton
	 * @param string $separator
	 * @access public
	 * @return string
	 */
	public function renderRelatedTicketListForCurrentTicket($renderLinks = true, $renderDeleteButton = true, $renderWrapDiv = true, $separator = '<br />') {/*{{{*/
		$lcObj=t3lib_div::makeInstance('tslib_cObj');
		$content = '';

		if (is_array($this->internal['currentRow']) && count($this->internal['currentRow'])) {


				// render ke_ukb relations instead of related tickets
				// if the extension ke_ukb is installed
			if (t3lib_extMgm::isLoaded('ke_ukb')) {

					// make instance, set pids and render the content
					// render only the names of the related elements
					// ($renderFullVersion = 0)
				$storagePids = $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']);
				$wikiSingleView = $this->ffdata['drwikisingleview'];
				$wikiStorage = $this->ffdata['drwikistorage'];
				$renderFullVersion = 0;
				$content = $this->ukb->renderContent(
							'tx_ketroubletickets_tickets',
							$this->internal['currentRow']['uid'],
							$storagePids,
							$this,
							$renderFullVersion);
			} else {
				// relations from this ticket to other tickets
				if ($this->internal['currentRow']['related_tickets']) {
					$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $this->tablename, 'uid IN (' . $this->internal['currentRow']['related_tickets'] . ')' . $lcObj->enableFields($this->tablename));
				}

				// relations from other tickets to this ticket
				$rows2 = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $this->tablename,
					$GLOBALS['TYPO3_DB']->listQuery('related_tickets', $this->internal['currentRow']['uid'], $this->tablename)
					. $lcObj->enableFields($this->tablename)
				);

				// merge the arrays
				if (is_array($rows)) {
					$rows = array_merge($rows, $rows2);
				} else {
					$rows = $rows2;
				}

				// render related tickets
				$ticketListTemp = array();
				$content = '';
				if (is_array($rows) && count($rows)) {
					foreach ($rows as $row) {
						if ($this->checkPermissionForTicket($row['uid']) && !in_array($row['uid'], $ticketListTemp)) {
							$ticketListTemp[] = $row['uid'];
							if ($renderLinks) {
								if ($content) $content .= $separator;
								// render the "delete"-link
								if ($renderDeleteButton) {
									// get the delete image configuration from typoscript
									$imageConf = $this->conf['icons.']['deleteFile.'];
									$imageConf['file'] = $this->getFilePath($imageConf['file']);

									// generate the delete link
									$additionalParams = '&' . $this->prefixId . '[showUid]=' . $this->internal['currentRow']['uid'];
									$additionalParams .= '&' . $this->prefixId . '[deleteRelatedTicket]=' . $row['uid'];
									$additionalParams .= $this->getAdditionalParamsFromKeepPiVars();
									$deleteLinkConf = array(
											'parameter' => $GLOBALS['TSFE']->id,
											'additionalParams' => $additionalParams
											);
									$deleteLink_URL = $this->cObj->typoLink_URL( $deleteLinkConf );
									$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . $deleteLink_URL . '\')">|</a>';
									//$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $deleteLink_URL . '\')">|</a>';

									// generate the alt text
									$imageConf['altText'] = $this->pi_getLL('altText_delete_related_ticket', 'Delete related ticket.');

									// finally generate the delete icon
									$imageConf['file'] = $this->getFilePath($imageConf['file']);
									$content .= $lcObj->IMAGE($imageConf);
									$content .= ' ';
								}

								$linktext = sprintf($this->conf['ticket_uid_formatstring'],$row['uid']);
								$linktext .= ' ' . $row['title'];
								$linkConf = array(
										'parameter' => $GLOBALS['TSFE']->id,
										'additionalParams' => $this->getAdditionalParamsFromKeepPiVars() . '&' . $this->prefixId . '[showUid]=' . $row['uid']
										);
								$content .= $this->cObj->typoLink($linktext, $linkConf);

								// show status of the ticket
								$content .= ' (' . $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($row['status']))) . ')';
							} else {
								if ($content) $content .= $separator;
								$content .= sprintf($this->conf['ticket_uid_formatstring'],$row['uid']) . ' ' . $row['title'] ;
							}
						}
					}
				}
				unset($ticketListTemp);

				if ($content && $renderWrapDiv) {
					$content = '<div class="related_tickets">' . $content . '</div>';
				}
			}
		}

		return $content;
	}/*}}}*/

	/**
	 * Listview of the trouble tickets
	 *
	 * @return	HTML list of table entries
	 */
	public function listView()	{/*{{{*/
			// which template should be used?
		$templateSubpart = $this->listViewConf['templateSubpart'];
		$templateSubpartRow = $this->listViewConf['templateSubpartRow'];
		$content = $this->cObj->getSubpart($this->templateCode, $templateSubpart);

			// Initialize pointer
		if (!isset($this->piVars['pointer'])) {
			$this->piVars['pointer']=0;
		}

			/* --------------------------------
			// Initialize the query parameters.
			/* -------------------------------*/

			// Tablename
		$this->internal['currentTable'] = $this->tablename;

			// set orderBy and descFlag
		list($this->internal['orderBy'], $this->internal['descFlag']) = explode('-', $this->piVars['sort']);

			// Number of results to show in a listing.
        if ($this->getNumericTYPO3versionNumber() >= 6000000) {
            $this->internal['results_at_a_time'] = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($this->listViewConf['results_at_a_time'],0,1000,10);
        } else {
            $this->internal['results_at_a_time'] = t3lib_div::intInRange($this->listViewConf['results_at_a_time'],0,1000,10);
        }
		if ($this->piVars['entries_per_page']) $this->internal['results_at_a_time'] = $this->piVars['entries_per_page'];

			// The maximum number of "pages" in the browse-box: "Page 1", "Page 2", etc.
        if ($this->getNumericTYPO3versionNumber() >= 6000000) {
            $this->internal['maxPages'] = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($this->listViewConf['maxPages'],0,1000,5);
        } else {
            $this->internal['maxPages'] = t3lib_div::intInRange($this->listViewConf['maxPages'],0,1000,5);
        }


			// fields to search in
		$this->internal['searchFieldList'] = 'title,description';

			// fields allowed for the ORDER BY command
			//$this->internal['orderByList']='uid,title,crdate,until_date';
		$this->internal['orderByList'] = $this->conf['listView.']['headerList'];

			// center the page browser
		$this->internal['pagefloat'] = 'CENTER';

			// PERMISSION CHECKS
		$addWhere = $this->getUserAccessibleTicketsWhereClause($GLOBALS['TSFE']->fe_user->user['uid']);

			// add filter
		if (is_array($this->filter)) {
			foreach ($this->filter as $filterName => $filterValue) {
					// HACK for the "open and working" filter
					// TODO: Should be configurable in Typoscript in future versions
				if ($filterName == 'status') {
					switch ($filterValue) {
						case 'open_and_working':
							$addWhere .= ' AND (status="open" OR status="working")';
						break;
						case 'all_not_closed':
							/* Fetch only tickets which are not closed.
							* closed tickets are all ticket types that have the
							* CONST_STATUS_CLOSED (normally "closed") in their key.
							* That rule also applies to locked ticket which have the
							* key "closed_locked".
							* You can invent new "closed"-types like
							* "closed_without_solution" or "closed_another_reason" ...
							*/
							$addWhere .= ' AND status NOT LIKE "' . CONST_STATUS_CLOSED . '%"';
						break;
						case 'all':
						break;
						default:
							$addWhere .= ' AND ' . $filterName . '="' . mysql_real_escape_string($filterValue) . '"';
						break;
					}
					// HACK for the "not fully charged" filter
					// TODO: Should be configurable in Typoscript in future versions
				} else if ($filterName == 'charged') {
					switch ($filterValue) {
						case NOT_FULLY_CHARGED_FILTER:
							$addWhere .= ' AND (charged!="fully_charged")';
						break;
						default:
							$addWhere .= ' AND ' . $filterName . '="' . mysql_real_escape_string($filterValue) . '"';
						break;
					}
				} else if ($filterName == 'closed_in_month') {
					$from = intval($filterValue);
					$month_to = date('m', $from) + 1;
					$year_to = date('Y', $from);
					if ($month_to > 12) {
						$month_to = 1;
						$year_to++;

					}
					$to = mktime(0,0,0, $month_to, 1, $year_to);
					$addWhere .= ' AND close_time >=' . $from . ' AND close_time <= ' . $to;
				} else {
					$addWhere .= ' AND ' . $filterName . '="' . mysql_real_escape_string($filterValue) . '"';
				}
			}
		}

			// filter for categories
		if ($this->ffdata['listcategories']!=''){
			$addWhere .= ' AND category IN (' . $this->ffdata['listcategories'] . ') ';
		}

			// Get number of records:
		$res = $this->pi_exec_query($this->tablename, 1, $addWhere);
		list($this->internal['res_count']) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);

			// we exit here, if the listview has no results (if configured so)
		if ($this->listViewConf['hideIfNoResults'] && !$this->internal['res_count']) {
			return '';
		}

			// Check if submitted sort is allowed, if not, set it to default
		if ($this->piVars['sort'] && $this->piVars['sort'] != DEFAULT_SORT && !t3lib_div::inList(t3lib_div::uniqueList($this->internal['orderByList']),$this->internal['orderBy'])) {
			list($this->internal['orderBy'], $this->internal['descFlag']) = explode(',', DEFAULT_SORT);
		}

			// compile orderBy-parameter
		$orderBy = $this->internal['orderBy'] . ($this->internal['descFlag'] ? ' DESC' : '');

			// add a second sorting
		if ($this->listViewConf['sort2']) {
			$orderBy .= $this->listViewConf['sort2'];
		}

			// Increase limit for the csv export
		if (isset($this->piVars['export']) && $this->piVars['export'] == 'csv') {
			$this->internal['results_at_a_time'] = 1000000;
		}

			// exec the query
		$res = $this->pi_exec_query($this->tablename, '', $addWhere, '', '', $orderBy);

			// Now that we have the query, we can do the csv-export
		if (isset($this->piVars['export']) && $this->piVars['export']=='csv') {
			$this->outputCSV($res);
		}

			// render the sorting links
		$this->renderListSortingLinks();

			// render the filters
		foreach ($this->conf['formFieldList.'] as $fieldConf) {
			if (t3lib_div::inList(t3lib_div::uniqueList($this->listViewConf['filterList']),$fieldConf['name'])) {
				// dont't pre-select user values in the filter if the filter ist empty
				if ($fieldConf['prefillWithCurrentUserIfEmpty']) {
					$fieldConf['prefillWithCurrentUserIfEmpty'] = 0;
				}
				$this->markerArray['FILTER_' . strtoupper(trim($fieldConf['name']))] =
					$this->renderFormField(
						$fieldConf,
						RENDER_EMPTY_DRODOWN_ELEMENT,
						'onchange="this.form.submit();"'
					);
			}
		}

			// render the viewtype selector
		$this->markerArray['VIEWTYPE_SELECTOR'] =
			$this->renderFormField(
				$this->conf['viewtype_selector.'],
				DONT_RENDER_EMPTY_DRODOWN_ELEMENT,
				'onchange="this.form.submit();"'
			);

			// add the filter form markers
		$this->markerArray['FILTERFORM_NAME'] = $this->ticketFormName . '_filter';
		$this->markerArray['FILTERFORM_ACTION'] = $this->cObj->typoLink_URL(
				array(
					'parameter' => $GLOBALS['TSFE']->id,
					'additionalParams' => $this->getAdditionalParamsFromKeepPiVars()
				)
			);
		$this->markerArray['FILTER_SUBMIT'] = '<input type="submit" name="' . $this->prefixId . '[filter_submit]' . '" value="'.$this->pi_getLL('LABEL_FILTER_SUBMIT').'">';

			// show filter reset button / status icon
		$this->markerArray['FILTER_STATUSICON'] = $this->getFieldContent('filter_statusicon');

			// make the whole list
		$this->markerArray['LISTCONTENT'] = $this->makelist($res, $templateSubpartRow);

			// create the search box
		$this->markerArray['SEARCHBOX'] = $this->pi_list_searchBox();

			// create the result browser
		$wrapper['disabledLinkWrap'] = '<span class="disable">|</span>';
		$wrapper['inactiveLinkWrap'] = '<span class="inactive">|</span>';
		$wrapper['activeLinkWrap'] = '<span'.$this->pi_classParam('browsebox-SCell').'>|</span>';
		$wrapper['browseLinksWrap'] = '<div class="browseLinks">|</div>';
		$wrapper['browseLinksWrap'] .= '<div class="kett_entries_per_page">';
		$wrapper['browseLinksWrap'] .= '<label>'. $this->pi_getLL('LABEL_ENTRIES_PER_PAGE').'</label>';
		$wrapper['browseLinksWrap'] .= $this->getEntriesPerPageSelection($this->listViewConf);
		$wrapper['browseLinksWrap'] .= '</div><div class="kett_float_clean">&nbsp;</div>';
		$wrapper['showResultsWrap'] = '<p class="resultText">|</p>';
		$wrapper['browseBoxWrap'] = '<div '.$this->pi_classParam('browsebox').'> | </div>';
		$this->markerArray['PAGEBROWSER'] = $this->pi_list_browseresults(1, '', $wrapper);

			// get additional markers (locallang, ...)
		$this->markerArray = $this->getAdditionalMarkers($this->markerArray);

			// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);

			// check every filter if there is content for every filter, otherwise substitute
			// whole filter block subpart with empty content
		foreach ($this->conf['formFieldList.'] as $fieldConf) {
			if (t3lib_div::inList(t3lib_div::uniqueList($this->listViewConf['filterList']),$fieldConf['name'])) {
				if ($this->markerArray['FILTER_' . strtoupper(trim($fieldConf['name']))] == '' ) {
					$content = $this->cObj->substituteSubpart ($content, '###FILTER_BLOCK_' . strtoupper(trim($fieldConf['name'])), '');
				}
			}
			// remove Filters for Internal Fields if User is no internal User
			if($fieldConf['internal'] && !$this->isCurrentUserInternalUser())
				$content = $this->cObj->substituteSubpart ($content, '###FILTER_BLOCK_' . strtoupper(trim($fieldConf['name'])), '');
		}

			// overwrite status message subpart if no status message is set
		if (empty($this->markerArray['STATUS_MESSAGE_TEXT'])) {
			$content = $this->cObj->substituteSubpart ($content, '###STATUS_MESSAGE###', '');
		}

			// Returns the content from the plugin.
		return $content;
	}/*}}}*/

	/**
	 * getUserAccessibleTicketsWhereClause
	 *
	 * PERMISSION CHECKS
	 * compile a where-clause:
	 * 1. show only tickets the user is owner of, responsible user or observer
	 * 2. If the flexform option "show_tickets" is set to "all_for_admins" and
	 * the current user is one of the "ticket_administrators", or if the option
	 * is set to "all_always", allow the current user to see (and edit) all
	 * tickets
	 *
	 * @param int $fe_user_uid
	 * @access public
	 * @return string
	 */
	public function getUserAccessibleTicketsWhereClause($fe_user_uid=0) {/*{{{*/
		if ($this->ffdata['show_tickets'] == CONST_SHOW_ALL_ALWAYS
				||
				($this->ffdata['show_tickets'] == CONST_SHOW_ALL_FOR_ADMINS
				&& $this->ffdata['ticket_administrators']
				&& t3lib_div::inList($this->ffdata['ticket_administrators'], $fe_user_uid))) {

			$addWhere = '';

		} else {

			$addWhere .= ' AND (';
			$addWhere .= '(owner_feuser=' . $fe_user_uid . ')';
			$addWhere .= ' OR (responsible_feuser=' . $fe_user_uid . ')';
			$addWhere .= ' OR ' . $GLOBALS['TYPO3_DB']->listQuery('observers_feuser', $fe_user_uid, $this->tablename);
			$addWhere .= ')';

		}
		return $addWhere;
	}/*}}}*/


	/**
	 * Creates a list from a database query
	 *
	 * @param	ressource	$res: A database result ressource
	 * @param	string	template part
	 * 	 * @return	A HTML list if result items
	 */
	public function makelist($res, $templateSubpartRow='')	{/*{{{*/
		$items=array();

			// Make list table rows
		while($this->internal['currentRow'] = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$items[]=$this->makeListItem($templateSubpartRow);
		}

		$out = implode(chr(10),$items);
		return $out;
	}/*}}}*/

	/**
	 * Implodes a single row from a database to a single line
	 *
	 * @param	string	template part
	 * @return	Imploded column values
	 */
	public function makeListItem($templateSubpartRow='')	{/*{{{*/
		$content = $this->cObj->getSubpart($this->templateCode, $templateSubpartRow);

			// get the content of each field of a single ticket
			// take into account which listview should be rendered
		if (strlen($this->listViewConf['fieldList'])) {
			foreach (explode(',', $this->listViewConf['fieldList']) as $fieldName) {
				$this->markerArray[strtoupper(trim($fieldName))] = $this->getFieldContent(strtolower(trim($fieldName)));
			}
		}

			// render special marker: own task
			// set it to 'is_own_task' if the current task belongs to the current user, otherwise set it to 0
		$this->markerArray['OWN_TASK'] = $this->getFieldContent('own_task');

			// render special marker: is_overdue
			// set it to 1 if the "until_date" of the current Ticket is in the past
		$this->markerArray['IS_OVERDUE'] = $this->getFieldContent('is_overdue');

			// render special marker: has_attachment
			// set it to 1 if the "until_date" of the current Ticket is in the past
		$this->markerArray['IS_OVERDUE'] = $this->getFieldContent('is_overdue');

			// get additional markers (locallang, ...)
		$this->markerArray = $this->getAdditionalMarkers($this->markerArray);

			// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);

		return $content;
	}/*}}}*/

	/**
	 * Returns the content of a given field
	 * this function is mainly used from the listview function
	 * maybe it does not give the exptected results for some fields if used by other functions
	 *
	 * @param	string		$fieldName: name of table field
	 * @param	string		$renderType: may be set to "email" in order to change the rendering for the output in the notification email
	 * @param   array       $fieldConf: configuration of the field as set in typoscript. not necessary for most of the fields, but e.g. for filelist.
	 *
	 * @return	Value of the field
	 */
	public function getFieldContent($fieldName, $renderType='default', $fieldConf=array())	{/*{{{*/
		$lcObj=t3lib_div::makeInstance('tslib_cObj');
		switch($fieldName) {
			/*
			case 'uid':
				// The "1" means that the display of single items is CACHED! Set to zero to disable caching.
				return $this->pi_list_linkSingle($this->internal['currentRow'][$fieldName],$this->internal['currentRow']['uid'],0);
				break;
			 */

			case 'own_task':
				// render special marker: own task
				// set it to "is_own_task" if the current task belongs to the
				// current user, otherwise set it to "is_not_own_task"
				if ($this->internal['currentRow']['responsible_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']) {
					return 'is_own_task';
				} else {
					return 'is_not_own_task';
				}
				break;

			case 'singleview_pagetitle':
				// used for displaying the pagetitle in the teaser view
				$singleViewPageId = $this->getSingleViewPageIdForCurrentTicket();
				if ($singleViewPageId) {
					$res_page = $GLOBALS['TYPO3_DB']->exec_SELECTquery('title', 'pages', 'uid=' . $singleViewPageId . $lcObj->enableFields('pages'));
					$page = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_page);
					$GLOBALS['TYPO3_DB']->sql_free_result($res_page);
					if (strlen($page['title']) > intval($this->conf['listView.']['cropSingleviewPagetitle'])) {
						$page['title'] = substr($page['title'], 0, intval($this->conf['listView.']['cropSingleviewPagetitle']));
					}
					return '[' . $page['title'] . ']';
				}
				break;

			case 'is_overdue':
				// render special marker: is_overdue
				// set it to 'is_overdue' if the "until_date" of the current Ticket is in the past
				if ($this->internal['currentRow']['until_date'] && $this->internal['currentRow']['until_date'] < time()) {
					return 'is_overdue';
				} else {
					return 'is_not_overdue';
				}
				break;

			case 'title':
				$mainPage = $this->getSingleViewPageIdForCurrentTicket();

				// crop title
				$title = $this->internal['currentRow']['title'];
				$length = intval($this->listViewConf['cropTitle']);
				if ( $length && strlen($title) > $length) {
					$title = $this->cObj->crop($title, $length . '| ...|1');
				}

				// don't link the title in the email and csv view
				if ($renderType == CONST_RENDER_TYPE_EMAIL) {
					$retval = $this->cleanUpHtmlOutput($title);
				} else if ($renderType == CONST_RENDER_TYPE_CSV) {
					$retval = $title;
				} else {

					// AK 13.08.2010
					// add backPid to singleview link if activated in TS
					// and singleview pid is different than current pid
					$mergeArray = array();
					if ($this->conf['listView.']['backPidRedirect.']['activated'] && $mainPage != $GLOBALS['TSFE']->id) {
						$mergeArray = array('backPid' => $GLOBALS['TSFE']->id);
					}

					// function pi_list_linkSingle($str,$uid,$cache=FALSE,$mergeArr=array(),$urlOnly=FALSE,$altPageId=0)
					$retval = $this->pi_list_linkSingle($title, $this->internal['currentRow']['uid'], 0, $mergeArray, 0, $mainPage);

				}
				return $retval;
				break;

			case 'status':
			case 'billing':
			case 'charged':
			case 'priority':
				return $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($this->internal['currentRow'][$fieldName])));
				break;

			case 'status_raw_value':
			case 'billing_raw_value':
			case 'charged_raw_value':
			case 'priority_raw_value':
				return strtoupper(trim($this->internal['currentRow'][str_replace('_raw_value', '', $fieldName)]));
				break;

			case 'priority_number':
				return $this->internal['currentRow']['priority'];
				break;

			case 'priority_wrapped':
				$returnValue = $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($this->internal['currentRow']['priority'])));
				// add a wrap to priority strings
				if ($this->conf['priorityWrap.'][trim($this->internal['currentRow']['priority'])]) {
					$returnValue = $lcObj->TEXT(
							array(
								'value' => $returnValue,
								'wrap' => $this->conf['priorityWrap.'][trim($this->internal['currentRow']['priority'])]
								)
							);
				}
				return $returnValue;
				break;

			case 'description':
				if ($renderType == CONST_RENDER_TYPE_CSV) {
					$retval = strip_tags($this->internal['currentRow']['description']);
					$retval = str_replace("\n", '', $retval);
					$retval = str_replace("\r", '', $retval);
				} else if ($renderType == CONST_RENDER_TYPE_EMAIL) {
					$retval = $this->cleanUpHtmlOutput($this->internal['currentRow']['description'], CONST_KEEP_TAGS_YES);
				} else {
					$retval = $this->pi_RTEcssText($this->internal['currentRow']['description']);
				}
				return $retval;
				break;

			case 'description_clean':
				$retval = $this->internal['currentRow']['description'];
				$retval = str_replace("</p>", ' ', $retval);
				$retval = str_replace("<br />", ' ', $retval);
				$retval = strip_tags($retval);
				return $this->cropSentence($retval, $this->conf['listView.']['cropDescription']);
				break;

			case 'responsible_feuser':
			case 'owner_feuser':
			case 'observers_feuser':
				if (empty($this->internal['currentRow'][$fieldName])) {
					return '';
				}

					// get the user data from fe_users
				$retval = $this->renderNamesFromFeUserUids($this->internal['currentRow'][$fieldName]);

				if ($renderType == CONST_RENDER_TYPE_EMAIL) {
					$retval = $this->cleanUpHtmlOutput($retval);
				}

				return $retval;
				break;

			case 'number_of_comments':
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'*',
						$this->commentsTablename,
						'ticket_uid ='
							. $this->internal['currentRow']['uid']
							. $lcObj->enableFields($this->commentsTablename)
						);
				$number_of_comments =  $GLOBALS['TYPO3_DB']->sql_num_rows($res);
				return $number_of_comments;
				break;

			case 'number_of_attachments':
				if (empty($this->internal['currentRow']['files'])) 	return 0;
				else {
					$attachments = explode(',',$this->internal['currentRow']['files']);
					return count($attachments);
				}
				break;

			case 'comments':
				return $this->renderCommentList($this->internal['currentRow']['uid'], $renderType);
				break;

			case 'latest_comment':
				// get latest comment
				$latest_comment = $this->renderCommentList($this->internal['currentRow']['uid'], $renderType, $latest=1);
				// strip tags and trim
				$latest_comment = trim(strip_tags($latest_comment));
				return $latest_comment;
				break;

			case 'related_tickets':
				if ($renderType == CONST_RENDER_TYPE_CSV) {
					// public function renderRelatedTicketListForCurrentTicket($renderLinks = true, $renderDeleteButton = true, $renderWrapDiv = true, $separator = '<br />')
					return $this->renderRelatedTicketListForCurrentTicket(false, false, false, ',');
				} else if ($renderType == CONST_RENDER_TYPE_EMAIL) {
					return $this->renderRelatedTicketListForCurrentTicket(false, false, true, ',');
				} else {
					return $this->renderRelatedTicketListForCurrentTicket(true, false);
				}
				break;

			case 'status_icon':
				$imageConf = $this->conf['statusImage.'][$this->internal['currentRow']['status'] . '.'];
				$imageConf['altText'] = $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($this->internal['currentRow']['status'])), $this->internal['currentRow']['status']);
				$imageConf['file'] = $this->getFilePath($imageConf['file']);
				return $lcObj->IMAGE($imageConf);
				break;

			case 'status_icon_teaser':
				$imageConf = $this->conf['statusImageTeaser.'][$this->internal['currentRow']['status'] . '.'];
				$imageConf['altText'] = $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($this->internal['currentRow']['status'])), $this->internal['currentRow']['status']);
				$imageConf['file'] = $this->getFilePath($imageConf['file']);
				return $lcObj->IMAGE($imageConf);
				break;

			case 'charged_icon':
				if ($this->internal['currentRow']['charged'] == 'not_charged' || !$this->internal['currentRow']['charged']) {
						// no image if "charged" is 0
					$returnValue = '';
				} else {
						// two different icons for "charge":
						// fully charged or partly charged
					if ($this->internal['currentRow']['charged'] == 'fully_charged') {
						$imageConfigName = 'fully_charged';
					} else {
						$imageConfigName = 'partly_charged';
					}
					$imageConf = $this->conf['chargedImage.'][$imageConfigName . '.'];
					$imageConf['altText'] = $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($this->internal['currentRow']['charged'])), $this->internal['currentRow']['charged']);
					$imageConf['titleText'] = $imageConf['altText'];
					$imageConf['file'] = $this->getFilePath($imageConf['file']);
					$returnValue = $lcObj->IMAGE($imageConf);
				}
				return $returnValue;
				break;

			case 'edit_icon':
			case 'delete_icon':
			case 'close_icon':
				// what kind of icon?
				$do = str_replace('_icon', '', $fieldName);

				// show the delete link only to the owner
				if ($do == 'delete'
					&& $this->internal['currentRow']['owner_feuser'] != $GLOBALS['TSFE']->fe_user->user['uid']) {
					return '';
				}

				// don't show the close icon, if the ticket is already closed
				if ($do == 'close'
					&& stristr($this->internal['currentRow']['status'], CONST_STATUS_CLOSED) ) {
					return '';
				}

				// show the delete link only to the owner and the responsible
				// user and only if the ticket isn't already closed
				if (($do == 'close'
					&& !($this->internal['currentRow']['owner_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']
					|| $this->internal['currentRow']['responsible_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']) )
					|| stristr($this->internal['currentRow']['responsible_feuser'], CONST_STATUS_CLOSED)) {
					return '';
				}

				// get the image configuration from typoscript
				$imageConf = $this->conf['icons.'][$do . '.'];

				// generate the delete link
				if ($do == 'delete') {
					$additionalParams = '&' . $this->prefixId . '[deleteUid]=' . $this->internal['currentRow']['uid'];
					$additionalParams .= $this->getAdditionalParamsFromKeepPiVars();
					$deleteLinkConf = array(
						'parameter' => $GLOBALS['TSFE']->id,
						'additionalParams' => $additionalParams
					);
					$deleteLink_URL = $this->cObj->typoLink_URL( $deleteLinkConf );
					$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . $deleteLink_URL . '\')">|</a>';
					//$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $deleteLink_URL . '\')">|</a>';
				}

				// basic configuration for the single view link
				if ($do == 'edit') {
					$additionalParams = '&' . $this->prefixId . '[showUid]=' . $this->internal['currentRow']['uid'];
				}

				// basic configuration for the close ticket link
				if ($do == 'close') {
					$additionalParams = '&' . $this->prefixId . '[closeUid]=' . $this->internal['currentRow']['uid'];
				}

				// generate the single view and the close ticket link
				if ($do == 'close' || $do == 'edit') {
					$imageConf['stdWrap.']['typolink.']['parameter'] = $GLOBALS['TSFE']->id;
					$imageConf['stdWrap.']['typolink.']['additionalParams'] = $this->getAdditionalParamsFromKeepPiVars() . $additionalParams;
				}

				// generate the alt text
				$imageConf['altText'] = $this->pi_getLL('altText_' . $do, $do);

				$imageConf['file'] = $this->getFilePath($imageConf['file']);
				return $lcObj->IMAGE($imageConf);
				break;

			case 'from_date':
			case 'until_date':
			case 'crdate':
			case 'close_time':
				if (empty($this->internal['currentRow'][$fieldName])) {
					return $this->conf['emptyDate'];
				}
				return date($this->conf['datefield_dateformat'], $this->internal['currentRow'][$fieldName]);
				break;

			case 'close_time_with_text':
				return '<span class="close_time">' . $this->pi_getLL('LABEL_CLOSE_TIME','close time') . ': ' . $this->getFieldContent('close_time', $renderType) . '</span>';
				break;

			case 'files':
				return str_replace(',', ', ', $this->internal['currentRow'][$fieldName]);
				break;

			case 'filelist':
					// show the files, which already have been uploaded
					// including a delete link
				$retval = '';
				$filelist = $this->internal['currentRow']['files'];
				if (strlen($filelist)) {
					foreach (explode(',', $filelist) as $filename) {
						if (file_exists($this->fileUploadDir . $filename)) {

								// get the subpart
							$fileLinkSubpart = $this->cObj->getSubpart($this->templateCode,'###FILELINK###');
							$fileLinkMarkers = array();

								// show the delete link only to the owner
							if ($this->internal['currentRow']['owner_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']) {
								// get the delete image configuration from typoscript
								$imageConf = $this->conf['icons.']['deleteFile.'];

									// generate the delete link
								$additionalParams = '&' . $this->prefixId . '[showUid]=' . $this->internal['currentRow']['uid'];
								$additionalParams .= '&' . $this->prefixId . '[deleteFile]=' . $filename;
								$additionalParams .= $this->getAdditionalParamsFromKeepPiVars();
								$deleteLinkConf = array(
										'parameter' => $GLOBALS['TSFE']->id,
										'additionalParams' => $additionalParams
										);
								$deleteLink_URL = $this->cObj->typoLink_URL($deleteLinkConf);
								$imageConf['wrap'] = '<span class="deleteFile"><a href="javascript:areYouSure(\'' . $deleteLink_URL . '\')">|</a></span>';
								//$imageConf['wrap'] = '<span class="deleteFile"><a href="javascript:areYouSure(\'' . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $deleteLink_URL . '\')">|</a></span>';

									// generate the alt text
								$imageConf['altText'] = $this->pi_getLL('altText_deletefile', 'Delete file.');

									// finally generate the delete icon
								$imageConf['file'] = $this->getFilePath($imageConf['file']);
								$fileLinkMarkers['DELETELINK'] = $lcObj->IMAGE($imageConf);
							} else {
								$fileLinkMarkers['DELETELINK'] = '';
							}

								// show thumbnails
							$filetype = substr(strrchr($filename, '.'), 1);
							$filetype = strtolower($filetype);
							unset($imageConf);
							if (t3lib_div::inList($fieldConf['thumbnails'], $filetype)) {
								if (file_exists($this->fileUploadDir . $filename)) {
									$imageConf = $this->conf['thumbnailImage.']['preview.'];
									$imageConf['file'] = $this->fileUploadDir . $filename;
									$fileLinkMarkers['THUMBNAIL'] = $lcObj->IMAGE($imageConf);
								}
							} else {
								if ($fieldConf['showThumbsForNonImages']) {
									if (is_array($this->conf['thumbnailImage.'][$filetype . '.'])) {
										$imageConf = $this->conf['thumbnailImage.'][$filetype . '.'];
									} else {
										if (is_array($this->conf['thumbnailImage.']['default.'])) {
											$imageConf = $this->conf['thumbnailImage.']['default.'];
										}
									}
									if (is_array($imageConf)) {
										$fileLinkMarkers['THUMBNAIL'] = $lcObj->IMAGE($imageConf);
									}
								}
							}

								// generate the link to the file
							$fileLinkMarkers['LINKTOFILE'] = $lcObj->typoLink(
								$filename,
								array('parameter' => $this->fileUploadDir . $filename . ' _blank')
							);

								// render the file size
							$fileLinkMarkers['FILESIZE'] = $this->lib->filesize_format(filesize($this->fileUploadDir . $filename));

								// fill in the markers
							$retval .= $this->cObj->substituteMarkerArray($fileLinkSubpart,$fileLinkMarkers,'###|###',true);
						}
					}
				}
				return $retval;
				break;

			case 'category':
				$retval = $this->lib->getNameListFromUidList($this->internal['currentRow'][$fieldName], $this->categoryTablename, 'title');
				return $retval;
				break;

			case 'time_planned':
			case 'time_used':
				if (strlen($this->internal['currentRow'][$fieldName])) {
					return $this->lib->m2h($this->internal['currentRow'][$fieldName]);
				} else {
					return '';
				}
				break;

			case 'effort':
				$retval = $this->cleanUpHtmlOutput($this->internal['currentRow'][$fieldName]);
				$retval = $retval ? $retval : '&nbsp;';
				return $retval;
				break;

			case 'filter_statusicon':
				// no filter is set and status filter is set to default "all_not_closed"
				if (count($this->filter)==1 && $this->filter['status'] == 'all_not_closed') {
					unset($imageConf);
					$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/images/reset_gray.gif';
					$imageConf['altText'] = $this->pi_getLL('LABEL_FILTER_NOT_SET');
					$imageConf['titleText'] = $imageConf['altText'];
					return $this->cObj->IMAGE($imageConf);
				}
				// filter is set
				else {
					unset($imageConf);
					$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/images/reset.gif';
					$imageConf['altText'] = $this->pi_getLL('LABEL_FILTER_RESET');
					$imageConf['titleText'] = $imageConf['altText'];
					$resetImage = $this->cObj->IMAGE($imageConf);
					//build link with reset filters
					unset($linkconf);
					$linkconf['parameter'] = $GLOBALS['TSFE']->id;
					$linkconf['additionalParams'] = '&'.$this->prefixId.'[pointer]=0';
					// reset every single filter
					$filterList = t3lib_div::trimExplode(',',$this->conf['listView.']['filterList'],1);
					foreach($filterList as $filter) {
						$linkconf['additionalParams'] .= '&'.$this->prefixId.'['.$filter.']=';
					}
					$linkconf['additionalParams'] .= '&'.$this->prefixId.'[filter]=1';
					$resetLink = $this->cObj->typoLink($resetImage,$linkconf);
					return $resetLink;
				}
				break;

			case 'additional_info':
				$retval = nl2br($this->cleanUpHtmlOutput($this->internal['currentRow'][$fieldName]));
				return $retval;
				break;
			
			case 'progress':
				return $this->internal['currentRow']['progress'];
				break;
			
			case 'todo_list':
				$toDoEntries = $this->lib->getToDoEntriesForTicket($this->internal['currentRow']['uid']);
				$toDoContent = '';
				foreach ($toDoEntries as $key => $toDo) {
					if ($renderType == CONST_RENDER_TYPE_EMAIL) {
						$toDoContent .= '<span style="font-family:\'Lucida Console\', Monaco, monospace ">';
					} else {
						$toDoContent .= '<span class="todo_status">';
					}
					$toDoContent .= $toDo['done'] == 1 ? '[X] ' : '[ ] ';
					$toDoContent .= '</span>'.$toDo['title'].'<br />';
				}
				return $toDoContent;
				break;

			default:
				$retval = $this->cleanUpHtmlOutput($this->internal['currentRow'][$fieldName]);
				return $retval;
				break;
		}
	}/*}}}*/

	/**
	 * getSingleViewForCurrentTicket
	 *
	 * returns the single view page uid for the current ticket
	 *
	 * @access public
	 * @return integer
	 */
	public function getSingleViewPageIdForCurrentTicket() {/*{{{*/
		$lcObj=t3lib_div::makeInstance('tslib_cObj');

		// where is the singleview?
		// may be set in the flexform of the plugin (first priority)
		// or in the category data (second priority)

		// Get the category data. Normally, every ticket should have a category. But maybe, there isn't a category ...
		if ($this->internal['currentRow']['category']) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('singleviewpage',$this->categoryTablename,'uid=' . $this->internal['currentRow']['category'] . $lcObj->enableFields($this->categoryTablename));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
				$categorydata = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			}
		}

		// find out the singleview page id
		if (is_array($categorydata) && !empty($categorydata['singleviewpage'])) {
			$singleviewpage = $categorydata['singleviewpage'];
		} else if (!empty($this->ffdata['page_of_main_plugin'])) {
			$singleviewpage = $this->ffdata['page_of_main_plugin'];
		} else {
			$singleviewpage = $GLOBALS['TSFE']->id;
		}

		return $singleviewpage;
	}/*}}}*/

	/**
	 * Returns the label for a fieldname from local language array
	 *
	 * @param	[type]		$fN: ...
	 * @return	[type]		...
	 */
	public function getFieldHeader($fN)	{/*{{{*/
		switch($fN) {
			case "title":
				return $this->pi_getLL('listFieldHeader_title','<em>title</em>');
			break;
			default:
				return $this->pi_getLL('listFieldHeader_'.$fN,'['.$fN.']');
			break;
		}
	}/*}}}*/

	/**
	 * Returns a sorting link for a column header
	 *
	 * @param	string		$fN: Fieldname
	 * @return	The fieldlabel wrapped in link that contains sorting vars
	 */
	public function getFieldHeader_sortLink($fN)	{/*{{{*/
		return $this->pi_linkTP_keepPIvars(
			$this->getFieldHeader($fN),
			array('sort'=>trim($fN) . '-' . ($this->internal['descFlag'] ? 0 : 1))
		);
	}/*}}}*/

	/**
	 * Returns a Search box, sending search words to piVars "sword"
	 *
	 * @param	string		Attributes for the table tag which is wrapped around the table cells containing the search box
	 * @return	string		Output HTML, wrapped in <div>-tags with a class attribute
	 */
	function pi_list_searchBox($tableParams='')	{/*{{{*/
		// if no searchword is set, use the default value from locallang
		$searchword = $this->piVars['sword'] ? htmlspecialchars($this->piVars['sword']) : $this->pi_getLL('pi_list_searchBox_default_value', 'searchword');

		$sTables = '<div'.$this->pi_classParam('searchbox').'>'
			. '<form action="'. $this->cObj->typoLink_URL(array('parameter' => $GLOBALS['TSFE']->id)) .'" method="post" name="ke_troubletickets_searchbox">'
				. '<input type="text" name="'.$this->prefixId.'[sword]" value="' . $searchword . '"'.$this->pi_classParam('searchbox-sword').' onFocus="document.ke_troubletickets_searchbox.elements[0].value=\'\'" />'
				. '<input type="submit" value="'.$this->pi_getLL('pi_list_searchBox_search','Search',TRUE).'"'.$this->pi_classParam('searchbox-button').' />'
				. '<input type="hidden" name="'.$this->prefixId.'[pointer]" value="" />'
				. '<input type="hidden" name="'.$this->prefixId.'[sort]" value="'.$this->piVars['sort'].'" />'
				. '<input type="hidden" name="'.$this->prefixId.'[filter]" value="'.$this->piVars['filter'].'" />'
			. '</form></div>';

		return $sTables;
	}/*}}}*/

	/**
	 * Returns a mode selector; a little menu in a table normally put in the top of the page/list.
	 *
	 * @param	array		Key/Value pairs for the menu; keys are the piVars[mode] values and the "values" are the labels for them.
	 * @param	string		Attributes for the table tag which is wrapped around the table cells containing the menu
	 * @return	string		Output HTML, wrapped in <div>-tags with a class attribute
	 */
	function pi_list_modeSelector($items=array(),$tableParams='')	{/*{{{*/
		$cells=array();
		reset($items);
		while(list($k,$v)=each($items))	{
			$cells[]='
					<span '.($this->piVars['mode']==$k?$this->pi_classParam('modeSelector-SCell'):'').'>'.
				$this->pi_linkTP_keepPIvars(htmlspecialchars($v),array('mode'=>$k),$this->pi_isOnlyFields($this->pi_isOnlyFields)) .
				'</span>';
		}

		$sTables = '<div'.$this->pi_classParam('modeSelector').'>'
		.implode('',$cells).'</div>';

		return $sTables;
	}/*}}}*/

	/**
	 * Returns a results browser. This means a bar of page numbers plus a "previous" and "next" link. For each entry in the bar the piVars "pointer" will be pointing to the "result page" to show.
	 * Using $this->piVars['pointer'] as pointer to the page to display. Can be overwritten with another string ($pointerName) to make it possible to have more than one pagebrowser on a page)
	 * Using $this->internal['res_count'], $this->internal['results_at_a_time'] and $this->internal['maxPages'] for count number, how many results to show and the max number of pages to include in the browse bar.
	 * Using $this->internal['dontLinkActivePage'] as switch if the active (current) page should be displayed as pure text or as a link to itself
	 * Using $this->internal['showFirstLast'] as switch if the two links named "<< First" and "LAST >>" will be shown and point to the first or last page.
	 * Using $this->internal['pagefloat']: this defines were the current page is shown in the list of pages in the Pagebrowser. If this var is an integer it will be interpreted as position in the list of pages. If its value is the keyword "center" the current page will be shown in the middle of the pagelist.
	 * Using $this->internal['showRange']: this var switches the display of the pagelinks from pagenumbers to ranges f.e.: 1-5 6-10 11-15... instead of 1 2 3...
	 * Using $this->pi_isOnlyFields: this holds a comma-separated list of fieldnames which - if they are among the GETvars - will not disable caching for the page with pagebrowser.
	 *
	 * The third parameter is an array with several wraps for the parts of the pagebrowser. The following elements will be recognized:
	 * disabledLinkWrap, inactiveLinkWrap, activeLinkWrap, browseLinksWrap, showResultsWrap, showResultsNumbersWrap, browseBoxWrap.
	 *
	 * If $wrapArr['showResultsNumbersWrap'] is set, the formatting string is expected to hold template markers (###FROM###, ###TO###, ###OUT_OF###, ###FROM_TO###, ###CURRENT_PAGE###, ###TOTAL_PAGES###)
	 * otherwise the formatting string is expected to hold sprintf-markers (%s) for from, to, outof (in that sequence)
	 *
	 * @param	integer		determines how the results of the pagerowser will be shown. See description below
	 * @param	string		Attributes for the table tag which is wrapped around the table cells containing the browse links
	 * @param	array		Array with elements to overwrite the default $wrapper-array.
	 * @param	string		varname for the pointer.
	 * @param	boolean		enable htmlspecialchars() for the pi_getLL function (set this to false if you want f.e use images instead of text for links like 'previous' and 'next').
	 * @return	string		Output HTML-Table, wrapped in <div>-tags with a class attribute (if $wrapArr is not passed,
	 */
	function pi_list_browseresults($showResultCount=1, $tableParams='', $wrapArr=array(), $pointerName='pointer', $hscText=TRUE)	{/*{{{*/

		// example $wrapArr-array how it could be traversed from an extension
		/* $wrapArr = array(
			'browseBoxWrap' => '<div class="browseBoxWrap">|</div>',
			'showResultsWrap' => '<div class="showResultsWrap">|</div>',
			'browseLinksWrap' => '<div class="browseLinksWrap">|</div>',
			'showResultsNumbersWrap' => '<span class="showResultsNumbersWrap">|</span>',
			'disabledLinkWrap' => '<span class="disabledLinkWrap">|</span>',
			'inactiveLinkWrap' => '<span class="inactiveLinkWrap">|</span>',
			'activeLinkWrap' => '<span class="activeLinkWrap">|</span>'
		); */

			// Initializing variables:
		$pointer = intval($this->piVars[$pointerName]);
		$count = intval($this->internal['res_count']);
        if ($this->getNumericTYPO3versionNumber() >= 6000000) {
            $results_at_a_time = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($this->internal['results_at_a_time'],1,1000);
        } else {
            $results_at_a_time = t3lib_div::intInRange($this->internal['results_at_a_time'],1,1000);
        }
		$totalPages = ceil($count/$results_at_a_time);
        if ($this->getNumericTYPO3versionNumber() >= 6000000) {
            $maxPages = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($this->internal['maxPages'],1,100);
        } else {
            $maxPages = t3lib_div::intInRange($this->internal['maxPages'],1,100);
        }

		$pi_isOnlyFields = $this->pi_isOnlyFields($this->pi_isOnlyFields);

			// $showResultCount determines how the results of the pagerowser will be shown.
			// If set to 0: only the result-browser will be shown
			//	 		 1: (default) the text "Displaying results..." and the result-browser will be shown.
			//	 		 2: only the text "Displaying results..." will be shown
		$showResultCount = intval($showResultCount);

			// if this is set, two links named "<< First" and "LAST >>" will be shown and point to the very first or last page.
		$showFirstLast = $this->internal['showFirstLast'];

			// if this has a value the "previous" button is always visible (will be forced if "showFirstLast" is set)
		$alwaysPrev = $showFirstLast?1:$this->pi_alwaysPrev;

		if (isset($this->internal['pagefloat'])) {
			if (strtoupper($this->internal['pagefloat']) == 'CENTER') {
				$pagefloat = ceil(($maxPages - 1)/2);
			} else {
				// pagefloat set as integer. 0 = left, value >= $this->internal['maxPages'] = right
                if ($this->getNumericTYPO3versionNumber() >= 6000000) {
                    $pagefloat = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($this->internal['pagefloat'],-1,$maxPages-1);
                } else {
                    $pagefloat = t3lib_div::intInRange($this->internal['pagefloat'],-1,$maxPages-1);
                }
			}
		} else {
			$pagefloat = -1; // pagefloat disabled
		}

			// default values for "traditional" wrapping with a table. Can be overwritten by vars from $wrapArr
		$wrapper['disabledLinkWrap'] = '<td nowrap="nowrap"><p>|</p></td>';
		$wrapper['inactiveLinkWrap'] = '<td nowrap="nowrap"><p>|</p></td>';
		$wrapper['activeLinkWrap'] = '<td'.$this->pi_classParam('browsebox-SCell').' nowrap="nowrap"><p>|</p></td>';
		$wrapper['browseLinksWrap'] = trim('<table '.$tableParams).'><tr>|</tr></table>';
		$wrapper['showResultsWrap'] = '<p>|</p>';
		$wrapper['browseBoxWrap'] = '
		<!--
			List browsing box:
		-->
		<div '.$this->pi_classParam('browsebox').'>
			|
		</div>';

			// now overwrite all entries in $wrapper which are also in $wrapArr
		$wrapper = array_merge($wrapper,$wrapArr);

		if ($showResultCount != 2) { //show pagebrowser
			if ($pagefloat > -1) {
				$lastPage = min($totalPages,max($pointer+1 + $pagefloat,$maxPages));
				$firstPage = max(0,$lastPage-$maxPages);
			} else {
				$firstPage = 0;
                if ($this->getNumericTYPO3versionNumber() >= 6000000) {
                    $lastPage = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($totalPages,1,$maxPages);
                } else {
                    $lastPage = t3lib_div::intInRange($totalPages,1,$maxPages);
                }

			}
			$links=array();

				// Make browse-table/links:
			if ($showFirstLast) { // Link to first page
				if ($pointer>0)	{
					$links[]=$this->cObj->wrap($this->pi_linkTP_keepPIvars($this->pi_getLL('pi_list_browseresults_first','<< First',$hscText),array($pointerName => null),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
				} else {
					$links[]=$this->cObj->wrap($this->pi_getLL('pi_list_browseresults_first','<< First',$hscText),$wrapper['disabledLinkWrap']);
				}
			}
			if ($alwaysPrev>=0)	{ // Link to previous page
				if ($pointer>0)	{
					$links[]=$this->cObj->wrap($this->pi_linkTP_keepPIvars($this->pi_getLL('pi_list_browseresults_prev','< Previous',$hscText),array($pointerName => ($pointer-1?$pointer-1:'')),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
				} elseif ($alwaysPrev)	{
					$links[]=$this->cObj->wrap($this->pi_getLL('pi_list_browseresults_prev','< Previous',$hscText),$wrapper['disabledLinkWrap']);
				}
			}
			for($a=$firstPage;$a<$lastPage;$a++)	{ // Links to pages
				if ($this->internal['showRange']) {
					$pageText = (($a*$results_at_a_time)+1).'-'.min($count,(($a+1)*$results_at_a_time));
				} else {
					$pageText = trim($this->pi_getLL('pi_list_browseresults_page','Page',$hscText).' '.($a+1));
				}
				if ($pointer == $a) { // current page
					if ($this->internal['dontLinkActivePage']) {
						$links[] = $this->cObj->wrap($pageText,$wrapper['activeLinkWrap']);
					} else {
						$links[] = $this->cObj->wrap($this->pi_linkTP_keepPIvars($pageText,array($pointerName  => ($a?$a:'')),$pi_isOnlyFields),$wrapper['activeLinkWrap']);
					}
				} else {
					$links[] = $this->cObj->wrap($this->pi_linkTP_keepPIvars($pageText,array($pointerName => ($a?$a:'')),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
				}
			}
			if ($pointer<$totalPages-1 || $showFirstLast)	{
				if ($pointer==$totalPages-1) { // Link to next page
					$links[]=$this->cObj->wrap($this->pi_getLL('pi_list_browseresults_next','Next >',$hscText),$wrapper['disabledLinkWrap']);
				} else {
					$links[]=$this->cObj->wrap($this->pi_linkTP_keepPIvars($this->pi_getLL('pi_list_browseresults_next','Next >',$hscText),array($pointerName => $pointer+1),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
				}
			}
			if ($showFirstLast) { // Link to last page
				if ($pointer<$totalPages-1) {
					$links[]=$this->cObj->wrap($this->pi_linkTP_keepPIvars($this->pi_getLL('pi_list_browseresults_last','Last >>',$hscText),array($pointerName => $totalPages-1),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
				} else {
					$links[]=$this->cObj->wrap($this->pi_getLL('pi_list_browseresults_last','Last >>',$hscText),$wrapper['disabledLinkWrap']);
				}
			}
			$theLinks = $this->cObj->wrap(implode(chr(10),$links),$wrapper['browseLinksWrap']);
		} else {
			$theLinks = '';
		}

		$pR1 = $pointer*$results_at_a_time+1;
		$pR2 = $pointer*$results_at_a_time+$results_at_a_time;

		if ($showResultCount) {
			if ($wrapper['showResultsNumbersWrap']) {
				// this will render the resultcount in a more flexible way using markers (new in TYPO3 3.8.0).
				// the formatting string is expected to hold template markers (see function header). Example: 'Displaying results ###FROM### to ###TO### out of ###OUT_OF###'

				$markerArray['###FROM###'] = $this->cObj->wrap($this->internal['res_count'] > 0 ? $pR1 : 0,$wrapper['showResultsNumbersWrap']);
				$markerArray['###TO###'] = $this->cObj->wrap(min($this->internal['res_count'],$pR2),$wrapper['showResultsNumbersWrap']);
				$markerArray['###OUT_OF###'] = $this->cObj->wrap($this->internal['res_count'],$wrapper['showResultsNumbersWrap']);
				$markerArray['###FROM_TO###'] = $this->cObj->wrap(($this->internal['res_count'] > 0 ? $pR1 : 0).' '.$this->pi_getLL('pi_list_browseresults_to','to').' '.min($this->internal['res_count'],$pR2),$wrapper['showResultsNumbersWrap']);
				$markerArray['###CURRENT_PAGE###'] = $this->cObj->wrap($pointer+1,$wrapper['showResultsNumbersWrap']);
				$markerArray['###TOTAL_PAGES###'] = $this->cObj->wrap($totalPages,$wrapper['showResultsNumbersWrap']);
				// substitute markers
				$resultCountMsg = $this->cObj->substituteMarkerArray($this->pi_getLL('pi_list_browseresults_displays','Displaying results ###FROM### to ###TO### out of ###OUT_OF###'),$markerArray);
			} else {
				// render the resultcount in the "traditional" way using sprintf
				$resultCountMsg = sprintf(
					str_replace('###SPAN_BEGIN###','<span'.$this->pi_classParam('browsebox-strong').'>',$this->pi_getLL('pi_list_browseresults_displays','Displaying results ###SPAN_BEGIN###%s to %s</span> out of ###SPAN_BEGIN###%s</span>')),
					$count > 0 ? $pR1 : 0,
					min($count,$pR2),
					$count);
			}
			$resultCountMsg = $this->cObj->wrap($resultCountMsg,$wrapper['showResultsWrap']);
		} else {
			$resultCountMsg = '';
		}

		$sTables = $this->cObj->wrap($resultCountMsg.$theLinks,$wrapper['browseBoxWrap']);

		return $sTables;
	}/*}}}*/

	/**
	 * Helper public function for handleUpload
	 * Is the file extension allowed?
	 *
	 * @return boolean
	 */
	function extAllowed($filename) {/*{{{*/
		// set default values
		$this->conf['checkFileExt'] = $this->conf['checkFileExt'] ? $this->conf['checkFileExt'] : 1;
		$this->conf['extInclude'] = $this->conf['extInclude'] ? $this->conf['extInclude'] : 'pdf,doc,rtf,txt,odt,sxw,jpg,jpeg,gif,png,bmp';

		//all extensions allowed?
		if (!($this->conf['checkExt'])) return TRUE;

		$includelist = explode(",",$this->conf['extInclude']);

		//overrides includelist
		$excludelist = explode(",",$this->conf['extExclude']);

		$extension='';
		if($extension=strstr($filename,'.')){
			$extension=strtolower(substr($extension, 1));
			return ((in_array($extension,$includelist) || in_array('*',$includelist)) && (!in_array($extension,$excludelist)));
		} else {
			return false;
		}
	}/*}}}*/

	/**
	 * outputCSV
	 *
	 * Output Contents from db query as csv
	 *
	 * @param mixed $res
	 * @access public
	 * @return void
	 */
	public function outputCSV($res) {/*{{{*/
		//header("Content-type: text/plain; charset=us-ascii");
		//header("Content-Transfer-Encoding: 7bit");

		// Set Excel as default application
		header('Pragma: private');
		header('Cache-control: private, must-revalidate');
		header("Content-Type: application/vnd.ms-excel");

		// Set file name
		header('Content-Disposition: attachment; filename="' . str_replace('###DATE###', date('Y-m-d'), $this->csv_filename) . '"');
		$endLineChar = $this->conf['csvView.']['endLineChar'];
		$endLineChar = str_replace('\r', "\r", $endLineChar);
		$endLineChar = str_replace('\n', "\n", $endLineChar);
		$endLineChar = str_replace('\t', "\t", $endLineChar);

		$wrapChar = $this->conf['csvView.']['wrapChar'];
		$wrapChar = str_replace('\r', "\r", $wrapChar);
		$wrapChar = str_replace('\n', "\n", $wrapChar);
		$wrapChar = str_replace('\t', "\t", $wrapChar);

		$splitChar = $this->conf['csvView.']['splitChar'];
		$splitChar = str_replace('\r', "\r", $splitChar);
		$splitChar = str_replace('\n', "\n", $splitChar);
		$splitChar = str_replace('\t', "\t", $splitChar);
		echo $this->renderTable2CSV($res, $this->tablename, $this->conf['listView.']['csvExportFieldList'],1, $splitChar, $wrapChar, $endLineChar);
		exit();
	}/*}}}*/

	/**
	 * renderTable2CSV
	 *
	 * renders a CSV file from a sql query
	 *
	 * @param mixed $res DB query result
	 * @param mixed $table  DB table name
	 * @param string $fieldList Fields to include in the output
	 * @param int $renderHeader  Render field names into the header?
	 * @param string $splitChar Character to use as divider.
	 * @param string $wrapChar Wrap fields into " for example.
	 * @param string $endLineChar Character(s) to append at the end of each line.
	 * @access public
	 * @return string
	 */
	public function renderTable2CSV($res,$table,$fieldList='',$renderHeader=1,$splitChar=',',$wrapChar='"',$endLineChar="\r\n") {/*{{{*/
		$data='';
		$fields=t3lib_div::trimExplode(',',$fieldList);
		if ($renderHeader) {
			$line='';
			foreach ($fields as $field) {
				if ($line) $line.=$splitChar;
				$line.=$wrapChar.$this->getFieldHeader($field).$wrapChar;
			}
			if ($line) $line.=$endLineChar;
			$data.=$line;
		}
		while($this->internal['currentRow']=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$line='';
			foreach ($fields as $field) {
				if ($line) $line .= $splitChar;
				$line .= $wrapChar . $this->formatCSVContent($this->getFieldContent($field, CONST_RENDER_TYPE_CSV)) . $wrapChar;
			}
			if ($line) $line .= $endLineChar;
			$data .= $line;
		}
		return $data;
	}/*}}}*/

	/**
	 * formatCSVContent
	 *
	 * format content for export into a csv file
	 *
	 * @param string $data
	 * @access public
	 * @return string
	 */
	public function formatCSVContent($data) {/*{{{*/
		$data = trim($data);
		$data = html_entity_decode($data, ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
		$data = strip_tags($data);
		$data = str_replace("\"", '', $data);
		$data = str_replace("\n", '', $data);
		$data = str_replace("\r", '', $data);
		return $data;
	}/*}}}*/

	/**
	 * renderListSortingLinks
	 * renders the sortlinks (historically called "headers" in TYPO3 plugins)
	 *
	 * @access public
	 * @return void
	 */
	public function renderListSortingLinks() {/*{{{*/
		$mainPage = $this->ffdata['page_of_main_plugin'] ? $this->ffdata['page_of_main_plugin'] : $GLOBALS['TSFE']->id;

		foreach (t3lib_div::trimExplode(',', $this->conf['listView.']['headerList']) as $headerName) {

			// add the sort parameter to the link
			// AK 13.08.2010
			// for priority: always show highest first
			if ($headerName == 'priority' && $this->piVars['sort'] != 'priority') {
				$additionalParams = '&' . $this->prefixId . '[sort]=' . trim($headerName) . '-1';
			} else if ($headerName == 'priority' && $this->piVars['sort'] == 'priority'){
				$additionalParams = '&' . $this->prefixId . '[sort]=' . trim($headerName) . '-0';
			} else {
				$additionalParams = '&' . $this->prefixId . '[sort]=' . trim($headerName) . '-' . ($this->internal['descFlag'] ? 0 : 1);
			}

			// Mark this Link, if it is the currently active sorting
			$wrap = $this->internal['descFlag'] ? '<span class="sort_active_desc">|</span>' : '<span class="sort_active_asc">|</span>';
			$wrap = (substr($this->piVars['sort'], 0, strlen($headerName)) == $headerName) ? $wrap : '';

			// make the link
			$this->markerArray['HEADER_' . strtoupper(trim($headerName))] .= $this->cObj->typoLink(
					$this->pi_getLL('LABEL_' . strtoupper(trim($headerName)), $headerName),
					array(
						'parameter' => $mainPage,
						'additionalParams' => $this->getAdditionalParamsFromKeepPiVars() . $additionalParams,
						'wrap' => $wrap
						)
					);
		}
	}/*}}}*/

	/**
	 * cropSentence
	 * You pass the script a string, a length you want the string     #
	 * to be and the trailing characters, what the function does,     #
	 * is takes the string, finds the last word that will fit into    #
	 * the overall length, and return a string that has been cropped. #
	 * The function makes sure that a word is not cut in half.        #
	 *                                                                #
	 *#################################################################
	 *        Written by David Speake - david@evilwarus.com           #
	 *      Adapted from Oliver Southgate's ASP interpretation        #
	 *     http://www.haneng.com/code/VBScript/CropSentence.txt       #
	 *#################################################################
	 *                                                                #
	 * Examples:                                                      #
	 *                                                                #
	 * $strTemp = "Hello, I am a fish and you are not.";              #
	 * $strTemp = cropSentence($strTemp, 16, "...");                  #
	 * //returns "Hello, I am a..."                                   #
	 *                                                                #
	 * $strTemp = "Hello, I am a fish and you are not.";              #
	 * $strTemp = cropSentence($strTemp, 17, "...");                  #
	 * //returns "Hello, I am a fish..."                              #
	 *
	 * @param string $strText
	 * @param integer $intLength
	 * @param string $strTrail
	 * @access public
	 * @return string
	 */
	public function cropSentence ($strText, $intLength = 200, $strTrail = '...') {/*{{{*/
		$wsCount = 0;
		$intTempSize = 0;
		$intTotalLen = 0;
		$intLength = $intLength - strlen($strTrail);
		$strTemp = "";

		if (strlen($strText) > $intLength) {
			$arrTemp = explode(" ", $strText);
			foreach ($arrTemp as $x) {
				if (strlen($strTemp) <= $intLength) $strTemp .= " " . $x;
			}
			$CropSentence = $strTemp . $strTrail;
		} else {
			$CropSentence = $strText;
		}

		return $CropSentence;
	}/*}}}*/



	/**
	 * checks if uid is a valid ticket uid
	 * Author: Andreas Kiefer (kiefer@kennziffer.com)
	 * @param int $uid
	 * @return bool
	 */
	public function isValidTicketUid($uid) {
			// uid cannot be zero or negative
		if ($uid<=0) return false;

			// check if ticket uid can be found in db
			// CB 06.05.2010: check for correct sysfolder
		$where = 'uid=' . intval($uid);
		$where .= ' AND pid IN (' . $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']) . ')';
		$where .= $this->cObj->enableFields($this->tablename);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', $this->tablename, $where);
		$num = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		return $num ? true : false;
	}


	/**
	 * Description:
	 * Author: Andreas Kiefer (kiefer@kennziffer.com)
	 */
	 public function getEntriesPerPageSelection($lConf) {
		$formAction = $this->cObj->typoLink_URL(array('parameter' => $GLOBALS['TSFE']->id));
		$fieldContent .= '<form method="post" action="'.$formAction.'"><select name="'.$this->prefixId.'[entries_per_page]" onchange="this.form.submit();">';
		$entriesPerPageOptions = explode(',',trim($this->conf['listView.']['entries_per_page_options']));
		$entries_per_page = $this->piVars['entries_per_page'] ? $this->piVars['entries_per_page'] : $lConf['results_at_a_time'];
		foreach ($entriesPerPageOptions as $opt) {
			$fieldContent .= '<option value="'.$opt.'" ';
			if ($entries_per_page == $opt) $fieldContent .= ' selected="selected" ';
			$fieldContent .= '>'.$opt.'</option>';
		}
		$fieldContent .= '</select>';
		$fieldContent .= '<input type="hidden" name="'.$this->prefixId.'[filter]" value="'.$this->piVars['filter'].'">';
		$fieldContent .= '<input type="hidden" name="'.$this->prefixId.'[sort]" value="'.$this->piVars['sort'].'">';
		$fieldContent .= '<input type="hidden" name="'.$this->prefixId.'[pointer]" value="0">';
		$fieldContent .= '</form>';
		return $fieldContent;
	}

	/**
 	* Inits date2cal extension for use in the frontend
 	*
 	* @return  void
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Tue Aug 17 2010 10:13:47 GMT+0200
 	*/
	public function initDate2Cal() {
			// include jscalendar api
		include_once(t3lib_extMgm::siteRelPath('date2cal') . '/src/class.jscalendar.php');

			// init jscalendar class
		$this->JSCalendar = JSCalendar::getInstance();

			// set options
			// don't show Natural Langage Parser
		$this->JSCalendar->setNLP(false);

			// Don't show time, use format from typoscript config
		$this->JSCalendar->setDateFormat(false, $this->conf['datefield_inputfieldformat']);

		// get initialisation code of the calendar
		if (($jsCode = $this->JSCalendar->getMainJS()) != '') {
            if ($this->getNumericTYPO3versionNumber() >= 6000000) {
                $GLOBALS['TSFE']->getPageRenderer()->addHeaderData($jsCode);
            } else {
                $GLOBALS['TSFE']->additionalHeaderData['date2cal'] = $jsCode;
            }
		}

		$this->useDate2Cal = true;
	}

	/**
 	* Tracks the time, saves it to an array.
 	*
 	* @param   string $title
 	* @return  void
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Thu Aug 19 2010 13:35:30 GMT+0200
 	*/
	public function trackTime($title='') {
		if ($title == 'start') {
			$this->timetracking[$title] = microtime(true) * 1000;
		} else {
			$this->timetracking[$title] = round((microtime(true) * 1000 - $this->timetracking['start']));
		}
	}

    /**
     * Returns the current TYPO3 version number as an integer, eg. 4005000 for version 4.5
     *
     * @return int
     */
    public function getNumericTYPO3versionNumber() {
        if (class_exists('VersionNumberUtility')) {
            $numeric_typo3_version = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
        } else if (class_exists('t3lib_utility_VersionNumber')) {
            $numeric_typo3_version = t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version);
        } else {
            $numeric_typo3_version = t3lib_div::int_from_ver(TYPO3_version);
        }
        return $numeric_typo3_version;
    }

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php']);
}

?>
