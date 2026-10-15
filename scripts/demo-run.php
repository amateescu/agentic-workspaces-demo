<?php

/**
 * @file
 * Drives the demo through the browser, without the AI steps.
 *
 *   ddev exec php scripts/demo-run.php "$(ddev drush uli --no-browser | tail -1)" <workspace label> <blog nid>
 *
 * Screenshots land in demo-shots/. The script prints one line per step and
 * exits non-zero on the first failed check.
 */

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

require __DIR__ . '/../vendor/autoload.php';

$base = getenv('DDEV_PRIMARY_URL');
$login = $argv[1] ?? '';
if (!$login) {
  fwrite(STDERR, "Pass the one-time login URL as the first argument.\n");
  exit(2);
}
$login = preg_replace('#^https?://[^/]+#', $base, $login);
$shots = dirname(__DIR__) . '/demo-shots';
is_dir($shots) || mkdir($shots);
$step = 0;
$driver = NULL;

$options = (new ChromeOptions())->addArguments(['--no-sandbox', '--ignore-certificate-errors', '--allow-insecure-localhost', '--disable-features=HttpsUpgrades', '--window-size=1440,1000', '--headless=new']);
$caps = DesiredCapabilities::chrome();
$caps->setCapability(ChromeOptions::CAPABILITY_W3C, $options);
$driver = RemoteWebDriver::create('http://chrome:4444', $caps, 60000, 60000);
register_shutdown_function(static function () use (&$driver): void {
  try {
    $driver?->quit();
  }
  catch (\Throwable) {
  }
});

$shot = function (string $name) use (&$step, $driver, $shots): void {
  $step++;
  $driver->takeScreenshot(sprintf('%s/%02d-%s.png', $shots, $step, $name));
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
  // JS click avoids Gin's sticky action bar intercepting a native click.
  $driver->executeScript('arguments[0].click()', [$find($css)]);
  $driver->wait(30)->until(fn () => $driver->executeScript('return document.readyState') === 'complete');
};

// 1. Log in and look at Live.
$go($login);
$shot('logged-in');
$go('/blog');
$exists('body.user-logged-in') || $fail('login did not stick');
$say('logged in, blog listing on Live');
$shot('live-blog');

// 2. The workspace with the staged field is prepared before this script runs.
// Activate it from the listing (plain core switch, instant).
$label = $argv[2] ?? 'Reading time demo';
$nid = (int) ($argv[3] ?? 0);
$nid || $fail('Pass the blog post ID as the third argument.');
$go('/admin/config/workflow/workspaces');
$shot('workspaces-list');
$rows = $driver->findElements(WebDriverBy::cssSelector('table tbody tr'));
$activated = FALSE;
foreach ($rows as $tr) {
  if (!str_contains($tr->getText(), $label)) { continue; }
  $links = $tr->findElements(WebDriverBy::cssSelector("a[href*='/activate']"));
  $links || $fail("no activate link in the '$label' row");
  $go($links[0]->getAttribute('href'));
  $exists('#edit-submit') && $submit('#edit-submit');
  $activated = TRUE;
  break;
}
$activated || $fail("workspace '$label' not found in the listing");
$go('/admin/content');
$selected = trim((string) $driver->executeScript("var s = document.querySelector('select[name*=workspace]'); if (!s) return ''; var o = s.options[s.selectedIndex]; return o ? o.text : '';"));
str_contains($selected, $label) || $fail("switcher shows '$selected', expected '$label'");
$workspaceId = trim((string) $driver->executeScript("var s = document.querySelector('select[name*=workspace]'); return s ? s.value : '';"));
$say("workspace '$label' active ($workspaceId)");
$shot('workspace-activated');

// 4. The field is already staged in this workspace; confirm it is listed.
$machine = 'reading_time';
$go('/admin/structure/types/manage/blog/fields');
str_contains($find('body')->getText(), 'Reading time') || $fail('field not listed on the blog fields page in the workspace');
$say('field "Reading time" (field_reading_time) shown on the blog fields page in the workspace');
$shot('fields-in-workspace');

// 6. Set a value on a blog post inside the workspace.
$go('/node/' . $nid . '/edit');
$input = $driver->findElements(WebDriverBy::cssSelector("input[name='field_{$machine}[0][value]']"));
$input || $fail('reading time input missing on the node form in the workspace');
$input[0]->clear();
$input[0]->sendKeys('7');
$title = $find('#edit-title-0-value');
$original = $title->getAttribute('value');
$title->clear();
$title->sendKeys($original . ' (staged)');
$submit('#edit-submit');
$shot('node-saved-in-workspace');
$say("node $nid saved in the workspace with reading time 7 and a staged title");

// 7. Live is untouched.
$live = $anon('/blog');
str_contains($live, '(staged)') && $fail('Live blog listing shows the staged title');
$say('Live blog listing unchanged');

// 8. PAL report.
$go('/admin/reports/activity-log');
$shot('pal-report');
$text = $find('body')->getText();
(str_contains($text, 'field_' . $machine) || str_contains($text, 'Reading time')) || $fail('PAL report does not mention the staged field');
$say('PAL report lists the staged changes');

// 9. Publish.
$go('/admin/config/workflow/workspaces/manage/' . $workspaceId . '/publish');
$exists('#edit-submit') || $fail('publish confirm form did not load');
$shot('publish-confirm');
$submit('#edit-submit');
$shot('published');
$say('workspace published');

// 10. Live has it now.
$live = $anon('/blog');
str_contains($live, '(staged)') || $fail('Live blog listing does not show the published title');
$driver->manage()->deleteAllCookies();
$go('/blog');
$shot('live-after-publish');
$say('Live shows the published changes');

$driver->quit();
echo "DEMO RUN OK\n";
