# Contributing

Thank you for considering contributing to the Laravel Multi-Cart package! We welcome contributions from the community and are pleased to have them.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Development Setup](#development-setup)
- [Making Changes](#making-changes)
- [Testing](#testing)
- [Coding Standards](#coding-standards)
- [Pull Request Process](#pull-request-process)
- [Reporting Issues](#reporting-issues)
- [Feature Requests](#feature-requests)
- [Security Issues](#security-issues)

## Code of Conduct

This project and everyone participating in it is governed by our Code of Conduct. By participating, you are expected to uphold this code.

## Development Setup

### Requirements

- PHP 8.2+
- Composer
- Laravel 11.0+
- Git

### Setup Steps

1. **Fork the Repository**
   ```bash
   # Fork the repository on GitHub, then clone your fork
   git clone https://github.com/YOUR_USERNAME/laravel-multi-cart.git
   cd laravel-multi-cart
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Set Up Testing Environment**
   ```bash
   # Copy the test environment file
   cp .env.testing.example .env.testing
   
   # Set up the test database (SQLite is used by default)
   touch database/testing.sqlite
   ```

4. **Run Initial Tests**
   ```bash
   composer test
   ```

## Making Changes

### Before You Start

1. **Check Existing Issues**: Look through existing issues to see if your bug/feature is already being discussed.

2. **Create an Issue**: For significant changes, please create an issue first to discuss the proposed changes.

3. **Create a Branch**: Create a new branch for your feature or bug fix:
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bug-fix
   ```

### Development Guidelines

1. **Follow PSR Standards**: We follow PSR-12 coding standards.

2. **Write Tests**: All new features must include tests. Bug fixes should include tests that would have caught the bug.

3. **Update Documentation**: Update README.md and other documentation as needed.

4. **Maintain Backward Compatibility**: Unless it's a major version, avoid breaking changes.

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
./vendor/bin/pest tests/CartServiceTest.php

# Run tests with specific filter
./vendor/bin/pest --filter="can add items"
```

### Test Structure

We use Pest for testing. Tests are organized by functionality:

- `tests/CartServiceTest.php` - Core cart operations
- `tests/TraitsTest.php` - HasCarts and Cartable trait tests
- `tests/ProvidersTest.php` - Storage provider tests
- `tests/EventsTest.php` - Event system tests
- `tests/ModelsTest.php` - Model functionality tests
- `tests/ConfigurationTest.php` - Configuration tests
- `tests/IntegrationTest.php` - Complex integration scenarios
- `tests/ExceptionsTest.php` - Exception handling tests

### Writing Tests

```php
<?php

use HCart\LaravelMultiCart\Facades\LaravelMultiCart;

describe('Feature Name', function () {
    it('should do something specific', function () {
        // Arrange
        $cart = LaravelMultiCart::cart('test_cart');
        $product = Product::factory()->create();
        
        // Act
        $cart->add($product, 2);
        
        // Assert
        expect($cart->count())->toBe(2)
            ->and($cart->has($product))->toBeTrue();
    });
});
```

### Test Database

Tests use SQLite in-memory database by default. The test environment is configured in `phpunit.xml.dist`.

## Coding Standards

### PSR-12 Compliance

We follow PSR-12 coding standards. Run PHP CS Fixer to ensure compliance:

```bash
composer format
```

### Code Style Guidelines

1. **Use Type Declarations**: Always use type hints for parameters and return types.

```php
public function add(Model $cartable, int $quantity = 1, array $attributes = []): self
{
    // Implementation
}
```

2. **Use Strict Types**: All PHP files should declare strict types.

```php
<?php

declare(strict_types=1);

namespace HCart\LaravelMultiCart\Services;
```

3. **Documentation**: Use PHPDoc for all public methods.

```php
/**
 * Add item to cart
 *
 * @param  Model  $cartable  The model to add to cart
 * @param  int  $quantity  Quantity to add
 * @param  array  $attributes  Additional attributes
 * @return self
 */
public function add(Model $cartable, int $quantity = 1, array $attributes = []): self
{
    // Implementation
}
```

4. **Error Handling**: Use specific exceptions from the package's exception namespace.

```php
use HCart\LaravelMultiCart\Exceptions\CartNotFoundException;

if (!$this->exists()) {
    throw new CartNotFoundException($this->name);
}
```

### Architecture Guidelines

1. **Single Responsibility**: Each class should have a single responsibility.

2. **Interface Segregation**: Use specific interfaces rather than large ones.

3. **Dependency Injection**: Use Laravel's service container for dependency injection.

4. **Event-Driven**: Use events for extensibility points.

## Pull Request Process

### Before Submitting

1. **Run Tests**: Ensure all tests pass.
   ```bash
   composer test
   ```

2. **Check Code Style**: Run PHP CS Fixer.
   ```bash
   composer format
   ```

3. **Update Documentation**: Update README.md if your changes affect the public API.

4. **Write Descriptive Commit Messages**: Use clear, descriptive commit messages.

### Pull Request Template

When creating a pull request, please include:

1. **Description**: Clear description of what the PR does.

2. **Related Issues**: Reference any related issues.

3. **Testing**: Describe how you tested your changes.

4. **Breaking Changes**: Note any breaking changes.

5. **Checklist**: Use the provided PR template checklist.

### Example PR Description

```markdown
## Description
Add support for custom item validation callbacks in cart configuration.

## Related Issues
Fixes #123

## Changes Made
- Added `item_validation` callback to configuration
- Updated CartService to use validation callback before adding items
- Added comprehensive test coverage
- Updated documentation

## Testing
- All existing tests pass
- Added new tests for validation scenarios
- Tested with different validation callbacks

## Breaking Changes
None - this is backward compatible.

## Checklist
- [x] Tests added/updated
- [x] Documentation updated
- [x] Code follows style guidelines
- [x] No breaking changes (or clearly documented)
```

## Reporting Issues

### Bug Reports

When reporting bugs, please include:

1. **Laravel Version**: Version of Laravel you're using
2. **Package Version**: Version of the package
3. **PHP Version**: Your PHP version
4. **Steps to Reproduce**: Clear steps to reproduce the issue
5. **Expected Behavior**: What you expected to happen
6. **Actual Behavior**: What actually happened
7. **Code Samples**: Minimal code to reproduce the issue

### Issue Template

```markdown
**Package Version:** 1.0.0
**Laravel Version:** 11.0.0
**PHP Version:** 8.2.0

**Description:**
Brief description of the issue.

**Steps to Reproduce:**
1. Step one
2. Step two
3. Step three

**Expected Behavior:**
What should happen.

**Actual Behavior:**
What actually happens.

**Code Sample:**
```php
// Minimal code to reproduce
```

**Additional Context:**
Any additional information, screenshots, etc.
```

## Feature Requests

For feature requests:

1. **Check Existing Issues**: Ensure the feature hasn't been requested already.

2. **Describe the Problem**: Explain what problem the feature would solve.

3. **Propose a Solution**: Describe your proposed solution.

4. **Consider Alternatives**: Have you considered alternative solutions?

5. **Additional Context**: Any additional context about the feature request.

## Security Issues

**Do not create public GitHub issues for security vulnerabilities.**

Instead, please email security issues to: [security@hcart.dev](mailto:security@hcart.dev)

Include:
- A description of the vulnerability
- Steps to reproduce (if applicable)
- Any potential impact
- Suggested fix (if you have one)

We will respond to security issues within 48 hours.

## Recognition

Contributors will be recognized in:
- The CHANGELOG.md file
- The README.md credits section
- GitHub's contributor graph

## Getting Help

If you need help with contributing:

1. **Check Documentation**: Review the README.md and code comments
2. **Join Discussions**: Use GitHub Discussions for questions
3. **Create an Issue**: Create an issue with the "question" label

## Development Tips

### Useful Commands

```bash
# Run tests with watcher
./vendor/bin/pest --watch

# Generate test coverage report
composer test-coverage

# Fix code style issues
composer format

# Run static analysis (if configured)
./vendor/bin/phpstan analyse

# Clear test database
rm database/testing.sqlite && touch database/testing.sqlite
```

### IDE Setup

For better development experience:

1. **Install Laravel IDE Helper**
2. **Configure PHP CS Fixer in your IDE**
3. **Set up Pest/PHPUnit integration**
4. **Configure Xdebug for debugging**

### Debugging Tests

```php
// Use Ray for debugging (if installed)
ray($variable);

// Use dump and die in tests
dump($cart->items());
dd($cart->getConfig());

// Use expectation debugging
expect($cart->count())->dd()->toBe(2);
```

Thank you for contributing to Laravel Multi-Cart! 
