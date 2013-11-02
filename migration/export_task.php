#!/usr/bin/env php
<?php
/*
 *
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
require_once $gfwww.'tracker/include/ArtifactTypeHtml.class.php';
require_once $gfwww.'tracker/include/ArtifactHtml.class.php';
require_once $gfwww.'tracker/include/ArtifactFileHtml.class.php';
require_once 'storage.php';

function lookup_user_name($user_id)
{
  $res = db_query_params ('SELECT user_name FROM users WHERE user_id=$1', array($user_id));
  return db_result($res, 0, 'user_name');
}

for ($aid=intval($argv[1]); $aid<intval($argv[2]); $aid++) {

/* lookup by id */
$group_id = null;
$atid = null;

if ($aid && (!$group_id && !$atid)) {
  $a =& artifact_get_object($aid);

  if (!$a || !is_object($a) || $a->isError()) {
    echo "SKIP " . $aid . "\n";
    continue;
  } else {
    echo "GO " . $aid . "\n";
    $group_id=$a->ArtifactType->Group->getID();
    $atid=$a->ArtifactType->getID();
  }
}

$group =& group_get_object($group_id);

if (!$group || !is_object($group) || $group->isError())
  echo "Group invalid\n";

$ath = new ArtifactTypeHtml($group,$atid);

if (!$ath || !is_object($ath) || $ath->isError())
  echo "Artifact type invalid\n";

$ah=new ArtifactHtml($ath,$aid);

if (!$ah || !is_object($ah) || $ah->isError())
  echo "Artifact invalid\n";

/* create task */
$mtasks = array();

$mtask = new MigrateTask();
$mtask->id = $ah->getID();
$mtask->author = $ah->getSubmittedUnixName();
$mtask->title = $ah->getSummary();
$mtask->description = $ah->getDetails();
$mtask->date = $ah->getOpenDate();
$mtask->assign = $ah->getAssignedUnixName();
$mtask->state = $ah->getStatusName();
$mtask->priority = $ah->getPriority();

$mtask->project = $group->getPublicName();
$mtask->tracker = $ath->getName();

/* comments */
$result= $ah->getMessages();
$rows=db_numrows($result);

for ($i=0; $i < $rows; $i++) {
  $mcomment = new MigrateComment();
  $mcomment->user = db_result($result, $i,'user_name');
  $mcomment->description = db_result($result, $i, 'body');
  $mcomment->date = db_result($result, $i, 'adddate');
  $mtask->comments[] = $mcomment;
}

/* history */
$history=$ah->getHistory();
$historyrows= db_numrows($history);

/* files */
$file_list =& $ah->getFiles();
$rows=count($file_list);

for ($i=0; $i<$rows; $i++) {
  $af = $file_list[$i];

  $afd=new ArtifactFile($ah,$af->getID());

  $fileuser = null;

  for ($j=0; $j < $historyrows; $j++) {
    $hvalue = db_result($history, $j, 'old_value');
    if($hvalue == $af->getID() + ": " + $af->getName())
      $fileuser = db_result($history, $j, 'user_name');
  }

  $mfile = new MigrateFile();
  $mfile->user = $fileuser;
  $mfile->name = $af->getName();
  $mfile->date = $af->getDate();
  $mfile->type = $af->getType();
  $mfile->contents = $afd->getData();
  $mtask->files[] = $mfile;
}

/* history */
for ($i=0; $i < $historyrows; $i++) {
  $mhistory = new MigrateHistory();
  $mhistory->user = db_result($history, $i, 'user_name');
  $mhistory->date = db_result($history, $i, 'entrydate');
  $mhistory->field = db_result($history, $i, 'field_name');
  $mhistory->old = db_result($history, $i, 'old_value');
  $mtask->history[] = $mhistory;
}

/* subscribers */
$res = db_query_params ('SELECT user_id FROM artifact_monitor WHERE artifact_id=$1', array($ah->getID()));
$ccids = util_result_column_to_array($res);

$ccs = array();
foreach($ccids as $ccid)
  if($ccid)
    $ccs[] = lookup_user_name($ccid);

if($ah->getAssignedTo())
  $ccs[] = lookup_user_name($ah->getAssignedTo());
if($ah->getSubmittedBy())
  $ccs[] = lookup_user_name($ah->getSubmittedBy());

$result= $ah->getMessages();
$rows=db_numrows($result);

for ($i=0; $i < $rows; $i++)
  $ccs[] = lookup_user_name(db_result($result,$i,'user_id'));

$mtask->ccs = array_unique($ccs);
$mtask->extra_fields = $ah->getExtraFieldDataText();

file_put_contents('dump/task_' . $ah->getID(), serialize($mtask));

}

?>

