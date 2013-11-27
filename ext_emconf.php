<?php

########################################################################
# Extension Manager/Repository config file for ext "ke_troubletickets".
#
# Auto generated 09-10-2012 15:18
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Troubletickets',
    'description' => 'Manage your troubletickets, tasks or todo-lists or use the extension as a bugtracker, helpdesk or project management tool. Assign tickets to users, define observers, receive email notifications and group tickets into categories and projects.',
    'category' => 'fe',
    'shy' => 0,
    'version' => '1.6.0',
    'dependencies' => 'cms,rtehtmlarea',
    'conflicts' => '',
    'priority' => '',
    'loadOrder' => '',
    'module' => 'mod1',
    'state' => 'stable',
    'uploadfolder' => 1,
    'createDirs' => 'uploads/tx_ketroubletickets/rte/',
    'modify_tables' => '',
    'clearcacheonload' => 0,
    'lockType' => '',
    'author' => 'C. Buelter, C. Keramitsis, A. Kiefer (kennziffer.com)',
    'author_email' => 'buelter@kennziffer.com',
    'author_company' => 'www.kennziffer.com GmbH',
    'CGLcompliance' => '',
    'CGLcompliance_note' => '',
    'constraints' => array(
	'depends' => array(
	    'cms' => '',
	    'rtehtmlarea' => '',
	    'php' => '5.3.0-0.0.0',
	    'typo3' => '4.5.0-6.1.99',
	),
	'conflicts' => array(
	),
	'suggests' => array(
	),
    ),
    '_md5_values_when_last_written' => 'a:116:{s:9:"ChangeLog";s:4:"cbd0";s:10:"README.txt";s:4:"ee2d";s:12:"ext_icon.gif";s:4:"bd76";s:17:"ext_localconf.php";s:4:"e2a2";s:14:"ext_tables.php";s:4:"74c8";s:14:"ext_tables.sql";s:4:"fe24";s:15:"flexform_ds.xml";s:4:"30de";s:39:"icon_tx_ketroubletickets_categories.gif";s:4:"475a";s:37:"icon_tx_ketroubletickets_comments.gif";s:4:"475a";s:36:"icon_tx_ketroubletickets_history.gif";s:4:"475a";s:36:"icon_tx_ketroubletickets_tickets.gif";s:4:"475a";s:13:"locallang.xml";s:4:"6604";s:16:"locallang_db.xml";s:4:"25fc";s:7:"tca.php";s:4:"c160";s:14:"doc/manual.sxw";s:4:"7bd7";s:20:"js/datetimepicker.js";s:4:"6a19";s:37:"lib/class.tx_ketroubletickets_lib.php";s:4:"14e0";s:26:"mod1/backendmenu.class.php";s:4:"2bd4";s:14:"mod1/clear.gif";s:4:"cc11";s:13:"mod1/conf.php";s:4:"074f";s:14:"mod1/index.php";s:4:"5547";s:18:"mod1/locallang.xml";s:4:"e7d7";s:22:"mod1/locallang_mod.xml";s:4:"41b8";s:19:"mod1/moduleicon.gif";s:4:"8e6e";s:14:"pi1/ce_wiz.gif";s:4:"3833";s:37:"pi1/class.tx_ketroubletickets_pi1.php";s:4:"ad8d";s:45:"pi1/class.tx_ketroubletickets_pi1_wizicon.php";s:4:"be95";s:13:"pi1/clear.gif";s:4:"cc11";s:17:"pi1/locallang.xml";s:4:"5f30";s:24:"pi1/static/editorcfg.txt";s:4:"ac64";s:20:"pi1/static/setup.txt";s:4:"a17e";s:37:"pi2/class.tx_ketroubletickets_pi2.php";s:4:"45e9";s:17:"pi2/locallang.xml";s:4:"16ed";s:29:"res/css/ke_troubletickets.css";s:4:"6e9a";s:39:"res/css/ke_troubletickets_printview.css";s:4:"e297";s:27:"res/images/button_minus.gif";s:4:"10d6";s:26:"res/images/button_plus.gif";s:4:"0440";s:18:"res/images/cal.gif";s:4:"e05d";s:23:"res/images/calendar.png";s:4:"5307";s:28:"res/images/charged_fully.png";s:4:"bf7c";s:29:"res/images/charged_partly.png";s:4:"9ef1";s:28:"res/images/css_gradient1.gif";s:4:"e42b";s:28:"res/images/css_gradient2.gif";s:4:"c7b1";s:28:"res/images/css_gradient3.gif";s:4:"e863";s:28:"res/images/css_gradient4.gif";s:4:"bed1";s:28:"res/images/css_gradient5.gif";s:4:"8229";s:18:"res/images/csv.gif";s:4:"414e";s:23:"res/images/fe_close.gif";s:4:"13ed";s:24:"res/images/fe_delete.gif";s:4:"0ef9";s:30:"res/images/fe_delete_small.png";s:4:"7bd3";s:22:"res/images/fe_edit.gif";s:4:"a913";s:19:"res/images/gray.png";s:4:"3a72";s:20:"res/images/green.png";s:4:"a078";s:30:"res/images/icon_arrow_down.gif";s:4:"77f8";s:30:"res/images/icon_arrow_left.png";s:4:"5050";s:28:"res/images/icon_arrow_up.gif";s:4:"b202";s:26:"res/images/icon_attach.png";s:4:"0cb6";s:28:"res/images/icon_comments.png";s:4:"7f92";s:26:"res/images/icon_delete.png";s:4:"6846";s:24:"res/images/icon_disk.png";s:4:"bb6d";s:31:"res/images/icon_edit_ticket.png";s:4:"2161";s:27:"res/images/icon_history.png";s:4:"19bf";s:30:"res/images/icon_new_ticket.png";s:4:"148a";s:31:"res/images/icon_sort_active.gif";s:4:"914a";s:38:"res/images/list_item_foot_bg_alien.gif";s:4:"e52a";s:39:"res/images/list_item_foot_bg_normal.gif";s:4:"7288";s:38:"res/images/list_item_head_bg_alien.gif";s:4:"8d12";s:39:"res/images/list_item_head_bg_normal.gif";s:4:"bd6f";s:38:"res/images/list_item_subt_bg_alien.gif";s:4:"d74f";s:39:"res/images/list_item_subt_bg_normal.gif";s:4:"1d82";s:42:"res/images/list_item_userhead_bg_alien.gif";s:4:"7942";s:42:"res/images/list_item_userhead_bg_inter.gif";s:4:"e2fa";s:42:"res/images/list_item_userhead_bg_norma.gif";s:4:"4378";s:18:"res/images/new.gif";s:4:"b429";s:21:"res/images/orange.png";s:4:"9144";s:24:"res/images/permalink.png";s:4:"51b1";s:24:"res/images/printlink.png";s:4:"2424";s:18:"res/images/red.png";s:4:"a7c6";s:20:"res/images/reset.gif";s:4:"14c6";s:25:"res/images/reset_gray.gif";s:4:"3455";s:41:"res/images/single_item_head_bg_normal.gif";s:4:"bddd";s:36:"res/images/status_acknowledgment.gif";s:4:"3b99";s:26:"res/images/status_edit.gif";s:4:"c8a2";s:30:"res/images/status_finished.gif";s:4:"dd85";s:28:"res/images/status_locked.gif";s:4:"175e";s:33:"res/images/status_message_bgr.gif";s:4:"0de1";s:27:"res/images/status_offer.gif";s:4:"f777";s:26:"res/images/status_open.gif";s:4:"31ca";s:28:"res/images/status_parked.gif";s:4:"42db";s:26:"res/images/status_wait.gif";s:4:"cdc5";s:28:"res/images/thumb_default.gif";s:4:"d095";s:21:"res/images/yellow.png";s:4:"b9dd";s:28:"res/images/fileicons/avi.gif";s:4:"27bd";s:28:"res/images/fileicons/css.gif";s:4:"4786";s:28:"res/images/fileicons/csv.gif";s:4:"e413";s:32:"res/images/fileicons/default.gif";s:4:"ec6e";s:28:"res/images/fileicons/doc.gif";s:4:"8c62";s:28:"res/images/fileicons/htm.gif";s:4:"54de";s:29:"res/images/fileicons/html.gif";s:4:"3cea";s:27:"res/images/fileicons/js.gif";s:4:"7a5a";s:28:"res/images/fileicons/mov.gif";s:4:"d5e6";s:28:"res/images/fileicons/mp3.gif";s:4:"b37e";s:29:"res/images/fileicons/mpeg.gif";s:4:"15b5";s:28:"res/images/fileicons/mpg.gif";s:4:"15b5";s:28:"res/images/fileicons/pdf.gif";s:4:"5c5f";s:28:"res/images/fileicons/psd.gif";s:4:"4448";s:28:"res/images/fileicons/rtf.gif";s:4:"f660";s:29:"res/images/fileicons/tmpl.gif";s:4:"5114";s:28:"res/images/fileicons/ttf.gif";s:4:"9f93";s:28:"res/images/fileicons/txt.gif";s:4:"d7f9";s:28:"res/images/fileicons/wav.gif";s:4:"6931";s:28:"res/images/fileicons/xls.gif";s:4:"4a22";s:28:"res/images/fileicons/xml.gif";s:4:"2e7b";s:28:"res/images/fileicons/zip.gif";s:4:"5de4";s:39:"res/template/ke_troubletickets.tmpl.htm";s:4:"d72e";s:43:"res/template/ke_troubletickets_pi2.tmpl.htm";s:4:"b8e0";}',
    'suggests' => array(
    ),
);
?>