<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

  ## Extending TypoScript from static template uid=43 to set up userdefined tag:
t3lib_extMgm::addTypoScript($_EXTKEY,'editorcfg','
	tt_content.CSS_editor.ch.tx_ketroubletickets_pi1 = < plugin.tx_ketroubletickets_pi1.CSS_editor
',43);


t3lib_extMgm::addPItoST43($_EXTKEY,'pi1/class.tx_ketroubletickets_pi1.php','_pi1','list_type',0);

$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['tx_ketroubletickets_ical'] = 'EXT:ke_troubletickets/ical/class.tx_ketroubletickets_icalendar.php';

t3lib_extMgm::addTypoScript($_EXTKEY,'setup','
	tt_content.shortcut.20.0.conf.tx_ketroubletickets_tickets = < plugin.'.t3lib_extMgm::getCN($_EXTKEY).'_pi1
	tt_content.shortcut.20.0.conf.tx_ketroubletickets_tickets.CMD = singleView
',43);

t3lib_extMgm::addPageTSConfig('

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
t3lib_extMgm::addPageTSConfig('

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
?>
