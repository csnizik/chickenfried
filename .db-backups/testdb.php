<?php
/**
 * triage-db-and-drupal.php
 * Build stamp (UTC): 2025-08-11T21:35:00Z
 */

header('Content-Type: text/html; charset=utf-8');

// Gate access
if (($_SERVER['HTTP_HOST'] ?? '') !== 'localhost' && !isset($_GET['allow'])) {
  http_response_code(403);
  echo 'Access denied. Add ?allow=1 to URL if you need to test on non-localhost';
  exit;
}

// Tiny helpers
function h($s){return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');}
function line($k,$v,$ok=null){$m=$ok===true?'✅':($ok===false?'❌':'•');echo "<p><strong>$m ".h($k).":</strong> ".h($v)."</p>";}
function fail($msg){http_response_code(500); echo "<p style='color:#b00020'>".$msg."</p>"; exit;}

// Build fingerprint (so you know it deployed)
$build_stamp = '2025-08-11T21:35:00Z';
$fingerprint = sha1_file(__FILE__) ?: 'n/a';
$mtime = gmdate('c', @filemtime(__FILE__) ?: time());

echo "<h1>Drupal kernel bootstrap & entity type sanity</h1>";
line('Build stamp (UTC)', $build_stamp);
line('File mtime (UTC)', $mtime);
line('File SHA1', $fingerprint);

// --- Find autoload.php regardless of where this file sits ---
$base = __DIR__;
$candidates = [
  $base . '/autoload.php',
  $base . '/web/autoload.php',
  dirname($base) . '/autoload.php',
  dirname($base) . '/web/autoload.php',
];
$autoload = null;
foreach ($candidates as $p) {
  if (is_file($p)) { $autoload = $p; break; }
}
if (!$autoload) {
  fail("Could not locate autoload.php. Tried:\n<pre>".h(implode("\n",$candidates))."</pre>");
}
$webRoot = dirname($autoload);

// --- Bootstrap Drupal kernel ---
try {
  $autoloader = require_once $autoload;
  $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
  $kernel = \Drupal\Core\DrupalKernel::createFromRequest($request, $autoloader, 'prod');
  $kernel->boot();
  echo "<p style='color:#0a7b0a'><strong>✅ Drupal kernel booted.</strong></p>";
} catch (\Throwable $e) {
  fail("⚠️ Drupal bootstrap failed:<br><pre>".h($e->getMessage())."</pre>");
}

// Optional: cache/container rebuild over HTTP (?do=cr)
if (isset($_GET['do']) && $_GET['do'] === 'cr') {
  // common.inc holds drupal_flush_all_caches()
  $common = $webRoot . '/core/includes/common.inc';
  if (!function_exists('drupal_flush_all_caches')) {
    if (!is_file($common)) {
      fail("Missing file: " . h($common));
    }
    require_once $common;
  }
  \Drupal::service('kernel')->invalidateContainer();
  drupal_flush_all_caches();
  echo "<p><strong>🧹 Performed container & cache rebuild.</strong></p>";
}

// Report autoload + entity definitions
$exists = class_exists(\Drupal\Core\Entity\Display\EntityViewDisplay::class);
line('class_exists(EntityViewDisplay)', $exists ? 'true' : 'false', $exists);

$etm = \Drupal::entityTypeManager();
$has_evd = (int) $etm->hasDefinition('entity_view_display');
$has_efd = (int) $etm->hasDefinition('entity_form_display');

echo "<p><strong>hasDefinition(entity_view_display): $has_evd</strong></p>";
echo "<p>hasDefinition(entity_form_display): $has_efd</p>";

// Extra context
$install_task = \Drupal::state()->get('install_task', '(missing)');
line('Install state (state:install_task)', $install_task, $install_task === 'done');

$mods = \Drupal::service('extension.list.module')->getList();
$enabled = array_keys(array_filter($mods, fn($i)=>!empty($i->status)));
line('Enabled modules (count)', (string)count($enabled), count($enabled)>0);

echo "<hr><p><small>Remove this file after testing. 202508111620 Build: $build_stamp · SHA1: $fingerprint</small></p>";
