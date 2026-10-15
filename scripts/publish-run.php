<?php

/**
 * @file
 * Browser check for the publish step: activates a workspace, publishes it from
 * the UI, and checks that Live now has the staged field, post, Canvas page and
 * redirect.
 *
 *   ddev exec php scripts/publish-run.php "$(ddev drush uli --no-browser | tail -1)" "Summer update" [old URL of the renamed post]
 */

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;

require __DIR__ . '/../vendor/autoload.php';

$base = getenv('DDEV_PRIMARY_URL');
$login = preg_replace('#^https?://[^/]+#', $base, $argv[1] ?? '');
$label = $argv[2] ?? 'Summer update';
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
  $driver->takeScreenshot(sprintf('%s/publish-%02d-%s.png', $shots, $step, $name));
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
$anon = function (string $path) use ($base): array {
  $ch = curl_init($base . $path);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_SSL_VERIFYPEER => FALSE, CURLOPT_SSL_VERIFYHOST => 0]);
  $body = (string) curl_exec($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);
  return [$info['http_code'], $info['redirect_url'] ?? '', $body];
};
$find = fn (string $css) => $driver->findElement(WebDriverBy::cssSelector($css));
$exists = fn (string $css) => count($driver->findElements(WebDriverBy::cssSelector($css))) > 0;
$submit = function (string $css) use ($driver, $find): void {
  $driver->executeScript('arguments[0].click()', [$find($css)]);
  $driver->wait(60)->until(fn () => $driver->executeScript('return document.readyState') === 'complete');
};

// Live before.
[$code] = $anon('/reef-restoration');
$code === 404 || $fail("Live already serves /reef-restoration ($code)");
[$code] = $anon('/blog/reef-restoration');
$code === 404 || $fail("Live already serves the new post ($code)");
$say('Live before publish: no landing page, no new post');

$go($login);
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

$go("/admin/config/workflow/workspaces/manage/$id");
$shot('tracked-changes');
$go("/admin/config/workflow/workspaces/manage/$id/publish");
$shot('publish-confirm');
$exists('#edit-submit') || $fail('no publish confirm button');
$submit('#edit-submit');
$shot('published');
$text = $find('body')->getText();
str_contains($text, 'error') && !str_contains($text, 'Successful') && $fail('publish form reported an error');
$say('workspace published from the UI');

// Live after.
$go('/admin/structure/types/manage/blog/fields');
str_contains($find('body')->getText(), 'Reading time') || $fail('Reading time field missing on Live after publish');
$shot('live-fields');
[$code, , $body] = $anon('/blog/reef-restoration');
$code === 200 || $fail("new post not on Live after publish ($code)");
[$code, , $body] = $anon('/reef-restoration');
$code === 200 && str_contains($body, 'Reef Restoration') || $fail("landing page not on Live after publish ($code)");
// The old URL of the renamed post. The redirect can take a few seconds to show up on
// Live after publish, so poll briefly.
$oldUrl = $argv[3] ?? '/blog/2026-09/inside-reef-restoration-project-bringing-coral-back';
for ($i = 0; $i < 10; $i++) {
  [$code, $to] = $anon($oldUrl);
  if ($code === 301 && str_ends_with($to, '/blog/reef-restoration')) { break; }
  sleep(3);
}
$code === 301 && str_ends_with($to, '/blog/reef-restoration') || $fail("redirect not on Live after publish ($code -> $to)");
$say('Live after publish: field, post, landing page and redirect are all live');
$say('DONE');
