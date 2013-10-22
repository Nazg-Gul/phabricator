<?php

function create_custom_field_transaction($task, $user, $value, $template)
{
    $field_list = PhabricatorCustomField::getObjectFields($task, PhabricatorCustomField::ROLE_EDIT);

    foreach ($field_list->getFields() as $field) {
		$field->setObject($task);
		$field->setViewer($user);
	}

    $field_list->readFieldsFromStorage($task);
    $aux_fields = $field_list->getFields();

	$old_values = array();
	foreach ($aux_fields as $aux_arr_key => $aux_field) {
        $aux_old_value = $aux_field->getOldValueForApplicationTransactions();
		$aux_field->setValueFromStorage($value);
        $aux_new_value = $aux_field->getNewValueForApplicationTransactions();

        $placeholder_editor = new PhabricatorUserProfileEditor();

        $field_errors = $aux_field->validateApplicationTransactions(
          $placeholder_editor,
          PhabricatorTransactions::TYPE_CUSTOMFIELD,
          array(
            id(new ManiphestTransaction())
              ->setOldValue($aux_old_value)
              ->setNewValue($aux_new_value),
          ));

        foreach ($field_errors as $error) {
          $errors[] = $error->getMessage();
        }

        $old_values[$aux_field->getFieldKey()] = $aux_old_value;
	}

	$transactions = array();

	foreach ($aux_fields as $aux_field) {
		$transaction = clone $template;
		$transaction->setTransactionType(PhabricatorTransactions::TYPE_CUSTOMFIELD);
		$aux_key = $aux_field->getFieldKey();
		$transaction->setMetadataValue('customfield:key', $aux_key);
		$old = idx($old_values, $aux_key);
		$new = $aux_field->getNewValueForApplicationTransactions();

		$transaction->setOldValue($old);
		$transaction->setNewValue($new);

		$transactions[] = $transaction;
	}

	return $transactions;
}

function create_task($user, $id, $title, $projects, $description, $assign_user, $date, $ccs, $priority, $field_value)
{
	/* create task */
	$task = ManiphestTask::initializeNewTask($user);
	$task->setTitle($title);
	$task->setProjectPHIDs($projects);
	$task->setCCPHIDs($ccs);
	$task->setDescription($description);
	$task->setPriority($priority);
	$task->setOverrideDate($date);
	$task->setOverrideID($id);

	if ($assign_user)
		$task->setOwnerPHID($assign_user->getPHID());

	/* content source */
	$content_source = PhabricatorContentSource::newForSource(
		PhabricatorContentSource::SOURCE_UNKNOWN,
		array());

	/* transactions */
	$changes = array();
	$changes[ManiphestTransaction::TYPE_STATUS] = ManiphestTaskStatus::STATUS_OPEN;
	$changes[PhabricatorTransactions::TYPE_VIEW_POLICY] = 'public';
	$changes[PhabricatorTransactions::TYPE_EDIT_POLICY] = 'admin'; // TODO which permission?

	$template = new ManiphestTransaction();

	$transactions = array();

	foreach ($changes as $type => $value) {
		$transaction = clone $template;
		$transaction->setTransactionType($type);
		$transaction->setNewValue($value);
		$transaction->setOverrideDate($date);
		$transactions[] = $transaction;
	}

	/* type */
	$transactions = array_merge($transactions, create_custom_field_transaction($task, $user, $field_value, $template));

	$editor = id(new ManiphestTransactionEditorPro())
	->setActor($user)
	->setContentSource($content_source)
	->setContinueOnNoEffect(true)
	->applyTransactions($task, $transactions);

	return $task;
}

function create_comment($task, $user, $description, $date)
{
	/* content source */
	$content_source = PhabricatorContentSource::newForSource(
		PhabricatorContentSource::SOURCE_UNKNOWN,
		array());

	/* transactions */
	$comment = id(new ManiphestTransactionComment());
	$comment->setContent($description);

	$transaction = id(new ManiphestTransaction());
	$transaction->setTransactionType(PhabricatorTransactions::TYPE_COMMENT);
	$transaction->setOverrideDate($date);
	$transaction->attachComment($comment);

	$transactions = array();
	$transactions[] = $transaction;

	/* apply */
    $editor = id(new ManiphestTransactionEditorPro())
      ->setActor($user)
      ->setContentSource($content_source)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($task, $transactions);
}

function create_file($task, $user, $name, $contents, $date)
{
	/* content source */
	$content_source = PhabricatorContentSource::newForSource(
		PhabricatorContentSource::SOURCE_UNKNOWN,
		array());

	/* files */
	$files = array();

	$file = PhabricatorFile::newFromFileData(
		$contents,
		array(
		  'authorPHID' => $user->getPHID(),
	      'name' => $name,
		));
	$files[] = $file;

	$files = mpull($files, 'getPHID', 'getPHID');
	$new = $task->getAttached();
	foreach ($files as $phid) {
		if (empty($new[PhabricatorFilePHIDTypeFile::TYPECONST])) {
			$new[PhabricatorFilePHIDTypeFile::TYPECONST] = array();
		}

		$new[PhabricatorFilePHIDTypeFile::TYPECONST][$phid] = array();
	}

	/* transaction */
	$transaction = new ManiphestTransaction();
	$transaction->setTransactionType(ManiphestTransaction::TYPE_ATTACH);
	$transaction->setNewValue($new);
	$transaction->setOverrideDate($date);
	$transactions[] = $transaction;

	/* apply */
    $editor = id(new ManiphestTransactionEditorPro())
      ->setActor($user)
      ->setContentSource($content_source)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($task, $transactions);
}

function set_status($task, $user, $status, $date)
{
	/* content source */
	$content_source = PhabricatorContentSource::newForSource(
		PhabricatorContentSource::SOURCE_UNKNOWN,
		array());

	/* transactions */
	$changes = array();
	$changes[ManiphestTransaction::TYPE_STATUS] = $status;

	$template = new ManiphestTransaction();

	$transactions = array();

	foreach ($changes as $type => $value) {
		$transaction = clone $template;
		$transaction->setTransactionType($type);
		$transaction->setNewValue($value);
		$transaction->setOverrideDate($date);
		$transactions[] = $transaction;
	}

	$editor = id(new ManiphestTransactionEditorPro())
	->setActor($user)
	->setContentSource($content_source)
	->setContinueOnNoEffect(true)
	->applyTransactions($task, $transactions);
}

function create_project($name, $username, $active, $membernames, $blurb = '')
{
	$user = lookup_user($username);

	/* create project */
	$project = new PhabricatorProject();
	$project->setAuthorPHID($user->getPHID());
	$project->attachMemberPHIDs(array());
	$profile = new PhabricatorProjectProfile();

	$xactions = array();

	/* policy */
	$project->setViewPolicy("public");
	$project->setEditPolicy("admin");
	$project->setJoinPolicy("admin");

	/* archived */
	if(!$active)
		$project->setStatus(PhabricatorProjectStatus::STATUS_ARCHIVED);
	/* name transaction */
	$xaction = new PhabricatorProjectTransaction();
	$xaction->setTransactionType(
	  PhabricatorProjectTransactionType::TYPE_NAME);
	$xaction->setNewValue($name);
	$xactions[] = $xaction;

	/* members transaction */
	$member_phids = array();
	foreach($membernames as $membername) {
		$member_phids[] = lookup_user($membername)->getPHID();
	}

	$xaction = new PhabricatorProjectTransaction();
	$xaction->setTransactionType(
	  PhabricatorProjectTransactionType::TYPE_MEMBERS);
	$xaction->setNewValue($member_phids);
	$xactions[] = $xaction;

	/* apply transaction */
	$editor = new PhabricatorProjectEditor($project);
	$editor->setActor($user);
	$editor->applyTransactions($xactions);

	/* set blurb */
	$profile->setBlurb($blurb);

	$project->save();
	$profile->setProjectPHID($project->getPHID());
	$profile->save();
}

