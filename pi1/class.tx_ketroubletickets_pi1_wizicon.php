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




/**
 * Class that adds the wizard icon.
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_ketroubletickets
 */
class tx_ketroubletickets_pi1_wizicon {

    /**
     * Processing the wizard items array
     *
     * @param	array		$wizardItems: The wizard items
     * @return	Modified array with wizard items
     */
    function proc($wizardItems)	{
        global $LANG;

        $LL = $this->includeLocalLang();

        $wizardItems['plugins_tx_ketroubletickets_pi1'] = array(
            'icon'=>\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('ke_troubletickets').'pi1/ce_wiz.gif',
            'title'=>$LANG->getLLL('pi1_title',$LL),
            'description'=>$LANG->getLLL('pi1_plus_wiz_description',$LL),
            'params'=>'&defVals[tt_content][CType]=list&defVals[tt_content][list_type]=ke_troubletickets_pi1'
        );

        return $wizardItems;
    }

    /**
     * Reads the [extDir]/locallang.xml and returns the $LOCAL_LANG array found in that file.
     *
     * @return	The array with language labels
     */
    function includeLocalLang() {
        $llFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('ke_troubletickets') . 'locallang.xml';
        if ($this->getNumericTYPO3versionNumber() >= 6000000) {
            $xmlParser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Parser\LocallangXmlParser::class);
            $LOCAL_LANG = $xmlParser->getParsedData($llFile, $GLOBALS['LANG']->lang);
        } else {
            $LOCAL_LANG = \TYPO3\CMS\Core\Utility\GeneralUtility::readLLXMLfile($llFile, $GLOBALS['LANG']->lang);
        }
        return $LOCAL_LANG;
    }

    /**
     * Returns the current TYPO3 version number as an integer, eg. 4005000 for version 4.5
     *
     * @return int
     */
    public function getNumericTYPO3versionNumber() {
        if (class_exists(\TYPO3\CMS\Core\Utility\VersionNumberUtility::class)) {
            $numeric_typo3_version = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
        } else if (class_exists('t3lib_utility_VersionNumber')) {
            $numeric_typo3_version = t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version);
        } else {
            $numeric_typo3_version = \TYPO3\CMS\Core\Utility\GeneralUtility::int_from_ver(TYPO3_version);
        }
        return $numeric_typo3_version;
    }
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1_wizicon.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/pi1/class.tx_ketroubletickets_pi1_wizicon.php']);
}

?>
