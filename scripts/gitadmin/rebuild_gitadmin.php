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

$projects_to_repo_map =
  array('Addons' => 'blender-addons',
        'Blender 2.x Release' => 'blender',
        'Blender UI Translations' => 'blender-translations');

$viewer = id(new PhabricatorUser())
  ->loadOneWhere('username = %s', 'sergey');

$projects = id(new PhabricatorProjectQuery())
  ->setViewer($viewer)
  ->needMembers(true)
  ->execute();

$old_configuration = parse_ini_file(
  $configuration_file, true, INI_SCANNER_RAW);
$new_configuration = array();

// Remove groups from previous automated configuration built
foreach ($old_configuration as $group => $values) {
  if (!startswith($group, 'group PHAB')) {
    $new_configuration[$group] = $values;
  }
}

// Fill in new ocnfiguration and keys
$used_keys = array();
foreach ($projects as $project_id => $project) {
  if (!array_key_exists($project->getName(),
    $projects_to_repo_map)) {
    continue;
  }

  $memberPHIDs = $project->getMemberPHIDs();
  $members = array();
  foreach ($memberPHIDs as $memberPHID) {
    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($memberPHID))
      ->executeOne();

    $keys = id(new PhabricatorUserSSHKey())->loadAllWhere(
      'userPHID = %s',
       $user->getPHID());

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
  }

  if (count($members)) {
    $escaped_project_name = escape_name($project->getName());
    $repo = $projects_to_repo_map[$project->getName()];
    $group_name = "PHAB_${escaped_project_name}";
    $values = array();
    $values['members'] = join(' ', $members);
    $values['readonly'] = '@all';
    $values['writable'] = $repo;
    $new_configuration["group $group_name"] = $values;
  }
}

write_ini_file($new_configuration, $configuration_file);

// Remove unused keys
$files = scandir($keydir);
foreach ($files as $file) {
  if (startswith($file, "PHAB")) {
    if (!array_key_exists($file, $used_keys)) {
      unlink("$keydir/$file");
    }
  }
}

?>
