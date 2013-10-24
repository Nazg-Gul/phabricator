<?php

final class ManiphestSearchController
  extends PhabricatorApplicationSearchController {

  private $projectKey;
  private $taskTypeKey;
  private $taskTypes;

  protected function getDescruptionForQuery($query) {
    $description = 'Showing results for ';
    if ($query->getIsBuiltin()) {
      $description .= ' query "%s"';
    } else {
      $description .= ' saved query "%s"';
    }

    if ($this->projectKey) {
      $user = $this->getRequest()->getUser();
      $project = id(new PhabricatorProjectQuery())
        ->setViewer($user)
        ->withIDs(array($this->projectKey))
        ->executeOne();
      if ($project) {
        $description .= ' from project "'.pht($project->getName()).'"';
      } else {
        throw new Exception('Unknown project');
      }
    }

    if ($this->taskTypeKey) {
      $task_type = idx($this->taskTypes, $this->taskTypeKey);
      if ($task_type) {
        $description .= ', task type "'.pht($task_type).'"';
      } else {
        throw new Exception('Unknown task type');
      }
    }

    return pht(
        $description,
        $query->getQueryName());
  }

  public function setProjectKey($projectKey) {
    $this->projectKey = $projectKey;
    return $this;
  }

  public function setTaskTypes($taskTypes) {
    $this->taskTypes = $taskTypes;
    return $this;
  }

  public function setTaskTypeKey($taskTypeKey) {
    $this->taskTypeKey = $taskTypeKey;
    return $this;
  }
}
