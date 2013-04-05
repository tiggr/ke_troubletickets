jQuery(document).ready(function() {
	
	var ticketClosed = false;
	
	// check if ticket is closed
	ticketClosed = function() {
		var currentStatus = jQuery('select[name="tx_ketroubletickets_pi1[status]"]').val();
		if (currentStatus == 'closed' || currentStatus == 'closed_locked') {
			return true;
		} else {
			return false;
		}
	}
	
	// get all todos for current ticket
	getToDos = function() {
		jQuery.ajax({
			type: "POST",
			url: "index.php?eID=kett",
			dataType: 'json',
			data: {
				action: 'getToDos',
				ticketUid: ticketUid,
				storagePid: storagePid
			},
			success: function (data) {
				jQuery('#kett_todo_list').html();
				jQuery.each(data, function(key, val) {
					if (!ticketClosed) {
						// Ticket is not closed - editable
						if (val.done == "1") checkedAttr = 'checked="checked" ';
						else checkedAttr = '';
						jQuery('#kett_todo_list').append(
							'<li id="todo_' +val.uid+ '">\n\
								<input type="checkbox" '+checkedAttr+ ' id="check_'+val.uid+'" />\n\
								<label for="check_'+val.uid+'"> ' + val.title + ' </label>\n\
								<span class="remove_todo"></span>\n\
								<span class="clearer">&nbsp;</span>\n\
							</li>'
						);
					} else {
						// Ticket is closed - read only
						if (val.done == "1") checkedAttr = 'checked="checked" ';
						else checkedAttr = '';
						jQuery('#kett_todo_list').append(
							'<li>\n\
								<input type="checkbox" '+checkedAttr+ ' disabled="disabled" />\n\
								' + val.title + '\n\
								<span class="clearer">&nbsp;</span>\n\
							</li>'
						);
					}
				});
			}
		});
	};
	
	// add new todo to db
	function addToDo() {
	//addToDo = function(){
		title = jQuery('#kett_todo_new').val();
		if (!title) return;
		
		jQuery.ajax({
			type: "POST",
			url: "index.php?eID=kett",
			dataType: 'json',
			data: {
				action: 'addToDo',
				ticketUid: ticketUid,
				title: title,
				storagePid: storagePid
			},
			success: function (data) {
				jQuery('#kett_todo_list').append(
					'<li id="todo_' +data.uid+ '">\n\
						<input type="checkbox" id="check_'+data.uid+'" />\n\
						<label for="check_'+data.uid+'"> ' + title + ' </label>\n\
						<span class="remove_todo"></span>\n\
						<span class="clearer">&nbsp;</span>\n\
					</li>'
				);
				updateTicketProgress();
			}
		});
	};
	
	// delete todo from db
	removeToDo = function(toDoId){
		cleanID = toDoId.replace('todo_', '');
		jQuery.ajax({
			type: "POST",
			url: "index.php?eID=kett",
			dataType: 'json',
			data: {
				action: 'removeToDo',
				toDoUid: parseInt(cleanID),
				ticketUid: ticketUid,
				storagePid: storagePid
			},
			success: function (data) {
				jQuery('#'+toDoId).detach();
				updateTicketProgress();
			}
		});
	};
	
	// update todo status in db
	updateToDoStatus = function(toDoId) {
		if (jQuery('#'+toDoId).find('input').is(':checked')) toDoStatus = 1;
		else toDoStatus = 0;
		cleanID = toDoId.replace('todo_', '');
		jQuery.ajax({
			type: "POST",
			url: "index.php?eID=kett",
			dataType: 'json',
			data: {
				action: 'updateToDoStatus',
				toDoUid: parseInt(cleanID),
				ticketUid: ticketUid,
				doneStatus: toDoStatus,
				storagePid: storagePid
			},
			success: function (data) {
				updateTicketProgress();
			}
		});
	};
	
	// update progress in db
	updateTicketProgress = function() {
		jQuery.ajax({
			type: "POST",
			url: "index.php?eID=kett",
			dataType: 'json',
			data: {
				action: 'calculateTicketProgress',
				ticketUid: ticketUid,
				storagePid: storagePid
			},
			success: function (data) {
				progressValue = parseInt(data);
				jQuery('#kett_sv_progress').animate({width: parseInt(data)+'%'},500,function(){});
				jQuery('#kett_sv_progress_percent').text(parseInt(data) + '%');
				
				// set color of progress bar
				jQuery('#kett_sv_progress').removeClass('redBar');
				jQuery('#kett_sv_progress').removeClass('orangeBar');
				jQuery('#kett_sv_progress').removeClass('greenBar');
				if (progressValue < 33) jQuery('#kett_sv_progress').addClass('redBar');
				else if (progressValue < 66) jQuery('#kett_sv_progress').addClass('orangeBar');
				else jQuery('#kett_sv_progress').addClass('greenBar');
			}
		});
	};
	
	// save sorting in db
	updateSorting = function(sorting) {
		jQuery.ajax({
			type: "POST",
			url: "index.php?eID=kett",
			data: {
				ticketUid: ticketUid,
				action: 'updateSorting',
				sorting: sorting.toString(),
				storagePid: storagePid
			}
		});
	};
	
	
	
	// ---------- ACTIONS ----------------
	// check if ticket is closed
	ticketClosed = ticketClosed();
	
	// list all todos on domready
	getToDos();
	
	// update progress bar
	updateTicketProgress();
	
	// activate functions if ticket not closed
	if (!ticketClosed) {
		
		if (ticketUid == 0) {
			jQuery('#kett_todo_new').hide();
			jQuery('#addToDo').hide();
		}
		
		// add todo when submitting todo form
		jQuery('#addToDo').click(function(){
			addToDo();
			jQuery('#kett_todo_new').val('').focus();
		});

		// submit todo with return key
		jQuery('#kett_todo_new').keypress(function(e) {
			if(e.which == 13) {
				e.preventDefault();
				addToDo();
				jQuery('#kett_todo_new').val('').focus();
				return false;
			}
		});

		// add todo when clicking remove element
		jQuery(document).on('click', '.remove_todo', function(){
			removeToDo(jQuery(this).parent('li').attr('id'));
		});

		// set todo status when clicking checkbox
		jQuery(document).on('change', '#kett_todo_list li input:checkbox', function(){
			updateToDoStatus(jQuery(this).parent('li').attr('id'));
		});

		// make todo list sortable 
		jQuery('#kett_todo_list').sortable({
			cursor: 'move',
			axis: 'y',
			stop: function(i) {
				updateSorting(jQuery(this).sortable("toArray"));
			}
		});
	} else {
		// hide elements when ticket is closed
		jQuery('#kett_todo_new').hide();
		jQuery('#addToDo').hide();
	}
});