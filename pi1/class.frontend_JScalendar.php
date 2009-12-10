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
 * This class extends the date2cal class in order to make it work in the frontend.
 * Date2cal relies on backend function which do not work in the frontend if no backend
 * user is logged in.
 * Tested with date2cal version 7.1.0
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 */

// include the date2cal library
require_once(t3lib_extMgm::extPath('date2cal') . 'src/class.jscalendar.php');

class frontend_JScalendar extends JScalendar {

	/**
	 * Creates a singleton instance of JSCalendar. Its important to use only this funcion than
	 * a direct initialization of the class! You can use this method via a static call.
	 *
	 * @return object instance of JSCalendar
	 */
	function &getInstance() {
		static $instance;
		if (!isset($instance))
			$instance = new frontend_JSCalendar();
		return $instance;
	}

	/**
	 * Reads and prepareas the global date2cal configuration.
	 *
	 * @return array global date2cal configuration
	 */
	function readGlobalConfig() {
		// unserialize configuration
		$extConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['date2cal']);

		// get calendar image
		$extConfig['calImg'] = t3lib_div::getFileAbsFileName($extConfig['calImg']);
		$extConfig['calImg'] = $this->config['backPath'] .
			substr($extConfig['calImg'], strlen(PATH_site));

		// get help image
		$extConfig['helpImg'] = t3lib_div::getFileAbsFileName($extConfig['helpImg']);
		$extConfig['helpImg'] = $this->config['backPath'] .
			substr($extConfig['helpImg'], strlen(PATH_site));

		// user/group settings
		/*
		 * Don't get the TSConfig as this requires backend functions and
		 * leads to errors in the frontend
		 */
		/*
		$userProps = t3lib_BEfunc::getModTSconfig($this->pageinfo['uid'], 'tx_date2cal');

		if (!is_array($userProps))
			$extConfig = array_merge($extConfig, $userProps['properties']);
		*/

		return $extConfig;
	}
}
?>
