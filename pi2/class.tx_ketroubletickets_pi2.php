<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Christian Bülter <buelter@kennziffer.com>
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

require_once(t3lib_extMgm::extPath('ke_troubletickets').'lib/class.tx_ketroubletickets_lib.php');

/**
 * Plugin 'Trouble Ticket System' for the 'ke_troubletickets' extension.
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_ketroubletickets
 */
class tx_ketroubletickets_pi2 extends tslib_pibase {
	var $prefixId 			= 'tx_ketroubletickets_pi2';		// Same as class name
	var $scriptRelPath 		= 'pi2/class.tx_ketroubletickets_pi2.php';	// Path to this script relative to the extension dir.
	var $extKey 			= 'ke_troubletickets';	// The extension key.
	var $tablename 		 	= 'tx_ketroubletickets_tickets';
	var $categoryTablename 	= 'tx_ketroubletickets_categories';
	var $historyTablename 	= 'tx_ketroubletickets_history';
	var $commentsTablename 	= 'tx_ketroubletickets_comments';

	/**
	 * Plugin Main Method
	 *
	 * @param	string	$content: The content of the PlugIn
	 * @param	array		$conf: The PlugIn Configuration
	 * @return	The content that should be displayed on the website
	 */
	public function main($content,$conf) {/*{{{*/
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

			// path to this extension
		$this->extPath = t3lib_extMgm::siteRelPath($this->extKey);

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
		$this->conf = $conf;

			// a local content object (with clear configuration)
		$lcObj=t3lib_div::makeInstance('tslib_cObj');

			// start with empty content
		$content = '';


			// get the template
		$templateFile = $this->conf['templateFile'] ? 
				$this->conf['templateFile'] : 
				$this->extPath . 'res/template/ke_troubletickets_pi2.tmpl.htm';

		$this->templateCode = $lcObj->fileResource($templateFile);
		if (!$this->templateCode) {
			return '<p class="error">' . $this->pi_getLL('error_no_template') . '</p>';
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


				// get ticket data
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid,category', $this->tablename, 'uid=' . $this->piVars['showUid'] . $lcObj->enableFields($this->tablename));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				$ticket = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			}

				// get the category data
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('singleviewpage',
					$this->categoryTablename,
					'uid="' . $ticket['category'] . '" '
					. $lcObj->enableFields($this->categoryTablename)
				);

			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
				$categoryData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			}

				// find out the singleview pid
				// the singleviewpage must be set in the category
				// If the category of the current ticket contains a single view page,
				// use that. Otherwise use the current page.
			if (is_array($categoryData) && !empty($categoryData['singleviewpage'])) {
				$singleViewPage = $categoryData['singleviewpage'];
					// render the link
				$linkToTicketURL = $this->pi_getPageLink($singleViewPage, '_blank', array(
						'tx_ketroubletickets_pi1[showUid]' => $ticket['uid']
					)
				);

					// do the redirect
				header('Location:'.t3lib_div::locationHeaderUrl($linkToTicketURL));
			} else {
				$content .= '<p class="error">' . $this->pi_getLL('error_no_singleviewpage') . '</p>';
			}

		}

		if ($this->piVars['sword']) {
			$content .= '<p class="error">' . $this->pi_getLL('error_not_found') . '</p>';
		}

		$content .= $this->renderForm();

		return $this->pi_wrapInBaseClass($content);
	}/*}}}*/


	/**
	 * Renders the search form.
	 *
	 * @author Christian Bülter <buelter@kennziffer.com>
	 * @return type string
	 */
	public function renderForm() {
			// get the template subpart
		$content = $this->cObj->getSubpart($this->templateCode,'###SEARCHFORM###');

		$this->markerArray['TICKETFORM_NAME'] = 'ke_troubletickets_uid_searchform';
		$this->markerArray['TICKETFORM_ACTION'] = $this->cObj->typoLink_URL(array('parameter' => $GLOBALS['TSFE']->id));

			// substitute the markers
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,'###|###',true);

		return $content;
	}


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
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php']);
}

?>
