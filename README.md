# ARS Apps Drupal Site

A multi-research group web application platform for the USDA Agricultural Research Service (ARS), providing secure, accessible web applications with flexible group-based content management.

## Overview

ARS Apps Drupal site serves as a centralized platform for multiple USDA Agricultural Research Service labs and research groups. Each group can maintain their own content area with flexible visibility controls, while sharing common infrastructure and design patterns.

### Key Features

- **Group-based Content Management**: Each research group has isolated content areas with customizable permissions
- **USWDS Compliance**: Built on the U.S. Web Design System for Section 508 accessibility and federal design standards
- **No-Code Content Building**: Empowers content editors to create rich layouts using USWDS components
- **Flexible Permissions**: Supports varying visibility requirements from public to group-restricted content
- **Modern Drupal Stack**: Built on Drupal 11 with contemporary development practices

## Architecture

### Core Technology Stack

- **CMS**: Drupal 11.2+
- **PHP**: 8.3+ (required for Drupal 11)
- **Theme Framework**: UI Suite with USWDS components
- **Group Management**: Drupal Group module v3.3+
- **Component System**: UI Patterns with USWDS paragraph types

### Key Modules

#### Group Management

- **drupal/group** (3.3+): Core group functionality for multi-tenancy
- **drupal/groupmedia** (4.0+): Group-specific media management
- **drupal/group_content_menu** (3.0+): Group-based menu systems
- **drupal/flexible_permissions**: Granular permission control per group

#### UI/UX Framework

- **drupal/ui_suite_uswds** (4.0+): USWDS theme and components
- **drupal/ui_suite_uswds_paragraphs** (4.0+): USWDS paragraph types for content building
- **drupal/ui_suite_uswds_extras** (4.0+): Additional USWDS components
- **drupal/ui_patterns**: Pattern library system for reusable components
- **drupal/ui_skins** (1.1@alpha): Component styling system

#### Content Management

- **drupal/paragraphs**: Flexible content paragraphs
- **drupal/entity_reference_revisions**: Enhanced entity referencing
- **drupal/pathauto**: Automatic URL pattern generation
- **drupal/token**: Token replacement system

#### Administrative Tools

- **drupal/admin_toolbar**: Enhanced administrative interface
- **drupal/devel**: Development and debugging tools

## Theme Structure

### Active Theme: `ui_suite_arsapps`

Custom subtheme of UI Suite USWDS providing:

- ARS-specific branding and styling
- USWDS component integration
- Custom Sass compilation with Gulp
- Federal accessibility compliance

**Location**: `/web/themes/custom/ui_suite_arsapps/`

### Theme Features

- **USWDS Integration**: Full U.S. Web Design System component library
- **Section 508 Compliance**: Built-in accessibility features
- **Component-Based**: Reusable UI patterns for consistent design
- **Responsive Design**: Mobile-first, government-standard responsive layouts

## Group-Based Architecture

### Content Isolation Strategy

Each research group operates within isolated content spaces:

1. **Private Groups**: Content visible only to group members
2. **Public Groups**: Content viewable by all, editable by group members
3. **Mixed Visibility**: Granular control over individual content items
4. **Shared Resources**: Common content available across all groups

### Permission Flexibility

The flexible permissions system allows each group to configure:
- **View Permissions**: Public, authenticated users, or group members only
- **Edit Permissions**: Group administrators, content editors, or specific roles
- **Media Management**: Group-specific or shared media libraries
- **Menu Management**: Independent navigation structures per group

## Development Workflow

### Getting Started

1. **Install Dependencies**

   ```bash
   composer install
   ```

2. **Install Site from Configuration**

   ```bash
   drush site:install --existing-config
   ```

3. **Build Theme Assets**

   ```bash
   cd web/themes/custom/ui_suite_arsapps
   npm install
   npm run build
   ```

### Configuration Management

- **Export Configuration**: `drush config:export -y`
- **Import Configuration**: `drush config:import -y`
- **Verify Changes**: `drush config:export --diff`

Configuration is stored in `/config/sync/` and version controlled.

### Code Quality

- **Coding Standards**: Drupal coding standards via `phpcs`
- **Static Analysis**: PHPStan for code quality checking
- **Testing**: PHPUnit for automated testing

**Quality Commands**:

```bash
# Check coding standards
phpcs

# Run static analysis
phpstan

# Run tests
phpunit --filter Test path/to/test
```

## Content Building

### USWDS Component System

Content editors can build rich, accessible layouts using:

- **Cards**: Information cards with headers, media, and actions
- **Accordions**: Expandable content sections
- **Alerts**: Status and notification messages
- **Hero Sections**: Featured content areas
- **Step Indicators**: Process and workflow guides
- **Collection Items**: Grouped content displays

### Paragraph Types Available

The following USWDS paragraph types are available for content building:

- `ui_suite_uswds_card`: Card components with optional media
- `ui_suite_uswds_card_group`: Collections of related cards
- `ui_suite_uswds_accordion`: Expandable content sections
- `ui_suite_uswds_hero`: Featured content with background images
- `ui_suite_uswds_alert`: Status messages and notifications

## Security & Compliance

### Section 508 Accessibility

- USWDS components are Section 508 compliant by design
- Automated accessibility testing integrated into build process
- Semantic HTML structure and ARIA labels
- Keyboard navigation support
- Screen reader compatibility

### Security Features

- Regular security updates via Composer
- Role-based access control
- Group-based content isolation
- Secure media handling
- Input sanitization and validation

## Group Architecture Decisions

### Group Types

The ARS Apps platform implements three distinct group types to accommodate varying research collaboration needs:

1. **Public Research Groups** - Open science initiatives with public content visibility
   - Anonymous users can view content but not contribute
   - Automated membership approval for authenticated USDA users
   - Full media capabilities for educational outreach

2. **Private Research Groups** - Restricted access for sensitive research
   - No anonymous access
   - Invitation-only membership
   - Audit trail for compliance
   - Limited to essential content types

3. **Hybrid Research Groups** - Mixed visibility requirements
   - Public face with private working areas
   - Per-content visibility controls
   - Membership by approval
   - Full content type flexibility

### Key Architectural Decisions

**Role Structure**: Leverages Group v3's flexible permissions with distinct Outsider/Insider/Individual scopes, synchronized with Drupal's authentication system.

**Content Strategy**: Article nodes for updates, Basic pages for static content, comprehensive media library integration for research materials.

**Security Model**: Defense-in-depth approach with private groups completely hidden from non-members, while public groups encourage open science collaboration.

**Administrative Oversight**: Global administrators maintain access across all groups for compliance and support, with granular permission delegation to group administrators.

**Field Architecture**: Consistent metadata across group types (PI, funding, dates) with type-specific additions (NDAs for private, ORCID for public).

This architecture supports USDA ARS's dual mandate of advancing agricultural science through collaboration while protecting sensitive research and industry partnerships.

## Deployment

### Environment Configuration

- **Development**: Local development with ddev or similar
- **Staging**: Pre-production testing environment
- **Production**: Live federal hosting environment

### Build Process

1. Dependency installation via Composer
2. Configuration import from version control
3. Theme asset compilation
4. Cache clearing and site optimization

## Support & Maintenance

### Regular Maintenance Tasks

- Security updates via `composer update`
- Configuration exports after changes
- Cache clearing: `drush cache:rebuild`
- Log monitoring: `drush watchdog:show`
- Cron execution: `drush cron`

### Development Resources

- **Drupal Documentation**: https://www.drupal.org/docs
- **USWDS Components**: https://designsystem.digital.gov/
- **Group Module**: https://www.drupal.org/project/group
- **UI Suite**: https://www.drupal.org/project/ui_suite_uswds

## Contributing

When contributing to this project:

1. Follow Drupal coding standards
2. Test changes in development environment
3. Export configuration changes
4. Ensure accessibility compliance
5. Document significant changes

## License

This project follows Drupal's GPL-2.0-or-later licensing for custom code and integrates with federal open-source components where applicable.
