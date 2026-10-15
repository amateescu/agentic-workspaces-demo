<?php

/**
 * @file
 * Points the AI module at Anthropic, reading the API key from the
 * ANTHROPIC_API_KEY environment variable through a Key entity. Idempotent.
 *
 *   ddev drush scr scripts/configure-anthropic.php
 *
 * The variable comes from .ddev/config.local.yaml (web_environment), which
 * DDEV keeps out of version control.
 */

use Drupal\key\Entity\Key;

$out = static fn (string $m) => print("$m\n");
$model = 'claude-sonnet-5';

$key = Key::load('anthropic_api_key') ?? Key::create(['id' => 'anthropic_api_key', 'label' => 'Anthropic API key']);
$key->set('key_type', 'authentication');
$key->set('key_provider', 'env');
$key->set('key_provider_settings', ['env_variable' => 'ANTHROPIC_API_KEY', 'base64_encoded' => FALSE, 'strip_line_breaks' => TRUE]);
$key->set('key_input', 'none');
$key->save();

\Drupal::configFactory()->getEditable('ai_provider_anthropic.settings')->set('api_key', 'anthropic_api_key')->save();

$settings = \Drupal::configFactory()->getEditable('ai.settings');
$defaults = $settings->get('default_providers') ?? [];
foreach (['chat', 'chat_with_complex_json', 'chat_with_tools', 'chat_with_structured_response', 'chat_with_image_vision'] as $op) {
  $defaults[$op] = ['provider_id' => 'anthropic', 'model_id' => $model];
}
$settings->set('default_providers', $defaults)->save();

$value = (string) $key->getKeyValue();
$out('Key anthropic_api_key reads ANTHROPIC_API_KEY: ' . ($value === '' ? 'EMPTY, set it in .ddev/config.local.yaml and ddev restart' : 'present (' . strlen($value) . ' chars)'));
$out("Chat operations now default to anthropic / $model.");
