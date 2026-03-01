# Changelog

All notable changes to the Sheepdog module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-15

### Added
- Initial release of Sheepdog migration monitoring module
- 🐕 Event-driven migration logging with detailed change tracking
- Configurable ID field mapping for flexible entity matching
- Support for any Drupal entity type and migration source
- Separate log channels for general events and detailed edits
- Real-time migration monitoring capabilities
- Comprehensive Drush commands for log analysis:
  - `sheepdog logs:export` - Export logs in JSON, NDJSON, or CSV formats
  - `sheepdog logs:analyze` - Statistical analysis of migration performance
  - `sheepdog logs:tail` - Real-time log monitoring
- ELK stack compatible structured logging output
- Configurable logging levels and field tracking
- Skip detection for empty/invalid source rows
- Migration-agnostic design works with any migration module
- Comprehensive documentation and README
- Installation hooks with status messages
- Requirements checking for dependencies

### Features
- **Smart Entity Matching**: Multiple strategies for finding existing entities
- **Detailed Change Tracking**: Field-by-field diff generation
- **Performance Optimizations**: Memory management and lazy loading
- **Flexible Configuration**: Fine-grained control over logging behavior
- **Developer Friendly**: Easy to extend and customize
- **Production Ready**: Comprehensive error handling and logging

### Technical
- Compatible with Drupal 10.0+ and 11.0+
- Requires PHP 8.1+
- Full Drupal coding standards compliance
- Comprehensive PHPDoc documentation
- Event subscriber architecture for migration hooks
- Service-based dependency injection
- Configuration API integration