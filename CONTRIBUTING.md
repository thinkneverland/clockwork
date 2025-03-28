# Contributing to Tapped

Thank you for considering contributing to Tapped! This document outlines the guidelines and processes for contributing to the project.

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Environment](#development-environment)
4. [Architecture Guidelines](#architecture-guidelines)
5. [Pull Request Process](#pull-request-process)
6. [Testing Guidelines](#testing-guidelines)
7. [Documentation](#documentation)
8. [Release Process](#release-process)

## Code of Conduct

By participating in this project, you agree to abide by our Code of Conduct. Please be respectful, considerate, and collaborative.

## Getting Started

1. **Fork the Repository**
   - Create a fork of the Tapped repository on GitHub

2. **Clone Your Fork**
   ```bash
   git clone https://github.com/YOUR-USERNAME/tapped.git
   cd tapped
   ```

3. **Add Upstream Remote**
   ```bash
   git remote add upstream https://github.com/thinkneverland/tapped.git
   ```

4. **Create a Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Environment

### Requirements

- PHP 8.0 or higher
- Composer
- Node.js 14+ and npm (for browser extension)
- Laravel 8+ project for testing

### Setup Steps

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Extension Development**
   ```bash
   cd extension
   npm install
   npm run dev
   ```

3. **Set Up Laravel Test App**
   ```bash
   # Create a new Laravel app
   composer create-project laravel/laravel tapped-test-app
   cd tapped-test-app
   
   # Link local Tapped package
   composer config repositories.tapped path "../tapped"
   composer require thinkneverland/tapped:@dev
   ```

4. **Configure for Development**
   ```bash
   php artisan vendor:publish --provider="ThinkNeverland\Tapped\TappedServiceProvider"
   ```

## Architecture Guidelines

Tapped follows these architectural principles:

### 1. Service Class Pattern

- Business logic should be encapsulated in service classes
- Services should have a single responsibility
- Use dependency injection for service dependencies

Example:
```php
// Good
class DebugStateSerializer
{
    public function toJson($data) { /* ... */ }
    public function toBinary($data) { /* ... */ }
}

// Avoid
class DebugState
{
    public function serialize() { /* ... */ }
    public function deserialize() { /* ... */ }
    public function collectData() { /* ... */ } // Too many responsibilities
}
```

### 2. SOLID Principles

- **Single Responsibility**: Classes should have one reason to change
- **Open/Closed**: Open for extension, closed for modification
- **Liskov Substitution**: Subtypes must be substitutable for their base types
- **Interface Segregation**: Many specific interfaces over one general interface
- **Dependency Inversion**: Depend on abstractions, not concretions

### 3. Logical Structure

Follow this structure for new code:
- `src/` - Main package code
  - `DataCollectors/` - Classes that collect debug data
  - `Http/Controllers/` - HTTP controllers
  - `Services/` - Service classes
  - `Support/` - Helper classes and traits
  - `Facades/` - Laravel facades
  - `Console/Commands/` - Artisan commands
- `config/` - Configuration files
- `resources/` - Assets and views
- `extension/` - Browser extension code
- `ide-integrations/` - IDE integration code
- `tests/` - Test files

### 4. PSR Standards Compliance

- Follow PSR-1 and PSR-12 coding standards
- Use PSR-4 for autoloading
- Follow PSR-2 for code style

## Pull Request Process

1. **Update Your Fork**
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

2. **Make Your Changes**
   - Write code following architecture guidelines
   - Add/update tests for new functionality
   - Update documentation as needed

3. **Run Tests and Code Style Checks**
   ```bash
   composer test
   composer cs:fix
   ```

4. **Commit Your Changes**
   ```bash
   git add .
   git commit -m "Add feature: brief description"
   ```

5. **Push to Your Fork**
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Create Pull Request**
   - Create a PR from your fork to the main repository
   - Fill out the PR template with details about your changes
   - Link any related issues

7. **Code Review**
   - Address review feedback
   - Make requested changes
   - Push additional commits to your branch

## Testing Guidelines

1. **Write Tests for New Code**
   - Unit tests for individual components
   - Feature tests for API endpoints
   - Browser tests for UI components

2. **Test Structure**
   - Unit tests in `tests/Unit/`
   - Feature tests in `tests/Feature/`
   - Browser tests in `tests/Browser/`

3. **Running Tests**
   ```bash
   # Run all tests
   composer test
   
   # Run specific test suite
   vendor/bin/phpunit --testsuite Unit
   vendor/bin/phpunit --testsuite Feature
   vendor/bin/phpunit --testsuite Browser
   ```

4. **Coverage**
   - Aim for at least 80% code coverage
   - Test edge cases and error scenarios

## Documentation

1. **Code Comments**
   - Add DocBlocks to classes and methods
   - Comment complex logic sections

2. **Update Documentation**
   - Update relevant markdown files in the `docs/` directory
   - Keep README up-to-date with new features

3. **Examples**
   - Add examples for new features
   - Update existing examples as needed

## Release Process

1. **Version Bumping**
   - Follow semantic versioning (MAJOR.MINOR.PATCH)
   - Update version in package.json and composer.json

2. **CHANGELOG Updates**
   - Add entries for new features, changes, and fixes
   - Link to relevant PRs and issues

3. **Release Notes**
   - Write clear release notes
   - Highlight breaking changes

4. **Tagging**
   - Create git tag for new version
   - Push tags to repository

Thank you for contributing to Tapped! Your efforts help make this package better for everyone.
