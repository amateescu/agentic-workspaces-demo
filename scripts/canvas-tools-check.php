<?php
// Builds a Canvas page with canvas_tools inside a workspace and publishes the draft there.
\Drupal::service('account_switcher')->switchTo(\Drupal\user\Entity\User::load(1));
$m = \Drupal::service('plugin.manager.tool');
$run = function (string $id, array $values) use ($m) {
  $t = $m->createInstance($id);
  foreach ($values as $k => $v) { $t->setInputValue($k, $v); }
  $t->execute();
  echo ($t->getResultStatus() ? 'OK   ' : 'FAIL '), $id, ': ', mb_substr(strip_tags((string) $t->getResultMessage()), 0, 160), "\n";
  return $t->getResult();
};
$ws = \Drupal::entityTypeManager()->getStorage('workspace')->create(['id' => 'canvas_probe', 'label' => 'Canvas probe']);
$ws->save();
\Drupal::service('workspaces.manager')->executeInWorkspace($ws->id(), function () use ($run) {
  $r = $run('canvas_create_page', ['title' => 'Reef restoration update', 'path' => '/reef-restoration-update']);
  $out = $r?->getContextValues() ?? [];
  echo 'outputs: ', json_encode(array_map(fn ($v) => is_scalar($v) ? $v : gettype($v), $out)), "\n";
  $target = $out['page_id'] ?? NULL;
  if ($target === NULL) { return; }
  $added = $run('canvas_add_component', ['target_type' => 'page', 'target_id' => (string) $target, 'component_id' => 'sdc.haven_theme.heading']);
  $uuid = $added?->getContextValues()['uuid'] ?? NULL;
  echo 'component uuid: ', $uuid ?? 'none', "\n";
  if ($uuid) {
    $run('canvas_update_component_props', ['target_type' => 'page', 'target_id' => (string) $target, 'uuid' => $uuid, 'props' => json_encode(['heading_text' => 'Reef restoration update', 'level' => 2])]);
  }
  $run('canvas_publish_auto_saves', ['target_type' => 'page', 'target_id' => (string) $target]);
});
\Drupal::entityTypeManager()->getStorage('canvas_page')->resetCache();
$pages = \Drupal::entityTypeManager()->getStorage('canvas_page')->loadByProperties(['title' => 'Reef restoration update']);
$page = current($pages);
echo 'LIVE default revision: ', $page ? ('published=' . var_export($page->isPublished(), TRUE) . ' components=' . count($page->get('components'))) : 'none', "\n";
\Drupal::service('workspaces.manager')->executeInWorkspace($ws->id(), function () use ($page) {
  \Drupal::entityTypeManager()->getStorage('canvas_page')->resetCache();
  $p = \Drupal::entityTypeManager()->getStorage('canvas_page')->load($page->id());
  echo 'WORKSPACE revision: published=', var_export($p->isPublished(), TRUE), ' components=', count($p->get('components')), "\n";
});
echo 'tracked in workspace: ', count(\Drupal::service('workspaces.tracker')->getTrackedEntities($ws->id(), 'canvas_page')['canvas_page'] ?? []), "\n";
