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
 * @author	Christian Bülter <buelter@kennziffer.com>
 */

/**
 * Library Class for the 'ke_troubletickets' extension.
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_ketroubletickets
 */
class tx_ketroubletickets_lib {
	var $prefixId 			= 'tx_ketroubletickets_lib';		// Same as class name
	var $scriptRelPath 		= 'lib/class.tx_ketroubletickets_lib.php';	// Path to this script relative to the extension dir.
	var $extKey 			= 'ke_troubletickets';	// The extension key.

	/**
	 * getNameListFromUidList
	 *
	 * generates a list of real names / titles from a list of uids
	 * $titleField may be a commalist of fields in the table
	 *
	 * @param string $uid_list
	 * @param string $table
	 * @param string $titleField
	 * @access public
	 * @return void
	 */
	function getNameListFromUidList($uid_list='', $table='', $titleField='') {/*{{{*/
		$content = '';

		if (strlen($uid_list)) {
			foreach (explode(',', $uid_list) as $uid) {
				if (strlen($content)) {
					$content .= ', ';
				}

				// get the user data from fe_users
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($titleField, $table, 'uid=' . $uid );
				if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					$title = '';
					foreach (explode(',', $titleField) as $currentTitleField) {
						if (empty($title)) {
							$title = $row[$currentTitleField];
						}
					}
					$content .= $title;
				}

				// keep the uid in the output list
				//$content .= '-' . $uid;
			}
		}

		return $content;
	}/*}}}*/

	/**
	 * m2h
	 *
	 * Minutes to Hours
	 *
	 * @param mixed $mins
	 * @access public
	 * @return void
	 */
	public function m2h($mins) {/*{{{*/
		// return an empty string, if no minutes are given
		// (or the value is zero)
		if (empty($mins)) {
			return '';
		}

		if ($mins < 0) {
			$min = Abs($mins);
		} else {
			$min = $mins;
		}
		$H = Floor($min / 60);

		//$M = ($min - ($H * 60)) / 100;
		// set the part of an hour relative to 60, not to 100
		$M = ($min - ($H * 60)) / 60;
		$M = round($M, 2);

		$hours = $H + $M;
		if ($mins < 0) {
			$hours = $hours * (-1);
		}
		$expl = explode(".", $hours);
		$H = $expl[0];
		if (empty($expl[1])) {
			$expl[1] = 00;
		}
		$M = $expl[1];
		if (strlen($M) < 2) {
			$M = $M . 0;
		}
		$hours = $H . "," . $M;
		return $hours;
	} /*}}}*/


    /**
     * Format a number of bytes into a human readable format.
     * Optionally choose the output format and/or force a particular unit
     *
     * @param   int     $bytes      The number of bytes to format. Must be positive
     * @param   string  $format     Optional. The output format for the string
     * @param   string  $force      Optional. Force a certain unit. B|KB|MB|GB|TB
     * @return  string              The formatted file size
     */
    function filesize_format($bytes, $format = '', $force = '') {/*{{{*/
        $force = strtoupper($force);
        $defaultFormat = '%01d %s';
        if (strlen($format) == 0)
            $format = $defaultFormat;

        $bytes = max(0, (int) $bytes);

        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

        $power = array_search($force, $units);

        if ($power === false)
            $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return sprintf($format, $bytes / pow(1024, $power), $units[$power]);
    }/*}}}*/

	/**
 	* API-function: generate link to a ticket. Takes Troubletickets permissions
 	* into account.
 	*
 	* @param   integer $ticketUid ticket to link to.
 	* @param   integer $overWriteSingleViewPid normaly the single view comes from the category. you may overwrite it here.
 	* @param   object $troubleticketsObj if the call comes from ke_troubletickets, the troubletickets object is given here.
 	* @return  string
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Thu Jun 17 2010 13:19:40 GMT+0200
 	*/
	public function getLinkToTicket_URL($ticketData, $overWriteSingleViewPid=0, $troubleticketsObj=false) {
		$lcObj=t3lib_div::makeInstance('tslib_cObj');
		$url = '';
		if (is_array($ticketData) && count($ticketData)) {
				// find out the singleview pid
				// the singleviewpage must be set in the category
				// If the category of the current ticket contains a single view page,
				// use that. Otherwise no link generation is possible (if no
				// $overWriteSingleViewPid is set).
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'singleviewpage',
				'tx_ketroubletickets_categories',
				'uid=' . $ticketData['category']
				. $lcObj->enableFields('tx_ketroubletickets_categories')
			);

			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
				$categoryData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			}

			if (is_array($categoryData) && !empty($categoryData['singleviewpage'])) {
				$singleViewPage = $categoryData['singleviewpage'];
			}

			if ($overWriteSingleViewPid) {
				$singleViewPage = $overWriteSingleViewPid;
			}

				// generate the link
			if ($singleViewPage) {
				$url = $lcObj->typoLink_URL(array(
					'parameter' => $singleViewPage,
					'additionalParams' => '&tx_ketroubletickets_pi1[showUid]=' . $ticketData['uid']
					)
				);
			}

		}
		return $url;
	}

	/**
	 * checkPermissionForTicket
	 *
	 * checks if the current user has access to a given ticket
	 *
	 * Permissions:
	 * 1. show only tickets the current logged in user is owner of,
	 * responsible user or observer
	 *
	 * 2. If the flexform option "show_tickets" is set to "all_for_admins" and
	 * the current user is one of the "ticket_administrators", or if the option
	 * is set to "all_always", allow the current user to see and edit all
	 * tickets
	 *
	 * Checks also for the right sysfolder, that means: is the
	 * ticket in the page/sysfolder defined in the troubletickets plugin.
	 *
	 * returns false if he has no rights
	 * returns 1 if he has full rights (owner or responsible user)
	 * returns 2 if he has limited rights (observer)
	 *
	 * If the call comes from ke_troubletickets, $troubleticketsObj is set and
	 * the check is also done for the right storage pid and if the current user
	 * is a ticket administrator. If the call comes from another extension, these
	 * checks are not possible, therefore we go the safe way and assume the
	 * following:
	 * 1. The user isn't a ticket administrator therefore does not have access
	 * if he is not owner, responsible or observer
	 * 2. The ticket is in a sysfolder the plugin has access to. If this is
	 * not true, the permission is granted, but then will be denied in the single
	 * view of the troubleticket plugin.
	 *
	 * @param int $ticketUid Ticket to check permissions for.
	 * @param int $troubleticketsObj troubletickets object
	 * @access public
	 * @return integer
	 */
	public function checkPermissionForTicket($ticketUid=0, $troubleticketsObj=false) {/*{{{*/
		$permission = false;
		if ($GLOBALS['TSFE']->loginUser) {

				// Fetch the ticket from the database. This is the first
				// permission check (enableFields).
			$lcObj = t3lib_div::makeInstance('tslib_cObj');
			$where = 'uid=' . $ticketUid . $lcObj->enableFields('tx_ketroubletickets_tickets');

				// if the call is coming from ke_troubletickets, the configuration
				// is available, therefore we can check if the ticket is on the page
				// configured as storage page for the current troubletickets plugin.
				// If the call is coming from another extension, we don't check this.
				// That means a link may be generated, but after the user clicks on it,
				// the user will get the "access denied" message.
			if (is_object($troubleticketsObj)) {
				$where .= ' AND pid IN (' . $troubleticketsObj->pi_getPidList($troubleticketsObj->conf['pidList'], $troubleticketsObj->conf['recursive']) . ')';
			}

			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_ketroubletickets_tickets', $where);
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
				$ticketRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($ticketRow['owner_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']) {
					$permission = true;
				}
				if ($ticketRow['responsible_feuser'] == $GLOBALS['TSFE']->fe_user->user['uid']) {
					$permission = true;
				}
				if (t3lib_div::inList($ticketRow['observers_feuser'], $GLOBALS['TSFE']->fe_user->user['uid'])) {
					$permission = true;
				}

					// if the call is coming from ke_troubletickets, the flexform configuration
					// is available, therefore we can check if the user is a ticket
					// administrator. If not, we don't check and don't set the permission.
				if (is_object($troubleticketsObj)) {
					if ($troubleticketsObj->ffdata['show_tickets'] == CONST_SHOW_ALL_FOR_ADMINS && $troubleticketsObj->ffdata['ticket_administrators'] && t3lib_div::inList($troubleticketsObj->ffdata['ticket_administrators'], $GLOBALS['TSFE']->fe_user->user['uid'])) {
						$permission = true;
					}
					if ($troubleticketsObj->ffdata['show_tickets'] == CONST_SHOW_ALL_ALWAYS) {
						$permission = true;
					}
				}
			}
		}
		return $permission;
	}/*}}}*/

	/**
	* fe_getRecord
	*
	* Returns one record from a certain table.
	*
	* @param string $fields
	* @param string $from_table
	* @param string $where_clause
	* @param string $groupBy
	* @param string $orderBy
	* @param string $limit
	* @access public
	* @return array / false
	*/
   function fe_getRecord($fields, $from_table, $where_clause, $groupBy='',$orderBy='',$limit='1') {
	   $lcObj=t3lib_div::makeInstance('tslib_cObj');
	   $where_clause .= $lcObj->enableFields($from_table);
	   $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$from_table,$where_clause,$groupBy,$orderBy,$limit);
	   if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
		   $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
	   }
	   return is_array($row) ? $row : false;
   }

   /**
 	* returns the full row of a ticket
 	*
 	* @param   integer $ticketUid
 	* @return  array
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Thu Jun 17 2010 13:41:49 GMT+0200
 	*/
   public function getTicketData($ticketUid) {
		return $this->fe_getRecord('*', 'tx_ketroubletickets_tickets', 'uid=' . $ticketUid);
   }
   
   
	/**
	 * returns the todo entries for a ticket
	 *
	 * @param   integer $ticketUid
	 * @return  array
	 * @author  Andreas Kiefer <kiefer@kennziffer.com>
	 * @since   Wed Apr 3 2013 16:37:32 GMT+0200
	 */
	public function getToDoEntriesForTicket($ticketUid) {
		$lcObj=t3lib_div::makeInstance('tslib_cObj');
		$table = 'tx_ketroubletickets_todo';
		$fields = '*';
		$where = 'ticket_uid=' . intval($ticketUid);
		$where .= $lcObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', 'sorting, uid');
		$results = array();
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$results[] = $row;
		}
		return $results;
	}
   
	/**
	 * calculates the ticket progress regarding to todo entries 
	 * 
	 *
	 * @param   integer $ticketUid
	 * @return  integer
	 * @author  Andreas Kiefer <kiefer@kennziffer.com>
	 * @since   Thu Apr 4 2013 11:45:22 GMT+0200
	 */
	function getTicketProgressFromToDo($ticketUid) {
		$lcObj=t3lib_div::makeInstance('tslib_cObj');
		$table = 'tx_ketroubletickets_todo';
		$fields = 'count(uid) as total, (SELECT count(uid) FROM tx_ketroubletickets_todo WHERE ticket_uid='.$ticketUid.' AND done=1) as completed';
		$where = 'ticket_uid=' . intval($ticketUid);
		$where .= $lcObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		if ($row['total'] > 0) {
			// calculation based on existing todos
			return round((100 * $row['completed']) / $row['total']);
		} else {
			// calculation based on ticket status
			return 0;
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php']);
}
?>
