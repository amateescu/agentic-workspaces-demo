<?php
// Creates a workspace by label and stages field_reading_time in it. Idempotent.
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\workspaces\Entity\Workspace;
$label = $extra[0] ?? 'Reading time demo';
$existing = \Drupal::entityTypeManager()->getStorage('workspace')->loadByProperties(['label' => $label]);
$ws = $existing ? current($existing) : Workspace::create(['id' => 'reading_time_demo', 'label' => $label]);
if (!$existing) { $ws->save(); }
\Drupal::service('workspaces.manager')->executeInWorkspace($ws->id(), function () {
  if (!FieldStorageConfig::loadByName('node', 'field_reading_time')) {
    FieldStorageConfig::create(['field_name' => 'field_reading_time', 'entity_type' => 'node', 'type' => 'integer'])->save();
  }
  if (!FieldConfig::loadByName('node', 'blog', 'field_reading_time')) {
    FieldConfig::create(['field_name' => 'field_reading_time', 'entity_type' => 'node', 'bundle' => 'blog', 'label' => 'Reading time'])->save();
  }
  $repo = \Drupal::service('entity_display.repository');
  $repo->getFormDisplay('node', 'blog')->setComponent('field_reading_time', ['type' => 'number', 'weight' => -4])->save();
  $repo->getViewDisplay('node', 'blog')->setComponent('field_reading_time', ['type' => 'number_integer', 'weight' => -4])->save();
});
echo "workspace {$ws->id()} ready with field_reading_time staged\n";
