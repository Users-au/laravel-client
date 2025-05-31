# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Comprehensive test suite with PHPUnit 10.x support
- GitHub Actions workflows for automated testing and code quality checks
- Support for PHP 8.0, 8.1, 8.2, and 8.3
- Support for Laravel 8.x, 9.x, and 10.x
- Detailed API documentation in README
- Troubleshooting section in documentation
- Migration guide for version upgrades
- Code coverage reporting
- Mockery integration for better testing

### Changed
- Updated README with comprehensive documentation
- Improved package structure and organization
- Enhanced error handling in AuthController
- Updated PHPUnit configuration for modern versions
- Modernized composer.json dependencies

### Fixed
- Route naming consistency issues
- Database migration compatibility
- OAuth callback error handling
- Test environment configuration
- Service provider registration

### Security
- Updated dependencies to latest secure versions
- Improved token handling and storage

## [1.0.0] - 2023-09-01

### Added
- Initial release
- Basic Users.au OAuth integration
- Laravel service provider
- Database migrations for user authentication
- Basic route definitions

### Features
- OAuth 2.0 authentication flow
- Automatic user creation and updates
- Token management (access and refresh tokens)
- Configurable redirect URLs
- Middleware support
