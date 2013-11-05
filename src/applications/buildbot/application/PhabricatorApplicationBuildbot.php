<?php

final class PhabricatorApplicationBuildbot extends PhabricatorApplication {

  public function getBaseURI() {
    return '/buildbot/';
  }

  public function getIconName() {
    return 'phlux';
  }

  public function getShortDescription() {
    return 'Automated Builds';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x94";
  }

  public function getFlavorText() {
    return pht('Builds by bots.');
  }

  public function getApplicationGroup() {
    return self::GROUP_CORE;
  }

  public function getRoutes() {
    return array(
      '/buildbot/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorBuildbotController',
      ),
    );
  }

}
