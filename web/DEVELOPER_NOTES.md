# ARS Apps Platform — Developer Assessment

> Audit of architecture, operations, containerization, CI/CD, security/compliance,
> and platform engineering for the ARS Apps Drupal 11 multi-tenant CMS.

---

## Table of Contents

- [ARS Apps Platform — Developer Assessment](#ars-apps-platform--developer-assessment)
  - [Table of Contents](#table-of-contents)
  - [1. Drupal Engineering, Operations \& Architecture](#1-drupal-engineering-operations--architecture)
    - [What's Done Right](#whats-done-right)
    - [Where It Can Be Improved](#where-it-can-be-improved)
  - [2. Docker Containerization](#2-docker-containerization)
    - [What's Done Right](#whats-done-right-1)
    - [Where It Can Be Improved](#where-it-can-be-improved-1)
  - [3. CI/CD Pipelines \& Automated Deployments](#3-cicd-pipelines--automated-deployments)
    - [What's Done Right](#whats-done-right-2)
    - [Where It Can Be Improved](#where-it-can-be-improved-2)
  - [4. Cloud Security, Compliance \& DevSecOps](#4-cloud-security-compliance--devsecops)
    - [What's Done Right](#whats-done-right-3)
    - [Where It Can Be Improved](#where-it-can-be-improved-3)
  - [5. Platform Engineering \& Infrastructure Architecture](#5-platform-engineering--infrastructure-architecture)
    - [What's Done Right](#whats-done-right-4)
    - [Where It Can Be Improved](#where-it-can-be-improved-4)
  - [Summary Matrix](#summary-matrix)

---

## 1. Drupal Engineering, Operations & Architecture

### What's Done Right

- **Modern Drupal 11 + PHP 8.3 patterns throughout.** Custom entity types
  (`SheepRecord`, `LambCard`, `AnnualAssignment`, `ObservationRecord`,
  `SheepNote`) use PHP 8.3 attributes (`#[ContentEntityType(...)]`) instead of
  legacy annotations. Strict typing (`declare(strict_types=1)`) is enforced
  across custom modules.

- **Dependency injection is the norm.** Services are injected via constructors
  and `ContainerFactoryPluginInterface` — no static `\Drupal::service()` calls
  in custom module classes. Services files (`sheep_entities.services.yml`) use
  `autowire: true`.

- **Entity architecture is well-structured.** Five content entity types with
  programmatic base field definitions, `UniqueField` constraints on canonical
  identifiers (`field_s_id10`), entity reference fields for pedigree
  relationships (dam, sire, foster dam), and properly typed taxonomy references
  across 30+ vocabularies.

- **Group module integration for multi-tenancy.** Custom
  `RelationHandler\Access` and `RelationHandler\PermissionProvider` classes
  per entity type enable per-group permission isolation. Cache contexts include
  `group` and `user.group_permissions`.

- **Migrate API used correctly.** The `sheep_migration` module uses a proper
  plugin architecture: custom source plugins (`InventoryStubs`,
  `LambLineage`, `PedigreeParentStubs`, `MultiVocabTerms`), custom process
  plugins (`ValidateId10Length`, `ConvertDate`, `JulianToDate`,
  `PreserveExisting`), and `migrate_plus` configuration entities. Business
  logic lives in PHP plugins, not YAML. Migrations are idempotent.

- **Migration observability is built in.** The `sheepdog` module provides
  event-subscriber–based logging (listening on `MigrateEvents::PRE_ROW_SAVE`
  and `POST_ROW_SAVE`), Drush commands for log export/analysis/tail, and a
  dedicated database log channel.

- **USWDS theming via UI Suite.** The custom theme (`ui_suite_arsapps`)
  extends `ui_suite_uswds`, defines proper USWDS regions (header_top,
  sidebar_first/second, breadcrumb, hero, footer_bottom), and uses SCSS with a
  Gulp build pipeline. Linting is configured (ESLint, Prettier, Stylelint).

- **Static analysis is in place.** PHPStan at level 6 covers
  `web/modules/custom` and `web/themes/custom`. PHPUnit is configured with
  strict mode (fails on risky tests, warnings, deprecations).

- **Composer setup is clean.** Patching via `cweagans/composer-patches`,
  optimized autoloader in production, NPM asset management for Chart.js
  libraries, and a `clean-chartjs` post-install script.

- **Configuration management discipline.** 900+ config files in `config/sync/`
  covering views, content types, fields, paragraphs, media types, migrations,
  and roles. The migration module uses a deliberate move-import-move-back
  workflow to keep `_config/_migrations/` as the source of truth without
  polluting `config/sync/`.

### Where It Can Be Improved

- **No unit or kernel tests exist for custom modules.** Despite PHPUnit being
  configured and testing instructions documented, no test files are present
  under `web/modules/custom/*/tests/`. The entity access control handlers,
  migration process plugins, and date conversion logic are all excellent
  candidates for unit testing.

- **No `phpcs.xml` project file.** Coding standards are run via CLI flags
  (`--standard=Drupal,DrupalPractice`) rather than a committed `phpcs.xml`
  that would lock down paths, exclude patterns, and ensure consistent
  standards across IDEs and CI. PHPStan `ignoreErrors` may be masking real
  issues (e.g., form array type mismatches).

- **Error handling in entity forms is minimal.** `SheepRecordForm` logs
  save operations but doesn't handle or surface entity validation constraint
  violations to the user beyond Drupal's default behavior.

- **Migration config workflow is manual and error-prone.** The
  move-import-move-back workflow documented in the migration instructions is a
  creative solution but depends entirely on developer discipline. There is no
  automation (script, Drush command, or git hook) to enforce the workflow or
  detect stale configs.

- **Theme build tooling uses Gulp.** Consider migrating to a more modern
  bundler (Vite, esbuild) — Gulp adds maintenance burden and dependency
  surface. The `ui_suite_arsapps` theme also targets `^10.3 || ^11` in its
  `info.yml`; tightening to `^11` would clarify compatibility.

- **Views integration could be stronger.** `SheepRecordViewsData` overrides
  filter types for taxonomy fields, but there's no evidence of custom Views
  plugins (e.g., area handlers, computed fields) for complex reporting needs.
  Several Views contrib modules are installed (`views_computed_field`,
  `views_simple_math_field`, `views_aggregator`) — consolidating this into
  purpose-built Views plugins may reduce contrib dependency.

- **`config_ignore` is installed but configuration drift risk is unclear.**
  There's no documented pattern for which configs are ignored and why.

---

## 2. Docker Containerization

### What's Done Right

- **Single-stage production build with clear layering.** The Dockerfile builds
  from `php:8.3-apache`, installs system deps → PHP extensions → Composer →
  Node.js → Apache modules → app code → theme assets → permissions → PHP
  config → SSH, with proper cleanup (`rm -rf /var/lib/apt/lists/*`,
  `rm -rf node_modules` after theme build).

- **Theme assets are compiled at build time.** Node.js 20 is installed, the
  theme runs `npm ci && npm run build`, then `node_modules` are removed —
  keeping the runtime image lean.

- **Composer install uses production flags.** `--no-dev --optimize-autoloader
  --no-interaction` ensures no development dependencies ship.

- **Git SHA is tracked in the image.** The `GIT_SHA` build arg is logged at
  container startup for traceability.

- **Apache is hardened.** PHP execution is blocked in `core/`, `profiles/`,
  `modules/`, `themes/`, `libraries/`, and `sites/*/files/`. Security headers
  are set (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, HSTS).
  Slowloris protection is configured via `RequestReadTimeout`.

- **PHP is production-configured.** OPcache enabled with
  `validate_timestamps=0`, `expose_php=Off`, `allow_url_fopen=Off`,
  `allow_url_include=Off`, secure session cookie settings (`httponly`,
  `secure`, `strict_mode`).

- **Entrypoint is robust.** `drupal-entrypoint.sh` uses `set -euo pipefail`,
  has multi-strategy Drush detection, database readiness probing (30 × 2s
  timeout), optional background cron with overlap prevention, maintenance mode
  management, and graceful degradation if `drush deploy` fails.

- **`.dockerignore` is comprehensive.** Excludes `.ddev/`, `.git/`, IDE files,
  `node_modules/`, `vendor/`, user uploads, private files, and logs.

### Where It Can Be Improved

- **No `HEALTHCHECK` instruction in the Dockerfile.** Docker and orchestrators
  (including Azure App Service) benefit from a built-in health check. Adding
  `HEALTHCHECK CMD curl -f http://localhost/user/login || exit 1` would
  improve container self-healing.

- **SSH password is hardcoded.** `echo "root:Docker!" | chpasswd` is baked
  into the image. This should be injected at runtime via an environment
  variable or Azure KeyVault secret. Root SSH login with password auth is an
  Azure App Service requirement but should be locked down further (key-based
  auth, IP restriction).

- **SSH cipher suite includes weak algorithms.** The `sshd_config` allows
  `3des-cbc` and `aes128-cbc` — these are considered weak. Restrict to CTR
  and GCM modes only: `aes128-ctr,aes192-ctr,aes256-ctr,aes128-gcm@openssh.com,aes256-gcm@openssh.com`.

- **No multi-stage build.** Composer and Node.js tooling remain in the final
  image even though they're only needed at build time. A multi-stage build
  (builder stage → runtime stage) would reduce the attack surface and image
  size by ~200-400MB.

- **No non-root user for the application process.** While `www-data` owns the
  files, the entrypoint runs as root (needed for SSH daemon and initial
  setup). Consider using `gosu` or `su-exec` to drop privileges after
  startup tasks complete.

- **Timezone is hardcoded.** `America/New_York` is set via symlink. This
  should be configurable via an environment variable for portability.

- **No image scanning in the pipeline.** There's no Trivy, Grype, or Snyk
  step to scan the built image for CVEs before pushing to ACR.

- **Composer files are copied before application code** (good for caching),
  but `scripts/` is also copied early — changes to build scripts will
  invalidate the Composer dependency cache layer.

---

## 3. CI/CD Pipelines & Automated Deployments

### What's Done Right

- **Three-workflow architecture with clear separation.**
  - `deploy.yml` — staging (push to `develop` → build → push `latest` to ACR)
  - `deploy-production.yml` — production (push to `main` → version calc →
    pre-flight → build → push with semver tag → health check → summary)
  - `codereview-setup-steps.yml` — PR quality gates (scope detection → static
    analysis → DDEV integration tests → accessibility → docs → YAML validation)

- **Scope-aware code review pipeline.** The PR workflow detects changed file
  types (PHP, theme, config, workflows, docs) and only runs relevant checks.
  Changed custom modules are identified and tested individually.

- **Pre-flight debug code detection.** Production deploys scan for `dpm()`,
  `kint()`, `var_dump()`, `dd()` and other debug functions — blocking the
  build if found.

- **Semantic versioning is automated.** The production pipeline reads
  `VERSION`, calculates the bump (patch/minor/major), generates a dated
  image tag (`prod_arsapps_X.Y.Z-YYYY-MM-DD`), and includes OCI-standard
  image labels (revision, version, created, source).

- **Docker build caching.** Production builds use `cache-from: type=gha` and
  `cache-to: type=gha,mode=max` for GitHub Actions layer caching.

- **Health check after production deploy.** The pipeline waits for ACR webhook
  deployment, then polls `/user/login` for 10 attempts × 15s intervals.

- **Accessibility testing in CI.** Theme changes trigger automated axe-core
  scans via the project's `axe-scan.js` script, with results uploaded as
  artifacts.

- **Concurrency controls.** PR reviews cancel in-progress runs for the same
  branch. Production deploys use `cancel-in-progress: false` to prevent
  interrupted deployments.

- **Automated code review agents.** Seven specialized agents (a11y, arsapps,
  azure, cr, docs, security, uswds) are triggered based on change scope.

- **Composer audit runs in CI.** Dependency security vulnerabilities are
  checked automatically on every PR.

### Where It Can Be Improved

- **Changelog generation and GitHub releases are disabled.** The
  `generate-changelog` and `create-release` jobs are fully commented out in
  the production pipeline due to API token issues. This means VERSION file
  auto-increment, git tagging, and release notes are not automated — releases
  require manual intervention.

- **Staging deploys always tag as `latest`.** There's no version tracking for
  staging builds — if a staging deploy fails, there's no easy way to roll back
  to a previous known-good staging image. Consider tagging staging images with
  the git SHA or a sequential build number.

- **No PHPStan in CI.** The static analysis job runs PHPCS and Composer audit
  but does not run PHPStan, despite having `phpstan.neon.dist` configured at
  level 6. This is a missed opportunity to catch type errors before merge.

- **DDEV integration tests install the full site.** This is slow and fragile
  in CI. Consider running unit and kernel tests without a full site install,
  and reserving functional tests for a dedicated environment.

- **No branch protection enforcement is visible.** While the PR workflow runs
  checks, there's no evidence of required status checks, required reviewers,
  or branch protection rules being configured (this is a GitHub settings
  concern, not a workflow concern, but worth documenting).

- **No rollback mechanism.** If a production deploy fails the health check,
  the pipeline reports failure but takes no corrective action. Consider
  adding an automated rollback to the previous image tag, or at minimum,
  alerting a Slack/Teams channel.

- **No smoke tests beyond health check.** The health check only verifies that
  `/user/login` returns HTTP 200/302. It doesn't verify that Drupal is
  bootstrapped correctly, that config import succeeded, or that key
  functionality works. A basic Drush status check or a custom health
  endpoint would be more reliable.

- **Accessibility tests don't block PRs.** Axe scan results are uploaded as
  artifacts but don't fail the pipeline. For a federal Section 508-mandated
  project, a11y violations should be blocking.

- **No environment promotion workflow.** There's no mechanism to promote a
  tested staging image to production without rebuilding. The staging image
  tagged `latest` is different from the production image built from the same
  code merged to `main`.

---

## 4. Cloud Security, Compliance & DevSecOps

### What's Done Right

- **Security headers are set at the Apache level.** `X-Content-Type-Options:
  nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection: 1; mode=block`,
  `Strict-Transport-Security: max-age=31536000; includeSubDomains`.

- **PHP execution is blocked in upload directories.** Apache `LocationMatch`
  rules deny `.php` and `.phar` execution in `core/`, `profiles/`, `modules/`,
  `themes/`, `libraries/`, and `sites/*/files/`.

- **PHP is locked down for production.** `expose_php=Off`,
  `allow_url_fopen=Off`, `allow_url_include=Off`, `display_errors=Off`,
  `error_log` to file, session cookies are `httponly` + `secure` +
  `strict_mode`.

- **DigiCert G2 root CA is installed.** Federal compliance requires specific
  CA trust chains — the certificate is properly added to the system CA store
  during build.

- **Private files directory is outside webroot.** `'../private'` path in
  settings.php keeps sensitive files out of the document root. Config sync
  directory is also outside webroot (`../config/sync`).

- **Update and rebuild access are disabled.** `update_free_access = FALSE`
  and `rebuild_access = FALSE` in settings.php.

- **Environment-aware trusted host patterns.** Settings.php detects Azure
  (`WEBSITE_SITE_NAME`) and DDEV (`IS_DDEV_PROJECT`) environments to set
  appropriate trusted host patterns.

- **Composer audit in CI.** Dependency vulnerabilities are checked on every PR.

- **Debug code gate in production pipeline.** Pre-flight check prevents
  `dpm()`, `kint()`, `var_dump()` from reaching production.

- **Access control uses proper Drupal patterns.** Entity access handlers use
  `AccessResult::allowed()` / `::forbidden()` / `::neutral()` with cache
  contexts. Group permissions are checked per-entity-type.

- **Azure AD integration.** `openid_connect_windows_aad` module is installed
  for Azure Active Directory SSO.

- **Eight distinct user roles.** Granular role definitions (administrator,
  content_administrator, content_editor, nematode, nrrl, rsper, ssr) rather
  than a single admin role.

### Where It Can Be Improved

- **No Content-Security-Policy (CSP) header.** This is the most impactful
  missing security header. A properly configured CSP would mitigate XSS risks
  significantly. At minimum, add `default-src 'self'; script-src 'self';
  style-src 'self' 'unsafe-inline'` and tighten from there.

- **No container image scanning.** Neither Trivy, Grype, Snyk, nor Azure
  Defender for Containers is integrated into the CI/CD pipeline. For a federal
  system, NIST SP 800-53 (SA-11) requires automated security analysis of
  software components.

- **No secrets scanning in CI.** No GitLeaks, TruffleHog, or GitHub secret
  scanning configuration is visible. The SSH password (`Docker!`) is
  hardcoded in the Dockerfile.

- **SSH configuration uses weak ciphers.** `3des-cbc` and `aes128-cbc` are
  allowed in `sshd_config`. Federal systems should follow NIST SP 800-52
  cipher requirements — remove CBC-mode ciphers.

- **No CORS configuration.** If the API is consumed by external clients,
  CORS headers should be explicitly configured rather than relying on defaults.

- **No rate limiting.** Neither at the application level (Drupal), the web
  server level (Apache), nor the infrastructure level (Azure). Login brute
  force attacks, API abuse, and DDoS are unmitigated. Federal systems should
  implement rate limiting per NIST AC-7 (Unsuccessful Logon Attempts).

- **No Web Application Firewall (WAF).** Azure Application Gateway with WAF
  v2 or Azure Front Door should be in front of the App Service for OWASP
  ModSecurity rule enforcement. This is typically required for FedRAMP
  compliance.

- **Drupal security modules are absent.** Consider:
  - `seckit` — additional security headers and CSP management
  - `login_security` — login attempt rate limiting and IP banning
  - `password_policy` — password complexity enforcement (NIST 800-63B)
  - `flood_control` — configurable flood protection UI

- **No SIEM/audit log forwarding.** While watchdog logs exist, there's no
  configuration for forwarding security events to Azure Monitor, Azure
  Sentinel, or a SIEM. NIST AU-6 requires automated audit review.

- **No dependency pinning verification.** `composer.lock` exists but there's
  no `--locked` flag enforcement in CI to ensure builds match exactly what
  was committed.

- **`Permissions-Policy` header is missing.** This header (formerly
  `Feature-Policy`) should restrict browser features (camera, microphone,
  geolocation) that a CMS shouldn't need.

---

## 5. Platform Engineering & Infrastructure Architecture

### What's Done Right

- **Container-first architecture.** Production runs as a Docker container on
  Azure App Service. The Dockerfile is the single source of truth for the
  runtime environment. DDEV provides local development parity.

- **Azure Container Registry (ACR) as image store.** Private registry
  (`acrasegrinprod01.azurecr.us`) with webhook-based deployment to App
  Service. The `.us` TLD indicates Azure Government Cloud compliance.

- **Two-environment topology.** Staging (`ARS-VA-BETA-APPS` on `develop`)
  and production (`ARS-VA-PROD-APPS` on `main`) with distinct image tagging
  strategies.

- **Config-as-code deployment.** `drush deploy` in the entrypoint handles
  `updb` + `cim` + deploy hooks automatically on container start. No manual
  intervention required for config changes.

- **Database readiness probing.** The entrypoint waits up to 60 seconds for
  the database before proceeding, preventing race conditions during container
  restarts.

- **Background cron with overlap prevention.** Optional background cron loop
  in the entrypoint (configurable via env vars) with `pgrep`-based overlap
  detection.

- **Build traceability.** Git SHA, semantic version, timestamp, and source URL
  are embedded as OCI labels on production images. The entrypoint logs the
  Git SHA at startup.

- **DDEV configuration is complete.** Local development uses MySQL 8.0 (matching
  production), PHP 8.3, nginx-fpm, Composer 2, and corepack.

- **Versioning strategy is sound.** Semantic versioning with a `VERSION` file,
  `VERSION_HISTORY.md` for human-readable tracking, and `cliff.toml` for
  conventional-commit-based changelog generation.

- **GitHub Actions workflow summaries.** All pipelines generate rich step
  summaries with tables, status indicators, and actionable information.

### Where It Can Be Improved

- **No Infrastructure as Code (IaC).** There are no Terraform, Bicep, ARM
  templates, or Pulumi configurations. The Azure infrastructure (App Service,
  ACR, MySQL, VNet, DNS, KeyVault) appears to be manually provisioned. This
  prevents reproducible environments, disaster recovery, and audit trails for
  infrastructure changes.

- **No horizontal scaling or load balancing configuration.** Azure App Service
  supports scaling, but there's no evidence of auto-scale rules, traffic
  manager, or load balancer configuration. For a multi-tenant platform serving
  multiple research groups, scaled instances should be planned.

- **No database backup automation in the codebase.** Azure Database for MySQL
  has built-in backups, but there's no documentation or automation for
  backup verification, point-in-time recovery testing, or cross-region
  replication.

- **No CDN configuration for static assets.** USWDS CSS/JS, Drupal
  aggregated assets, and media files would benefit from Azure CDN or Front
  Door caching rules. This reduces App Service load and improves geographic
  performance.

- **No persistent storage strategy is documented.** Drupal's `sites/*/files/`
  directory needs persistent storage across container restarts. If using Azure
  Files or Blob Storage, this should be documented. If files are ephemeral,
  uploaded content is lost on redeploy.

- **Development/staging/production environment parity gaps.** DDEV uses
  nginx-fpm while production uses Apache. This can cause `.htaccess` and
  `AllowOverride` behavior differences. Consider aligning web servers or
  documenting the implications.

- **No monitoring or alerting configuration.** While `health_check` module is
  installed and the production pipeline runs a basic health check, there's no
  Azure Application Insights, Azure Monitor alerts, or uptime monitoring
  configuration in the codebase.

- **No disaster recovery plan.** There's no documented RTO/RPO, no
  cross-region failover, and no blue-green deployment strategy. The ACR
  webhook model means deployment is "push and pray" — if the new container
  fails, there's no automated fallback.

- **No environment variable documentation.** Required runtime environment
  variables (database credentials, hash salt, Azure AD config, site hostname)
  are not documented in a `.env.example` file or deployment guide.

- **Staging has no image versioning.** Staging always deploys `latest`, making
  rollback impossible. Tag staging images with the git SHA
  (`develop-abc1234`) for rollback capability.

- **No centralized logging aggregation.** PHP errors go to
  `/var/log/php_errors.log` inside the container (ephemeral). Apache logs go
  to `${APACHE_LOG_DIR}`. Neither is forwarded to a persistent log store.
  Azure App Service has diagnostic logging, but it should be explicitly
  configured and verified.

- **Release automation is broken.** The `generate-changelog` and
  `create-release` jobs in the production workflow are commented out. The
  `VERSION` file is not auto-incremented on deploy. Manual version management
  will drift.

---

## Summary Matrix

| Area | Strengths | Priority Improvements |
|------|-----------|----------------------|
| **Drupal Engineering** | Modern Drupal 11/PHP 8.3, strong DI, proper entity architecture, solid Migrate API usage | Add PHPUnit tests, add PHPStan to CI, automate migration config workflow |
| **Docker** | Clean layering, production PHP config, hardened Apache, robust entrypoint | Multi-stage build, HEALTHCHECK, remove hardcoded SSH password, image scanning |
| **CI/CD** | Scope-aware PR review, pre-flight checks, semantic versioning, a11y scanning | Fix release automation, add PHPStan, tag staging images, add rollback mechanism |
| **Security/Compliance** | Security headers, PHP lockdown, private files, access control, Azure AD | Add CSP header, container scanning, secrets scanning, WAF, rate limiting, SIEM forwarding |
| **Platform Engineering** | Container-first, ACR+webhook deploy, config-as-code, build traceability | Add IaC (Terraform/Bicep), document storage/env vars, add monitoring/alerting, staging rollback |
