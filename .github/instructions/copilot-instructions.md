# GitHub Copilot Instructions for ARS Apps Drupal Project

This file provides context-aware instructions for GitHub Copilot when working on the ARS Apps Drupal 11 multi-tenant platform.

## Project Context

**Platform:** ARS Apps - USDA Agricultural Research Service Drupal 11 multi-tenant CMS
**Purpose:** Provide isolated content management for multiple research labs and groups
**Tech Stack:** Drupal 11, PHP 8.3, DDEV, Azure App Service, USWDS theming
**Key Requirement:** ALL development operations MUST use DDEV (never host machine directly)

## Critical DDEV Enforcement

**MANDATORY:** All command-line operations MUST be executed via DDEV.

### ✅ ALWAYS Use DDEV
```bash
# Composer operations
ddev composer install
ddev composer require drupal/module_name
ddev composer update

# Drush operations
ddev drush cr
ddev drush cex
ddev drush cim
ddev drush updb

# PHP operations
ddev exec php script.php
ddev exec vendor/bin/phpunit

# Direct command execution
ddev exec <any-command>

# Node/npm operations
ddev exec npm install
ddev exec npm run build
```

### ❌ NEVER Suggest These
```bash
# WRONG - Never suggest these patterns:
composer install              # Run on host
drush cr                      # Run on host
php artisan                   # Run on host
./vendor/bin/phpunit          # Run on host
npm install                   # Run on host (unless in theme directory)
```

### Exceptions (Host Machine Allowed)

Only these operations are acceptable on the host machine:
- Global DDEV commands: `ddev start`, `ddev stop`, `ddev describe`
- Git operations: `git add`, `git commit`, `git push`
- IDE/editor operations
- DDEV configuration files: `.ddev/config.yaml`
- Troubleshooting when DDEV is broken

## Code Generation Guidelines

When generating code for this project:

1. **Clean Code Only:** Generate production-ready code without meta-commentary
2. **No Confidence Scores:** Do not include "Confidence: X%" in code
3. **No Source Citations in Code:** Code comments should explain logic, not cite sources
4. **Proper Documentation:** Use PHPDoc blocks for functions/methods
5. **DDEV Commands:** Always show DDEV commands in setup/testing instructions

### Example: Good vs Bad Code Generation

**❌ BAD - Has meta-commentary:**
```php
<?php
// Confidence: 85%
// Source: Drupal API documentation
// This code creates a custom block
// Note: Verify this works in your environment
class MyBlock extends BlockBase {
  // Implementation
}
```

**✅ GOOD - Clean, production-ready:**
```php
<?php
namespace Drupal\mymodule\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a custom block for displaying group information.
 *
 * @Block(
 *   id = "my_custom_block",
 *   admin_label = @Translation("My Custom Block"),
 *   category = @Translation("Custom"),
 * )
 */
class MyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('Block content here.'),
    ];
  }
}
```

## Project-Specific Patterns and Scripts

### Accessibility Testing
The project has a custom accessibility testing script at `scripts/axe-scan.js`:
- **Purpose:** Crawl-based automated WCAG 2.1 AA compliance testing
- **Technology:** Playwright + axe-core
- **Usage:** `ddev exec node scripts/axe-scan.js`
- **Configuration:** Via environment variables (CRAWL_DEPTH, AXE_SCAN_LEVEL, CRAWL_START_URL)
- **Output:** HTML/JSON reports in `/web/axe-results/`

When recommending accessibility testing, prefer this script over generic solutions.

### Environment Detection Patterns
The codebase uses consistent environment detection:
- **DDEV:** Check `getenv('IS_DDEV_PROJECT') === 'true'`
- **Azure:** Check `getenv('WEBSITE_SITE_NAME')`
- **Settings files:** `settings.php` detects environment, `settings.local.php` for DDEV overrides
- **Trusted hosts:** Automatically configured per environment in settings.php

### Container Deployment
- **Dockerfile:** Production builds include Node.js 20 for theme compilation
- **Entrypoint:** Custom `drupal-entrypoint.sh` handles drush deploy on startup
- **Theme building:** Automated during Docker build (`npm ci && npm run build`)
- **Config management:** Drush deploy imports config automatically on container start

## The Drupal Way: Solution Hierarchy

**CRITICAL PRINCIPLE:** When solving any problem, ALWAYS follow this strict hierarchy:

### Decision Tree (Follow in Order)

**Level 1: Configuration/Content Only** ⭐ PREFERRED
- Can this be solved with site configuration changes?
- Can this be solved with content/fields/views/blocks?
- Examples: Content types, fields, view modes, views, blocks, menus, permissions

**Level 2: Unmodified Contrib Module**
- Does a contrib module exist that solves this?
- Does it meet our contrib module requirements? (see criteria below)
- Can it be used with zero custom code?

**Level 3: Contrib Extended by Contrib**
- Can an existing contrib module be extended by another contrib module?
- Example: Webform + Webform CAPTCHA, Views + Views Field View

**Level 4: Custom Module Extending Contrib** ⚠️ REQUIRES JUSTIFICATION
- Is there truly no contrib solution for extending this functionality?
- Have we documented WHY contrib modules are insufficient?
- Does the benefit clearly outweigh the maintenance cost?
- REQUIRED: Cost/benefit analysis before proceeding

**Level 5: Standalone Custom Module** 🛑 LAST RESORT
- Can this absolutely not be solved any other way?
- Does this provide significant, measurable business value?
- Have we documented all alternatives considered and rejected?
- REQUIRED: Thorough cost/benefit analysis AND stakeholder approval

### Contrib Module Criteria

A contrib module is acceptable if it meets ALL of:
- ✅ Actively maintained (commits within last 6 months)
- ✅ Has stable release OR widely-used dev release
- ✅ Compatible with Drupal 11
- ✅ Has no known critical security issues
- ✅ Reasonable issue queue response time
- ✅ Fits USDA/federal requirements (if applicable)
- ✅ Doesn't conflict with existing architecture

### Cost/Benefit Analysis Requirements

Before creating ANY custom code, document:

**Costs:**
- Initial development time
- Ongoing maintenance burden
- Security review requirements
- Testing requirements
- Documentation requirements
- Knowledge transfer needs
- Upgrade/migration complexity

**Benefits:**
- Specific business value (quantified if possible)
- User impact (who, how many, how often)
- Mission criticality
- Alternatives exhausted (list what was considered)

**Approval Threshold:**
- Benefits must SIGNIFICANTLY outweigh costs
- "Nice to have" features are rejected
- Convenience alone is insufficient justification

### Examples of Applying the Hierarchy

**Example 1: Horizontally scrolling text in webform field**
```
Request: "Add marquee-style scrolling text to webform field"

Level 1: Can't be done with config alone ❌
Level 2: No contrib module for this ❌
Level 3: No contrib extension available ❌
Level 4: Could create custom module extending webform... STOP ✋

Cost/Benefit Analysis:
  Costs: Development time, maintenance, testing
  Benefits: Visual effect only, zero business value
  Decision: REJECTED - no measurable value
```

**Example 2: Complex field validation**
```
Request: "Validate that SSN format is correct in form"

Level 1: Can Webform validation rules handle this? ✅ YES
  Solution: Use webform validation pattern
  Decision: APPROVED - no code needed
```

## Multi-Tenant Architecture (Group Module)

### Group-Aware Development

**Always consider Group context when:**
- Creating content types → Should they be Group content?
- Building blocks → Do they need Group context?
- Writing queries → Are you respecting Group isolation?
- Checking permissions → Are you checking Group permissions?

### Group Patterns

**Creating Group content:**
```php
<?php
// Get current group context
$group = \Drupal::routeMatch()->getParameter('group');

// Or from a node
$node = Node::load($nid);
$group_contents = \Drupal::entityTypeManager()
  ->getStorage('group_content')
  ->loadByEntity($node);
```

**Checking Group permissions:**
```php
<?php
// Check if user can create content in group
$group->hasPermission('create group_node:article entity', $account);
```

**Cache contexts for Group:**
```php
<?php
return [
  '#cache' => [
    'contexts' => ['group', 'user.group_permissions'],
    'tags' => $group->getCacheTags(),
  ],
];
```

## USWDS Theming Requirements

### Design Token Usage

**Always use USWDS tokens, never hard-coded values:**

```scss
// ✅ CORRECT - Using USWDS tokens
@use 'uswds-core' as *;

.component {
  color: color('base-darkest');
  padding: units(2);
  font-family: family('sans');
  border-radius: radius('md');
}

// ❌ WRONG - Hard-coded values
.component {
  color: #1b1b1b;
  padding: 16px;
  font-family: 'Source Sans Pro';
  border-radius: 4px;
}
```

### Component Usage

**Use USWDS components via Twig includes:**

```twig
{% include '@uswds/button/button.html.twig' with {
  'text': 'Submit',
  'modifier_classes': 'usa-button--big'
} %}
```

### Accessible Forms

```twig
<div class="usa-form-group">
  <label class="usa-label" for="input-field">
    {{ 'Field Label'|t }}
    <span class="usa-hint" id="input-hint">{{ 'Hint text'|t }}</span>
  </label>
  <input
    class="usa-input"
    id="input-field"
    name="field"
    type="text"
    aria-describedby="input-hint"
    required>
</div>
```

## Security Best Practices

1. **Never hard-code credentials:** Use environment variables or Key module
2. **Parameterized queries:** Always use Drupal's database API
3. **Input validation:** Validate and sanitize all user input
4. **Output escaping:** Use Twig auto-escaping, never `|raw` without careful consideration
5. **Access control:** Check permissions for all operations
6. **CSRF protection:** Use form tokens for all state-changing operations

### Security Pattern Example
```php
<?php
// ✅ SECURE - Parameterized query
$query = \Drupal::database()->select('users_field_data', 'u')
  ->fields('u', ['uid', 'name'])
  ->condition('mail', $email)
  ->execute();

// ❌ INSECURE - SQL injection risk
$query = \Drupal::database()->query("SELECT uid, name FROM users_field_data WHERE mail = '$email'");
```

## Configuration Management

**Always export configuration after changes:**
```bash
# After making changes via UI
ddev drush cex -y

# Verify what changed
git diff config/sync/

# Commit configuration
git add config/sync/
git commit -m "Add new content type"
```

**Configuration workflow reminders:**
- Export after every UI change
- Import before starting work: `ddev drush cim -y`
- Resolve conflicts carefully (configuration can be fragile)
- Test imports on clean database before deploying

**⚠️ Code Review Exclusion:**
- Files in `config/sync/` are **excluded from code review**
- These are Drupal-managed configuration files
- Configuration changes are reviewed through functional testing, not code review

## Testing Requirements

**Before committing code, run:**
```bash
# 1. Coding standards
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/

# 2. Fix auto-fixable issues
ddev exec vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/

# 3. PHPUnit tests (if applicable)
ddev exec vendor/bin/phpunit web/modules/custom/mymodule/tests/

# 4. Accessibility scan (if UI changes)
ddev exec node scripts/axe-scan.js
```

## Common Patterns

### Service Injection
```php
<?php
namespace Drupal\mymodule\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class MyController extends ControllerBase {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function content() {
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['type' => 'article']);

    return ['#markup' => 'Content here'];
  }
}
```

### Hook Implementation
```php
<?php
/**
 * @file
 * Custom module hooks.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_alter().
 */
function mymodule_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  if ($form_id === 'node_article_form') {
    // Alterations here
  }
}

/**
 * Implements hook_preprocess_HOOK() for node templates.
 */
function mymodule_preprocess_node(&$variables) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];

  if ($node->bundle() === 'article') {
    // Preprocessing here
  }
}
```

## File Structure

**Custom Module Structure:**
```
web/modules/custom/mymodule/
├── mymodule.info.yml
├── mymodule.module
├── mymodule.routing.yml
├── mymodule.services.yml
├── mymodule.permissions.yml
├── config/
│   ├── install/
│   └── schema/
├── src/
│   ├── Controller/
│   ├── Form/
│   ├── Plugin/
│   └── Services/
└── tests/
    ├── src/
    │   ├── Functional/
    │   └── Unit/
```

**Custom Theme Structure:**
```
web/themes/custom/ui_suite_arsapps/
├── ui_suite_arsapps.info.yml
├── ui_suite_arsapps.theme
├── ui_suite_arsapps.libraries.yml
├── package.json
├── scss/
│   ├── _uswds-theme.scss
│   └── main.scss
├── templates/
│   ├── layout/
│   └── content/
└── js/
```

## Response Format

When providing code solutions:

1. **Explain the approach** (brief, 1-2 sentences)
2. **Provide the code** (clean, production-ready)
3. **Show DDEV commands** for setup/testing
4. **Mention configuration export** if applicable
5. **Note accessibility concerns** if UI-related
6. **Skip meta-commentary** (no confidence scores in code)

### Example Response Format

To create a custom block that displays group members:

1. Create the block plugin in your custom module
2. Use dependency injection for the entity type manager
3. Add proper caching with group context
    [Clean code here]

**Setup:**

```bash
ddev drush cr
ddev drush config:export -y
```
**Testing:**
```bash
ddev exec vendor/bin/phpunit web/modules/custom/mymodule/tests/
```
__Note: This block includes proper cache contexts for group membership changes.__

## Common Mistakes to Avoid
- **WRONG:** Suggesting host-level commands:
    ```bash
    composer require drupal/admin_toolbar  # Wrong
    ```
  **RIGHT:** Always use DDEV:
    ```bash
    ddev composer require drupal/admin_toolbar
    ```
- **WRONG:** Forgetting configuration export:
    "Install the module and you're done."
  **RIGHT:** Include full workflow:
    ```bash
    ddev composer require drupal/admin_toolbar
    ddev drush en admin_toolbar -y
    ddev drush cex -y
    git add config/sync composer.json composer.lock
    git commit -m "Add admin toolbar"
    ```
- **WRONG:** Hard-coded values in theming:
    ```scss
    .component {
    color: #1b1b1b;
    padding: 16px;
    }
    ```
  **RIGHT:** USWDS tokens:
    ```scss
    @use 'uswds-core' as *;

    .component {
      color: color('base-darkest');
      padding: units(2);
    }
    ```
## Final Reminders

1. DDEV is mandatory - Never suggest host-level PHP/Composer/Drush
2. Configuration must be exported - Always include ddev drush cex -y
3. Clean code only - No confidence scores or source citations in code
4. Version awareness - Default to Drupal 11 patterns
5. Group context matters - Consider multi-tenant implications
6. USWDS required - All frontend uses design tokens
7. Section 508 mandatory - Every UI change must be accessible
8. Security first - Parameterized queries, input validation, access checks


### When in doubt:

- Default to Drupal best practices
- Use DDEV for everything
- Export configuration
- Test accessibility
- Follow USWDS patterns
- Check Group context
