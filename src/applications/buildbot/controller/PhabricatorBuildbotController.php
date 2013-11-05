<?php

/**
 * @group buildbot
 */
final class PhabricatorBuildbotController extends PhabricatorController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
      ->setName('Buildbot'));

    return $crumbs;
  }

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {

    $html = '
      <script type="text/javascript">
        function resize_builder_iframe() {
          var iframe = document.getElementById("builder_iframe");
          window.addEventListener("message", function(event) {
            if (event.origin !== "http://builder.blender.org") return;
            if (isNaN(event.data)) return;
            iframe.height = (parseInt(event.data) + 32) + "px";
          }, false);
        }
      </script>

      <iframe src="http://builder.blender.org/download" id="builder_iframe" onload="resize_builder_iframe();" style="width: 100%; border: 0;">';

    $panel = new AphrontPanelView();
    $panel->appendChild(phutil_safe_html($html));
    $panel->setNoBackground();

    $content = array(
      $panel
    );

    $crumbs = $this->buildApplicationCrumbs();

    return $this->buildApplicationPage(
      array(
         $crumbs,
         $panel,
      ),
      array(
        'title' => 'Buildbot',
        'device' => true,
      ));
  }

}
