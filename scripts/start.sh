#!/bin/bash
set -Eeuo pipefail

# --- configuration ---
DRUPAL_ROOT="/var/www/html"
DRUSH="$DRUPAL_ROOT/vendor/bin/drush"
APACHE_CMD="apache2-foreground"
BOOTSTRAP_TIMEOUT="${BOOTSTRAP_TIMEOUT:-120}"   # seconds to wait for a successful bootstrap
SLEEP_INTERVAL=2

umask 022
cd "$DRUPAL_ROOT"

# If anything fails, ensure maintenance mode is OFF and still start Apache so logs/UI are reachable.
cleanup_and_start() {
  $DRUSH state:set system.maintenance_mode 0 --input-format=integer -y || true
  exec "$APACHE_CMD"
}
trap cleanup_and_start EXIT

echo "Waiting for Drupal bootstrap (timeout: ${BOOTSTRAP_TIMEOUT}s)..."
SECONDS=0
until "$DRUSH" status --field=bootstrap 2>/dev/null | grep -q "Successful"; do
  "$DRUSH" status || true
  if (( SECONDS >= BOOTSTRAP_TIMEOUT )); then
    echo "WARNING: Timed out waiting for Drupal bootstrap. Proceeding anyway."
    break
  fi
  sleep "$SLEEP_INTERVAL"
done
echo "Bootstrap check complete (elapsed ${SECONDS}s)."

# Maintenance on, message set, deploy, maintenance off.
$DRUSH state:set system.maintenance_mode 1 --input-format=integer -y
$DRUSH config:set system.maintenance message "The website is currently undergoing scheduled maintenance. We apologize for any inconvenience. Please check back later." -y

# 'drush deploy' includes updb + cim (+ deploy hooks)
$DRUSH deploy -vvv -y

$DRUSH state:set system.maintenance_mode 0 --input-format=integer -y

# Success path: prevent the EXIT trap and run Apache as PID 1
trap - EXIT
exec "$APACHE_CMD"
