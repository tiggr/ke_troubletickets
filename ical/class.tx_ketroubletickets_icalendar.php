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
 * This class implements the iCalender functionality for ke_troubletickets.
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 */

//require_once('iCalcreator.class.php');
//error_reporting(E_ALL);
require_once('iCalendar/class.iCal.inc.php');
require_once('iCalendar/class.iCalAlarm.inc.php');
require_once('iCalendar/class.iCalBase.inc.php');
require_once('iCalendar/class.iCalEvent.inc.php');
require_once('iCalendar/class.iCalFreeBusy.inc.php');
require_once('iCalendar/class.iCalJournal.inc.php');
require_once('iCalendar/class.iCalToDo.inc.php');

/**
 * iCalendar Class für ke_troubletickets
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_ketroubletickets
 */
class tx_ketroubletickets_icalendar {
	protected $tablename = 'tx_ketroubletickets_tickets';

	protected function init() {

		// *********************
		// Libraries included
		// *********************
		require_once(PATH_tslib.'class.tslib_fe.php');
		require_once(PATH_t3lib.'class.t3lib_page.php');
		//require_once(PATH_t3lib.'class.t3lib_userauth.php');
		//require_once(PATH_tslib.'class.tslib_feuserauth.php');
		//require_once(PATH_t3lib.'class.t3lib_tstemplate.php');
		//require_once(PATH_t3lib.'class.t3lib_cs.php');

		// ***********************************
		// Create $TSFE object (TSFE = TypoScript Front End)
		// Connecting to database
		// ***********************************
		$temp_TSFEclassName = t3lib_div::makeInstanceClassName('tslib_fe');
		$TSFE = new $temp_TSFEclassName(
				$TYPO3_CONF_VARS,
				t3lib_div::_GP('id'),
				t3lib_div::_GP('type'),
				t3lib_div::_GP('no_cache'),
				t3lib_div::_GP('cHash'),
				t3lib_div::_GP('jumpurl'),
				t3lib_div::_GP('MP'),
				t3lib_div::_GP('RDCT')
			);

		// initialize the database
		$TSFE->connectToDB();

		// initialize the TCA
		$TSFE->includeTCA();

		// we neet the "page"-object
		$this->page = t3lib_div::makeInstance('t3lib_pageSelect');
	}

	public function login() {
		// Todo: use http-Authorization to identify and login the user
		// For Testing:
		$username = '';
		$password = '';

		// sanitizeData
		$username = $this->sanitizeData($username);
		$password = $this->sanitizeData($password);

		$where_clause = 'username = "' . $username . '"';
		$where_clause .= $this->page->enableFields('fe_users');
		
		// get the user data
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','fe_users',$where_clause);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$this->user = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		} else {
			exit ('ERROR: Access denied.');
		}

		// check the password
		if (md5($this->user['password']) != md5($password)) {
			unset($this->user);
			exit ('ERROR: Access denied.');
		}
	}

	protected function renderICalendarFromTicket($ticket) {
		/*
		$t = new vtodo();
		$t->setProperty( 'dtstart'
				, '19970415T133000 GMT' );
		$t->setProperty( 'due'
				, '19970416T045959 GMT' );
		$t->setProperty( 'summary'
				, '1996 Income Tax Preparation' );
		$t->setProperty( 'class'
				, 'CONFIDENTIAL' );
		$t->setProperty( 'categories'
				, 'FAMILY' );
		$t->setProperty( 'categories'
				, 'FINANCE' );
		$t->setProperty( 'priority'
				, 1 );
		$t->setProperty( 'status'
				, 'NEEDS-ACTION' );
		return $t;
		*/

		// example:
		/*
		$this->iCal->addToDo(
				'Air and Style Snowboard Contest', // Title
				'See handout for more details...', // Description
				'Vienna', // Location
				time()+3600, // Start time
				300, // Duration in minutes
				'', // End time
				45, // Percentage complete
				5, // Priority = 0-9
				1, // Status of the event (0 = TENTATIVE, 1 = CONFIRMED, 2 = CANCELLED)
				0, // Class (0 = PRIVATE | 1 = PUBLIC | 2 = CONFIDENTIAL)
				$organizer, // Organizer
				$attendees, // Array (key = attendee name, value = e-mail, second value = role of the attendee [0 = CHAIR | 1 = REQ | 2 = OPT | 3 =NON])
				$categories, // Array with Strings
				time(), // Last Modification
				$alarm, // Sets the time in minutes an alarm appears before the event in the programm. no alarm if empty string or 0
				5, // frequency: 0 = once, secoundly - yearly = 1-7
				10, // recurrency end: ('' = forever | integer = number of times | timestring = explicit date)
				1, // Interval for frequency (every 2,3,4 weeks...)
				$days, // Array with the number of the days the event accures (example: array(0,1,5) = Sunday, Monday, Friday
				0, // Startday of the Week ( 0 = Sunday - 6 = Saturday)
				'', // exeption dates: Array with timestamps of dates that should not be includes in the recurring event
				'http://example.com/', // optional URL for that event
				'de', // Language of the Strings
				'' // Optional UID for this ToDo
			);
				*/

		// db-fields:
		/*
		title tinytext NOT NULL,
		description text NOT NULL,
		until_date int(11) DEFAULT '0' NOT NULL,
		from_date int(11) DEFAULT '0' NOT NULL,
		owner_feuser blob NOT NULL,
		responsible_feuser blob NOT NULL,
		observers_feuser blob NOT NULL,
		category blob NOT NULL,
		status varchar(20) DEFAULT '' NOT NULL,
		billing varchar(20) DEFAULT '' NOT NULL,
		priority int(11) DEFAULT '0' NOT NULL,
		notifications_owner varchar(20) DEFAULT '' NOT NULL,
		notifications_responsible varchar(20) DEFAULT '' NOT NULL,
		notifications_observer varchar(20) DEFAULT '' NOT NULL,
		files blob NOT NULL,
		time_used int(11) DEFAULT '0' NOT NULL,
		time_planned int(11) DEFAULT '0' NOT NULL,
		effort tinytext NOT NULL,
		close_time int(11) DEFAULT '0' NOT NULL,
		related_tickets blob NOT NULL
		*/

		// TODO: 
		// Owner, get data from fe_users table
		$organizer = (array) array('', '');

		// TODO:
		// Observers, get data from fe_users table
		$attendees = (array) array(
				'Michi' => 'flaimo2@example.net,1',
				'Felix' => ' ,2',
				'Walter' => 'flaimo2@example.net,3'
				);  // Name => e-mail,role (see iCalEvent class)


		// TODO:
		// Categories, get from categories table
		$categories = array('Meine Kategorie 1','Meine Kategorie 2');

		// TODO:
		// set alarm
		/*
		$alarm = (array) array(
				0, // Action: 0 = DISPLAY, 1 = EMAIL, (not supported: 2 = AUDIO, 3 = PROCEDURE)
				150,  // Trigger: alarm before the event in minutes
				'Wake Up!', // Title
				'...and go shopping', // Description
				$attendees, // Array (key = attendee name, value = e-mail, second value = role of the attendee [0 = CHAIR | 1 = REQ | 2 = OPT | 3 =NON])
				5, // Duration between the alarms in minutes
				3  // How often should the alarm be repeated
				);
		*/
		$alarm = array();

		// TODO
		// set the single view url for this ticket
		$url = '';

		// Percentage complete
		$percentage = 0;

		// Language
		$language = 'de';

		// TODO
		// Add duration (planned time in minutes)
		$duration = 60;

		// Format title and description
		$title = utf8_decode(strip_tags($ticket['title']));
		$description = utf8_decode(strip_tags($ticket['description']));

		$this->iCal->addToDo(
				$title, // Title
				$description, // Description
				'', // Location
				$ticket['from_date'], // Start time
				$duration, // Duration in minutes
				'', // End time
				$percentage, // Percentage complete
				5, // Priority = 0-9
				1, // Status of the event (0 = TENTATIVE, 1 = CONFIRMED, 2 = CANCELLED)
				0, // Class (0 = PRIVATE | 1 = PUBLIC | 2 = CONFIDENTIAL)
				$organizer, // Organizer
				$attendees, // Array (key = attendee name, value = e-mail, second value = role of the attendee [0 = CHAIR | 1 = REQ | 2 = OPT | 3 =NON])
				$categories, // Array with Strings
				$ticket['tstamp'], // Last Modification
				$alarm, // Sets the time in minutes an alarm appears before the event in the programm. no alarm if empty string or 0
				0, // frequency: 0 = once, secoundly - yearly = 1-7
				'', // recurrency end: ('' = forever | integer = number of times | timestring = explicit date)
				1, // Interval for frequency (every 2,3,4 weeks...)
				array(), // Array with the number of the days the event accures (example: array(0,1,5) = Sunday, Monday, Friday
				0, // Startday of the Week ( 0 = Sunday - 6 = Saturday)
				'', // exeption dates: Array with timestamps of dates that should not be includes in the recurring event
				$url, // optional URL for that event
				$language, // Language of the Strings
				$ticket['uid'] // Optional UID for this ToDo
			);
	}

	protected function getTheTickets($userUid=0) {
		$content = '';

		// only tickets of the current user
		//$where_clause = 'owner_feuser = ' . $this->user['uid'];
		
		// only tickets the current user is responsible for
		$where_clause = 'responsible_feuser = ' . $this->user['uid'];

		// only "not closed" tickets
		$where_clause .= ' AND status NOT LIKE "closed%"';

		// enablefields
		$where_clause .= $this->page->enableFields($this->tablename);

		$groupBy = '';
		$orderBy = 'uid';
		$limit = '';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->tablename, $where_clause, $groupBy, $orderBy, $limit);

		// render the iCalendar entries
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$this->iCal = (object) new iCal('', 0, ''); // (ProgrammID, Method (1 = Publish | 0 = Request), Download Directory)

			//$c = new vcalendar ();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

				//$content .= $row['title'] . '<br />';
				//$c->setComponent( $this->renderICalendarFromTicket($row) );
				$this->renderICalendarFromTicket($row);
			}
			//return $c->createCalendar();
		}
	}

	public function main() {
		die('Experimental Feature. Disabled.')
		$this->init();
		$this->login();
		$content = $this->getTheTickets();
		$this->iCal->outputFile('ics'); // output file as ics (xcs and rdf possible)
	}

	/**
	 * sanitizeData 
	 *
	 * sanitizeData
	 * 
	 * @param string $data 
	 * @access public
	 * @return string
	 */
	function sanitizeData($data='') {/*{{{*/
		return htmlspecialchars($data, ENT_QUOTES);
	}/*}}}*/

}

/************************************************
 * Make the instance and do the output ...
 ***********************************************/ 
$icalendar = t3lib_div::makeInstance('tx_ketroubletickets_icalendar');
$icalendar->main();
?>
