<?php

/**
 * @file
 * Checks that redirects are workspace-supported: a redirect created in the
 * 'Redirect check' workspace resolves there and not on Live.
 *
 *   ddev drush scr scripts/verify-redirect.php
 */

use Drupal\redirect\Entity\Redirect;
use Drupal\workspaces\Entity\Workspace;

$out = static fn (string $m) => print("$m\n");
$repo = \Drupal::service('redirect.repository');
$lookup = static fn () => ($m = $repo->findMatchingRedirect('old-reef-story', [], 'en')) ? $m->getRedirectUrl()->toString() : 'none';

$type = \Drupal::entityTypeManager()->getDefinition('redirect');
$info = \Drupal::service('workspaces.information');
$out('redirect: revisionable=' . var_export($type->isRevisionable(), TRUE) . ' supported=' . var_export($info->isEntityTypeSupported($type), TRUE));

// WSE replaces a new workspace's ID with its UUID, so find it by label.
$ws = current(\Drupal::entityTypeManager()->getStorage('workspace')->loadByProperties(['label' => 'Redirect check'])) ?: Workspace::create(['id' => 'redirect_check', 'label' => 'Redirect check']);
$ws->save();
$ws_id = (string) $ws->id();
\Drupal::service('workspaces.manager')->executeInWorkspace($ws_id, function () use ($out, $lookup) {
  if (!\Drupal::service('redirect.repository')->findBySourcePath('old-reef-story')) {
    $r = Redirect::create(['redirect_source' => ['path' => 'old-reef-story'], 'redirect_redirect' => ['uri' => 'internal:/node/1'], 'status_code' => 301, 'language' => 'en']);
    $r->save();
    $out('IN WS: saved redirect ' . $r->id() . ' rev ' . $r->getRevisionId());
  }
  $out('IN WS: lookup -> ' . $lookup());
});
\Drupal::service('entity.memory_cache')->deleteAll();
$out('LIVE: lookup -> ' . $lookup() . ' (must be none)');
