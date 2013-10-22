#!/usr/bin/env php
<?php

$root = dirname(dirname(__FILE__));
require_once $root.'/scripts/__init_script__.php';
require_once 'storage.php';
require_once 'adapt.php';
require_once 'phab.php';

/* code */

function isValidTimezoneId($timezoneId)
{
	@$tz=timezone_open($timezoneId);
	return $tz!==FALSE;
}

$musers = unserialize(file_get_contents("dump/users"));
$importedusers = array();
$usedemails = array();

foreach ($musers as $muser)
{
	$username = $muser->name;
	$userid = $muser->id;
	$realname = trim($muser->realname);
	$email = $muser->email;
	$date = $muser->date;
	$timezone = $muser->timezone;

	// TODO broken intentionally to avoid sending emails during tests
	$email = "brokenemail___" . $email . "___brokenemail"; 

	/* skip duplicate users */
	if(array_key_exists($username, $migrate_dedup_users))
		continue;

	/* log imported users */
	$importeduser = new MigrateImportedUser();
	$importeduser->name = $username;
	$importeduser->email = $email;

	/* detect spam */
	$isspam = false;

	foreach($spammails as $spam)
		if(endsWith($email, $spam))
			$isspam = true;
	
	if(!$isspam && strlen($realname) > 4) {
		if(substr($realname, 0, strlen($realname)-2) == $username . " " . $username) {
			if(ctype_upper(substr($realname, strlen($realname)-2, 2))) {
				$isspam = true;
			}
		}
	}
	
	if ($isspam) {
		$importeduser->isspam = true;
		$importedusers[$username] = $importeduser;
		continue;
	}

	/* fix timezones */
	if (!isValidTimezoneId($timezone)) {
		switch($timezone) {
			case "China/Beijing": $timezone = "Asia/Shanghai"; break;
			case "China/Shanghai":  $timezone = "Asia/Shanghai"; break;
			case "-1": $timezone = "GMT"; break;
			case "-2": $timezone = "GMT"; break;
			case "-3": $timezone = "GMT"; break;
			case "0": $timezone = "GMT"; break;
			case "1": $timezone = "GMT"; break;
			case "2": $timezone = "GMT"; break;
			case "3": $timezone = "GMT"; break;
			case "Asia/Beijing": $timezone = "Asia/Shanghai"; break;
			case "Asia/Riyadh87": $timezone = "Asia/Riyadh"; break;
			case "Asia/Riyadh88": $timezone = "Asia/Riyadh"; break;
			case "Asia/Riyadh89": $timezone = "Asia/Riyadh"; break;
			case "Mideast/Riyadh87": $timezone = "Asia/Riyadh"; break;
			case "Mideast/Riyadh88": $timezone = "Asia/Riyadh"; break;
			case "Mideast/Riyadh89": $timezone = "Asia/Riyadh"; break;
		}
	}

	if (!isValidTimezoneId($timezone)) {
		echo "ERROR: invalid timezone " . $timezone . ", changed to GMT\n";
		$timezone = "GMT";
	}
	
	/* check existing user and email */
	$existing_user = id(new PhabricatorUser())->loadOneWhere('username = %s', $username);
	if ($existing_user) {
		echo 'ERROR: ' . $username . " already exists.\n";
	}

	$existing_email = id(new PhabricatorUserEmail())->loadOneWhere('address = %s', $email);
	if ($existing_email) {
		echo 'ERROR: ' . $email . " already exists for user " . $username . ".\n";
	}

	if ($existing_email || $existing_user) {
		$importedusers[$username] = $importeduser;
		continue;
	}

	/* create user */
	$user = new PhabricatorUser();
	$user->setUsername($username);
	$user->setRealname($realname);
	$user->setOverrideDate($date);
	$user->setOverrideID($userid);
	$user->setTimezoneIdentifier($timezone);

	if(in_array($username, $admins))
		$user->setIsAdmin(true);

	$email_object = id(new PhabricatorUserEmail())
	  ->setAddress($email)
	  ->setIsVerified(1);

	id(new PhabricatorUserEditor())
	  ->setActor($user)
	  ->createNewUser($user, $email_object);

	/* set password */
	$envelope = new PhutilOpaqueEnvelope($muser->password);
	id(new PhabricatorUserEditor())
	  ->setActor($user)
	  ->changePassword($user, $envelope, true);

	/* log imported user */
	$importedusers[$username] = $importeduser;
	$usedemails[strtolower($email)] = $username;
}

file_put_contents('dump/importedusers', serialize($importedusers));

