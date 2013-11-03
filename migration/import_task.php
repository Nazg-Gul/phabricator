#!/usr/bin/env php
<?php

$root = dirname(dirname(__FILE__));
require_once $root.'/scripts/__init_script__.php';
require_once 'storage.php';
require_once 'adapt.php';
require_once 'phab.php';

for($id = intval($argv[1]); $id < intval($argv[2]); $id+=1) {
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
  $status = ManiphestTaskStatus::STATUS_OPEN;
  $description = '%%%' . str_replace("\r\n", "\n", html_entity_decode($mtask->description)) . '%%%';
  $title = html_entity_decode($mtask->title);

  /* spam detection */
  if(($author == "None" || $author == null) && startsWith($description, "%%%<a href= http://") && strpos($title, " ") == false)
    continue;

  /* missing author */
  if($author == null)
    $author = lookup_user("None");
  
  if($assign && $assign->getUsername() == "None")
    $assign = null;
  
  /* extra fields */
  $extra = "";
  $task_type = "Bug";
  $remove_subscribers = false;

  /* BF Blender tasks */
  if($mtask->project == "Blender 2.x BF release") {
    $category = null;
    $category_key = null;
    $mstatus = null;
    $mstatus_key = null;
    $resolution = null;
    $resolution_key = null;
    $old_resolution = null;
    $old_resolution_key = null;
    $data_type = null;
    $date_type_key = null;

    foreach($mtask->extra_fields as $key => $field) {
      if($field['name'] == "Category") {
        $category = $field['value'];
        $category_key = $key;
      }
      else if($field['name'] == "Status") {
        $mstatus = $field['value'];
        $mstatus_key = $key;
      }
      else if($field['name'] == "Resolution") {
        $resolution = $field['value'];
        $resolution_key = $key;
      }
      else if($field['name'] == "Resolution(Old, use status)") {
        $old_resolution = $field['value'];
        $old_resolution_key = $key;
      }
      else if($field['name'] == "Data Type") {
        $data_type = $field['value'];
        $data_type_key = $key;
      }
    }

    if($mtask->tracker == "Blender 2.6 Bug Tracker") {
      $projects[] = lookup_project("BF Blender")->getPHID();
      $close_as_archived = false;
      $task_type = "Bug";
      $priority = 40;

      if($category) {
        if($bf_blender_categories[$category])
          $projects[] = lookup_project($bf_blender_categories[$category])->getPHID();
        unset($mtask->extra_fields[$category_key]);
      }

      switch($mstatus) {
        case "New":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Reopened":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Investigate":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Confirmed":
          $priority = 50;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Incomplete":
          $priority = 30;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Fixed / Closed":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
          break;
        case "Rejected / Closed":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID;
          break;
        case "Closed":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID;
          break;
        case "None": /* happens for old bugs that were closed */
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID;
          break;
        case "Todo / Closed":
          $task_type = "To Do";
          $priority = 40;
          $assign = null;
          $status = ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED;
          break;
        case "Out of scope / Closed":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID;
          break;
        case "Ready":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "*RELEASE BLOCKER*":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        default:
          echo "ERROR: unkown status \"" . $mstatus . "\" (" . $id . ")\n";
          break;
      }

      unset($mtask->extra_fields[$mstatus_key]);
    }
    else if($mtask->tracker == "Blender 2.4x Bug Tracker") {
      $projects[] = lookup_project("BF Blender")->getPHID();
      $task_type = "Bug";
      $priority = 20;
      $close_as_archived = true;
      $remove_subscribers = true;
      $status = ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED;
    }
    else if($mtask->tracker == "Game Engine") {
      $projects[] = lookup_project("Game Engine")->getPHID();
      $task_type = "Bug";
      $priority = 40;
      $close_as_archived = false;
      $status = ManiphestTaskStatus::STATUS_OPEN;

      switch($mstatus) {
        case "New":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "None": /* happens for old bugs that were closed */
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID;
          break;
        case "Reopened":
        case "Investigate":
        case "Ready":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Fixed":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
          break;
        case "Duplicate":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE;
          break;
        case "Rejected":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID;
          break;
        case "Closed":
          $priority = 40;
          $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID;
          break;
        default:
          echo "ERROR: unkown resolution \"" . $resolution . "\" (" . $id . ")\n";
          break;
      }

      unset($mtask->extra_fields[$mstatus_key]);
    }
    else if($mtask->tracker == "Todo") {
      $projects[] = lookup_project("BF Blender")->getPHID();
      $task_type = "To Do";
      $assign = null;
      $priority = 20;
      $close_as_archived = false;
      $remove_subscribers = true;
      $status = ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED;
    }
    else if($mtask->tracker == "OpenGL errors") {
      $projects[] = lookup_project("BF Blender")->getPHID();
      $task_type = "OpenGL Error";
      $priority = 20;
      $close_as_archived = false;
      $remove_subscribers = true;
      $status = ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED;
    }
    else if($mtask->tracker == "Patches") {
      $projects[] = lookup_project("BF Blender")->getPHID();
      $task_type = "Patch";
      $priority = 40;
      $close_as_archived = false;

      if($category) {
        if($bf_blender_categories[$category])
          $projects[] = lookup_project($bf_blender_categories[$category])->getPHID();
        unset($mtask->extra_fields[$category_key]);
      }

      switch($resolution) {
        case "None":
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Open":
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Investigate":
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Need updates":
          $priority = 30;
          $status = ManiphestTaskStatus::STATUS_OPEN;
          break;
        case "Applied":
          $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
          break;
        case "Closed":
          $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
          break;
        default:
          echo "ERROR: unkown resolution \"" . $resolution . "\" (" . $id . ")\n";
          break;
      }

      if($status == ManiphestTaskStatus::STATUS_OPEN) {
        switch($old_resolution) {
          case "Approved":
            $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
            break;
          case "Rejected":
            $status = ManiphestTaskStatus::STATUS_CLOSED_INVALID;
            break;
          case "Postponed":
            $status = ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED;
            break;
          case "Fixed":
            $status = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
            break;
        }

        /* weird stuff goes on here, seems there are closed patches
         * that do no show any closed resolution, so force it */
        if($mtask->state == "Closed")
          $status = ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED;
      }

      unset($mtask->extra_fields[$resolution_key]);
      if($old_resolution)
        unset($mtask->extra_fields[$old_resolution_key]);
    }
    else {
      $projects[] = lookup_project("BF Blender")->getPHID();
      echo "ERROR: unknown BF Blender tracker " . $mtask->tracker . " (" . $id . ")\n";
      $priority = 20; 
      $close_as_archived = true;
      $status = ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED;
      $remove_subscribers = true;
    }
  }
  else if($mtask->project == "Blender Extensions") {
    // TODO: import extensions as addons
    // split bugs and leave out extensions

    /* switch($mtask->tracker) {
      case "Py Scripts Extern":
        $extension_type = "Python Script Extern";
        break;
      case "dev-tools":
        break;
      case "test-tracker":
        break;
      case "Plugins Release":
        $extension_type = "Plugin Release";
        break;
      case "Plugins Contrib":
        $extension_type = "Plugin Contrib";
        break;
      case "Plugins Upload":
        $extension_type = "Plugin Upload";
        break;
      case "Py Scripts Release":
        $extension_type = "Python Script Release";
        break;
      case "Py Scripts Contrib":
        $extension_type = "Python Script Contrib";
        break;
      case "Py Scripts Upload":
        $extension_type = "Python Script Upload";
        break;
      case "Bugs":
        break;
      default:
        echo "ERROR: unkown extension tracker \"" . $mtask->tracker . "\" (" . $id . ")\n";
        break;
    }

    $projects[] = lookup_project("Addons")->getPHID();
    $task_type = "Extension";
    $priority = 50; 
    $status = ManiphestTaskStatus::STATUS_OPEN;
    $close_as_archived = false;*/

    continue;
  }
  else {
    $extra .= "**Project**: " . $mtask->project . "\n";
    $extra .= "**Tracker**: " . $mtask->tracker . "\n";

    $task_type = "Other";
    $priority = 20; 
    $close_as_archived = true;
    $status = ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED;
    $remove_subscribers = true;
  }

  /* add remaining extra fields to description */
  foreach($mtask->extra_fields as $field) {
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

  if(!$remove_subscribers) {
    if(!$close_as_archived) { /* don't notify for dead issues */
      foreach ($mtask->ccs as $mcc) {
        if($mcc && $mcc != "" && $mcc != "None") {
          $ccuser = lookup_user(dedup_user($mcc));
          if($ccuser)
            $ccs[] = $ccuser->getPHID();
        }
      }
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
  $old_status = $status;
  $found_last_status = false;

  foreach ($mtask->history as $mhistory) {
    if($mhistory->field == "Status" || $mhistory->field == "status_id" || $mhistory->field == "Resolution") {
      $sorted_dates[] = $mhistory->date + 2; // couldn't find stable sort, so hack
      $sorted_actions[] = array($mhistory->user, $mhistory->date, $old_status);
      $old_status = $mhistory->old;
      $found_last_status = true;
      break; // only does last status, too messy to figure out from history
    }
  }

  if(!$found_last_status) {
    $sorted_dates[] = $mtask->date;
    $sorted_actions[] = array("None", $mtask->date, $old_status);
  }

  /* sort and apply in order */
  array_multisort($sorted_dates, $sorted_actions);

  foreach($sorted_actions as $action) {
    if(is_object($action) && get_class($action) == "MigrateFile") {
      /* create file */
      $mfile = $action;
      $user = lookup_user(dedup_user($mfile->user));

      if($user)
        create_file($task, $user, $mfile->name, $mfile->contents, $mfile->date);
        //create_comment($task, $user, "Attach file: " . $mfile->name, $mfile->date);
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

      set_status($task, $user, $mstatus, $mdate);
    }
  }

  /* final status check */
  if($task->getStatus() == ManiphestTaskStatus::STATUS_OPEN && $close_as_archived)
    set_status($task, lookup_user("None"), ManiphestTaskStatus::STATUS_CLOSED_ARCHIVED, $mtask->date);

  if($task->getStatus() == ManiphestTaskStatus::STATUS_OPEN && $mtask->state == "Closed")
    echo "ERROR: status out of sync, task should have been closed (" . $id . ")\n";
}

