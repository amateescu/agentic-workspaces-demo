<?php
foreach (\Drupal::entityTypeManager()->getStorage('workspace')->loadMultiple() as $w) {
  try { $w->delete(); echo "deleted ", $w->label(), "\n"; }
  catch (\Throwable $e) { echo "DELETE FAILED ", $w->label(), ": ", ($e->getPrevious() ?? $e)->getMessage(), "\n"; }
}
