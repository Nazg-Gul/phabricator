<?php

abstract class ManiphestController extends PhabricatorController {

  protected $projectKey;
  protected $taskTypeKey;

  public function willProcessRequest(array $data) {
    $this->projectKey = idx($data, 'projectKey');
    $this->taskTypeKey = idx($data, 'taskTypeKey');
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  private function getBlenderTaskTypeField() {
    $config = PhabricatorEnv::getEnvConfig(
      'maniphest.custom-field-definitions',
      array());
    $task_type = idx($config, 'blender:task-type');
    if (!$task_type) {
      throw new Exception(
        'Custom definition for blender:task-type ' .
        'is not found in maniphest settings.');
    }
    return $task_type;
  }

  public function getBlenderTaskTypes() {
    $task_types = $this->getBlenderTaskTypeField();
    $options = idx($task_types, 'options');
    if (!$options) {
      throw new Exception(
        'Custom definition for blender:task-type ' .
        'doesn\'t have options field.');
    }
    return $options;
  }

  private function buildProjectsNavigation($nav) {
    $user = $this->getRequest()->getUser();
    if (!$this->projectKey) {
      $nav->addLabel(pht('Projects'));

      $show_item_id = celerity_generate_unique_node_id();
      $hide_item_id = celerity_generate_unique_node_id();

      $show_item = id(new PHUIListItemView())
        ->setName(pht('Show all projects'))
        ->setHref('#')
        ->addSigil('reveal-content')
        ->setID($show_item_id);

      $hide_item = id(new PHUIListItemView())
        ->setName(pht('Hide inactive Projects'))
        ->setHref('#')
        ->setStyle('display: none')
        ->setID($hide_item_id)
        ->addSigil('reveal-content');

      $nav->addMenuItem($show_item);
      $nav->addMenuItem($hide_item);

      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($user)
        ->execute();
      $project_ids = array($hide_item_id);

      foreach ($projects as $project) {
        $url = 'project/' . $project->getID();
        $name = $project->getName();
        $is_hide = !in_array(
          $name, array(
            'BF Blender',
            'Game Engine'));
        if ($is_hide) {
          $label_id = celerity_generate_unique_node_id();
          $project_ids[] = $label_id;
          $nav->addMenuItem(
            id(new PHUIListItemView())
              ->setName(pht($name))
              ->setType(PHUIListItemView::TYPE_LINK)
              ->setHref($url)
              ->setStyle('display: none;')
              ->setID($label_id));
        } else {
          $nav->addFilter($url, pht($name));
        }
      }

      Javelin::initBehavior('phabricator-reveal-content');

      $show_item->setMetadata(
        array(
          'showIDs' => $project_ids,
          'hideIDs' => array($show_item_id),
        ));
      $hide_item->setMetadata(
        array(
          'showIDs' => array($show_item_id),
          'hideIDs' => $project_ids,
        ));
    } else if (!$this->taskTypeKey) {
      $project = id(new PhabricatorProjectQuery())
        ->setViewer($user)
        ->withIDs(array($this->projectKey))
        ->executeOne();
      if ($project) {
        $nav->addLabel(pht($project->getName()));
        $task_types = $this->getBlenderTaskTypes();
        foreach ($task_types as $id => $name) {
          $nav->addFilter(
            'project/' . $this->projectKey . '/type/' . $id,
            pht($name));
        }
      }
    }
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('create', pht('Create Task'));
    }

    $this->buildProjectsNavigation($nav);

    id(new ManiphestTaskSearchEngine())
      ->setViewer($user)
      ->setProjectKey($this->projectKey)
      ->setTaskTypeKey($this->taskTypeKey)
      ->addNavigationItems($nav->getMenu());

    if ($user->isLoggedIn()) {
      // For now, don't give logged-out users access to reports.
      $nav->addLabel(pht('Reports'));
      $nav->addFilter('report', pht('Reports'));
    }

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    if ($this->projectKey) {
      $user = $this->getRequest()->getUser();
      $project = id(new PhabricatorProjectQuery())
        ->setViewer($user)
        ->withIDs(array($this->projectKey))
        ->executeOne();
      if ($project) {
        $crumb = id(new PhabricatorCrumbView())
            ->setName(pht($project->getName()));
        if ($this->taskTypeKey)
          $crumb->setHref('/maniphest/project/'.$this->projectKey);
        $crumbs->addCrumb($crumb);
      } else {
        throw new Exception('Unknown project was passed via the url');
      }
    }

    if ($this->taskTypeKey) {
      $task_types = $this->getBlenderTaskTypes();
      $type = idx($task_types, $this->taskTypeKey);
      if ($type) {
        $crumbs->addCrumb(
          id(new PhabricatorCrumbView())
            ->setName(pht($type)));
      } else {
        throw new Exception('Unknown task type was passed via the url');
      }
    }

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Task'))
        ->setHref($this->getApplicationURI('task/create/'))
        ->setIcon('create'));

    return $crumbs;
  }

  protected function renderSingleTask(ManiphestTask $task) {
    $user = $this->getRequest()->getUser();

    $phids = $task->getProjectPHIDs();
    if ($task->getOwnerPHID()) {
      $phids[] = $task->getOwnerPHID();
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs($phids)
      ->execute();

    $view = id(new ManiphestTaskListView())
      ->setUser($user)
      ->setShowSubpriorityControls(true)
      ->setShowBatchControls(true)
      ->setHandles($handles)
      ->setTasks(array($task));

    return $view;
  }


}
