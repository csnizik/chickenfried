
# Changelog

All notable changes to the ARS Apps platform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial production deployment pipeline with automated versioning
- Comprehensive release documentation generation
- Security scanning with Trivy vulnerability detection
- SBOM (Software Bill of Materials) generation for compliance

### Changed
- Refactored GitHub configuration to remove Codespaces dependencies
- Standardized on DDEV-only local development workflow

### Fixed
- Resolved automated code review workflow issues

### Security
- Added pre-deployment security audit checks
- Implemented container image vulnerability scanning

<!--
================================================================================
CHANGELOG GUIDELINES
================================================================================

When adding entries, use these categories:

### Added
- New features or capabilities

### Changed
- Changes to existing functionality

### Deprecated
- Features that will be removed in future versions

### Removed
- Features that have been removed

### Fixed
- Bug fixes

### Security
- Security-related changes or vulnerability fixes

TIPS:
- Write entries from the user's perspective
- Start each entry with a verb (Added, Fixed, Updated, etc.)
- Reference issue/PR numbers where applicable: (#123)
- Keep entries concise but descriptive
- Group related changes together

EXAMPLE ENTRY:
### Added
- New sheep breeding report export to CSV/Excel format (#234)
- Bulk import for group membership via CSV upload (#245)

================================================================================
-->
