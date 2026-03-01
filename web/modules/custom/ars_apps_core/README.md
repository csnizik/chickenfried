# ARS Apps Core

Core functionality module for the ARS Apps Drupal platform.

## Overview

The ARS Apps Core module provides shared functionality, custom plugins, and utilities that are used platform-wide across the ARS Apps multi-tenant system. This module contains custom code that extends Drupal core and contrib modules to meet the specific needs of USDA Agricultural Research Service applications.

**Purpose:**
- Provide reusable components for all ARS Apps instances
- House custom functionality that doesn't belong in tenant-specific modules
- Extend base Drupal and contrib module features with ARS-specific requirements

**Scope:**
This module is for platform-wide functionality only. Tenant-specific or group-specific features should be implemented in their respective custom modules.

## Requirements

- Drupal: ^10 || ^11
- Webform module

## Features

### 1. Webform Contextual Help Block

A dynamic block that displays page-specific help content in webform wizard sidebars.

**Plugin ID:** `webform_contextual_help`

**Purpose:**
Provides contextual help content that changes based on the current wizard page, improving user experience for multi-step webforms.

**Features:**
- Automatically detects current wizard page from URL query parameter
- Loads help content from specially-named webform elements
- Caches intelligently per page and webform configuration
- Supports both numeric and key-based page navigation

#### Configuration

1. **Place the block:**
   - Navigate to: Structure > Block layout
   - Add block to `sidebar_second` region (or desired region)
   - Configure the block instance

2. **Block settings:**
   - **Webform ID:** Machine name of the target webform (required)
   - **Heading:** Optional global heading (see note below)

3. **In your webform:**
   - Add a **Processed Text** (or **Advanced HTML/Text**) element to each wizard page
   - Name the element: `{page_key}_contextual_help`
   - Examples:
     - `general_contextual_help`
     - `sequences_contextual_help`
     - `review_contextual_help`
   - Add your help content to these elements
   - The elements are automatically hidden from the form and shown in the block

#### Usage Example

**Webform structure:**
```yaml
general:
  '#type': wizard_page
  '#title': 'General Information'
  general_contextual_help:
    '#type': processed_text
    '#text': '<p>Enter basic information about your submission...</p>'
  field_name:
    '#type': textfield
    '#title': 'Name'

sequences:
  '#type': wizard_page
  '#title': 'Sequence Data'
  sequences_contextual_help:
    '#type': processed_text
    '#text': '<p>Upload your sequence files in FASTA format...</p>'
  field_file:
    '#type': webform_document_file
    '#title': 'Sequence File'
```

**Result:**
- When user is on "General Information" page → Shows general help content
- When user is on "Sequence Data" page → Shows sequence help content
- Content automatically updates without page reload when navigating wizard

#### Technical Details

**Page Detection:**
The block determines the current page using the `page` query parameter:
- Empty/missing → First page (skips `webform_start` if present)
- Numeric (e.g., `?page=2`) → Page index (1-based)
- String (e.g., `?page=sequences`) → Page key directly

**Caching:**
- Cache context: `url.query_args:page` (varies by wizard page)
- Cache tags: `webform:{webform_id}` (invalidates when webform changes)

**Template:**
The block uses standard block theming. Custom styling is provided via the `webform_contextual_help` library.

#### Important Notes

**Heading Configuration:**
The "Heading" field in block configuration applies to ALL pages. For page-specific headings, skip this field and include headings directly in your help content using USWDS markup:

```html
<div class="usa-summary-box" role="region" aria-labelledby="summary-box-general">
  <div class="usa-summary-box__body">
    <h4 class="usa-summary-box__heading" id="summary-box-general">
      Page-Specific Heading
    </h4>
    <div class="usa-summary-box__text">
      Your help content here...
    </div>
  </div>
</div>
```

**Note:** Replace `general` with the appropriate page key (e.g., `contact`, `confirmation`) to match your webform page.

**Tech Debt:**
Current implementation has limitations with the heading prop. Future refactoring should properly separate heading from body content to align with USWDS summary box component structure.

---

## Module Structure

```
ars_apps_core/
├── README.md                                    # This file
├── ars_apps_core.info.yml                      # Module definition
├── ars_apps_core.module                        # Module hooks
├── ars_apps_core.libraries.yml                 # Asset libraries
├── css/
│   └── webform-contextual-help.css            # Block styling
└── src/
    └── Plugin/
        └── Block/
            └── WebformContextualHelpBlock.php  # Block plugin
```

## Development

**Coding Standards:**
This module follows Drupal coding standards. Check code with:

```bash
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/ars_apps_core
```

**Auto-fix issues:**
```bash
ddev exec vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/ars_apps_core
```

## Adding New Features

When adding new functionality to this module, follow these guidelines:

**When to add code here:**
- ✅ Functionality needed across multiple tenants/groups
- ✅ Custom plugins that extend Drupal core or contrib
- ✅ Shared services and utilities
- ✅ Platform-wide integrations with USDA systems
- ✅ Reusable components for ARS Apps instances

**When NOT to add code here:**
- ❌ Tenant-specific business logic
- ❌ Group-specific content types or workflows
- ❌ Lab-specific data structures
- ❌ Single-use functionality for one application

**Documentation Requirements:**
All new features must include:
1. Section in this README with usage examples
2. PHPDoc comments on all public methods
3. Inline code comments explaining complex logic
4. Configuration examples where applicable

## Coding Standards

This module follows Drupal and ARS Apps coding standards.

**Check code:**
```bash
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/ars_apps_core
```

**Auto-fix issues:**
```bash
ddev exec vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/ars_apps_core
```

**Run tests** (when available):
```bash
ddev exec vendor/bin/phpunit web/modules/custom/ars_apps_core/tests/
```

## Maintenance

This module is maintained by the ARS Apps development team. For issues, questions, or contributions, follow the standard ARS Apps development workflow.

**Before committing:**
1. Run coding standards checks
2. Update this README if adding new features
3. Export configuration: `ddev drush cex -y`
4. Test functionality in a clean environment

## License

Developed for USDA Agricultural Research Service. All rights reserved.
