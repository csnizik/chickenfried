#!/usr/bin/env bash
set -euo pipefail

# Define logging function; call with `log "Message: $VARIABLE"`
log() { printf '[entrypoint] %s\n' "$*" >&2; }

# Print latest git commit (if available via build-arg)
log "###################"
log "[BUILD] github.sha=${GIT_SHA:-unknown}"
log "###################"

# ============================================================================
# BACKGROUND CRON CONFIGURATION
# Set BACKGROUND_CRON_ENABLED=1 to enable background cron execution
# Set BACKGROUND_CRON_INTERVAL to change interval (default: 120 seconds)
# ============================================================================
BACKGROUND_CRON_ENABLED="${BACKGROUND_CRON_ENABLED:-0}"
BACKGROUND_CRON_INTERVAL="${BACKGROUND_CRON_INTERVAL:-120}"
BACKGROUND_CRON_PID=""

# Background cron loop function
start_background_cron() {
  local drush_bin="$1"
  local interval="$2"

  log "[CRON] Starting background cron loop (interval: ${interval}s)"

  (
    while true; do
      sleep "$interval"

      # Skip if drush cron is already running (prevent overlap)
      if pgrep -f "drush.*cron" > /dev/null 2>&1; then
        log "[CRON] Skipping - previous cron still running"
        continue
      fi

      log "[CRON] Executing drush cron at $(date '+%Y-%m-%d %H:%M:%S')"
      if "$drush_bin" cron 2>&1 | head -50; then
        log "[CRON] Completed successfully"
      else
        log "[CRON] Completed with errors (non-fatal)"
      fi
    done
  ) &

  BACKGROUND_CRON_PID=$!
  log "[CRON] Background cron started with PID $BACKGROUND_CRON_PID"
}

# ============================================================================
# DRUSH DETECTION
# ============================================================================

# Return the first executable Drush we can find without aborting the script.
detect_drush() {
  set +e
  local p=""
  # 1) Respect explicit override
  if [ -n "${DRUSH_BIN:-}" ] && [ -x "$DRUSH_BIN" ]; then p="$DRUSH_BIN"; fi
  # 2) Common project locations
  if [ -z "$p" ] && [ -x "/var/www/html/vendor/bin/drush" ]; then p="/var/www/html/vendor/bin/drush"; fi
  if [ -z "$p" ] && [ -x "$(pwd)/vendor/bin/drush" ]; then p="$(pwd)/vendor/bin/drush"; fi
  # 3) PATH (e.g., launcher present)
  if [ -z "$p" ] && command -v drush >/dev/null 2>&1; then p="$(command -v drush)"; fi
  set -e
  printf '%s' "$p"
}

DRUSH="$(detect_drush)"

if [ -n "$DRUSH" ]; then
  log "Drush found: $DRUSH"
  "$DRUSH" --version >&2 || true
  # Helper: check whether a Drupal site appears to be installed by looking for
  # the settings.php (simple but effective for container-first installs).
  site_installed() {
    [ -f web/sites/default/settings.php ] && return 0 || return 1
  }

  # Helper: wait for drush to be able to run commands that require DB access.
  # We probe `drush status` and consider the DB ready when the command exits
  # successfully. This avoids failing early when the DB container is still
  # starting. Returns 0 if ready, non-zero on timeout.
  wait_for_db() {
    local tries=0 max_tries=30 sleep_sec=2 out rc
    while [ $tries -lt $max_tries ]; do
      tries=$((tries + 1))
      # Temporarily disable errexit for the probe so we can inspect failures.
      set +e
      out="$($DRUSH status 2>&1)"; rc=$?
      set -e
      if [ $rc -eq 0 ]; then
        log "Drush/status probe succeeded on attempt $tries."
        return 0
      fi
      # If the output indicates the site isn't installed, treat that as not ready
      # for config import and return non-zero so caller can decide.
      if echo "$out" | grep -q "site.*not installed\|not installed"; then
        log "Site not installed according to drush status; skipping DB wait."
        return 2
      fi
      log "Drush/status probe attempt $tries/$max_tries failed (rc=$rc). Waiting ${sleep_sec}s..."
      sleep $sleep_sec
    done
    log "Timed out waiting for database/drush to be available after $max_tries attempts."
    return 1
  }

  if ! site_installed; then
    log "###################"
    log "No installed Drupal site detected (web/sites/default/settings.php not present)."
    log "Skipping drush deploy; handing off to apache..."
    log "###################"
  else
    log "Detected installed site. Running drush status..."
    log "*****-----*****"
    $DRUSH status -vvv -y
    log "*****-----*****"
    log "Probing database access before running drush deploy..."
    wait_for_db; wait_rc=$?
    if [ $wait_rc -eq 0 ]; then
      log "###################"
      log "Incoming configs:"
      # Allow drush to print verbose config:status but don't let an error abort the entrypoint
      set +e
      $DRUSH config:status -vvv --state='Only in sync dir' || true
      log "###################"
      log "Running drush deploy -vvv -y..."
      $DRUSH deploy -vvv -y || log "drush deploy exited with non-zero status"
      log "###################"
      set -e

      # ======================================================================
      # START BACKGROUND CRON (if enabled)
      # ======================================================================
      if [ "$BACKGROUND_CRON_ENABLED" = "1" ]; then
        start_background_cron "$DRUSH" "$BACKGROUND_CRON_INTERVAL"
        log "###################"
        log "[CRON] Starting background cron."
        log "Change cron settings using env vars;"
        log "Current values:"
        log "BACKGROUND_CRON_ENABLED=$BACKGROUND_CRON_ENABLED"
        log "BACKGROUND_CRON_INTERVAL=$BACKGROUND_CRON_INTERVAL"
        log "###################"
      else
        log "[CRON] Background cron disabled (set env var BACKGROUND_CRON_ENABLED=1 to enable)"
      fi

    elif [ $wait_rc -eq 2 ]; then
      log "Site appears uninstalled; skipping config import/deploy. Handing off to apache..."
    else
      log "Database did not become available; skipping drush deploy. Handing off to apache..."
    fi
  fi

else
  log "###################"
  log "Drush not found on PATH or in ./vendor/bin."
  log "Install per-project (recommended): /usr/bin/composer require drush/drush:^13 --no-interaction --no-dev"
  log "Handing off to apache BUT CONFIGS WERE NOT CHECKED"
  log "###################"
fi

# Start SSH daemon for Azure App Service
if [ -x /usr/sbin/sshd ]; then
  /usr/sbin/sshd
  log "SSH daemon started on port 2222"
fi


exec "$@"

