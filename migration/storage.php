<?php

/* serialization */

class MigrateFile {
  public $name;
  public $contents;
  public $user;
  public $date;
  public $type;
}

class MigrateComment {
  public $user;
  public $description;
  public $date;
}

class MigrateHistory {
  public $user;
  public $date;
  public $field;
  public $old;
}

class MigrateTask {
  public $id;
  public $author;
  public $title;
  public $description;
  public $date;
  public $assign;
  public $state;
  public $priority;

  public $project;
  public $tracker;

  public $comments = array();
  public $files = array();
  public $ccs = array();
  public $history = array();

  public $extra_fields = array();
}

class MigrateUser {
  public $name;
  public $id;
  public $realname;
  public $email;
  public $date;
  public $timezone;
  public $password;
}

class MigrateImportedUser {
  public $name;
  public $email;
  public $isspam = false;
  public $duplicateof = null;
}

/* utilities */

function startsWith($haystack, $needle)
{
  return $needle === "" || strpos($haystack, $needle) === 0;
}

function endsWith($haystack, $needle)
{
  return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function lookup_user($name)
{
  if($name == null)
    return null;

  $user = id(new PhabricatorUser())->loadOneWhere('username = %s', $name);
  if (!$user)
    echo "ERROR: lookup of user " . $name . " failed\n";

  return $user;
}

function lookup_project($name)
{
  if($name == null)
    return null;

  $project = id(new PhabricatorProject())->loadOneWhere('name = %s', $name);
  if (!$project)
    echo "ERROR: lookup of project " . $name . " failed\n";

  return $project;
}

