<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Christian Bülter <buelter@kennziffer.com>
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


    // DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require_once('conf.php');
require_once($BACK_PATH.'init.php');

require_once('../lib/class.tx_ketroubletickets_lib.php');
require_once('backendmenu.class.php');

$LANG->includeLLFile('EXT:ke_troubletickets/mod1/locallang.xml');
$BE_USER->modAccess($MCONF,1);    // This checks permissions and exits if the users has no permission for entry.
    // DEFAULT initialization of a module [END]

define(STATUS_ALL, 0);
define(STATUS_NOT_CLOSED, 1);
define(STATUS_CURRENT, 2);
define(STATUS_OFFER, 3);
define(STATUS_CLOSED, 4);

define(BILLING_ALL, 0);
define(BILLING_SERVICE, 1);
define(BILLING_INVOICE, 2);

/**
 * Module 'Troubletickets' for the 'ke_troubletickets' extension.
 *
 * @author    Christian Bülter <buelter@kennziffer.com>
 * @package    TYPO3
 * @subpackage    tx_ketroubletickets
 */
class  tx_ketroubletickets_module1 extends t3lib_SCbase {
                var $pageinfo;

				// Tables
				protected $tickets_table       = 'tx_ketroubletickets_tickets';
				protected $category_table      = 'tx_ketroubletickets_categories';
				protected $users_table         = 'fe_users';
				protected $address_table       = 'tt_address';
				protected $pages_table         = 'pages';
				protected $tickethistory_table = 'tx_ketroubletickets_history';

                /**
                 * Initializes the Module
                 * @return    void
                 */
                function init()    {/*{{{*/
                    global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

                    parent::init();

					$this->enableFields_tickets     = t3lib_BEfunc::deleteClause($this->tickets_table).t3lib_BEfunc::BEenableFields($this->tickets_table);
					$this->enableFields_fe_users    = t3lib_BEfunc::deleteClause($this->users_table).t3lib_BEfunc::BEenableFields($this->users_table);
					$this->enableFields_category    = t3lib_BEfunc::deleteClause($this->category_table).t3lib_BEfunc::BEenableFields($this->category_table);
					$this->enableFields_address     = t3lib_BEfunc::deleteClause($this->address_table).t3lib_BEfunc::BEenableFields($this->address_table);
					$this->enableFields_pages       = t3lib_BEfunc::deleteClause($this->pages_table).t3lib_BEfunc::BEenableFields($this->pages_table);

					$this->lib = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_ketroubletickets_lib');

					// get the page ts config
					$this->pageTSConfig = t3lib_BEfunc::getPagesTSconfig($this->id);

                    /*
                    if (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('clear_all_cache'))    {
                        $this->include_once[] = PATH_t3lib.'class.t3lib_tcemain.php';
                    }
                    */
                }/*}}}*/

                /**
                 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
                 *
                 * @return    void
                 */
                function menuConfig()    {/*{{{*/
                    global $LANG;
                    $this->MOD_MENU = Array (
                        'function' => Array (
                            '1' => $LANG->getLL('function1'),
                            '2' => $LANG->getLL('function2'),
                            '3' => $LANG->getLL('function3'),
                        )
                    );
                    parent::menuConfig();
                }/*}}}*/

				/**
				 * pidWhereClause
				 *
				 * generate a whereClause for the field pid, include the current page and all first level subpages (no recursion)
				 *
				 * @param string $field
				 * @access public
				 * @return string
				 */
				function pidWhereClause($field='') {/*{{{*/
					$pageRecord = t3lib_BEfunc::getRecord($this->pages_table, $this->id);
					$subPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,title', $this->pages_table, 'pid=' . $pageRecord['uid']);
					$uidList = strval($this->id);
					if (count($subPages)) {
						foreach ($subPages as $pageRecord) {
							$uidList .= ',' . $pageRecord['uid'];
						}
					}
					return $field . ' IN (' . $uidList . ')';
				}/*}}}*/

                /**
                 * Main function of the module. Write the content to $this->content
				 * If you chose "web" as main module, you will need to consider
				 * the $this->id parameter which will contain the uid-number of
				 * the page clicked in the page tree
                 *
                 * @return    [type]        ...
                 */
                function main()    {/*{{{*/
                    global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

                    // Access check!
                    // The page will show only if there is a valid page and if this page may be viewed by the user
                    $this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
                    $access = is_array($this->pageinfo) ? 1 : 0;

                    if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))    {

                            // Draw the header.
                        $this->doc = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
                        $this->doc->backPath = $BACK_PATH;
                        //$this->doc->form='<form action="" method="POST">';

                            // JavaScript
						/*
                        $this->doc->JScode = '
                            <script language="javascript" type="text/javascript">
                                script_ended = 0;
                                function jumpToUrl(URL)    {
                                    document.location = URL;
                                }
                            </script>
                        ';
                        $this->doc->postCode='
                            <script language="javascript" type="text/javascript">
                                script_ended = 1;
                                if (top.fsMod) top.fsMod.recentIds["web"] = 0;
                            </script>
                        ';
						*/

						// initialize tab menu
						$this->tabmenu = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('backendMenu');
						$this->doc->inDocStylesArray['tab_menu'] = $this->tabmenu->getStyleSheet();

						// Add CSS for the ticket table
						$this->doc->inDocStylesArray['tables'] = $this->getTableCSS();

                        $headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath']).'<br />'.$LANG->sL('LLL:EXT:lang/locallang_core.xml:labels.path').': '.\TYPO3\CMS\Core\Utility\GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'],-50);

						// Add css
						$this->doc->inDocStyles = 'div.typo3-mediumDoc { width:100%; }';

                        $this->content.=$this->doc->startPage($LANG->getLL('title'));
                        $this->content.=$this->doc->header($LANG->getLL('title'));
                        $this->content.=$this->doc->spacer(5);
                        //$this->content.=$this->doc->section('',$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function'])));
                        $this->content.=$this->doc->divider(5);

						// date2cal is needed for the time selector fields
						if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('date2cal')) {
							$this->tabmenu->useDate2Cal = true;
							include_once(\TYPO3\CMS\Core\Utility\GeneralUtility::resolveBackPath($BACK_PATH . '../' . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath('date2cal') . 'src/class.jscalendar.php'));
						}

						// Render content:
						$this->moduleContent();

                        // ShortCut
                        if ($BE_USER->mayMakeShortcut())    {
                            $this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
                        }

                        $this->content.=$this->doc->spacer(10);
                    } else {
                            // If no access or if ID == zero

                        $this->doc = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
                        $this->doc->backPath = $BACK_PATH;

                        $this->content.=$this->doc->startPage($LANG->getLL('title'));
                        $this->content.=$this->doc->header($LANG->getLL('title'));
                        $this->content.=$this->doc->spacer(5);
                        $this->content.=$this->doc->spacer(10);
                    }
                }/*}}}*/

                /**
                 * Prints out the module HTML
                 *
                 * @return    void
                 */
                function printContent()    {/*{{{*/

                    $this->content.=$this->doc->endPage();
                    echo $this->content;
                }/*}}}*/

				/**
				 * initTheMenus
				 *
				 * @access public
				 * @return void
				 */
				function initTheMenus() {/*{{{*/
					$this->tabmenu->initMenu('status',STATUS_ALL);
					$this->tabmenu->initMenu('billing',BILLING_ALL);
					$this->tabmenu->initMenu('owner',0);
					$this->tabmenu->initMenu('responsible_feuser',0);
					$this->tabmenu->initMenu('category',0);
					$this->tabmenu->initMenu('connected_custom','');
					$this->tabmenu->initMenu('connected_feuser',0);
					$this->tabmenu->initMenu('connected_addresses',0);
					// prefilled value for "from" is a month ago
					//$timestamp = time() - 30 * 24 * 60 * 60;
					//$this->tabmenu->initMenu('from_date',date('d-m-Y', $timestamp));
					$this->tabmenu->initMenu('from_date','');
					$this->tabmenu->initMenu('to_date','');
					$this->tabmenu->initMenu('from_date_closing_time','');
					$this->tabmenu->initMenu('to_date_closing_time','');
					$this->tabmenu->initMenu('entriesperpage',20);
					$this->tabmenu->initMenu('page',1);
				}/*}}}*/

				/**
				 * renderThePagebrowser
				 *
				 * @access public
				 * @return void
				 */
				function renderThePagebrowser() {/*{{{*/
					$content = '';
					$valueArray = array(0 => $GLOBALS['LANG']->getLL('all_entries'), 10 => '10', 20 => '20', 30 => '30', 50 => '50', 100 => '100', 200 => '200');
					$content .= $this->tabmenu->generateDropDownMenu($valueArray,'entriesperpage','&page=1');
					unset($valueArray);

					if (intval($this->tabmenu->getSelectedValue('entriesperpage')) > 0) {
						$numberOfPages = round($this->numberOfTickets / intval($this->tabmenu->getSelectedValue('entriesperpage')));
						$numberOfPages = $numberOfPages ? $numberOfPages : 1;
						for ($i = 1; $i<=$numberOfPages ; $i++) {
							$valueArray[$i] = $GLOBALS['LANG']->getLL('page') . ' ' . $i;
						}
						$content .= $this->tabmenu->generateDropDownMenu($valueArray,'page');
					}
					$content .= '<div style="clear:both;">&nbsp;</div>';
					return $content;
				}/*}}}*/

				/**
				 * renderTheMenus
				 *
				 * Generate the filter Dropdowns and Time-Selector-Fields
				 *
				 * @access public
				 * @return void
				 */
				function renderTheMenus() {/*{{{*/
					$content = '';

					/***********************************************
					 * Status
					 ***********************************************/
					// make valueArray
					$valueArray = array(
						STATUS_ALL => $GLOBALS['LANG']->getLL('status_all'),
						STATUS_CURRENT => $GLOBALS['LANG']->getLL('status_current'),
						STATUS_CLOSED => $GLOBALS['LANG']->getLL('status_closed'),
						STATUS_NOT_CLOSED => $GLOBALS['LANG']->getLL('status_not_closed'),
						STATUS_OFFER => $GLOBALS['LANG']->getLL('status_offer')
						);

					// More status filters, configured in Page-TS
					// you may use this feature to use the same status options
					// as a filter you defined for the frontend in your
					// TS-Template here in the backend module
					// example for page page TS-Config:
					// ke_troubletickets.mod1.filter.billing.my-status-key = Label for my Status key
					if (is_array($this->pageTSConfig['ke_troubletickets.']['mod1.']['filter.']['status.'])) {
						$additionalFilters = $this->pageTSConfig['ke_troubletickets.']['mod1.']['filter.']['status.'];
						foreach ($additionalFilters as $key => $label) {
							$valueArray[$key] = $label;
						}
					}

					$content .= $this->tabmenu->generateDropDownMenu($valueArray,'status');
					unset($valueArray);

					/***********************************************
					 * Billing
					 ***********************************************/
					// make valueArray
					$valueArray = array(
						BILLING_ALL => $GLOBALS['LANG']->getLL('billing_all'),
						BILLING_SERVICE => $GLOBALS['LANG']->getLL('billing_service'),
						BILLING_INVOICE => $GLOBALS['LANG']->getLL('billing_invoice'),
						);

					// More billing filters, configured in Page-TS
					if (is_array($this->pageTSConfig['ke_troubletickets.']['mod1.']['filter.']['billing.'])) {
						$additionalFilters = $this->pageTSConfig['ke_troubletickets.']['mod1.']['filter.']['billing.'];
						foreach ($additionalFilters as $key => $label) {
							$valueArray[$key] = $label;
						}
					}

					$content .= $this->tabmenu->generateDropDownMenu($valueArray,'billing');
					unset($valueArray);

					/***********************************************
					 * Categories
					 ***********************************************/
					//$whereClause = 'pid = '.$this->id ;
					$whereClause = $this->pidWhereClause('pid');
					$rows =  $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $this->category_table, $whereClause . $this->enableFields_category);

					// make valueArray
					$valueArray = array();
					$valueArray[0] = $GLOBALS['LANG']->getLL('all_categories');
					if (sizeof($rows)) {
						foreach ($rows as $key => $row) {
							$valueArray[$row['uid']] = $row['title'];
						}
					}

					$content .= $this->tabmenu->generateDropDownMenu($valueArray,'category');
					unset($valueArray);
					unset($rows);

					$content .= '<div style="clear:both;">&nbsp;</div>';

					/***********************************************
					 * Owners
					 ***********************************************/
					// find the fe_users which have tickets on this page
					$selectFields = $this->users_table.'.uid, ' . $this->users_table . '.username, ' . $this->users_table.'.name';
					//$whereClause = $this->tickets_table.'.pid = ' . $this->id . ' AND ' . $this->users_table.'.uid = ' . $this->tickets_table.'.owner_feuser';
					$whereClause = $this->pidWhereClause($this->tickets_table.'.pid') . ' AND ' . $this->users_table.'.uid = ' . $this->tickets_table.'.owner_feuser';
					$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($selectFields,
						$this->tickets_table.','.$this->users_table ,
						$whereClause . $this->enableFields_tickets . $this->enableFields_fe_users
					);

					// make valueArray
					$valueArray = array();
					$valueArray[0] = $GLOBALS['LANG']->getLL('all_owners');
					if (sizeof($rows)) {
						foreach ($rows as $key => $row) {
							$valueArray[$row['uid']] = $row['name'] . ' (' . $row['username'] . ')';
						}
					}

					$content .= $this->tabmenu->generateDropDownMenu($valueArray,'owner','',4);
					unset($valueArray);
					unset($rows);

					/***********************************************
					 * Responsible
					 ***********************************************/
					// find the responsible fe_users which have tickets on this page
					$selectFields = $this->users_table.'.uid, ' . $this->users_table . '.username, ' . $this->users_table.'.name';
					//$whereClause = $this->tickets_table.'.pid = ' . $this->id . ' AND ' . $this->users_table.'.uid = ' . $this->tickets_table.'.owner_feuser';
					$whereClause = $this->pidWhereClause($this->tickets_table.'.pid') . ' AND ' . $this->users_table.'.uid = ' . $this->tickets_table.'.responsible_feuser';
					$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($selectFields,
						$this->tickets_table.','.$this->users_table ,
						$whereClause . $this->enableFields_tickets . $this->enableFields_fe_users
					);

					// make valueArray
					$valueArray = array();
					$valueArray[0] = $GLOBALS['LANG']->getLL('all_responsible_users');
					if (sizeof($rows)) {
						foreach ($rows as $key => $row) {
							$valueArray[$row['uid']] = $row['name'] . ' (' . $row['username'] . ')';
						}
					}

					$content .= $this->tabmenu->generateDropDownMenu($valueArray,'responsible_feuser','',4);
					unset($valueArray);
					unset($rows);

					/***********************************************
					 * Time Range
					 ***********************************************/
					$content .= '<div style="clear:both;">&nbsp;</div>';
					$content .= $this->tabmenu->generateTimeInputField('from_date');
					$content .= $this->tabmenu->generateTimeInputField('to_date');
					$content .= '<div style="clear:both;">&nbsp;</div>';

					$content .= '<div style="clear:both;">&nbsp;</div>';
					$content .= $this->tabmenu->generateTimeInputField('from_date_closing_time');
					$content .= $this->tabmenu->generateTimeInputField('to_date_closing_time');
					$content .= '<div style="clear:both;">&nbsp;</div>';

					return $content;
				}/*}}}*/

				/**
				 * renderTheTicketList
				 *
				 * @access public
				 * @return string
				 */
				function renderTheTicketList() {/*{{{*/
					$content = '';

					// compile the base query
					//$whereClause = $this->tickets_table . '.pid=' . $this->id;
					$whereClause = $this->pidWhereClause($this->tickets_table . '.pid');
					$whereClause .= ' AND ' . $this->tickets_table . '.owner_feuser='. $this->users_table . '.uid';
					$whereClause .= ' AND ' . $this->tickets_table . '.category='. $this->category_table . '.uid';

					// add the filters
					if ($this->tabmenu->getSelectedValue('owner')) {
						$whereClause .= ' AND ' . $this->tickets_table . '.owner_feuser IN (' . $this->tabmenu->getSelectedValue('owner') . ')';
					}

					if ($this->tabmenu->getSelectedValue('responsible_feuser')) {
						$whereClause .= ' AND ' . $this->tickets_table . '.responsible_feuser IN (' . $this->tabmenu->getSelectedValue('responsible_feuser') . ')';
					}

					if ($this->tabmenu->getSelectedValue('status') == STATUS_NOT_CLOSED) {
						$whereClause .= ' AND ' . $this->tickets_table . '.status NOT LIKE "closed%"';
					}

					if ($this->tabmenu->getSelectedValue('status') == STATUS_CURRENT) {
						$whereClause .= ' AND (';
						$whereClause .= $this->tickets_table . '.status="open"';
						$whereClause .= ' OR ' . $this->tickets_table . '.status="working"';
						$whereClause .= ' OR ' . $this->tickets_table . '.status="acknowledged"';
						$whereClause .= ' OR ' . $this->tickets_table . '.status="wait"';
						$whereClause .= ')';
					}

					if ($this->tabmenu->getSelectedValue('status') == STATUS_OFFER) {
						$whereClause .= ' AND ' . $this->tickets_table . '.status="offer"';
					}

					if ($this->tabmenu->getSelectedValue('status') == STATUS_CLOSED) {
						$whereClause .= ' AND ' . $this->tickets_table . '.status="closed"';
					}

					// More status filters, configured in Page-TS
					if (is_array($this->pageTSConfig['ke_troubletickets.']['mod1.']['filter.']['status.'])) {
						$additionalFilters = $this->pageTSConfig['ke_troubletickets.']['mod1.']['filter.']['status.'];
						foreach ($additionalFilters as $key => $label) {
							if ($this->tabmenu->getSelectedValue('status') == $key) {
								$whereClause .= ' AND ' . $this->tickets_table . '.status="' . $key . '"';
							}
						}
					}

					if ($this->tabmenu->getSelectedValue('billing') == BILLING_SERVICE) {
						$whereClause .= ' AND ' . $this->tickets_table . '.billing LIKE "service%"';
					}

					if ($this->tabmenu->getSelectedValue('billing') == BILLING_INVOICE) {
						$whereClause .= ' AND ' . $this->tickets_table . '.billing LIKE "invoice%"';
					}

					// More billing filters, configured in Page-TS
					if (is_array($this->pageTSConfig['ke_troubletickets.']['mod1.']['filter.']['billing.'])) {
						$additionalFilters = $this->pageTSConfig['ke_troubletickets.']['mod1.']['filter.']['billing.'];
						foreach ($additionalFilters as $key => $label) {
							if ($this->tabmenu->getSelectedValue('billing') == $key) {
								$whereClause .= ' AND ' . $this->tickets_table . '.billing="' . $key . '"';
							}
						}
					}

					if (intval($this->tabmenu->getSelectedValue('category')) > 0) {
						$whereClause .= ' AND ' . $this->tickets_table . '.category=' . intval($this->tabmenu->getSelectedValue('category'));
					}

					if (intval($this->tabmenu->getSelectedValue('connected_custom')) != '') {
						$whereClause .= ' AND ' . $this->tickets_table . '.connected_custom=' . $this->tabmenu->getSelectedValue('connected_custom');
					}

					if (intval($this->tabmenu->getSelectedValue('connected_feuser')) > 0) {
						$whereClause .= ' AND ' . $this->tickets_table . '.connected_feuser=' . intval($this->tabmenu->getSelectedValue('connected_feuser'));
					}

					if (intval($this->tabmenu->getSelectedValue('connected_addresses')) > 0) {
						$whereClause .= ' AND ' . $this->tickets_table . '.connected_addresses=' . intval($this->tabmenu->getSelectedValue('connected_addresses'));
					}

					if ($this->tabmenu->getSelectedValue('from_date') != '') {
						$timestamp_from = strtotime($this->tabmenu->getSelectedValue('from_date'));
						$whereClause .= ' AND ' . $this->tickets_table . '.crdate >= ' . $timestamp_from;
					}

					if ($this->tabmenu->getSelectedValue('to_date') != '') {
						$timestamp_to = strtotime($this->tabmenu->getSelectedValue('to_date'));
						// add one day
						$timestamp_to += 24 * 60 * 60;
						$whereClause .= ' AND ' . $this->tickets_table . '.crdate <= ' . $timestamp_to;
					}

					if ($this->tabmenu->getSelectedValue('from_date_closing_time') != '') {
						$timestamp_from = strtotime($this->tabmenu->getSelectedValue('from_date_closing_time'));
						$whereClause .= ' AND ' . $this->tickets_table . '.close_time >= ' . $timestamp_from;
					}

					if ($this->tabmenu->getSelectedValue('to_date_closing_time') != '') {
						$timestamp_to = strtotime($this->tabmenu->getSelectedValue('to_date_closing_time'));
						// add one day
						$timestamp_to += 24 * 60 * 60;
						$whereClause .= ' AND ' . $this->tickets_table . '.close_time <= ' . $timestamp_to;
					}

					if (isset($timestamp_from) && isset($timestamp_to) && $timestamp_from >= $timestamp_to) {
						$content .= $GLOBALS['LANG']->getLL('error_from_greater_to') . '<br />';
					}

					// add enableFields to the query
					$whereClause .= $this->enableFields_tickets;
					$whereClause .= $this->enableFields_fe_users;

					// orderBy
					$orderBy = $this->tickets_table . '.crdate DESC';

					// groupBy
					$groupBy = '';

					// limit
					if (intval($this->tabmenu->getSelectedValue('entriesperpage')) > 0) {
						$limit = intval($this->tabmenu->getSelectedValue('entriesperpage'));
						if (intval($this->tabmenu->getSelectedValue('page'))) {
							$limit = ((intval($this->tabmenu->getSelectedValue('page')) - 1) * $limit) . ',' . $limit;
						}
					}

					$fieldList_DB =
						$this->tickets_table.'.uid,' .
						$this->tickets_table.'.title,' .
						$this->tickets_table.'.crdate,' .
						$this->tickets_table.'.close_time,' .
						$this->tickets_table.'.until_date,' .
						$this->tickets_table.'.billing,' .
						$this->tickets_table.'.priority,' .
						$this->tickets_table.'.time_used,' .
						$this->tickets_table.'.time_planned,' .
						$this->tickets_table.'.effort,' .
						$this->tickets_table.'.status,' .
						$this->tickets_table.'.responsible_feuser,' .
						$this->users_table.'.username AS owner_feuser,' .
						$this->category_table.'.title AS cat_title';

					$fieldList_TABLE = array(
							'uid' => 'text',
							'crdate' => 'day',
							//'until_date' => 'day',
							'title' => 'text',
							'status' => 'text',
							'billing' => 'text',
							'cat_title' => 'text',
							'owner_feuser' => 'text',
							'responsible_feuser' => 'feuser_uid',
							//'priority' => 'text',
							'reaction_time' => 'reaction_time',
							'close_time' => 'close_time',
							'time_planned' => 'hours_from_minutes',
							'time_used' => 'hours_from_minutes',
							'effort' => 'float_from_text'
					);

					$tableList = $this->tickets_table . ',' . $this->users_table . ',' . $this->category_table;

					//$GLOBALS['TYPO3_DB']->debugOutput = true;
					$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
						$fieldList_DB,
						$tableList,
						$whereClause,
						$groupBy,
						$orderBy,
						$limit
					);

					// Gesamtzahl der Tickets (ohne Limit) ermitteln
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($this->tickets_table . '.uid',$tableList,$whereClause);
					$this->numberOfTickets = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

					//debug($GLOBALS['TYPO3_DB']->SELECTquery($fieldList_DB, $tableList, $whereClause, $groupBy, $orderBy, $limit) );

					//if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
					if (is_array($rows) && count($rows)) {
						$tabletitle = $GLOBALS['LANG']->getLL('table_title') ;
						if ($limit) {
							if ($limit < $this->numberOfTickets ) {
								$limitArray = explode(',',$limit);
								$limitArray[0]++;
								$limitArray[1] = $limitArray[1] * intval($this->tabmenu->getSelectedValue('page'));
								$limitArray[1] = $limitArray[1] <= $this->numberOfTickets ? $limitArray[1] : $this->numberOfTickets;
								$limit_cleartext = $limitArray[0] . '-' . $limitArray[1];
							} else {
								$limit_cleartext = $this->numberOfTickets;
							}
							$tabletitle .= ' (' . $limit_cleartext . '/' . $this->numberOfTickets . ')';
						} else {
							$tabletitle .= ' (' . $this->numberOfTickets . ')';
						}
						$content .= $this->renderTable($tabletitle, $fieldList_TABLE, $rows, 'no_line_numbers', 'time_used,time_planned,reaction_time,effort' ,'');
					} else {
						$content .= $GLOBALS['LANG']->getLL('error_no_results');
					}

					return $content;
				}/*}}}*/

                /**
                 * Generates the module content
                 *
                 * @return    void
                 */
                function moduleContent()    {/*{{{*/
					$content = '';

					// are there any tickets on the selected page?
					//$where_clause = $this->tickets_table.'.pid = '.$this->id ;
					$where_clause = $this->pidWhereClause($this->tickets_table . '.pid');
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->tickets_table , $where_clause.$this->enableFields_tickets );
					if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
						$content = '<b> ' . $GLOBALS['LANG']->getLL('select_tickets_page'). '</b>';
					} else {
						$this->initTheMenus();

						// Filter menus
						$content .= $this->renderTheMenus();

						// Ticket list
						$content .= $this->renderTheTicketList();

						// Pagebrowser
						$content .= $this->renderThePagebrowser();

						// Additional Content
						$content .= '<em>' . $GLOBALS['LANG']->getLL('explanation') . '</em>';
					}

					$this->content.=$this->doc->section('',$content,0,1);

					/*
					$this->content.=$this->doc->section($GLOBALS['LANG']->getLL('title'),$content,0,1);
                    switch((string)$this->MOD_SETTINGS['function'])    {
                        case 1:
                            $content='<div align="center"><strong>Hello World!</strong></div><br />
                                The "Kickstarter" has made this module automatically, it contains a default framework for a backend module but apart from that it does nothing useful until you open the script '.substr(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('ke_troubletickets'),strlen(PATH_site)).$pathSuffix.'index.php and edit it!
                                <hr />
                                <br />This is the GET/POST vars sent to the script:<br />'.
                                'GET:'.t3lib_utility_Debug::viewArray($_GET).'<br />'.
                                'POST:'.t3lib_utility_Debug::viewArray($_POST).'<br />'.
                                '';
                            $this->content.=$this->doc->section($GLOBALS['LANG']->getLL('title'),$content,0,1);
                        break;
                        case 2:
                            $content='<div align=center><strong>Menu item #2...</strong></div>';
                            $this->content.=$this->doc->section('Message #2:',$content,0,1);
                        break;
                        case 3:
                            $content='<div align=center><strong>Menu item #3...</strong></div>';
                            $this->content.=$this->doc->section('Message #3:',$content,0,1);
                        break;
                    }
					 */
                }/*}}}*/

				/**
				 * returns a html table, rendered from the array $dataRows.
				 * $dataRows must contains one row for each row in the table.
				 * Each row is an array associative containing the data for the row.
				 *
				 * @param string $caption: Table-caption
				 * @param string $columns: array containing fields (the key) and the corresponding field types (the value) to display
				 * @param array $dataRows: data array
				 * @param string $columnsWithSum: name of the column for which a sum shall be calculated
				 * @param string $columnWithPercent: name of the column for which a sum shall be calculated
				 * @param string $special: special rendering options
				 * @return string
				 */
				function renderTable($caption='Table',$columns=array('uid'=>'integer'),$dataRows=array(),$special='',$columnsWithSum='',$columnWithPercent='') {/*{{{*/
					$content = '';

					$sumRow = array();
					// initialize the sum array
					if (count($dataRows) > 0) {
						foreach ($dataRows as $label => $dataRow) {
							foreach ($columns as $key => $fieldType) {
								if (!isset($sumRow[$key])) {
									$sumRow[$key] = 0;
								}
								//$sumRow[$key] += intval($dataRow[$key]);
							}
						}
					}

					// how many data columns will we have?
					if (count($dataRows) > 0) {
						reset($dataRows);
						$numberOfDataColumns = sizeof(current($dataRows));
						// add one for the percentage column
						if (!empty($columnWithPercent)) {
							$numberOfDataColumns += sizeof($columnWithPercent);
						}
					}

					// render table
					$content .= '<table class="ke-troubletickets-table" summary="'.$caption.'">';
					$content .= '<caption>'.$caption.'</caption>';

					// render the head
					$content .= '<thead>';
					$content .= '<tr>';
					// first we render a line number column
					if (!strstr($special,'no_line_numbers')) {
						$content .= '<th>'.$GLOBALS['LANG']->getLL('header_line_number').'</th>';
					}

					// render a header column for each data column
					foreach ($columns as $key => $fieldType) {
						$content .= '<th>'.$GLOBALS['LANG']->getLL('header_'.$key).'</th>';
					}
					/*
					// TODO: fix percent column
					if (!empty($columnWithPercent)) {
						for ($column_number=0; $column_number<$numberOfDataColumns; $column_number++) {
							if ($columns[$column_number-1] == $columnWithPercent) {
								$content .= '<th>'.$GLOBALS['LANG']->getLL('header_percent').'</th>';
							}
						}
					}
					*/
					$content .= '</tr>';
					$content .= '</thead>';
					$oddRow=0;

					// print the data rows
					if (count($dataRows) > 0) {
						$content .= '<tbody>';
						foreach ($dataRows as $key => $dataRow) {
							$content .= '<tr';
							if ($oddRow) {
								$content .= ' class="odd"';
							}
							$content .= '>';
							$oddRow = 1-$oddRow;
							$column_number = 0;
							// print the line number (which is the key in the data array)
							if (!strstr($special,'no_line_numbers')) {
								$content .= '<td>'.$key.'</td>';
							}
							foreach ($columns as $key => $fieldType) {

								switch ($fieldType) {
									case 'integer':
										// number format for integer fields
										$formatted_data = $dataRow[$key];
										if (strval(intval($formatted_data)) == $formatted_data) {
											$formatted_data = number_format(intval($formatted_data),0,'.',' ');
										}
									break;

									case 'day':
										if (intval($dataRow[$key])) {
											$formatted_data = date('d.m.y', intval($dataRow[$key]));
										}
									break;

									case 'feuser_uid':
										$formatted_data = $this->lib->getNameListFromUidList($dataRow[$key], $this->users_table, 'username,name,email');
									break;

									case 'hours_from_minutes':
										if (intval($dataRow[$key])) {
											$formatted_data = number_format($dataRow[$key] / 60, 2, ',', ' ');
										}
									break;

									case 'float_from_text':
										$formatted_data = floatval(str_replace(',', '.', $dataRow[$key]));
									break;

									case 'reaction_time':
										// "reaction time" ist the time between
										// opening the ticket an the first
										// change of the responsible user
										if (intval($dataRow['crdate'])) {

											$from = $dataRow['crdate'];

											// get the last change the responsible user made for this ticket
											// rows where fields where "databasefield" is empty are "opening tickets" changes
											$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
												'*',
												$this->tickethistory_table,
												'ticket_uid=' . $dataRow['uid'] . ' AND databasefield!=""',
												'',
												'crdate',
												'1'
											);

											if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
												$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
												$time = $this->daysAndHoursFromSeconds($row['crdate'] - $from);
												$dataRow[$key] = $row['crdate'] - $from;
												$title = $row['databasefield'] . ': ' . $row['value_old'] . '->' . $row['value_new'];
												$title = htmlspecialchars(strip_tags($title), ENT_QUOTES);
												$formatted_data = '<a class="tooltiplink" href="#" alt="' . $title . '" title="' . $title . '">';
												$formatted_data .= $time;
												$formatted_data .= '</a>';

												// debug
												/*
												$formatted_data .= '<br />';
												$formatted_data .= date('d.m.y H:i', intval($dataRow['crdate']));
												$formatted_data .= '-';
												$formatted_data .= date('d.m.y H:i', intval($row['crdate']));
												*/
											}

											// History-Fields
											/*
											   uid   	int(11)
											   pid  	int(11)
											   tstamp  	int(11)
											   crdate  	int(11)
											   cruser_id  	int(11)
											   ticket_uid  	blob
											   feuser_uid  	blob
											   databasefield  	tinytext
											   feuser_username  	tinytext
											   value_old  	text
											   value_new  	text
											 */
										}
									break;

									case 'close_time':
										if (intval($dataRow['close_time']) && intval($dataRow['crdate'])) {
											$formatted_data = date('d.m.y', $dataRow['close_time']);
											$time_to_solution = intval($dataRow['close_time']) - intval($dataRow['crdate']);
											$formatted_data .= '<br />' . $this->daysAndHoursFromSeconds($time_to_solution);
											// put the diffenrence into the $dataRow-field for sum and avarage calculating
											$dataRow['close_time'] = $time_to_solution;
										}
									break;

									default:
										$formatted_data = $dataRow[$key];
										// sanitize the output
										$formatted_data = htmlspecialchars($formatted_data, ENT_QUOTES);
									break;
								}

								// empty values
								if (empty($formatted_data)) {
									$formatted_data = '<span style="color:#cccccc;">' . $GLOBALS['LANG']->getLL('empty_value') . '</span>';
								} else {
									// calculate the sum
									if (in_array($key, \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $columnsWithSum))) {
										if (stristr($dataRow[$key], ',')) {
											$sumRow[$key] += floatval(str_replace(',', '.', $dataRow[$key]));
										} else {
											$sumRow[$key] += floatval($dataRow[$key]);
										}
									}
								}

								// add the data to the output
								$content .= '<td>'.$formatted_data.'</td>';

								unset($formatted_data);
							}

							// render the percent column
							/*
							// TODO: fix percent column
							if ($columnsArray[$column_number] == $columnWithPercent) {
								if (!empty($sumRow[$column_number])) {
									$percent = round(100 * intval($data) / $sumRow[$column_number],1);
								} else {
									$percent = '-';
								}
								$content.='<td>'.$percent.' %</td>';
							}
							*/
							$column_number++;
						}
						$content .= '</tr>';
					}
					$content .= '</tbody>';
					$content .= '<tfoot>';

					// make the sum row
					if (strlen($columnsWithSum) > 0) {
						$content .= '<tr>';
						$firstCol = true;
						foreach ($columns as $key => $fieldType) {
							// write "sum" into the first column, so the first column cannot be a sum column
							if ($firstCol) {
								$content .= '<th>'.$GLOBALS['LANG']->getLL('sum').'</th>';
								$firstCol = false;
							} else {
								if (in_array($key, \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',',$columnsWithSum))) {
									$sum = $sumRow[$key];
									switch ($fieldType) {
										case 'hours_from_minutes':
											if (intval($sum)) {
												$sum = number_format($sum / 60, 2, ',', ' ');
											}
											break;
										case 'reaction_time':
											$sum = $this->daysAndHoursFromSeconds($sum);
										break;

										default:
											$formatted_data = $sum;
											break;
									}
									$content .= '<td>'.$sum.'</td>';
								} else {
									$content .= '<td>&nbsp;</td>';
								}
							}
						}

						// make the avarage row
						if (count($dataRows)) {
							$content .= '<tr>';
							$firstCol = true;
							foreach ($columns as $key => $fieldType) {
								// write "average" into the first column, so the first column cannot be a sum column
								if ($firstCol) {
									$content .= '<th>'.$GLOBALS['LANG']->getLL('average').'</th>';
									$firstCol = false;
								} else {
									if (in_array($key, \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',',$columnsWithSum))) {
										$average = $sumRow[$key] / count($dataRows);
										switch ($fieldType) {
											case 'hours_from_minutes':
												if (floatval($average) > 0) {
													$average = number_format($average / 60, 2, ',', ' ');
												}
												break;
											case 'reaction_time':
												$average = $this->daysAndHoursFromSeconds($average);
											break;

											default:
												$formatted_data = $average;
												break;
										}
										$content .= '<td>'.$average.'</td>';
									} else {
										$content .= '<td>&nbsp;</td>';
									}
								}
							}
						}
						$content .= '</tr>';
					}
					$content .= '</tfoot>';
					$content .= '</table>';
					return $content;
				}/*}}}*/

				/**
				 * daysAndHoursFromSeconds
				 *
				 * generates a string containing days, hours and minutes from given seconds
				 *
				 * @param integer $seconds
				 * @access public
				 * @return string
				 */
				function daysAndHoursFromSeconds($s=0) {/*{{{*/
					if (empty($s)) {
						$retval = '0';
					} else {
						$d = intval($s/86400);
						$s -= $d*86400;

						$h = intval($s/3600);
						$s -= $h*3600;

						$m = intval($s/60);
						$s -= $m*60;

						if ($d) $retval = $d . 'd ';
						if ($h) $retval .= $h . 'h ';
						if ($m) $retval .= $m . 'm ';
						//if ($s) $retval .= $s . 's';
					}
					return $retval;
				}/*}}}*/

				/*
				 * returns the css for the result tables
				 *
				 * @return string
				 */
				function getTableCSS() {/*{{{*/
						return '

				.datebox {
					margin-right:5px;
					width:210px;
					height:40px;
					padding:4px;
					border:1px solid black;
					float:left;
				}

				.buttonlink {
					float:left;
					margin: .5em 3px 0 0;
					font-size: 10px;
					font-weight: bold;
					border: 1px solid gray;
					display:block;
					padding:2px;
				}

				table.ke-troubletickets-table {
					color: #7F7F7F;
					font-size: 10px;
					border-collapse: collapse;
					text-align:left;
					clear:left;
				}

				table.ke-troubletickets-table,
				table.ke-troubletickets-table caption {
					width:100%;
					border-right: 1px solid #CCC;
					border-left: 1px solid #CCC
				}

				table.ke-troubletickets-table caption {
					margin-top: 15px;
				}

				table.ke-troubletickets-table caption,
				table.ke-troubletickets-table th,
				table.ke-troubletickets-table td {
					border-left: 0;
					padding: 1px
				}

				table.ke-troubletickets-table caption,
				table.ke-troubletickets-table thead th,
				table.ke-troubletickets-table tfoot th,
				table.ke-troubletickets-table tfoot td {
					background-color: #B7B7CC;
					color: #FFF;
					font-weight: bold;
					text-transform: uppercase;
				}

				table.ke-troubletickets-table thead th {
					background-color: #E2E2E9;
				}

				table.ke-troubletickets-table tbody th {
					padding: 4px 2px;
				}

				table.ke-troubletickets-table tbody tr.odd {
					background-color: #F7F7F7;
					color: #666;
				}

				table.ke-troubletickets-table tbody a {
					padding: 1px 2px;
					color: #333;
					text-decoration: none;
					/*border-bottom: 1px dotted #333333;*/
				}

				table.ke-troubletickets-table tbody a:active,
				table.ke-troubletickets-table tbody a:hover,
				table.ke-troubletickets-table tbody a:focus,
				table.ke-troubletickets-table tbody a:visited {
					color: #666
				}

				table.ke-troubletickets-table tbody tr:hover {
					background-color: #EEE;
					color: #333
				}

				table.ke-troubletickets-table tbody tr:hover a {
					background-color: #FFF
				}

				table.ke-troubletickets-table tbody td+td+td+td a {
					color: #C30;
					font-weight: bold;
					border-bottom: 0
				}

				table.ke-troubletickets-table tbody a.tooltiplink:link,
				table.ke-troubletickets-table tbody a.tooltiplink:visited,
				table.ke-troubletickets-table tbody a.tooltiplink:hover,
				table.ke-troubletickets-table tbody a.tooltiplink:active,
				table.ke-troubletickets-table tbody a.tooltiplink:focus
				{
					border:1px dotted #999999;
					padding:1px;
					color:#666666;
					font-weight:normal;
					background:transparent;
				}

				table.ke-troubletickets-table tbody a:visited:after {
					/*content: "\00A0\221A"*/
				}

						';
					}/*}}}*/


            }



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/mod1/index.php'])    {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_troubletickets/mod1/index.php']);
}




// Make instance:
$SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_ketroubletickets_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)    include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
