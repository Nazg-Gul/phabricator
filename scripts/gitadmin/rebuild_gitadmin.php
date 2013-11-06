#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

function escape_name($name) {
  return preg_replace('/[^A-Za-z0-9\-]/', '_', $name);
}

function startswith($string, $prefix) {
  return substr($string, 0, strlen($prefix)) == $prefix;
}

function write_ini_file($array, $file) {
  $res = array();
  foreach ($array as $key => $val) {
    if (is_array($val)) {
      $res[] = "[$key]";
      foreach ($val as $skey => $sval) {
        $res[] = "$skey = $sval";
      }
      $res[] = '';
    } else {
      $res[] = "$key = $val";
    }
  }
  file_put_contents($file, implode("\n", $res));
}

function handleSingleUserPHID(
  $keydir, $viewer, $userPHID, &$used_keys) {
  $user = id(new PhabricatorPeopleQuery())
    ->setViewer($viewer)
    ->withPHIDs(array($userPHID))
    ->executeOne();

  $keys = id(new PhabricatorUserSSHKey())->loadAllWhere(
   'userPHID = %s',
    $user->getPHID());

  $members = array();
  foreach ($keys as $key) {
    $escaped_key_name = escape_name($key->getName());
    $member = 'PHAB_'.$user->getUserName().
      '@'.$escaped_key_name.
      '_'.$key->getID();
    $members[] = $member;
    if (!array_key_exists($member, $used_keys)) {
      $used_keys[$member] = true;
      $full_key_content =
        $key->getKeyType().' '.
        $key->getKeyBody().' '.
        $key->getKeyComment()."\n";
      file_put_contents("$keydir/$member", $full_key_content);
    }
  }
  return $members;
}

function handleSingleRepository(
  $keydir, $viewer, $repository, &$new_configuration, &$used_keys) {
  $policies = PhabricatorPolicyQuery::loadPolicies(
    $viewer,
    $repository);

  $pushable = $policies[DiffusionCapabilityPush::CAPABILITY];
  $type = phid_get_type($pushable->getPHID());

  $members = array();

  if ($type == PhabricatorProjectPHIDTypeProject::TYPECONST) {
    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needMembers(true)
      ->withPHIDs(array($pushable->getPHID()))
      ->executeOne();

    $memberPHIDs = $project->getMemberPHIDs();
    foreach ($memberPHIDs as $memberPHID) {
      $members = array_merge($members,
        handleSingleUserPHID($keydir, $viewer, $memberPHID, $used_keys));
    }
  } else if ($type == PhabricatorPeoplePHIDTypeUser::TYPECONST) {
      $members = handleSingleUserPHID(
        $keydir, $viewer, $pushable->getPHID(), $used_keys);
  } else if ($type == PhabricatorPolicyPHIDTypePolicy::TYPECONST) {
    /* pass */
  } else {
    /* pass */
  }

  if (count($members)) {
    $escaped_repository_name = escape_name($repository->getName());
    $group_name = "PHAB_${escaped_repository_name}";
    $values = array();
    $values['members'] = join(' ', $members);
    $values['readonly'] = '@all';
    $values['writable'] = $repository->getName();
    $new_configuration["group $group_name"] = $values;
  }
}

// Remove groups from previous automated configuration built
function getCleanOldConfiguration($old_configuration) {
  $new_configuration = array();
  foreach ($old_configuration as $group => $values) {
    if (!startswith($group, 'group PHAB')) {
      $new_configuration[$group] = $values;
    }
  }
  return $new_configuration;
}

// Remove unused public keys
function removeUnusedPublicKeys($keydir, $used_keys) {
  $files = scandir($keydir);
  foreach ($files as $file) {
    if (startswith($file, "PHAB")) {
      if (!array_key_exists($file, $used_keys)) {
        unlink("$keydir/$file");
      }
    }
  }
}

if (count($argv) != 2) {
  print("Usage: {$argv[0]} /path/to/gitosis-admin\n");
  exit(1);
}

$gitosis_root = $argv[1];
$configuration_file = "$gitosis_root/gitosis.conf";
$keydir = "$gitosis_root/keydir";
if (!file_exists($configuration_file)) {
  print("Not found: $configuration_file\n");
  exit(1);
}

$viewer = id(new PhabricatorUser())
  ->loadOneWhere('username = %s', 'sergey');

$old_configuration = parse_ini_file(
  $configuration_file, true, INI_SCANNER_RAW);

$new_configuration = getCleanOldConfiguration(
  $old_configuration);

// Fill in new configuration and keys
$used_keys = array();
$repositories = id(new PhabricatorRepositoryQuery())
  ->setViewer($viewer)
  ->execute();

foreach ($repositories as $repository_id => $repository) {
  handleSingleRepository(
    $keydir, $viewer, $repository, $new_configuration, $used_keys);
}

write_ini_file($new_configuration, $configuration_file);
removeUnusedPublicKeys($keydir, $used_keys);
?>
