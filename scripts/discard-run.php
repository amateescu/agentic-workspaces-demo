<?php

/**
 * @file
 * Browser check for the throwaway workspace step: a workspace with staged config and
 * content is reviewed and deleted from the UI, and Live is untouched.
 *
 *   ddev exec php scripts/discard-run.php "$(ddev drush uli --no-browser | tail -1)" "<label>" "<field label>" <strings expected on the tracked-changes page>...
 */

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;

require __DIR__ . '/../vendor/autoload.php';

$base = getenv('DDEV_PRIMARY_URL');
$login = preg_replace('#^https?://[^/]+#', $base, $argv[1] ?? '');
$label = $argv[2] ?? 'Agent experiment';
$fieldLabel = $argv[3] ?? 'Experiment';
$expect = array_slice($argv, 4) ?: ['Projects', 'field.storage.node.field_experiment', 'system.site'];
$login || exit(2);
$shots = dirname(__DIR__) . '/demo-shots';
is_dir($shots) || mkdir($shots);
$step = 0;
$driver = NULL;

$options = (new ChromeOptions())->addArguments(['--no-sandbox', '--ignore-certificate-errors', '--allow-insecure-localhost', '--disable-features=HttpsUpgrades', '--window-size=1440,1000', '--headless=new']);
$caps = DesiredCapabilities::chrome();
$caps->setCapability(ChromeOptions::CAPABILITY_W3C, $options);
$driver = RemoteWebDriver::create('http://chrome:4444', $caps, 60000, 60000);
register_shutdown_function(static function () use (&$driver): void {
  try { $driver?->quit(); } catch (\Throwable) {}
});
$shot = function (string $name) use (&$step, $driver, $shots): void {
  $step++;
  $driver->takeScreenshot(sprintf('%s/discard-%02d-%s.png', $shots, $step, $name));
};
$say = fn (string $m) => print("- $m\n");
$fail = function (string $m) use ($driver, $shot): never {
  $shot('failure');
  $driver->quit();
  fwrite(STDERR, "FAILED: $m\n");
  exit(1);
};
$go = function (string $path) use ($driver, $base): void {
  $driver->get(str_starts_with($path, 'http') ? $path : $base . $path);
  $driver->wait(30)->until(fn () => $driver->executeScript('return document.readyState') === 'complete');
};
$anon = function (string $path) use ($base): string {
  $ch = curl_init($base . $path);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_SSL_VERIFYPEER => FALSE, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_FOLLOWLOCATION => TRUE]);
  $body = (string) curl_exec($ch);
  curl_close($ch);
  return $body;
};
$find = fn (string $css) => $driver->findElement(WebDriverBy::cssSelector($css));
$exists = fn (string $css) => count($driver->findElements(WebDriverBy::cssSelector($css))) > 0;
$submit = function (string $css) use ($driver, $find): void {
  $driver->executeScript('arguments[0].click()', [$find($css)]);
  $driver->wait(30)->until(fn () => $driver->executeScript('return document.readyState') === 'complete');
};

$go($login);
$say('logged in');

// Find and activate the workspace.
$go('/admin/config/workflow/workspaces');
$id = '';
foreach ($driver->findElements(WebDriverBy::cssSelector('table tbody tr')) as $tr) {
  if (!str_contains($tr->getText(), $label)) { continue; }
  $links = $tr->findElements(WebDriverBy::cssSelector("a[href*='/activate']"));
  $links || $fail("no activate link for '$label'");
  $href = $links[0]->getAttribute('href');
  preg_match('#/manage/([^/]+)/activate#', $href, $m) || $fail("cannot read the workspace id from $href");
  $id = $m[1];
  $go($href);
  $exists('#edit-submit') && $submit('#edit-submit');
  break;
}
$id || $fail("workspace '$label' not listed");
$say("workspace '$label' ($id) active");

// Review: the tracked-changes page lists content and staged config.
$go("/admin/config/workflow/workspaces/manage/$id");
$text = $find('body')->getText();
$shot('tracked-changes');
foreach ($expect as $needle) {
  str_contains($text, $needle) || $fail("tracked changes page does not list '$needle'");
}
$say('tracked changes page lists: ' . implode(', ', $expect));
$go('/admin/structure/types/manage/blog/fields');
str_contains($find('body')->getText(), $fieldLabel) || $fail("staged field '$fieldLabel' not on the blog fields page inside the workspace");
$say("staged field '$fieldLabel' shown on the blog fields page inside the workspace");

// Delete the workspace from the UI.
$go("/admin/config/workflow/workspaces/manage/$id/delete");
$shot('delete-confirm');
$exists('#edit-submit') || $fail('no delete confirm button');
$submit('#edit-submit');
$shot('deleted');
$go('/admin/config/workflow/workspaces');
str_contains($find('body')->getText(), $label) && $fail("workspace '$label' still listed after delete");
$say('workspace deleted from the UI');

// Live is untouched.
$go('/admin/structure/types/manage/blog/fields');
str_contains($find('body')->getText(), $fieldLabel) && $fail('staged field leaked to Live');
$shot('live-fields');
str_contains($anon('/'), 'Reef Rescue HQ') && $fail('site name change leaked to Live');
$say("Live: no '$fieldLabel' field, site name unchanged");
$say('DONE');
