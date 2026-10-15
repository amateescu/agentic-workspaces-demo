<?php

/**
 * @file
 * Checks the demo's core claim: a field storage created in a workspace stays
 * out of Live until publish, and node values for it only reach the workspace
 * revision.
 *
 *   ddev drush scr scripts/verify-workspace-field.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\workspaces\Entity\Workspace;

$wm = \Drupal::service('workspaces.manager');
$config = \Drupal::configFactory();
$out = static fn (string $m) => print("$m\n");
$nid = (int) current(\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'blog')->sort('nid')->range(0, 1)->execute());

// WSE replaces a new workspace's ID with its UUID, so find it by label.
$ws = current(\Drupal::entityTypeManager()->getStorage('workspace')->loadByProperties(['label' => 'Stage'])) ?: Workspace::create(['id' => 'stage', 'label' => 'Stage']);
$ws->save();
$ws_id = (string) $ws->id();

$wm->executeInWorkspace($ws_id, function () use ($out, $nid) {
  if (!FieldStorageConfig::loadByName('node', 'field_reading_time')) {
    FieldStorageConfig::create(['field_name' => 'field_reading_time', 'entity_type' => 'node', 'type' => 'integer'])->save();
    $out('IN WS: created field.storage.node.field_reading_time');
  }
  if (!FieldConfig::loadByName('node', 'blog', 'field_reading_time')) {
    FieldConfig::create(['field_name' => 'field_reading_time', 'entity_type' => 'node', 'bundle' => 'blog', 'label' => 'Reading time'])->save();
    $out('IN WS: created field.field.node.blog.field_reading_time');
  }
  $node = Node::load($nid);
  $node->set('field_reading_time', 7);
  $node->setTitle($node->getTitle() . ' (staged)');
  $node->setNewRevision();
  $node->save();
  $out("IN WS: node $nid saved with field_reading_time=7, title=" . $node->getTitle());
});

// Fresh request semantics: drop static caches so Live is read from storage.
\Drupal::service('entity.memory_cache')->deleteAll();
\Drupal::entityTypeManager()->getStorage('node')->resetCache();
$live_storage = $config->get('field.storage.node.field_reading_time');
$out('LIVE: field.storage.node.field_reading_time exists in config? ' . ($live_storage->isNew() ? 'no' : 'YES (leak)'));
$live_node = Node::load($nid);
$out("LIVE: node $nid title = " . $live_node->getTitle());
$out("LIVE: node $nid has field_reading_time definition? " . ($live_node->hasField('field_reading_time') ? 'YES (leak)' : 'no'));

$wm->executeInWorkspace($ws_id, function () use ($out, $nid) {
  \Drupal::entityTypeManager()->getStorage('node')->resetCache();
  $n = Node::load($nid);
  $out("IN WS (reload): node $nid title = " . $n->getTitle() . ', field_reading_time=' . $n->get('field_reading_time')->value);
});

$db = \Drupal::database();
$out('DB: tables node__field_reading_time / node_revision__field_reading_time exist? ' . ($db->schema()->tableExists('node__field_reading_time') ? 'yes' : 'no') . ' / ' . ($db->schema()->tableExists('node_revision__field_reading_time') ? 'yes' : 'no'));
$out("DB: default revision rows for node $nid: " . $db->query('SELECT COUNT(*) FROM {node__field_reading_time} WHERE entity_id = :nid', [':nid' => $nid])->fetchField());
$row = $db->query('SELECT revision_id, field_reading_time_value AS v FROM {node_revision__field_reading_time} WHERE entity_id = :nid ORDER BY revision_id DESC LIMIT 1', [':nid' => $nid])->fetchAssoc();
$out("DB: workspace revision of node $nid vid=" . ($row['revision_id'] ?? 'none') . ' field_reading_time=' . ($row['v'] ?? 'none'));
