<?php

/**
 * @file
 * Publishes the 'stage' workspace and checks Live picked up the staged field.
 *
 *   ddev drush scr scripts/verify-publish.php
 */

use Drupal\node\Entity\Node;
use Drupal\workspaces\Entity\Workspace;

$out = static fn (string $m) => print("$m\n");
$ws = current(\Drupal::entityTypeManager()->getStorage('workspace')->loadByProperties(['label' => 'Stage']));
$ws_id = (string) $ws->id();
$before = (int) \Drupal::database()->query('SELECT COUNT(*) FROM {pal_activity}')->fetchField();
$ws->publish();
$out('published workspace stage');
\Drupal::service('entity.memory_cache')->deleteAll();
\Drupal::entityTypeManager()->getStorage('node')->resetCache();
$live = \Drupal::configFactory()->get('field.storage.node.field_reading_time');
$out('LIVE: field.storage.node.field_reading_time exists? ' . ($live->isNew() ? 'no (BUG)' : 'yes'));
$nid = (int) current(\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'blog')->sort('nid')->range(0, 1)->execute());
$node = Node::load($nid);
$out("LIVE: node $nid title = " . $node->getTitle() . ' reading_time=' . ($node->hasField('field_reading_time') ? $node->get('field_reading_time')->value : 'NO FIELD'));
$after = (int) \Drupal::database()->query('SELECT COUNT(*) FROM {pal_activity}')->fetchField();
$out("PAL: activities before publish=$before after=$after");
$rows = \Drupal::database()->query('SELECT activity_type, outcome FROM {pal_activity} ORDER BY id DESC LIMIT 12')->fetchAll();
foreach ($rows as $r) { $out('  ' . $r->activity_type . ' ' . $r->outcome); }
