<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Christian Bülter <buelter@kennziffer.com>
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
define(CONST_NEWTICKET, 'NEWTICKET');
define(CONST_NEWCOMMENT, 'NEWCOMMENT');
define(CONST_REOPENANDCOMMENT, 'REOPENANDNEWCOMMENT');
define(CONST_ONEVERYCHANGE, 'oneverychange');
define(CONST_NEVER, 'never');
define(CONST_ONSTATUSCHANGE, 'onstatuschange');
define(CONST_STATUS_OPEN, 'open');
define(CONST_STATUS_CLOSED, 'closed');
define(CONST_RENDER_TYPE_EMAIL, 'email');
define(CONST_RENDER_TYPE_CSV, 'csv');
define(CONST_SHOW_ALL_FOR_ADMINS, 'all_for_admins');
define(CONST_SHOW_ALL_ALWAYS, 'all_always');
define(DEFAULT_ORDERBY, 'crdate');
define(RENDER_EMPTY_DRODOWN_ELEMENT, true);
define(CONST_KEEP_TAGS_YES, 'keeptags');

// RTE
require_once(t3lib_extMgm::extPath('rtehtmlarea').'pi2/class.tx_rtehtmlarea_pi2.php');

// date2cal, modififed to work in the frontend
require_once(t3lib_extMgm::extPath('ke_troubletickets').'pi1/class.frontend_JScalendar.php');

// Basic file func, needed for checking filenames when uploading files
require_once(PATH_t3lib.'class.t3lib_basicfilefunc.php');

// Mail functions
require_once (PATH_t3lib.'class.t3lib_htmlmail.php');

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
    var $specConf = array();
    var $thisConfig = array();
    var $RTEtypeVal = 'text';

	/**
	 * Plugin Main Method
	 *
	 * @param	string		$content: The content of the PlugIn
	 * @param	array		$conf: The PlugIn Configuration
	 * @return	The content that should be displayed on the website
	 */
	public function main($content,$conf)	{/*{{{*/
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

		// path to this extension
		$this->extPath = t3lib_extMgm::siteRelPath($this->extKey);

		// Include default CSS?
		if ($this->conf['includeDefaultCSS']) {
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_css'] = '<link rel="stylesheet" type="text/css" href="' . $this->extPath . $this->defaultCSS . '" />';
		}

		// create instance of the extension library
		$this->lib = t3lib_div::makeInstance('tx_ketroubletickets_lib');

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
		$this->conf=$conf;

		// make the date2cal instance
		if (t3lib_extMgm::isLoaded('date2cal')) {
			$this->date2cal = frontend_JScalendar::getInstance();
		} else {
			return '<p class="error">' . $this->pi_getLL('error_date2cal_not_loaded') . '</p>';
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
		$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId.'_areyousure'] = '<script type="text/javascript">
function areYouSure(ziel) {
	if ( confirm("' . $this->pi_getLL('are_you_sure_delete', 'Are you sure?') . '") ) {
		window.location.href = ziel;
	}
}
</script>';

		// Some debugging ...
		// debug($this->piVars);
		// debug(t3lib_div::_POST());
		// debug($GLOBALS['TSFE']->fe_user->user);
		// debug($GLOBALS['TSFE']->loginUser);

		// Output database errors
		//$GLOBALS['TYPO3_DB']->debugOutput = true;

		// General permission check: This plugin only makes sense if a user is logged in
		if (!$GLOBALS['TSFE']->loginUser) {
				return $this->pi_wrapInBaseClass($this->pi_getLL('error_not_logged_in', 'Please log in.'));
		}

		// single view / update
		// get the database entry for the single view / the entry that will be updated.
		if ( $this->piVars['showUid'] || $this->piVars['updateUid'] )	{
			$uid = $this->piVars['showUid'] ? $this->piVars['showUid'] : $this->piVars['updateUid'];
			$this->internal['currentTable'] = $this->tablename;
			$this->internal['currentRow'] = $this->pi_getRecord($this->tablename, $uid);

			// PERMISSION CHECKS
			// only the owner, the responsible user and the observers may view or update ticket
			if (!$this->checkPermissionForCurrentTicket()) {
				return $this->pi_wrapInBaseClass($this->pi_getLL('error_no_permission', 'Sorry, you don\'t have access to this ticket.'));
			}
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

		// keep existing filter
		if ($this->piVars['filter']) {
			$this->filter = unserialize(base64_decode($this->piVars['filter']));
			//$this->filter = unserialize($this->piVars['filter']);
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
		}

		// save the filter in piVars
		// Use base64 because the serialized value contains quotes
		$this->piVars['filter'] = base64_encode(serialize($this->filter));
		//$this->piVars['filter'] = serialize($this->filter);

		// Render the main content:
		// Single View / New Ticket
		// or List View
		if ($this->ffdata['view']=='NORMAL') {
			if ( ($this->piVars['do'] == 'new') || $this->piVars['showUid'] || ($this->piVars['updateUid'] && count($this->formErrors)) || ($this->piVars['newticket'] && count($this->formErrors)) )	{
				$content .= $this->renderTicketForm();
			} else {
				$this->cleanUpPiVars();
				$content .= $this->listView();
			}
		}
		else if ($this->ffdata['view']=='TEASER_OWN') {
			$content .= $this->teaserView();
		}
		else if ($this->ffdata['view']=='TEASER_DEL') {
			$content .= $this->teaserView();
		}
		#$content .= t3lib_div::view_array($_POST);
		#$content .= t3lib_div::view_array($_FILES);
		return $this->pi_wrapInBaseClass($content);
	}/*}}}*/

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
	 *
	 * Permissions:
	 * 1. show only tickets the current logged in user is owner of, responsible user or observer
	 * 2. If the flexform option "show_tickets" is set to "all_for_admins" and
	 * the current user is one of the "ticket_administrators", or if the option
	 * is set to "all_always", allow the current user to see and edit all
	 * tickets
	 *
	 * returns false if he has no rights
	 * returns 1 if he has full rights (owner or responsible user)
	 * returns 2 if he has limited rights (observer)
	 *
	 * @param int $ticketUid
	 * @access public
	 * @return integer
	 */
	public function checkPermissionForTicket($ticketUid=0) {/*{{{*/
		$permission = false;
		if ($GLOBALS['TSFE']->loginUser) {
			$lcObj = t3lib_div::makeInstance('tslib_cObj');
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->tablename, 'uid=' . $ticketUid . $lcObj->enableFields($this->tablename));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
				$ticketRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($ticketRow['owner_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']) {
					$permission = 1;
				}
				if ($ticketRow['responsible_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']) {
					$permission = 1;
				}
				if (t3lib_div::inList($ticketRow['observers_feuser'], $GLOBALS['TSFE']->fe_user->user['uid'])) {
					$permission = 2;
				}
				if ($this->ffdata['show_tickets'] == CONST_SHOW_ALL_FOR_ADMINS && $this->ffdata['ticket_administrators'] && t3lib_div::inList($this->ffdata['ticket_administrators'], $GLOBALS['TSFE']->fe_user->user['uid'])) {
					$permission = 1;
				}
				if ($this->ffdata['show_tickets'] == CONST_SHOW_ALL_ALWAYS) {
					$permission = 1;
				}
			}
		}
		return $permission;
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

		// handle each of the fields defined in the typoscript setup
		foreach ($this->conf['formFieldList.'] as $fieldConf) {
			// ignore the submit-field
			if ($fieldConf['type'] != 'submit') {

				// required-check
				if ($fieldConf['required'] && empty($this->piVars[$fieldConf['name']])) {
					$this->formErrors[] = '<div class="error">' . $this->pi_getLL('formerror_required_start') . '"' . $this->pi_getLL('LABEL_' . strtoupper(trim($fieldConf['name']))) . '"' . $this->pi_getLL('formerror_required_end') . '</div>';
				}

				// generate the db-insert values
				if (!empty($this->piVars[$fieldConf['name']]) || $fieldConf['type'] == 'files') {

					// combine the "file" type fields --> use the already set value as default value for the next round
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

		// if everything is OK, insert the ticket into the database or update it
		if (!count($this->formErrors)) {

			if (!$this->piVars['updateUid']) {
				$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->tablename, $this->insertFields);
				$new_uid = $GLOBALS['TYPO3_DB']->sql_insert_id();
				$this->addHistoryEntry( array(
							'ticket_uid' => $new_uid,
							'databasefield' => '',
							'value_old' => '',
							'value_new' => $this->pi_getLL('history_new_ticket', 'new')
							));

				// send the notification emails
				$this->checkChangesAndSendNotificationEmails($new_uid, CONST_NEWTICKET);

			} else {

				// go through the form fields and check what has changend
				// add a history entry for every change
				$changedFields = '';

				foreach ($this->conf['formFieldList.'] as $fieldConf) {
					$value_old = $this->internal['currentRow'][$fieldConf['name']];
					$value_new = $this->insertFields[$fieldConf['name']];
					if ( (!empty($value_new) && !empty($value_new)) && ($value_old != $value_new)) {
						$this->addHistoryEntry( array(
									'ticket_uid' => $this->internal['currentRow']['uid'],
									'databasefield' => $fieldConf['name'],
									'value_old' => $value_old,
									'value_new' => $value_new
									));

						// update the "close_time" field, if the ticket is now closed and was not closed before
						if ($fieldConf['name'] == 'status' && $value_new == CONST_STATUS_CLOSED) {
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
					}
				}

				// If a commented has been submitted, process it now.
				// Comments are not normal fields but have an own table, so we cannot process them like the ticket fields.
				// Remember the fields that have changed for the notification mail.
				if (isset($this->piVars['content']) && !empty($this->piVars['content'])) {
					$this->handleSubmittedCommentForm();

					// if the ticket is currently is closed, re-open it.
					if ($this->internal['currentRow']['status'] == CONST_STATUS_CLOSED) {
						// change the status
						$this->insertFields['status'] = CONST_STATUS_OPEN;

						// add the 'status'-field to the list of changed fields
						if (strlen($changedFields)) {
							$changedFields .= ',';
						}
						$changedFields .= CONST_REOPENANDCOMMENT;

						// add a history entry
						$this->addHistoryEntry( array(
									'ticket_uid' => $this->internal['currentRow']['uid'],
									'databasefield' => 'status',
									'value_old' => $this->internal['currentRow']['status'],
									'value_new' => CONST_STATUS_OPEN
									));
					} else {
						if (strlen($changedFields)) {
							$changedFields .= ',';
						}
						$changedFields .= CONST_NEWCOMMENT;
					}

				}
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tablename, 'uid=' . $this->internal['currentRow']['uid'], $this->insertFields);

				// send the notification emails
				$this->checkChangesAndSendNotificationEmails($this->internal['currentRow']['uid'], $changedFields);
			}
		}
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
	public function removeFileFromTicket($filename) {/*{{{*/
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
			//debug($GLOBALS['TYPO3_DB']->UPDATEquery($this->tablename, 'uid=' . $ticketUid, array('related_tickets' => $new_related_ticket_list)));

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
	 * @return void
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
		$commentInsertFields['content'] = $this->lib->getNameListFromUidList($commentInsertFields['feuser_uid'], 'fe_users', 'name,username') . ': ' . $this->sanitizeData($this->piVars['content']);

		// insert the comment
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->commentsTablename, $commentInsertFields);

		// add a history entry
		$this->addHistoryEntry( array(
					'ticket_uid' => $commentInsertFields['ticket_uid'],
					'databasefield' => '',
					'value_old' => '',
					'value_new' => $this->pi_getLL('history_new_comment', 'new comment')
					));
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
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tablename, 'uid=' . $ticket_uid, array('status' => CONST_STATUS_CLOSED, 'close_time' => time()));

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
					$historyInsertFields['value_old'] = date ($this->conf['datefield_dateformat'], $historyInsertFields['value_old']);
					$historyInsertFields['value_new'] = date ($this->conf['datefield_dateformat'], $historyInsertFields['value_new']);
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
	 * TODO: Make it work also standalone, that means, without calling the main-function
	 * first (you will still have to instantiate the class). Needed for the
	 * notifications sent by a cronjob.
	 *
	 * @param integer $ticket_uid
	 * @param string $changedFields
	 * @param int $sendOverdueTickets
	 * @access public
	 * @return void
	 */
	public function checkChangesAndSendNotificationEmails($ticket_uid, $changedFields, $sendOverdueTickets=0) {/*{{{*/

		// send mails only if there have been changes
		if (!empty($changedFields)) {
			$lcObj = t3lib_div::makeInstance('tslib_cObj');

			// a notification will be sent if
			// 1. notification setting is "always"
			// 2. notification setting is "onstatuschange" and the "status" field changed
			// 3. $sendOverdueTickets is set and a ticket until_date is greater than the current date

			// does this ticket exist?
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->tablename, 'uid=' . $ticket_uid . $lcObj->enableFields($this->tablename));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {

				// get the ticket data
				$this->internal['currentTable'] = $this->tablename;
				$this->internal['currentRow'] = $this->pi_getRecord($this->tablename, $ticket_uid);

				// render the mailbody
				$emailbody = $this->renderNotificationMail($changedFields);

				// render the subject
				if ($this->conf['email_notifications.']['subject_prefix']) {
					$subject = $this->conf['email_notifications.']['subject_prefix'] . ' ';
				} else {
					$subject = '';
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

				// send notifications to owner
				// (don't send it if the current user is the owner, because the user normally should know that he just changed the ticket.)
				if ($this->internal['currentRow']['owner_feuser']
						&& ($this->internal['currentRow']['owner_feuser'] != $GLOBALS['TSFE']->fe_user->user['uid'])
						&& ( $this->internal['currentRow']['notifications_owner'] == CONST_ONEVERYCHANGE
							|| ($this->internal['currentRow']['notifications_owner'] == CONST_ONSTATUSCHANGE || stristr($changedFields, CONST_ONSTATUSCHANGE)))) {

					// get the user data of the owner
					$fe_user_data = $this->getFeUserData($this->internal['currentRow']['owner_feuser']);
					if (is_array($fe_user_data)) {
						$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody);
					}
				}

				// send notifications to responsible user
				// (don't send it if the current user is the responsible user, because the user normally should know that he just changed the ticket.)
				// (don't send it if the owner is the the same as the responsible user, because than he would receive two mails)
				if ($this->internal['currentRow']['responsible_feuser']
						&& ($this->internal['currentRow']['responsible_feuser'] != $GLOBALS['TSFE']->fe_user->user['uid'])
						&& ($this->internal['currentRow']['owner_feuser'] != $this->internal['currentRow']['responsible_feuser'])
						&& ( $this->internal['currentRow']['notifications_responsible'] == CONST_ONEVERYCHANGE
							|| ($this->internal['currentRow']['notifications_responsible'] == CONST_ONSTATUSCHANGE || stristr($changedFields, CONST_ONSTATUSCHANGE)))) {

					// get the user data of the responsible user
					$fe_user_data = $this->getFeUserData($this->internal['currentRow']['responsible_feuser']);
					if (is_array($fe_user_data)) {
						$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody);
					}
				}

				// send notifications to observers
				if (strlen($this->internal['currentRow']['observers_feuser'])) {
					foreach (explode(',', $this->internal['currentRow']['observers_feuser']) as $observer_uid) {
						if ( $this->internal['currentRow']['notifications_observer'] == CONST_ONEVERYCHANGE
								|| ($this->internal['currentRow']['notifications_observer'] == CONST_ONSTATUSCHANGE || stristr($changedFields, CONST_ONSTATUSCHANGE))) {

							// get the user data of the observer
							$fe_user_data = $this->getFeUserData($observer_uid);
							if (is_array($fe_user_data)) {
								$this->sendNotificationEmail($fe_user_data['email'], $subject, $emailbody);
							}
						}
					}
				}
			}
		}
	}/*}}}*/

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
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'uid=' . $fe_user_uid . $lcObj->enableFields('fe_users'));
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
			return $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		} else {
			return false;
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
	function renderNotificationMail($changedFields='') {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');
		$content = $this->cObj->getSubpart($this->templateCode,'###EMAIL_NOTIFICATION###');
		$localMarkerArray = array();

		// get the markers
		foreach (explode(',', $this->conf['email_notifications.']['fieldList']) as $fieldName) {
			if (strtolower(trim($fieldName)) == 'comments') {
				$markerContent = '<strong>' . $this->pi_getLL('LABEL_COMMENT_HEADER') . '</strong><br />';
				$markerContent .= $this->renderCommentList($this->internal['currentRow']['uid']);
			} else {
				$markerContent = $this->getFieldContent(strtolower(trim($fieldName)), CONST_RENDER_TYPE_EMAIL);
			}
			$localMarkerArray['EMAIL_FIELD_' . strtoupper(trim($fieldName))] = $markerContent;
		}

		// find out what type of change
		if (stristr($changedFields, CONST_NEWTICKET)) {
			$type = 'new';
		} else if ($this->internal['currentRow']['status'] == CONST_STATUS_CLOSED) {
			$type = 'closed';
		} else {
			$type = 'changed';
		}

		// what has happened?
		if ($GLOBALS['TSFE']->loginUser) {
			 $localMarkerArray['WHAT_HAS_HAPPENED'] = $this->pi_getLL('email_text_user', 'user:');
			 $localMarkerArray['WHAT_HAS_HAPPENED'] .= ' ' . $GLOBALS['TSFE']->fe_user->user['username'] . ' ';
			 if ($GLOBALS['TSFE']->fe_user->user['name']) {
				 $localMarkerArray['WHAT_HAS_HAPPENED'] .= '('. $GLOBALS['TSFE']->fe_user->user['name'] . ') ';
			 }
			 $localMarkerArray['WHAT_HAS_HAPPENED'] .= $this->pi_getLL('email_text_type_' . $type . '_with_user', $type);
		}  else {
			 $localMarkerArray['WHAT_HAS_HAPPENED'] = $this->pi_getLL('email_text_type_' . $type, $type);
		}

		$localMarkerArray['WHAT_HAS_HAPPENED'] = $this->cleanUpHtmlOutput($localMarkerArray['WHAT_HAS_HAPPENED']);

		// which fields have changed?
		if (!stristr($changedFields, CONST_NEWTICKET)) {
			$localMarkerArray['WHAT_HAS_HAPPENED'] .= '<br />';
			$localMarkerArray['WHAT_HAS_HAPPENED'] .= $this->cleanUpHtmlOutput($this->pi_getLL('email_text_fields_have_changed'));
			$localMarkerArray['WHAT_HAS_HAPPENED'] .= '<br />';
			foreach (explode(',', $changedFields) as $fieldName) {
				$localMarkerArray['WHAT_HAS_HAPPENED'] .= $this->cleanUpHtmlOutput($this->pi_getLL('LABEL_' . strtoupper(trim($fieldName)), $fieldName));
				$localMarkerArray['WHAT_HAS_HAPPENED'] .= '<br />';
			}
		}

		// generate a link to the ticket

		// get the category data
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('singleviewpage',$this->categoryTablename,'uid=' . $this->internal['currentRow']['category'] . $lcObj->enableFields($this->categoryTablename));
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$categoryData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}

		// find out the singleview pid
		// the singleviewpage must be set in the category
		if (is_array($categoryData) && !empty($categoryData['singleviewpage'])) {
			$singleViewPage = $categoryData['singleviewpage'];
			$linkToTicketSubpart = $this->cObj->getSubpart($this->templateCode,'###EMAIL_NOTIFICATION_LINKTOTICKET###');
			$linkToTicketURL = $this->pi_getPageLink($categoryData['singleviewpage'],'_blank', array(
					'tx_ketroubletickets_pi1[showUid]' => $this->internal['currentRow']['uid'],
					'tx_ketroubletickets_pi1[mode]' => 'not_closed'
				)
			);
			if (!empty($linkToTicketURL)) {
				$localSubpartMarkerArray = array(
					'URL_GO_TO_TICKET' => $linkToTicketURL,
					'LINKTEXT_GO_TO_TICKET' => $this->pi_getLL('LABEL_GO_TO_TICKET')
				);
				$linkToTicketSubpart = $this->cObj->substituteMarkerArray($linkToTicketSubpart,$localSubpartMarkerArray,'###|###',true);
			} else {
				$linkToTicketSubpart = '';
			}
		} else {
			$linkToTicketSubpart = '';
		}
		$localMarkerArray['LINK_TO_SINGLEVIEW_FROM_EMAIL'] = $linkToTicketSubpart;

		// get some more markers
		$localMarkerArray = $this->getAdditionalMarkers($localMarkerArray, CONST_RENDER_TYPE_EMAIL);

		// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$localMarkerArray,'###|###',true);

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

		// Only ASCII is allowed in the header
		$subject = html_entity_decode(t3lib_div::deHSCentities($subject), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
		$subject = t3lib_div::encodeHeader($subject, 'base64');

		// create the plain message body
		$message = html_entity_decode(strip_tags($html_body), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);

		// inspired by code from tt_products, thanks
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
			if ($message)	{
				$Typo3_htmlmail->addPlain($message);
			}
		} else {
			$Typo3_htmlmail->addPlain($message);
		}
		$Typo3_htmlmail->setHeaders();
		$Typo3_htmlmail->setContent();
		$Typo3_htmlmail->setRecipient(explode(',', $toEMail));
		$Typo3_htmlmail->sendTheMail();
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

		// the form fields
		$localMarkerArray['FIELD_CONTENT'] =  '<textarea name="' . $this->prefixId . '[content]" cols="' . $this->conf['comment_cols'] . '" rows="' . $this->conf['comment_rows'] . '"></textarea>';
		$localMarkerArray['FIELD_SUBMIT'] = '<input type="submit" name="' . $this->prefixId . '[comment_submit]' . '" value="'.$this->pi_getLL('LABEL_COMMENT_SUBMIT').'">';

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
	 * @param mixed $ticket_uid
	 * @access public
	 * @return string
	 */
	public function renderCommentList($ticket_uid, $renderType='') {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');
		$content = '';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->commentsTablename, 'ticket_uid=' . $ticket_uid . $lcObj->enableFields($this->commentsTablename));
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
	 * Parses and cleans up the submitted form values in order
	 * to insert them into the database
	 *
	 * @param mixed $fieldConf
	 * @access public
	 * @return void
	 */
	public function generateDBInsertValue($fieldConf, $returnValue = '') {/*{{{*/
		$lcObj = t3lib_div::makeInstance('tslib_cObj');

		switch ($fieldConf['type']) {

			case 'textareaRTE':
				$returnValue .= $this->piVars[$fieldConf['name']];
			break;

			case 'input':
			case 'textarea':
				$returnValue .= $this->sanitizeData($this->piVars[$fieldConf['name']]);
			break;

			case 'inputHoursToMinutes':
				// convert the hours to minutes
				$hours = floatval(str_replace(',','.',$this->piVars[$fieldConf['name']]));
				$returnValue = round( $hours * 60 );
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
				//debug ($fieldConf['name'] . ', ' . $fieldConf['type'] . ', ' . $fieldConf['multiple'] . ': ' . $returnValue);
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
				}
				break;

			case 'date':
				// parse the date to a timestamp
				$timestamp = strtotime($this->piVars[$fieldConf['name']]);
				if (!$timestamp) {
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
			// $content .= $this->pi_getLL('fileupload.uploadSuccess','File upload was successfull.');
			// change rights so that everyone can read the file
			chmod($uploadfile,octdec('0744'));
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

		} else {
			$this->hiddenFormFields['updateUid'] = '<input type="hidden" name="' . $this->prefixId . '[updateUid]" value="' . $this->internal['currentRow']['uid'] . '">';
			// remember the related tickets
			$this->hiddenFormFields['related_tickets_old'] = '<input type="hidden" name="' . $this->prefixId . '[related_tickets_old]" value="' . $this->internal['currentRow']['related_tickets'] . '">';
			$this->markerArray['LABEL_OPEN_NEW_TICKET'] = '';
		}

		// add date picker javascript to the header
		$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId.'_datetimepicker'] = '<script type="text/javascript" src="'.$this->extPath.'js/datetimepicker.js"></script>';

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
			$this->markerArray['FIELD_' . strtoupper(trim($fieldConf['name']))] = $this->renderFormField($fieldConf);

			// make the values of the ticket available without the need to put them into a form field
			// (only if we are editing an existing ticket)
			if ( ($this->piVars['showUid'] || $this->piVars['updateUid']) && strlen($this->internal['currentRow'][$fieldConf['name']])) {
				$this->markerArray['VALUE_' . strtoupper(trim($fieldConf['name']))] = $this->getFieldContent($fieldConf['name']);
			} else {
				$this->markerArray['VALUE_' . strtoupper(trim($fieldConf['name']))] = '';
			}

			// If this is an internal field:
			// If the current user may view internal fields, get the corresponding subpart and copy it into the template in order to replace the correct markers.
			// Otherwise clear the marker for the internal field.
			if ($fieldConf['internal']) {
				if (is_array($this->internalUserList) && in_array($GLOBALS['TSFE']->fe_user->user['uid'], $this->internalUserList)) {
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

		// add the hidden fields
		$this->markerArray['hidden_fields'] = implode("\n",$this->hiddenFormFields);

		// add the form markers
		$this->markerArray['TICKETFORM_NAME'] = $this->ticketFormName;
		$this->markerArray['TICKETFORM_ACTION'] = $this->cObj->typoLink_URL(array('parameter' => $GLOBALS['TSFE']->id));

		// add the ticket history
		$this->markerArray['OPTIONAL_TICKET_HISTORY'] = $this->renderTicketHistory($this->internal['currentRow']['uid']);

		// add the comment form and the list of comments (only if we are editing an existing ticket)
		if ($this->piVars['showUid'] || $this->piVars['updateUid']) {
			$this->markerArray['OPTIONAL_TICKET_COMMENT'] = $this->renderCommentForm($this->internal['currentRow']['uid']);
		} else {
			$this->markerArray['OPTIONAL_TICKET_COMMENT'] = '';
		}

		// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);

		return $content;
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
					if (!$is_internal || (is_array($this->internalUserList) && in_array($GLOBALS['TSFE']->fe_user->user['uid'], $this->internalUserList))) {
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
			$markerArray['UID'] = '[' . sprintf("%05d",$this->internal['currentRow']['uid']) . ']';
		} else {
			$markerArray['UID'] = '';
		}

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
		$markerArray['CURRENT_URL'] = t3lib_div::linkThisScript();

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

		// date2cal js for singleview
		$markerArray['DATE2CAL_JS'] = $this->date2cal->getMainJS();

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
	 * renderFormField
	 *
	 * renders a form field depending on the given configuration $fieldConf
	 * if $renderEmptyDropdownFields is set, and empty value in dropdown fields is added (useful for rendering the filter fields)
	 *
	 * @param array $fieldConf
	 * @param integer $renderEmptyDropdownFields
	 * @access public
	 * @return void
	 */
	public function renderFormField($fieldConf, $renderEmptyDropdownFields=0) {/*{{{*/
		$lcObj=t3lib_div::makeInstance('tslib_cObj');
		$content = '';

		// if the form just has been submitted, prefill the form fields with the
		// already parsed submitted values ($this->insertFields)
		// if we are updating an existing ticket, get the values from the database
		// ($this->internal['currentRow'])
		// if we are rendering fields for the listview filter, we find the values
		// in $this->filter
		if ($this->piVars['newticket'] && strlen($this->insertFields[$fieldConf['name']])) {
			$prefillValue = $this->insertFields[$fieldConf['name']];
		} else if ( ($this->piVars['showUid'] || $this->piVars['updateUid']) && strlen($this->internal['currentRow'][$fieldConf['name']])) {
			$prefillValue = $this->internal['currentRow'][$fieldConf['name']];
		} else if (is_array($this->filter)) {
			$prefillValue = $this->filter[$fieldConf['name']];
		} else {
			$prefillValue = '';
		}

		// render the form fields according to their type
		switch ($fieldConf['type']) {

			case 'submit':
					$content .= '<input type="submit" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . '" value="'.$this->pi_getLL('LABEL_' . strtoupper($fieldConf['name'])).'">';
			break;

			case 'input':
			case 'inputHoursToMinutes':
				if (strlen($prefillValue) && $fieldConf['type'] == 'inputHoursToMinutes') {
					$prefillValue = $this->lib->m2h($prefillValue);
				} else {
					$prefillValue = $this->cleanUpHtmlOutput($prefillValue);
				}
				$content .= '<input type="text" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="' . $prefillValue . '" size="' . $fieldConf['size'] . '" maxlength="' . $fieldConf['maxlength'] . '">';
			break;

			case 'select':
				$content ='<select name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '')  . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';
				foreach (explode(',',$this->conf[$fieldConf['valueList']]) as $value) {
					if (t3lib_div::inList($prefillValue, $value)) {
						$selected = ' selected';
					} else {
						$selected = '';
					}
					$value = trim($value);
					$content .= '<option value="' . $value . '"' . $selected . '>' . $this->pi_getLL('SELECTLABEL_' . strtoupper($value), $value) . '</option>';
				}
				$content .= '</select>';
			break;

			case 'input_related_tickets':
				$content = $this->renderRelatedTicketListForCurrentTicket();

				if ($this->piVars['newticket']) {
					$prefillValue = $this->piVars['related_tickets'];
				} else {
					$prefillValue = '';
				}

				$content .= $this->pi_getLL('LABEL_RELATED_TICKETS_ADD');
				$content .= ' <input type="text" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="' . $prefillValue . '" size="' . $fieldConf['size'] . '" maxlength="' . $fieldConf['maxlength'] . '">';
				break;

			case 'textareaRTE':
				// make RTE instance
				$this->RTEObj = t3lib_div::makeInstance('tx_rtehtmlarea_pi2');

				// initialize the RTE
				$this->RTEcounter++;
				$this->formName = $this->ticketFormName;
				$this->strEntryField = $fieldConf['name'];
				$this->PA['itemFormElName'] = $this->prefixId . '[' . $fieldConf['name'] . ']';
				$this->PA['itemFormElValue'] = $prefillValue;
				$this->thePidValue = $GLOBALS['TSFE']->id;
				// add 150px to the RTE width:
				$this->docLarge = true;
				$content = $this->RTEObj->drawRTE($this,'',$this->strEntryField,$row=array(), $this->PA, $this->specConf, $this->thisConfig, $this->RTEtypeVal, '', $this->thePidValue);

				// RTE Markers
				$this->markerArray['ADDITIONALJS_PRE'] = $this->additionalJS_initial.'
					<script type="text/javascript">'. implode(chr(10), $this->additionalJS_pre).'
					</script>';
				$this->markerArray['ADDITIONALJS_POST'] = '
					<script type="text/javascript">'. implode(chr(10), $this->additionalJS_post).'
					</script>';
				$this->markerArray['ADDITIONALJS_SUBMIT'] = implode(';', $this->additionalJS_submit);

			break;

			case 'textarea':
				$content .= '<textarea name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" cols="' . $fieldConf['cols'] . '" rows="' . $fieldConf['rows'] . '"  maxlength="' . $fieldConf['maxlength'] . '">';
				$content .= $prefillValue;
				$content .= '</textarea>';
			break;

			case 'date':
				if (!empty($prefillValue)) {
					$prefillValue = date($this->conf['datefield_dateformat'], $prefillValue);

					// replace the dots and slashes in the datestring
					$prefillValue = str_replace('.', '-', $prefillValue);
					$prefillValue = str_replace('/', '-', $prefillValue);
				} else {
					$prefillValue = '';
				}

				// render the datefield using the date2cal extension
				$field = $this->prefixId . '[' . $fieldConf['name'] . ']';

				$this->date2cal->config['inputField'] = $field;
				$this->date2cal->config['calConfig']['ifFormat'] = $this->conf['datefield_inputfieldformat'];
				$this->date2cal->setConfigOption('ifFormat', $this->conf['datefield_inputfieldformat']);

				$this->date2cal->setConfigOption('showsTime', 0, true);
				$this->date2cal->setConfigOption('time24', 1, true);
				$fieldContent = $this->date2cal->render($prefillValue, $field);

				$content .= $fieldContent;
			break;

			case 'files':
				// show the files, which already have been uploaded
				// including a delete link
				if (strlen($prefillValue)) {
					foreach (explode(',', $prefillValue) as $filename) {
						if (file_exists($this->fileUploadDir . $filename)) {
							$content .= '<div class="filename">';

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
								$deleteLink_URL = $this->cObj->typoLink_URL( $deleteLinkConf );
								//$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . $deleteLink_URL . '\')">|</a>';
								$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $deleteLink_URL . '\')">|</a>';

								// generate the alt text
								$imageConf['altText'] = $this->pi_getLL('altText_deletefile', 'Delete file.');

								// finally generate the delete icon
								$imageConf['file'] = $this->getFilePath($imageConf['file']);
								$content .= $lcObj->IMAGE($imageConf);
							}

							// show thumbnails
							$filetype = substr(strrchr($filename, '.'), 1);
							$filetype = strtolower($filetype);
							unset($imageConf);
							if (t3lib_div::inList($fieldConf['thumbnails'], $filetype)) {
								if (file_exists($this->fileUploadDir . $filename)) {
									$imageConf = $this->conf['thumbnailImage.'];
									$imageConf['file'] = $this->fileUploadDir . $filename;
									$content .= $lcObj->IMAGE($imageConf);
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
										$content .= $lcObj->IMAGE($imageConf);
									}
								}
							}


							// generate the link to the file
							$content .= ' ' . $lcObj->typoLink(
									$filename,
									array(
										'parameter' => $this->fileUploadDir . $filename,
										'target' => '_blank'
										)
									);

							// render the file size
							$content .= ' (' . $this->lib->filesize_format(filesize($this->fileUploadDir . $filename)) . ')';

							$content .= '</div>';
						}
					}
				}

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
						//$content .= ' <a href="' . t3lib_div::linkThisScript() . '#uploadfieldsmark" onClick="document.getElementById(\'' . $fieldConf['name'] . '_' . $j . '_row' . '\').style.display = \'inline\'; this.style.visibility = \'hidden\';">' . $this->pi_getLL('more_files') . '</a>';
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
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$this->categoryTablename,$where_clause,'','sorting');
				$num_rows = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

				// render the dropdown
				// if there is only one category possible, preselect that using a hidden form field
				// and don't display the dropdown
				if ($num_rows > 1) {
					$content .= '<select name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '')  . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';
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
						$content .= '<option value="' . $row['uid'] . '"' . $selected . '>' . $row['title'] . '</option>';
					}
					$content .= '</select>';
				} else if ($num_rows == 1) {
					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					$this->hiddenFormFields['category'] = '<input type="hidden" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="'. $row['uid'] .'">';
				}
			break;

			case 'feuserSelect':
				// if there is a single responsible user (or a user list) given in the flexform, preselect that using a hidden form field
				if (!empty($this->ffdata[$fieldConf['flexformFieldForPreselectedUser']]) && !$filterMode) {
					$this->hiddenFormFields[$fieldConf['name']] = '<input type="hidden" name="' . $this->prefixId . '[' . $fieldConf['name'] . ']" value="'. $this->ffdata[$fieldConf['flexformFieldForPreselectedUser']] .'">';
				} else {
					if ($this->ffdata[$fieldConf['flexformFieldForUsergroupToChoseFrom']] || $fieldConf['addCurrentUserToList'] || $filterMode) {

						$where_clause = '';
						$orderBy = '';
						$groupBy = '';
						$limit = '';

						// special query for "responsible"-filter
						if ($filterMode && $fieldConf['name'] == 'responsible_feuser') {
							$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $this->tablename,
									'pid IN (' . $this->pi_getPidList($this->conf['pidList'], $this->conf['recursive']) . ')'
									. ' AND status NOT LIKE "' . CONST_STATUS_CLOSED . '%"'
									. $lcObj->enableFields($this->tablename),'responsible_feuser');

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

						// add the currend fe-user if configured so
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
								$content .= '<select name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '') . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';

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
									$content .= $row['name'];
									$content .= ' (' . $row['username'] . ')';
									$content .= '</option>';
								}
								$content .= '</select>';
							}
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
					$content .= '<select name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '') . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';
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
							$content .= '<select name="' . $this->prefixId . '[' . $fieldConf['name'] . ']' . ($fieldConf['multiple'] ? '[]' : '') . '" size="' . $fieldConf['size'] . '"' . ($fieldConf['multiple'] ? ' multiple="multiple"' : '') .'>';
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

			default:

			break;
		}
		return $content;
	}/*}}}*/

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
								//$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . $deleteLink_URL . '\')">|</a>';
								$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $deleteLink_URL . '\')">|</a>';

								// generate the alt text
								$imageConf['altText'] = $this->pi_getLL('altText_delete_related_ticket', 'Delete related ticket.');

								// finally generate the delete icon
								$imageConf['file'] = $this->getFilePath($imageConf['file']);
								$content .= $lcObj->IMAGE($imageConf);
								$content .= ' ';
							}

							$linktext = '[' . sprintf("%05d",$row['uid']) . ']';
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
							$content .= '[' . sprintf("%05d",$row['uid']) . ']' . ' ' . $row['title'] ;
						}
					}
				}
			}
			unset($ticketListTemp);

			if ($content && $renderWrapDiv) {
				$content = '<div class="related_tickets">' . $content . '</div>';
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
		$content = $this->cObj->getSubpart($this->templateCode,'###LISTVIEW###');
		$lConf = $this->conf['listView.'];

		// Mode-Selecter
		$modeSelectorItems=array();
		foreach (explode(',', $this->conf['modes']) as $mode) {
			$mode = strtolower(trim($mode));
			$modeSelectorItems[$mode] = $this->pi_getLL('list_mode_' . $mode, $mode);
		}

		// Initialize mode
		if (!isset($this->piVars['mode']))	{
			$this->piVars['mode'] = $this->conf['defaultMode'];
		}

		// Initialize pointer
		if (!isset($this->piVars['pointer'])) {
			$this->piVars['pointer']=0;
		}

		// Initialize sorting
		if (!isset($this->piVars['sort'])) {
			$this->piVars['sort'] = DEFAULT_ORDERBY;
		}

		// Initializing the query parameters:

		// Tablename
		$this->internal['currentTable'] = $this->tablename;

		// ORDER BY
		list($this->internal['orderBy'],$this->internal['descFlag']) = explode(':',$this->piVars['sort']);

		// Number of results to show in a listing.
		$this->internal['results_at_a_time']=t3lib_div::intInRange($lConf['results_at_a_time'],0,1000,10);

		// The maximum number of "pages" in the browse-box: "Page 1", "Page 2", etc.
		$this->internal['maxPages']=t3lib_div::intInRange($lConf['maxPages'],0,1000,5);;

		// fields to search in
		$this->internal['searchFieldList'] = 'title,description';

		// fields allowed for the ORDER BY command
		//$this->internal['orderByList']='uid,title,crdate,until_date';
		$this->internal['orderByList'] = $this->conf['listView.']['headerList'];

		// center the page browser
		$this->internal['pagefloat']='CENTER';

		//function pi_list_query($table,$count=0,$addWhere='',$mm_cat='',$groupBy='',$orderBy='',$query='',$returnQueryArray=false)

		// PERMISSION CHECKS
		// 1. show only tickets the current logged in user is owner of, responsible user or observer
		// 2. If the flexform option "show_tickets" is set to "all_for_admins" and
		// the current user is one of the "ticket_administrators", or if the option
		// is set to "all_always", allow the current user to see and edit all
		// tickets
		if ($this->ffdata['show_tickets'] == CONST_SHOW_ALL_ALWAYS
				||
				($this->ffdata['show_tickets'] == CONST_SHOW_ALL_FOR_ADMINS
				&& $this->ffdata['ticket_administrators']
				&& t3lib_div::inList($this->ffdata['ticket_administrators'], $GLOBALS['TSFE']->fe_user->user['uid']))) {

			$addWhere = '';

		} else {

			$addWhere .= ' AND (';
			$addWhere .= '(owner_feuser=' . $GLOBALS['TSFE']->fe_user->user['uid'] . ')';
			$addWhere .= ' OR (responsible_feuser=' . $GLOBALS['TSFE']->fe_user->user['uid'] . ')';
			$addWhere .= 'OR (' . $GLOBALS['TYPO3_DB']->listQuery('observers_feuser', $GLOBALS['TSFE']->fe_user->user['uid'], $this->tablename) . ')';
			$addWhere .= ')';

		}

		// add filter
		if (is_array($this->filter)) {
			foreach ($this->filter as $filterName => $filterValue) {
				$addWhere .= ' AND ' . $filterName . '="' . mysql_real_escape_string($filterValue) . '"';
			}
		}

		// fetch only tickets which are not closed
		// closed tickets are all ticket types that have the
		// CONST_STATUS_CLOSED (normally "closed") in their key
		// So you can invent new "closed"-types like
		// "closed-without-solution" or "closed-another-reason" ...
		if ($this->piVars['mode'] == 'not_closed') {
			//$addWhere .= ' AND status!="' . CONST_STATUS_CLOSED . '"';
			$addWhere .= ' AND status NOT LIKE "%' . CONST_STATUS_CLOSED . '%"';
		}
		if ($this->ffdata['listcategories']!=''){
			$addWhere .= ' AND category IN (' . $this->ffdata['listcategories'] . ') ';
		}

		// Get number of records:
		$res = $this->pi_exec_query($this->tablename, 1, $addWhere);
		list($this->internal['res_count']) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);

		// Make listing query, pass query to SQL database
		if (t3lib_div::inList(t3lib_div::uniqueList($this->internal['orderByList']),$this->internal['orderBy'])) {
			$orderBy = $this->internal['orderBy'].($this->internal['descFlag']?' DESC':'');
		} else {
			$orderBy = DEFAULT_ORDERBY;
		}
		// add a second sorting (if sorting is not "priority"), second sorting is always priority
		if ($this->internal['orderBy']!='priority') {
			$orderBy .= ', priority DESC';
		}

		// No limit for the csv export
		if (isset($this->piVars['export']) && $this->piVars['export']=='csv') {
			$this->internal['results_at_a_time'] = 1000000;
		}

		// exec the query
		$res = $this->pi_exec_query($this->tablename, '', $addWhere, '', '', $orderBy);

		// Now that we have the query, we can do the excel-export
		if (isset($this->piVars['export']) && $this->piVars['export']=='csv') {
			$this->outputCSV($res);
		}

		// create the mode selector
		$this->markerArray['MODE_SELECTOR'] = $this->pi_list_modeSelector($modeSelectorItems);

		// render the sorting links
		$this->renderListSortingLinks();

		// render the filters
		foreach ($this->conf['formFieldList.'] as $fieldConf) {
			if (t3lib_div::inList(t3lib_div::uniqueList($this->conf['listView.']['filterList']),$fieldConf['name'])) {
				// dont't pre-select user values in the filter if the filter ist empty
				if ($fieldConf['prefillWithCurrentUserIfEmpty']) {
					$fieldConf['prefillWithCurrentUserIfEmpty'] = 0;
				}
				$this->markerArray['FILTER_' . strtoupper(trim($fieldConf['name']))] = $this->renderFormField($fieldConf, RENDER_EMPTY_DRODOWN_ELEMENT);
			}
		}

		// add the filter form markers
		$this->markerArray['FILTERFORM_NAME'] = $this->ticketformname . '_filter';
		$this->markerArray['FILTERFORM_ACTION'] = $this->cObj->typoLink_URL(
				array(
					'parameter' => $GLOBALS['TSFE']->id,
					'additionalParams' => $this->getAdditionalParamsFromKeepPiVars()
				)
			);
		$this->markerArray['FILTER_SUBMIT'] = '<input type="submit" name="' . $this->prefixId . '[filter_submit]' . '" value="'.$this->pi_getLL('LABEL_FILTER_SUBMIT').'">';

		// make the whole list
		$this->markerArray['LISTCONTENT'] = $this->makelist($res);

		// create the search box
		$this->markerArray['SEARCHBOX'] = $this->pi_list_searchBox();

		// create the result browser
		$wrapper['disabledLinkWrap'] = '<span class="disable">|</span>';
		$wrapper['inactiveLinkWrap'] = '<span class="inactive">|</span>';
		$wrapper['activeLinkWrap'] = '<span'.$this->pi_classParam('browsebox-SCell').'>|</span>';
		$wrapper['browseLinksWrap'] = '<div class="browseLinks">|</div>';
		$wrapper['showResultsWrap'] = '<p class="resultText">|</p>';
		$wrapper['browseBoxWrap'] = '<div '.$this->pi_classParam('browsebox').'> | </div>';
		$this->markerArray['PAGEBROWSER'] = $this->pi_list_browseresults(1, '', $wrapper);

		// get additional markers (locallang, ...)
		$this->markerArray = $this->getAdditionalMarkers($this->markerArray);

		// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);

		// Returns the content from the plugin.
		return $content;

	}/*}}}*/

	/**
	 * Creates a list from a database query
	 *
	 * @param	ressource	$res: A database result ressource
	 * @return	A HTML list if result items
	 */
	public function makelist($res)	{/*{{{*/
		$items=array();
			// Make list table rows
		while($this->internal['currentRow'] = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$items[]=$this->makeListItem();
		}

		$out = '<div'.$this->pi_classParam('listrow').'>
			'.implode(chr(10),$items).'
			</div>';
		return $out;
	}/*}}}*/

	/**
	 * Implodes a single row from a database to a single line
	 *
	 * @return	Imploded column values
	 */
	public function makeListItem()	{/*{{{*/
		$content = $this->cObj->getSubpart($this->templateCode,'###LISTVIEW_SINGLE_ROW###');

		// define specific markers
		if (strlen($this->conf['listView.']['fieldList'])) {
			foreach (explode(',', $this->conf['listView.']['fieldList']) as $fieldName) {
				$this->markerArray[strtoupper(trim($fieldName))] = $this->getFieldContent(strtolower(trim($fieldName)));
			}
		}

		// render special marker: own task
		// set it to 'is_own_task' if the current task belongs to the current user, otherwise set it to 0
		$this->markerArray['OWN_TASK'] = $this->getFieldContent('own_task');

		// render special marker: is_overdue
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
	 *
	 * @return	Value of the field
	 */
	public function getFieldContent($fieldName, $renderType='default')	{/*{{{*/
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
						//debug($GLOBALS['TYPO3_DB']->SELECTquery('title', 'uid=' . $singleViewPageId . $lcObj->enableFields('pages'), 'pages'));
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

				// don't link the title in the email and csv view
				if ($renderType == CONST_RENDER_TYPE_EMAIL) {
					$retval = $this->cleanUpHtmlOutput($this->internal['currentRow']['title']);
				} else if ($renderType == CONST_RENDER_TYPE_CSV) {
					$retval = $this->internal['currentRow']['title'];
				} else {
					// function pi_list_linkSingle($str,$uid,$cache=FALSE,$mergeArr=array(),$urlOnly=FALSE,$altPageId=0)
					$retval = $this->pi_list_linkSingle($this->internal['currentRow']['title'],$this->internal['currentRow']['uid'],0,0,0,$mainPage);
				}
				return $retval;
				break;

			case 'status':
			case 'billing':
			case 'priority':
				return $this->pi_getLL('SELECTLABEL_' . strtoupper(trim($this->internal['currentRow'][$fieldName])));
				break;

			case 'status_raw_value':
			case 'billing_raw_value':
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
					$retval = $this->internal['currentRow']['description'];
					$retval = $this->cleanUpHtmlOutput($retval, CONST_KEEP_TAGS_YES);
				} else {
					$retval = $this->pi_RTEcssText($this->internal['currentRow']['description']);
				}
				return $retval;
				break;

			case 'description_clean':
				$retval = strip_tags($this->internal['currentRow']['description']);
				$retval = $this->sanitizeData($retval);
				$retval = str_replace("\n", '', $retval);
				$retval = str_replace("\r", '', $retval);
				return $this->cropSentence($retval, $this->conf['listView.']['cropDescription']);
				break;

			case 'responsible_feuser':
			case 'owner_feuser':
			case 'observers_feuser':
				if (empty($this->internal['currentRow'][$fieldName])) {
					return '';
				}

				// get the user data from fe_users
				$retval = $this->lib->getNameListFromUidList($this->internal['currentRow'][$fieldName], 'fe_users', 'name,username');

				if ($renderType == CONST_RENDER_TYPE_EMAIL) {
					$retval = $this->cleanUpHtmlOutput($retval);
				}

				return $retval;
				break;

			case 'number_of_comments':
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->commentsTablename, 'ticket_uid =' . $this->internal['currentRow']['uid'] . $lcObj->enableFields($this->commentsTablename));
				$number_of_comments =  $GLOBALS['TYPO3_DB']->sql_num_rows($res);
				return $number_of_comments;
				break;

			case 'comments':
				return $this->renderCommentList($this->internal['currentRow']['uid'], $renderType);
				break;

			case 'related_tickets':
				// public function renderRelatedTicketListForCurrentTicket($renderLinks = true, $renderDeleteButton = true, $renderWrapDiv = true, $separator = '<br />')
				if ($renderType == CONST_RENDER_TYPE_CSV) {
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
					&& $this->internal['currentRow']['status'] == CONST_STATUS_CLOSED) {
					return '';
				}

				// show the delete link only to the owner and the responsible
				// user and only if the ticket isn't already closed
				if (($do == 'close'
					&& !($this->internal['currentRow']['owner_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']
					|| $this->internal['currentRow']['responsible_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']) )
					|| $this->internal['currentRow']['responsible_feuser'] == CONST_STATUS_CLOSED) {
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
					//$imageConf['wrap'] = '<a href="#" onclick="areYouSure(\' ' . $deleteLink_URL . '\')">|</a>';
					$imageConf['wrap'] = '<a href="javascript:areYouSure(\' ' . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $deleteLink_URL . '\')">|</a>';
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
				return str_replace(',', ', ',$this->internal['currentRow'][$fieldName]);
				break;

			case 'category':
				$retval = $this->lib->getNameListFromUidList($this->internal['currentRow'][$fieldName], $this->categoryTablename, 'title');
				return $retval;
				break;

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

		// get the category data
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('singleviewpage',$this->categoryTablename,'uid=' . $this->internal['currentRow']['category'] . $lcObj->enableFields($this->categoryTablename));
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$categorydata = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}

		// find out the singleview pid
		if (is_array($categorydata) && !empty($categorydata['singleviewpage'])) {
			$mainpage = $categorydata['singleviewpage'];
		} else if (!empty($this->ffdata['page_of_main_plugin'])) {
			$mainpage = $this->ffdata['page_of_main_plugin'];
		} else {
			$mainpage = $GLOBALS['TSFE']->id;
		}

		return $mainpage;
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
		return $this->pi_linkTP_keepPIvars($this->getFieldHeader($fN),array('sort'=>trim($fN).':'.($this->internal['descFlag']?0:1)));
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
		$results_at_a_time = t3lib_div::intInRange($this->internal['results_at_a_time'],1,1000);
		$totalPages = ceil($count/$results_at_a_time);
		$maxPages = t3lib_div::intInRange($this->internal['maxPages'],1,100);
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
				$pagefloat = t3lib_div::intInRange($this->internal['pagefloat'],-1,$maxPages-1);
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
				$lastPage = t3lib_div::intInRange($totalPages,1,$maxPages);
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

		echo $this->renderTable2CSV($res, $this->tablename, $this->conf['listView.']['csvExportFieldList']);
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
	public function renderTable2CSV($res,$table,$fieldList='',$renderHeader=1,$splitChar=",",$wrapChar='"',$endLineChar="\r\n") {/*{{{*/
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
			$additionalParams = '&' . $this->prefixId . '[sort]=' . trim($headerName) . ':' . ($this->internal['descFlag'] ? 0 : 1);

			// Mark this Link, if it is the currently active sorting
			$wrap = $this->internal['descFlag'] ? '<span class="sort_active_desc">|</span>' : '<span class="sort_active_asc">|</span>';
			$wrap = (substr($this->piVars['sort'],0,strlen($headerName)) == $headerName) ? $wrap : '';

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
	 * teaserView
	 *
	 * generates the teaser view
	 * TODO: merge the teaser view functions the normal listview functions (teaserView, makeTeaserItem, makeTeaserList)
	 *
	 * @access public
	 * @return void
	 */
	public function teaserView() {/*{{{*/
		$lConf = $this->conf['teaserView.'];

		if ($this->ffdata['view']=='TEASER_DEL') $content = $this->cObj->getSubpart($this->templateCode,'###TEASERVIEW_DEL###');
		if ($this->ffdata['view']=='TEASER_OWN') $content = $this->cObj->getSubpart($this->templateCode,'###TEASERVIEW_OWN###');

		if (!isset($this->piVars['mode']))	{
			$this->piVars['mode'] = $this->conf['defaultMode'];
		}

		// Initialize pointer
		if (!isset($this->piVars['pointer'])) {
			$this->piVars['pointer']=0;
		}

		// Initialize sorting
		if (!isset($this->piVars['sort'])) {
			$this->piVars['sort'] = 'crdate';
		}

		// Initializing the query parameters:

		// Tablename
		$this->internal['currentTable'] = $this->tablename;

		// ORDER BY
		list($this->internal['orderBy'],$this->internal['descFlag']) = explode(':',$this->piVars['sort']);

		// Number of results to show in a listing.
		$this->internal['results_at_a_time'] = 250;

		// The maximum number of "pages" in the browse-box: "Page 1", "Page 2", etc.
		$this->internal['maxPages']=t3lib_div::intInRange($lConf['maxPages'],0,1000,5);;

		// fields to search in
		$this->internal['searchFieldList'] = 'title,description';

		// fields allowed for the ORDER BY command
		//$this->internal['orderByList']='uid,title,crdate,until_date';
		$this->internal['orderByList'] = $this->conf['listView.']['headerList'];

		// center the page browser
		$this->internal['pagefloat']='CENTER';

		// PERMISSION CHECKS
		// 1. show only tickets the current logged in user is owner of, responsible user or observer
		// 2. If the flexform option "show_tickets" is set to "all_for_admins" and
		// the current user is one of the "ticket_administrators", or if the option
		// is set to "all_always", allow the current user to see and edit all
		// tickets

		$addWhere .= 'AND (';
		if ($this->ffdata['view']=='TEASER_DEL') {
			$addWhere .= '(owner_feuser=' . $GLOBALS['TSFE']->fe_user->user['uid'] . ')';
		} else if ($this->ffdata['view']=='TEASER_OWN') {
			$addWhere .= '(responsible_feuser=' . $GLOBALS['TSFE']->fe_user->user['uid'] .')';
		}

		//$addWhere .= 'OR (' . $GLOBALS['TYPO3_DB']->listQuery('observers_feuser', $GLOBALS['TSFE']->fe_user->user['uid'], $this->tablename) . ')';
		$addWhere .= ')';

		// fetch only tickets which are not closed
		if ($this->piVars['mode'] == 'not_closed') {
			//$addWhere .= 'AND status!="' . CONST_STATUS_CLOSED . '"';
			$addWhere .= ' AND status NOT LIKE "%' . CONST_STATUS_CLOSED . '%"';
		}

		// Get number of records:
		$res = $this->pi_exec_query($this->tablename, 1, $addWhere);
		list($this->internal['res_count']) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);

		// return nothing if there are no records
		if (!$this->internal['res_count']) {
			$content = '<p class="error">' . $this->pi_getLL('error_no_tickets_found', 'No tickets found.') . '</p>';
		} else {
			// Make listing query, pass query to SQL database
			$res = $this->pi_exec_query($this->tablename, '', $addWhere);

			// render the sorting links
			$this->renderListSortingLinks();

			// make the whole list
			$this->markerArray['LISTCONTENT'] = $this->makeTeaserList($res);

			// create the search box
			$this->markerArray['SEARCHBOX'] = $this->pi_list_searchBox();

			// create the result browser
			$wrapper['disabledLinkWrap'] = '<span class="disable">|</span>';
			$wrapper['inactiveLinkWrap'] = '<span class="inactive">|</span>';
			$wrapper['activeLinkWrap'] = '<span'.$this->pi_classParam('browsebox-SCell').'>|</span>';
			$wrapper['browseLinksWrap'] = '<div class="browseLinks">|</div>';
			$wrapper['showResultsWrap'] = '<p class="resultText">|</p>';
			$wrapper['browseBoxWrap'] = '<div '.$this->pi_classParam('browsebox').'> | </div>';
			$this->markerArray['PAGEBROWSER'] = $this->pi_list_browseresults(1, '', $wrapper);

			// get additional markers (locallang, ...)
			$this->markerArray = $this->getAdditionalMarkers($this->markerArray);

			// substitute the markers
			$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);
		}

		// Returns the content from the plugin.
		return $content;
	}/*}}}*/

	/**
	 * makeTeaserList
	 *
	 * renders the teaser list
	 *
	 * @param databaseresult $res
	 * @access public
	 * @return string
	 */
	public function makeTeaserList($res) {/*{{{*/
		$items=array();

		// Make list table rows
		while($this->internal['currentRow'] = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$items[]=$this->makeTeaserItem();
		}

		if (count($items)>0) {
			$out = '<div'.$this->pi_classParam('listrow').'>
			'.implode(chr(10),$items).'
			</div>';
		}

		return $out;
	}/*}}}*/

	/**
	 * Implodes a single row from a database to a single line
	 *
	 * @return	Imploded column values
	 */
	public function makeTeaserItem() {/*{{{*/

		if ($this->ffdata['view']=='TEASER_DEL') $content = $this->cObj->getSubpart($this->templateCode,'###TEASER_SINGLE_ROW_DEL###');
		if ($this->ffdata['view']=='TEASER_OWN') $content = $this->cObj->getSubpart($this->templateCode,'###TEASER_SINGLE_ROW_OWN###');

		// define specific markers
		if (strlen($this->conf['listView.']['fieldList'])) {
			foreach (explode(',', $this->conf['listView.']['fieldList']) as $fieldName) {
				$this->markerArray[strtoupper(trim($fieldName))] = $this->getFieldContent(strtolower(trim($fieldName)));
			}
		}

		// render special marker: own task
		// set it to 'is_own_task' if the current task belongs to the current user, otherwise set it to 0
		$this->markerArray['OWN_TASK'] = $this->getFieldContent('own_task');

		// render special marker: is_overdue
		// set it to 1 if the "until_date" of the current Ticket is in the past
		$this->markerArray['IS_OVERDUE'] = $this->getFieldContent('is_overdue');

		// get additional markers (locallang, ...)
		$this->markerArray = $this->getAdditionalMarkers($this->markerArray);

		// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);

		return $content;
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
	public function cropSentence ($strText, $intLength = 200, $strTrail = '...') {
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
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php']);
}

?>