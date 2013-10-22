#!/usr/bin/env php
<?php

$root = dirname(dirname(__FILE__));
require_once $root.'/scripts/__init_script__.php';
require_once 'storage.php';
require_once 'adapt.php';
require_once 'phab.php';

for($id = intval($argv[1]); $id < intval($argv[2]); $id+=100) { // XXX quick subset of tasks
	/* unserialize */
	$fname = "dump/task_" . $id;
	if(!file_exists($fname))
		continue;
	
	//echo "IMPORT " . $id . "\n";

	$fcontents = file_get_contents($fname);
	$mtask = unserialize($fcontents);

	/* extract basic data */
	$author = lookup_user(dedup_user($mtask->author));
	$assign = lookup_user(dedup_user($mtask->assign));
	$projects = array();
	// 100 Unbreak Now!, 90 Needs Triage, 80 High, 50 Normal, 25 Low, 0, Wishlist
	// TODO set feature request and todo priorities
	$priority = 50; 
	$status = ManiphestTaskStatus::STATUS_OPEN;
	$description = '%%%' . html_entity_decode($mtask->description) . '%%%'; // TODO replace urls
	$title = html_entity_decode($mtask->title);

	/* spam detection */
	// TODO: this only catches a small subet of spam
	if(($author == "None" || $author == null) && startsWith($description, "%%%<a href= http://") && strpos($title, " ") == false)
		continue;

	/* missing author */
	if($author == null)
		$author = lookup_user("None");
	
	if($assign && $assign->getUsername() == "None")
		$assign = null;
	
	/* extra fields */
	$extra = "";

	// TODO this is pretty bad and incomplete
	if($active_projects[$mtask->project][1]) {
		$projects[] = lookup_project($active_projects[$mtask->project][1])->getPHID();
	}
	else {
		$extra .= "**Project**: " . $mtask->project . "\n";
	}

	if($active_trackers[$mtask->tracker][1]) {
		$projects[] = lookup_project($active_trackers[$mtask->tracker][1])->getPHID();
	}

	$task_type = "Other";

	if($active_trackers[$mtask->tracker][2]) {
		$task_type = $active_trackers[$mtask->tracker][2];
	}
	else {
		$extra .= "**Tracker**: " . $mtask->tracker . "\n";
	}

	foreach($mtask->extra_fields as $field) {
		if($field['name'] == "Category") {
			if($active_categories[$field['value']])
				$projects[] = lookup_project($active_categories[$field['value']])->getPHID();

			continue;
		}

		if($field['name'] == "Status") {
			switch($field['value']) {
				case "None": /*$status = ManiphestTaskStatus::STATUS_OPEN;*/ break;
				case "Closed": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "New": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Fixed / Closed": $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED; break;
				case "Rejected": $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID; break;
				case "Fixed": $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED; break;
				case "Rejected / Closed": $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID; break;
				case "Duplicate": $status = ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE; break;
				case "Todo / Closed": $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID; break;
				case "Open": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Merged w/ others scripts": $status = ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE; break;
				case "Buggy": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "No wiki": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "&gt;Contrib": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Implemented": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Accepted": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Investigate": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Reopened": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "&gt;Release": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "On hold": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Investigating": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Awaiting Response": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Confirmed": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Out of scope / Closed": $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID; break;
				case "Ready": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Incomplete": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				default:
					echo "ERROR: unknown Status " . $field['value'] . "\n";
					break;
			}

			// XXX continue;
		}

		if($field['name'] == "Resolution") {
			switch($field['value']) {
				case "Investigate": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "New": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Approved": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Fixed": $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED; break;
				case "Rejected": $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID; break;
				case "Postponed": $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID; break;
				case "Ready": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "None": /*$status = ManiphestTaskStatus::STATUS_OPEN;*/ break;
				case "Ready": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Closed": $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED; break;
				case "Open": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Invalid": $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID; break;
				case "Accepted As Bug": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Won't Fix": $status = ManiphestTaskStatus::STATUS_CLOSED_WONTFIX; break;
				case "Duplicate": $status = ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE; break;
				case "Awaiting Response": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				case "Applied": $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED; break;
				case "Accepted": $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED; break;
				case "Need updates": $status = ManiphestTaskStatus::STATUS_OPEN; break;
				default:
					echo "ERROR: unknown Resolution " . $field['value'] . "\n";
					break;
			}
		}

		if($field['name'] == "Resolution(Old, use status)") {
			// XXX
		}

		if($field['value'] == "" || $field['value'] == 'None')
			continue;

		if($field['name'] == 'Relates to') {
			$extra .= "**Relates to**: ";
		}
		else if($field['name'] == 'Related to') {
			$extra .= "**Related to**: ";
		}
		else if($field['name'] == 'Duplicate') {
			$extra .= "**Duplicate**: ";
		}
		else if($field['name'] == 'Duplicates') {
			$extra .= "**Duplicates**: ";
		}
		else if($field['name'] == 'Patches') {
			$extra .= "**Patches**: ";
		}
		else if($field['name'] == 'Patch for') {
			$extra .= "**Patch for**: ";
		}
		else {
			$extra .= "**" . $field['name'] . "**: " . $field['value'] . "\n";
			continue;
		}

		$value = explode(" ", str_replace("#", "", $field['value']));
		$first = true;
		foreach($value as $subvalue) {
			if($first)
				$first = false;
			else
				$extra .= " ";

			$extra .= "T" . $subvalue;
		}

		$extra .= "\n";
	}

	if($extra != "")
		$description = $extra . "\n" . $description;
	
	/* subscribers */
	$ccs = array();
	foreach ($mtask->ccs as $mcc) {
		if($mcc && $mcc != "" && $mcc != "None") {
			$ccuser = lookup_user(dedup_user($mcc));
			if($ccuser)
				$ccs[] = $ccuser->getPHID();
		}
	}

	/* we don't check these */
	$projects = array_unique($projects);

	/* create task */
	$task = create_task($author, $mtask->id, $title, $projects,
		$description, $assign, $mtask->date, $ccs, $priority, $task_type);

	
	/* create array with all operations */
	$sorted_dates = array();
	$sorted_actions = array();

	/* files */
	foreach ($mtask->files as $mfile) {
		$sorted_dates[] = $mfile->date;
		$sorted_actions[] = $mfile;
	}

	/* comments */
	foreach ($mtask->comments as $mcomment) {
		$sorted_dates[] = $mcomment->date + 1;  // couldn't find stable sort, so hack
		$sorted_actions[] = $mcomment;
	}

	/* history */
	$old_status = $mtask->state;

	foreach ($mtask->history as $mhistory) {
		// TODO: these often have the wrong user (e.g. T17501)
		// TODO: different types of status fields exist
		if($mhistory->field == "Status") {
			$sorted_dates[] = $mhistory->date + 2; // couldn't find stable sort, so hack
			$sorted_actions[] = array($mhistory->user, $mhistory->date, $old_status);

			$old_status = $mhistory->old;
		}
	}

	$sorted_dates[] = $mtask->date;
	$sorted_actions[] = array($mtask->author, $mtask->date, $old_status);

	/* sort and apply in order */
	array_multisort($sorted_dates, $sorted_actions);

	foreach($sorted_actions as $action) {
		if(is_object($action) && get_class($action) == "MigrateFile") {
			/* create file */
			$mfile = $action;
			$user = lookup_user(dedup_user($mfile->user));

			// TODO skip low file IDS to avoid F1..F12 becoming shortcut key links
			// TODO mysql file size limit
			if($user)
				create_comment($task, $user, "Attach file: " . $mfile->name, $mfile->date);
				//create_file($task, $user, $mfile->name, $mfile->contents, $mfile->date);*/
		}
		else if(is_object($action) && get_class($action) == "MigrateComment") {
			/* create comment */
			$mcomment = $action;
			$user = lookup_user(dedup_user($mcomment->user));
			if($user) {
				$description = '%%%' . html_entity_decode($mcomment->description) . '%%%';
				create_comment($task, $user, $description, $mcomment->date);
			}
		}
		else {
			/* change status */
			$muser = $action[0];
			$mdate = $action[1];
			$mstatus = $action[2];

			$user = lookup_user(dedup_user($muser));
			if(!$user)
				$user = lookup_user("None");

			$status = ManiphestTaskStatus::STATUS_OPEN;

			if(strpos($mstatus, "Closed") !== false) 
				$status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
			
			set_status($task, $user, $status, $mdate);
		}
	}

	/* if me missed the status somehow, close anyway if task is closed */
	// TODO
	if($task->getStatus() == ManiphestTaskStatus::STATUS_OPEN && $mtask->state == "Closed")
		echo "ERROR: status out of sync, task should have been closed\n";
		//set_status($task, lookup_user("None"), ManiphestTaskStatus::STATUS_CLOSED_RESOLVED, $mtask->date);
}

