<?php

/*
 * eID script for ke_troubletickets extension
 * 
 * EXAMPLES:
 * 
 * index.php?eID=kett&ticketUid=1&progressValue=10&action=updateProgress
 * index.php?eID=kett&ticketUid=1&title=test&action=addToDo
 * index.php?eID=kett&ticketUid=1&toDoUid=1&doneStatus=1&action=updateToDoStatus
 * index.php?eID=kett&ticketUid=1&toDoUid=1&action=removeToDo
 * 
 */

require_once(PATH_tslib.'class.tslib_pibase.php');

class tx_ketroubletickets_eid extends tslib_pibase {
  
	var $ticketUid;
	var $storagePid;
	
	/*
	 * main method for eID script
	 */
	function main(){
		// Initialize FE user object
		$this->feUserObj = tslib_eidtools::initFeUser(); 
		
		//Connect to database
		tslib_eidtools::connectDB(); 

		// sanitize params
		$this->ticketUid = intval(t3lib_div::_GP('ticketUid'));
		if (!$this->ticketUid) die();
		$this->storagePid = intval(t3lib_div::_GP('storagePid'));
		$toDoUid = intval(t3lib_div::_GP('toDoUid'));
		$progressValue = intval(t3lib_div::_GP('progressValue'));
		$title = t3lib_div::removeXSS(t3lib_div::_GP('title'));
		$doneStatus = intval(t3lib_div::_GP('doneStatus'));
		$sorting = t3lib_div::_GP('sorting');
		
		// check user's permissions
		// exit if user has no permission for this ticket
		if (!$this->checkPermission()) exit;

		// switch actions
		switch (t3lib_div::_GP('action')) {
			case 'getToDos':
				echo json_encode($this->getToDos());
				break;
			case 'updateProgress';
				$this->setProgress($progressValue);
				break;
			case 'addToDo':
				$result = $this->addToDo($title, $storagePid);
				if ($result) echo json_encode($result);
				break;
			case 'updateToDoStatus':
				echo json_encode($this->updateToDoStatus($toDoUid, $doneStatus));
				break;
			case 'removeToDo':
				echo json_encode($this->removeToDo($toDoUid));
				break;
			case 'calculateTicketProgress':
				echo json_encode($this->calculateTicketProgress());
				break;
			case 'updateSorting':
				echo json_encode($this->updateSorting($sorting));
				break;
		}
	}
	
	/*
	 * get all todos for a ticket
	 */
	function getToDos() {
		$table = 'tx_ketroubletickets_todo';
		$fields = '*';
		$where = 'ticket_uid=' . $this->ticketUid;
		$where .= t3lib_BEfunc::BEenableFields($table);
		$where .= t3lib_befunc::deleteClause($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', 'sorting, uid');
		$results = array();
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$results[] = $row;
		}
		return $results;
	}
	
	
	/*
	 * add new todo to ticket
	 * 
	 * @param string $title				title of the todo
	 * @param int $storagePid			storage pid
	 */
	function addToDo($title, $storagePid) {
		// check values
		if (!$this->ticketUid || empty($title)) return false;
		
		// sanitize values
		$title = t3lib_div::removeXSS($title);
		
		// add ToDo
		$table = 'tx_ketroubletickets_todo';
		$fields_values = array(
			'tstamp' => time(),
			'ticket_uid' => $this->ticketUid,
			'title' => $title,
			'pid' => intval($storagePid),
			'sorting' => $this->getMaxSorting() + 10,
		);
		if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $fields_values)) {
			$result['uid'] = $GLOBALS['TYPO3_DB']->sql_insert_id();
			$result['progress'] = $this->calculateTicketProgress();
			$this->setProgress($this->ticketUid, $result['progress']);
			$this->addHistoryEntry($title, 'new', 0, $this->storagePid);
			return $result;
		}
		else return false;
	}
	
	/*
	 * get max sorting value for todos to current ticket
	 */
	function getMaxSorting() {
		$table = 'tx_ketroubletickets_todo';
		$fields = 'MAX(sorting) AS maxsort';
		$where = 'ticket_uid=' . $this->ticketUid;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', '', 1);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row['maxsort'];
	}
	
	 
	/*
	 * update status of existing todo
	 * 
	 * @param int $toDoUid				todo uid
	 * @param boolean $doneStatus		status of todo
	 */
	function updateToDoStatus($toDoUid, $doneStatus) {
		// sanitize values
		$toDoUid = intval($toDoUid);
		$doneStatus = intval($doneStatus);
		
		// check values
		if (!$toDoUid || ($doneStatus != 1 && $doneStatus != 0)) return false;
		
		// update ToDo
		$table = 'tx_ketroubletickets_todo';
		$where = 'uid='.$toDoUid;
		$fields_values = array(
			'done' => $doneStatus,
		);
		if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $fields_values)) {
			$toDoTitle = $this->getToDoTitle($toDoUid);
			$this->addHistoryEntry($toDoTitle, 'update', $doneStatus, $this->storagePid);
			return 'OK';
		}
		else return false;
	}
	
	
	/*
	 * remove todo
	 * 
	 * @param int $toDoUid		uid of todo
	 * @return boolean
	 */
	function removeToDo($toDoUid) {
		// sanitize values
		$toDoUid = intval($toDoUid);
		$toDoTitle = $this->getToDoTitle($toDoUid);
		
		// check value
		if (!$toDoUid) return false;
		
		// delete ToDo
		$table = 'tx_ketroubletickets_todo';
		$where = 'uid='.$toDoUid;
		if ($GLOBALS['TYPO3_DB']->exec_DELETEquery($table, $where)) {
			$this->addHistoryEntry($toDoTitle, 'remove', '', $this->storagePid);
			return 'OK';
		}
		else return false;
	}
	
	
	/*
	 * set the percentage progress value of a ticket
	 * 
	 * @param int progressValue		the progress value in percent (0-100)
	 * @return boolean				
	 */
	function setProgress($progressValue) {
		// sanitize value
		$progressValue = intval($progressValue);
		
		// check for valid progress value
		if ($progressValue > 100 || $progressValue < 0) return false;
		
		// update ticket
		$table = 'tx_ketroubletickets_tickets';
		$fields_values['progress'] = $progressValue;
		$where = 'uid='.intval($this->ticketUid);
		if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $fields_values)) return true;
		else return false;
	}
	
	
	/*
	 * calcluate the progress of a ticket
	 * 
	 * @return int
	 */
	function calculateTicketProgress() {
		$table = 'tx_ketroubletickets_todo';
		$fields = 'count(uid) as total, (SELECT count(uid) FROM tx_ketroubletickets_todo WHERE ticket_uid='.$this->ticketUid.' AND done=1) as completed';
		$where = 'ticket_uid=' . $this->ticketUid;
		$where .= t3lib_BEfunc::BEenableFields($table);
		$where .= t3lib_befunc::deleteClause($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		
		$ticketStatus = $this->getTicketStatus();
		
		// ticket already closed
		if ($ticketStatus == 'closed' || $ticketStatus == 'closed_locked') {
			return 100;
		} else {
			if ($row['total'] > 0) {
				// calculation based on existing todos
				$progress = round((100 * $row['completed']) / $row['total']);
				$this->setProgress($progress);
				return $progress;
			} else {
				// calculation based on ticket status
				$fields = 'status';
				$where = 'uid=' . $this->ticketUid;
				$where .= t3lib_BEfunc::BEenableFields('tx_ketroubletickets_tickets');
				$where .= t3lib_befunc::deleteClause('tx_ketroubletickets_tickets');
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, 'tx_ketroubletickets_tickets', $where);
				$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($row['status'] == 'closed') $progress = 100;
				else $progress = 0;
				$this->setProgress($progress);
				return $progress;
			}
		}
	}
	
	
	
	/**
	 * check user's permission 
	 *
	 * returns false if he has no rights
	 * 
	 * @return boolean
	 */
	function checkPermission() {
		$permission = false;

		if(is_array($this->feUserObj->user)) {
			// get user id
			$userId = $this->feUserObj->user['ses_userid'];
			
			// Fetch the ticket from the database. This is the first
			// permission check (enableFields).
			$where = 'uid=' . $this->ticketUid;
			$where .= t3lib_BEfunc::BEenableFields('tx_ketroubletickets_tickets');
			$where .= t3lib_befunc::deleteClause('tx_ketroubletickets_tickets');
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_ketroubletickets_tickets', $where);
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
				$ticketRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($ticketRow['owner_feuser'] == $userId) {
					$permission = true;
				}
				if ($ticketRow['responsible_feuser'] == $userId) {
					$permission = true;
				}
				if (t3lib_div::inList($ticketRow['observers_feuser'], $userId)) {
					$permission = true;
				}
			}
		}
		
		return $permission;
	}
	
	
	
	/*
	 * update sorting of todo list elements
	 * 
	 * @param string $sorting	the sort values
	 * @return string
	 */
	function updateSorting($sorting) {
		$elements = t3lib_div::trimExplode(',', $sorting);
		$newSorting = 0;
		foreach ($elements as $key => $element) {
			$toDoUid = intval(str_replace('todo_', '', $element));
			$newSorting += 10;
			// update todo sorting
			$table = 'tx_ketroubletickets_todo';
			$fields_values['sorting'] = $newSorting;
			$where = 'uid='.intval($toDoUid).' AND ticket_uid='.intval($this->ticketUid);
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $fields_values);
		}
		return 'OK';
	}
	
	
	
	
	
	function addHistoryEntry($toDoTitle, $action, $value, $storagePid) {
		
		// set old value
		if ($action == 'new') {
			$oldValue = '';
			$newValue = 'ToDo created: "' . $toDoTitle.'"';
		} else if ($action == 'update') {
			$oldValue = $value == 1 ? 'ToDo not done: "'.$toDoTitle.'"' : 'ToDo done: "' . $toDoTitle.'"';
			$newValue = $value == 1 ? 'ToDo done: "'.$toDoTitle.'"' : 'ToDo not done: "' . $toDoTitle.'"';
		} else if ($action == 'remove') {
			$oldValue = '';
			$newValue = 'ToDo removed: "'.$toDoTitle.'"';
		}
		
		$table = 'tx_ketroubletickets_history';
		$fields_values = array(
			'crdate' => time(),
			'ticket_uid' => $this->ticketUid,
			'pid' => intval($storagePid),
			'feuser_uid' => $this->feUserObj->user['ses_userid'],
			'databasefield' => 'todo',
			'feuser_username' => $this->feUserObj->user['username'],
			'value_old' => $oldValue,
			'value_new' => $newValue,
		);
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $fields_values);
	}
	
	
	
	function getToDoTitle($toDoUid) {
		$table = 'tx_ketroubletickets_todo';
		$fields = 'title';
		$where = 'uid=' . $toDoUid;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row['title'];
	}
	
	
	function getTicketStatus() {
		$table = 'tx_ketroubletickets_tickets';
		$fields = 'status';
		$where = 'uid=' . $this->ticketUid;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row['status'];
	}

	
	
}
 
$output = t3lib_div::makeInstance('tx_ketroubletickets_eid');
$output->main();

?>
