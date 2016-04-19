<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

  ## Extending TypoScript from static template uid=43 to set up userdefined tag:
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript($_EXTKEY,'editorcfg','
	tt_content.CSS_editor.ch.tx_ketroubletickets_pi1 = < plugin.tx_ketroubletickets_pi1.CSS_editor
',43);


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43($_EXTKEY,'pi1/class.tx_ketroubletickets_pi1.php','_pi1','list_type',0);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43($_EXTKEY,'pi2/class.tx_ketroubletickets_pi2.php','_pi2','list_type',0);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript($_EXTKEY,'setup','
	tt_content.shortcut.20.0.conf.tx_ketroubletickets_tickets = < plugin.'.\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getCN($_EXTKEY).'_pi1
	tt_content.shortcut.20.0.conf.tx_ketroubletickets_tickets.CMD = singleView
',43);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('

	# ***************************************************************************************
	# CONFIGURATION of RTE in table "tx_ketroubletickets_tickets", field "description"
	# ***************************************************************************************
RTE.config.tx_ketroubletickets_tickets.description {
  hidePStyleItems = H1, H4, H5, H6
  proc.exitHTMLparser_db=1
  proc.exitHTMLparser_db {
    keepNonMatchedTags=1
    tags.font.allowedAttribs= color
    tags.font.rmTagIfNoAttrib = 1
    tags.font.nesting = global
  }
}
');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('

	# ***************************************************************************************
	# CONFIGURATION of RTE in table "tx_ketroubletickets_comments", field "content"
	# ***************************************************************************************
RTE.config.tx_ketroubletickets_comments.content {
  hidePStyleItems = H1, H4, H5, H6
  proc.exitHTMLparser_db=1
  proc.exitHTMLparser_db {
    keepNonMatchedTags=1
    tags.font.allowedAttribs= color
    tags.font.rmTagIfNoAttrib = 1
    tags.font.nesting = global
  }
}
');

// register eID script
$TYPO3_CONF_VARS['FE']['eID_include']['kett'] = 'EXT:ke_troubletickets/eID/class.tx_ketroubletickets_eid.php';

?>
