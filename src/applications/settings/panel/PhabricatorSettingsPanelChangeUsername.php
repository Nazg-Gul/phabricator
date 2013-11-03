<?php

final class PhabricatorSettingsPanelChangeUsername
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'changeusername';
  }

  public function getPanelName() {
    return pht('Change Username');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function getAlternatives($user) {
    $root = dirname(phutil_get_library_root('phabricator'));
    require $root.'/migration/dedup.php';

    $alternatives = array();

    foreach($migrate_dedup_users as $from_user => $to_user) {
      if($from_user == $user->getUserName()) {
        $alternatives[$to_user] = $to_user;
      }
      else if($to_user == $user->getUserName()) {
        $alternatives[$from_user] = $from_user;
      }
    }

    return $alternatives;
  }

  public function isEnabledForUser($user) {
    return count($this->getAlternatives($user)) > 0;
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $alternatives = $this->getAlternatives($user);
    $new_username = $request->getStr("new_username");

    if ($request->isFormPost()) {
      if (array_key_exists($new_username, $alternatives)) {
        id(new PhabricatorUserEditor())
          ->setActor($user)
          ->changeUsername($user, $new_username);

        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?saved=true'));
      }
    }

    $instructions = pht(
      "Multiple of your accounts were merged into one because because Phabricator does not " .
      "support multiple accounts with the same email address. Here you can change your " .
      "account name your preferred one. For security purposes you must also enter choose " .
      "your password again (can be the same or new), for this you will receive an email.\n\n" .
      "Current username: **" . $user->getUserName() . "**.");

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions($instructions)
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel(pht('Other Username'))
        ->setName("new_username")
        ->setValue($user->getUserName())
        ->setOptions($alternatives))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Change Username')));

    $error_view = null;

    if ($request->getBool('saved')) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Username changed'))
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setErrors(array(pht('Your username has been changed, check your mail inbox for instructions on how to reset your password.')));
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Change Username'))
      ->setFormError($error_view)
      ->setForm($form);

    return array(
      $form_box,
    );
  }
}

