# Standard Operating Procedure: Development & Deployment

**Platform:** ARS Apps — USDA Agricultural Research Service Drupal 11 Multi-Tenant CMS
**Last Updated:** <!-- Update this date when revising -->

---

## Table of Contents

1. [Environment Setup](#1-environment-setup)
2. [Local Development Workflow](#2-local-development-workflow)
3. [Branching Strategy](#3-branching-strategy)
4. [Code Quality & Testing](#4-code-quality--testing)
5. [Configuration Management](#5-configuration-management)
6. [Theme Development](#6-theme-development)
7. [Deployment Pipeline](#7-deployment-pipeline)
8. [Post-Deployment Verification](#8-post-deployment-verification)
9. [Rollback Procedure](#9-rollback-procedure)
10. [Reference Commands](#10-reference-commands)

---

## 1. Environment Setup

### Prerequisites

- [DDEV](https://ddev.readthedocs.io/) installed and functional
- Git
- Docker Desktop (or compatible container runtime)

### Initial Setup

```bash
git clone <repository-url> ars-apps-develop-m
cd ars-apps-develop-m
ddev start
ddev composer install
ddev drush site:install --existing-config -y
```

### Theme Assets

```bash
cd web/themes/custom/ui_suite_arsapps
ddev exec npm install
ddev exec npm run build
```

> **MANDATORY:** All PHP, Composer, Drush, and Node commands MUST be executed via DDEV. Never run these directly on the host machine.

---

## 2. Local Development Workflow

### Starting Work

```bash
ddev start
ddev drush cim -y        # Import latest configuration
ddev drush cr             # Clear cache
```

### During Development

1. Write code following Drupal 11 coding standards and project conventions.
2. Use dependency injection; avoid static service calls where possible.
3. Respect multi-tenant (Group module) context in all content queries and access checks.
4. Use USWDS design tokens for all frontend work — never hard-code colors, spacing, or typography.

### Stopping Work

```bash
ddev drush cex -y         # Export any configuration changes
git add -A
git commit -m "Descriptive commit message"
ddev stop                 # Optional; conserves resources
```

---

## 3. Branching Strategy

| Branch | Purpose |
|---|---|
| `develop` | Integration branch; all feature work merges here |
| `feature/<name>` | Individual feature or task branches, branched from `develop` |
| `bugfix/<name>` | Bug fix branches, branched from `develop` |
| `main` | Production-ready code; receives merges from `develop` via release process |

### Workflow

1. Create a feature branch from `develop`:
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/my-feature
   ```
2. Develop and commit incrementally.
3. Push and open a Pull Request targeting `develop`.
4. After code review approval and CI checks pass, merge the PR.
5. Production deploys are triggered from `main` after a release merge.

---

## 4. Code Quality & Testing

### Coding Standards

```bash
# Check standards
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/

# Auto-fix violations
ddev exec vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/
```

### Static Analysis

```bash
ddev exec vendor/bin/phpstan analyse
```

### Unit & Functional Tests

```bash
ddev exec vendor/bin/phpunit web/modules/custom/<module>/tests/
```

### Accessibility Testing (Required for UI Changes)

```bash
ddev exec node scripts/axe-scan.js
```

Results are available at: `https://<ddev-site>/axe-results/axe-summary-report.html`

### Pre-Commit Checklist

- [ ] Coding standards pass (`phpcs`)
- [ ] Static analysis passes (`phpstan`)
- [ ] Relevant tests pass (`phpunit`)
- [ ] Accessibility scan passes for any UI changes (`axe-scan.js`)
- [ ] Configuration exported (`ddev drush cex -y`)
- [ ] No hard-coded credentials or sensitive values

---

## 5. Configuration Management

Configuration is the single source of truth for site structure and is stored in `config/sync/`.

### Exporting Configuration

After any change made via the Drupal UI or code that affects configuration:

```bash
ddev drush cex -y
git diff config/sync/              # Review changes
git add config/sync/
git commit -m "Export configuration: <brief description>"
```

### Importing Configuration

Before starting any new work or after pulling changes:

```bash
ddev drush cim -y
```

### Partial Imports (Module Install Config)

To reset or apply a module's install configuration:

```bash
ddev drush config:import --partial --source=modules/custom/<module>/config/install -y
```

### Rules

- Always export after UI-driven changes.
- Always import before starting work to avoid conflicts.
- Resolve merge conflicts in YAML files carefully — malformed config can break the site.
- Configuration files in `config/sync/` are excluded from code review; they are validated through functional testing.

---

## 6. Theme Development

**Theme location:** `web/themes/custom/ui_suite_arsapps/`

### Build Commands

```bash
cd web/themes/custom/ui_suite_arsapps

# Full build (copy USWDS assets + compile CSS + JS)
ddev exec npm run build

# Compile CSS only
ddev exec npx gulp compileCss

# Compile JS only
ddev exec npx gulp compileJs

# Watch for changes during development
ddev exec npm run watch
```

### USWDS Token Usage

Always use USWDS design tokens:

```scss
@use 'uswds-core' as *;

.component {
  color: color('base-darkest');
  padding: units(2);
  font-family: family('sans');
}
```

Never use hard-coded CSS values for color, spacing, typography, or border-radius.

---

## 7. Deployment Pipeline

### Overview

Deployments follow a containerized CI/CD pipeline:

1. **Code merges to `main`** trigger the production build.
2. **Docker image is built** — includes Composer install, theme compilation (`npm ci && npm run build`), and PHP configuration.
3. **Image is pushed** to Azure Container Registry.
4. **Azure App Service** pulls the new image.
5. **Entrypoint script** (`drupal-entrypoint.sh`) runs `drush deploy` automatically on container start, which executes:
   - `drush updatedb` — applies pending database updates
   - `drush config:import` — imports configuration from `config/sync/`
   - Clears caches

### Pre-Deploy Checklist

- [ ] All PRs merged and CI checks green on `develop`
- [ ] `develop` merged into `main` via release PR
- [ ] `VERSION` file updated if required
- [ ] No pending configuration conflicts (`ddev drush cex --diff` shows clean)
- [ ] Accessibility scan results reviewed

### Deployment Environments

| Environment | Trigger | URL |
|---|---|---|
| Development | Local DDEV | `https://ars-apps-drupal.ddev.site` |
| Staging | Push to staging branch / manual | Staging URL |
| Production | Merge to `main` | Production URL |

---

## 8. Post-Deployment Verification

After each deployment, verify the following:

1. **Site loads successfully** — check the home page and key landing pages.
2. **Configuration imported cleanly:**
   ```bash
   ddev drush config:export --diff
   # Should show no differences
   ```
3. **Check logs for errors:**
   ```bash
   ddev drush watchdog:show --count=20
   ```
4. **Verify cron runs:**
   ```bash
   ddev drush cron
   ```
5. **Spot-check Group content** — ensure multi-tenant isolation is intact.
6. **Run accessibility scan** if UI changes were included in the release.

---

## 9. Rollback Procedure

### Application Rollback

If a deployment introduces a critical issue:

1. **Revert to previous container image** in Azure App Service by selecting the prior image tag in the Container settings.
2. The entrypoint script will run `drush deploy` against the reverted codebase.

### Configuration Rollback

```bash
# Revert config changes in Git
git revert <commit-hash>
git push origin main

# Or manually import a known-good config state
ddev drush cim -y
```

### Database Rollback

Database backups are stored in `.db-backups/`. To restore:

```bash
ddev import-db --file=.db-backups/<backup-file>.sql
ddev drush cr
```

---

## 10. Reference Commands

| Task | Command |
|---|---|
| Start environment | `ddev start` |
| Stop environment | `ddev stop` |
| Install dependencies | `ddev composer install` |
| Add a module | `ddev composer require drupal/<module>` |
| Enable a module | `ddev drush en <module> -y` |
| Clear cache | `ddev drush cr` |
| Export config | `ddev drush cex -y` |
| Import config | `ddev drush cim -y` |
| Run database updates | `ddev drush updb -y` |
| Check site status | `ddev drush status` |
| View recent logs | `ddev drush watchdog:show --count=20` |
| Run cron | `ddev drush cron` |
| Build theme | `ddev exec npm run build` (from theme directory) |
| Run coding standards | `ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/` |
| Run static analysis | `ddev exec vendor/bin/phpstan analyse` |
| Run tests | `ddev exec vendor/bin/phpunit web/modules/custom/<module>/tests/` |
| Run accessibility scan | `ddev exec node scripts/axe-scan.js` |
| Migration status | `ddev drush migrate:status` |
| SSH into container | `ddev ssh` |

---

## Appendix: Decision Hierarchy for New Functionality

Before writing custom code, follow this priority order:

1. **Configuration / Content** — solve with Drupal site building (fields, views, blocks, permissions).
2. **Unmodified Contrib Module** — use an existing, maintained contrib module as-is.
3. **Contrib + Contrib** — extend one contrib module with another.
4. **Custom Module Extending Contrib** — requires documented justification.
5. **Standalone Custom Module** — last resort; requires cost/benefit analysis and stakeholder approval.

See the full decision framework in [.github/copilot-instructions.md](.github/copilot-instructions.md).
