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
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1.php']);
}

?>
