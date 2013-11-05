<?php

final class PhabricatorApplicationDocumentation extends PhabricatorApplication {

  public function getBaseURI() {
    return 'http://wiki.blender.org/index.php/Dev:Contents';
  }

  public function getIconName() {
    return 'diviner';
  }

  public function getShortDescription() {
    return 'Developer Wiki';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x94";
  }

  public function getFlavorText() {
    return pht('Developer wiki documentation.');
  }

  public function getApplicationGroup() {
    return self::GROUP_CORE;
  }

}
