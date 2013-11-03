#!/usr/bin/env php
<?php
/*
 * Script for exporting users to file
 *
 * GForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this file; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

require ('/data/www/projects.blender.org/gforge/www/env.inc.php');
require_once $gfwww.'include/squal_pre.php';
require_once 'storage.php';

// (A)ctive, (P)ending, (S)uspended ,(D)eleted
$res = db_query_params ('SELECT user_id,user_pw,user_name,realname,email,add_date,timezone FROM users WHERE status=$1 ORDER BY user_id', array('A'));

$user_ids = &util_result_column_to_array ($res, "user_id");
$user_pws = &util_result_column_to_array ($res, "user_pw");
$user_names = &util_result_column_to_array ($res, "user_name");
$user_realnames = &util_result_column_to_array ($res, "realname");
$user_emails = &util_result_column_to_array ($res, "email");
$user_dates = &util_result_column_to_array ($res, "add_date");
$user_timezones = &util_result_column_to_array ($res, "timezone");

$musers = array();

for ($i = 0; $i < count ($user_names); $i++)
{
  $muser = new MigrateUser();
  $muser->id = $user_ids[$i];
  $muser->password = $user_pws[$i];
  $muser->name = $user_names[$i];
  $muser->realname = trim($user_realnames[$i]);
  $muser->email = $user_emails[$i];
  $muser->date = $user_dates[$i];
  $muser->timezone = $user_timezones[$i];

  $musers[] = $muser;
}

file_put_contents('dump/users', serialize($musers));

?>

