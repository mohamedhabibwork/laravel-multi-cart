# Laravel Multi-Cart Package - Test Suite

This directory contains comprehensive tests for the Laravel Multi-Cart package using [Pest](https://pestphp.com/) testing framework.

## 📋 Test Overview

The test suite covers all aspects of the Laravel Multi-Cart package:

- **Core Functionality**: Cart operations, calculations, and management
- **Storage Providers**: Session, Cache, Database, Redis, and File providers
- **Models**: Cart and CartItem Eloquent models
- **Traits**: HasCarts and Cartable traits
- **Events**: Complete event system testing
- **Exceptions**: Error handling and recovery
- **Configuration**: Configuration management and customization
- **Integration**: Real-world scenarios and workflows

## 🧪 Test Files

### Core Tests

- **`CartServiceTest.php`** - Core cart functionality
  - Cart creation and management
  - Adding, updating, and removing items
  - Calculations (subtotal, tax, total)
  - Cart cloning and provider conversion
  - User association

- **`ProvidersTest.php`** - Storage provider testing
  - Session provider operations
  - Cache provider operations
  - Database provider with persistence
  - File provider operations
  - Provider switching and flushing

- **`ModelsTest.php`** - Eloquent model testing
  - Cart model functionality
  - CartItem model functionality
  - Relationships and scopes
  - Configuration handling

### Feature Tests

- **`TraitsTest.php`** - Trait functionality
  - HasCarts trait for User models
  - Cartable trait for products
  - Relationship management
  - Cart operations through traits

- **`EventsTest.php`** - Event system
  - Cart events (Created, Updated, Deleted)
  - Item events (Added, Updated, Removed)
  - Event data verification
  - Event listener testing

- **`ExceptionsTest.php`** - Exception handling
  - CartNotFoundException scenarios
  - CartExistsException scenarios
  - InvalidCartProviderException scenarios
  - InvalidConfigurationException scenarios
  - Error recovery testing

### Advanced Tests

- **`ConfigurationTest.php`** - Configuration system
  - LaravelMultiCartConfig class
  - Global configuration management
  - Cart-specific configuration
  - Configuration persistence
  - Custom callbacks

- **`IntegrationTest.php`** - Integration scenarios
  - Complete shopping workflows
  - Multi-user cart scenarios
  - Provider conversion workflows
  - Real-world shopping scenarios
  - Performance stress tests

- **`ArchTest.php`** - Architecture testing
  - Code quality rules
  - Dependency constraints
  - Naming conventions

## 🚀 Running Tests

### Using the Test Runner Script

The easiest way to run tests is using the provided test runner script:

```bash
# Run all tests
./tests/run-tests.sh

# Run with coverage
./tests/run-tests.sh coverage

# Run in parallel (faster)
./tests/run-tests.sh parallel

# Run specific test file
./tests/run-tests.sh specific tests/CartServiceTest.php

# Show help
./tests/run-tests.sh help
```

### Using Pest Directly

You can also run tests directly using Pest:

```bash
# Run all tests
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/CartServiceTest.php

# Run tests with coverage
vendor/bin/pest --coverage

# Run tests in parallel
vendor/bin/pest --parallel

# Run tests with minimum coverage requirement
vendor/bin/pest --coverage --min=80
```

### Running Specific Test Groups

```bash
# Run only core functionality tests
vendor/bin/pest tests/CartServiceTest.php tests/ProvidersTest.php tests/ModelsTest.php

# Run only feature tests
vendor/bin/pest tests/TraitsTest.php tests/EventsTest.php tests/ExceptionsTest.php

# Run only integration tests
vendor/bin/pest tests/IntegrationTest.php tests/ConfigurationTest.php
```

## 📊 Test Coverage

The test suite aims for comprehensive coverage:

- **Minimum Coverage**: 80%
- **Target Coverage**: 90%+
- **Critical Components**: 95%+

### Coverage Reports

When running with coverage, reports are generated in:
- **HTML Report**: `tests/coverage/index.html`
- **Clover XML**: `build/logs/clover.xml`

## 🏗️ Test Environment Setup

### Requirements

- PHP 8.2+
- Laravel 11+
- SQLite (for testing database)
- Pest PHP testing framework

### Environment Configuration

Tests use the following environment:
- **Database**: SQLite in-memory
- **Cache**: Array driver
- **Session**: Array driver
- **Queue**: Sync driver

### Test Database

Tests automatically create the following tables:
- `carts` - Cart storage
- `cart_items` - Cart item storage  
- `users` - Test user models
- `products` - Test product models

## 🎭 Test Fixtures

### User Model (`tests/Fixtures/User.php`)
- Uses `HasCarts` trait
- Provides user-cart relationship testing

### Product Model (`tests/Fixtures/Product.php`)
- Uses `Cartable` trait
- Provides product-cart relationship testing
- Implements cart-specific methods

## 📝 Writing Tests

### Test Structure

Tests follow the Pest framework structure:

```php
describe('Feature Group', function () {
    beforeEach(function () {
        // Setup for each test in this group
    });

    it('should do something specific', function () {
        // Test implementation
        expect($result)->toBe($expected);
    });
});
```

### Test Conventions

1. **Descriptive Names**: Test names should clearly describe what is being tested
2. **Arrange-Act-Assert**: Structure tests with clear setup, action, and verification
3. **Isolation**: Each test should be independent and not rely on other tests
4. **Fixtures**: Use the provided fixture models for consistent testing

### Adding New Tests

When adding new functionality:

1. **Create Test First**: Follow TDD principles
2. **Test All Paths**: Include happy path and edge cases
3. **Test Exceptions**: Verify error handling
4. **Update Documentation**: Add test descriptions to this README

## 🐛 Debugging Tests

### Common Issues

1. **Database Issues**
   ```bash
   # Clear test database
   rm -f database/database.sqlite
   ```

2. **Cache Issues**
   ```bash
   # Clear application cache
   php artisan cache:clear
   ```

3. **Memory Issues**
   ```bash
   # Increase memory limit
   php -d memory_limit=512M vendor/bin/pest
   ```

### Debugging Commands

```bash
# Run tests with verbose output
vendor/bin/pest --verbose

# Run specific test with debugging
vendor/bin/pest tests/CartServiceTest.php --filter="can add items to cart"

# Run tests and stop on first failure
vendor/bin/pest --stop-on-failure
```

## 🔧 Test Configuration

### Pest Configuration (`tests/Pest.php`)

Basic Pest setup and global test configuration.

### TestCase (`tests/TestCase.php`)

Base test case with:
- Laravel package testing setup
- Database migration setup
- Service provider registration
- Environment configuration

## 📈 Continuous Integration

### GitHub Actions

Tests run automatically on:
- Push to `main` or `develop` branches
- Pull requests to `main` or `develop` branches

### Test Matrix

- **PHP Versions**: 8.2, 8.3
- **Laravel Versions**: 11.*
- **Operating Systems**: Ubuntu, Windows
- **Dependencies**: prefer-lowest, prefer-stable

### Quality Gates

- All tests must pass
- Minimum 80% code coverage
- Code style checks (Pint)
- Static analysis (PHPStan)
- Architecture tests

## 🎯 Performance Testing

### Load Testing

The integration tests include performance scenarios:
- Large cart handling (100+ items)
- Concurrent cart operations
- Provider switching performance

### Benchmarking

```bash
# Run performance tests
vendor/bin/pest tests/IntegrationTest.php --filter="Performance"

# Profile specific operations
vendor/bin/pest --profile
```

## 📚 Additional Resources

- [Pest Documentation](https://pestphp.com/docs)
- [Laravel Testing](https://laravel.com/docs/testing)
- [Orchestra Testbench](https://packages.tools/testbench)
- [Package Development Guide](../README.md)

## 🤝 Contributing

When contributing tests:

1. Follow existing test patterns
2. Ensure comprehensive coverage
3. Include both positive and negative test cases
4. Update this documentation if needed
5. Run the full test suite before submitting

---

For questions about testing, please refer to the main package documentation or open an issue. 
