<?php
// Stages the walkthrough's Reading time field (plain text, field_reading_time) on
// blog posts, with its form and view display components, in the workspace with
// the given label. Creates the workspace when it does not exist. Idempotent.
//
//   ddev drush scr scripts/stage-field.php -- "Summer update"
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\workspaces\Entity\Workspace;
$label = $extra[0] ?? '';
if ($label === '') { echo "usage: ddev drush scr scripts/stage-field.php -- \"<workspace label>\"\n"; return; }
$ws = current(\Drupal::entityTypeManager()->getStorage('workspace')->loadByProperties(['label' => $label]));
if (!$ws) {
  $ws = Workspace::create(['id' => substr(preg_replace('/[^a-z0-9_]+/', '_', strtolower($label)), 0, 32), 'label' => $label]);
  $ws->save();
}
\Drupal::service('workspaces.manager')->executeInWorkspace($ws->id(), function () {
  if (!FieldStorageConfig::loadByName('node', 'field_reading_time')) {
    FieldStorageConfig::create(['field_name' => 'field_reading_time', 'entity_type' => 'node', 'type' => 'string'])->save();
  }
  if (!FieldConfig::loadByName('node', 'blog', 'field_reading_time')) {
    FieldConfig::create(['field_name' => 'field_reading_time', 'entity_type' => 'node', 'bundle' => 'blog', 'label' => 'Reading time'])->save();
  }
  $repo = \Drupal::service('entity_display.repository');
  $repo->getFormDisplay('node', 'blog')->setComponent('field_reading_time', ['type' => 'string_textfield', 'weight' => -4])->save();
  $repo->getViewDisplay('node', 'blog')->setComponent('field_reading_time', ['type' => 'string', 'weight' => -4])->save();
});
echo "field_reading_time staged in workspace '$label' (", $ws->id(), ")\n";
