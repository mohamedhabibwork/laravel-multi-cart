# Changelog

All notable changes to `laravel-multi-cart` will be documented in this file.

## [1.0.0] - 2024-01-15

### Added
- **Initial Release**: Complete Laravel Multi-Cart package with comprehensive features
- **Multiple Cart Instances**: Support for named cart instances (shopping, wishlist, favorites, etc.)
- **Configurable Storage Providers**: Session, cache, database, Redis, and file storage options
- **Polymorphic Relationships**: Full Eloquent model integration with morphable relationships
- **PHP 8+ Attributes**: Modern attribute-based configuration for tax and shipping
- **Advanced Tax System**: Percentage, fixed, and compound tax calculations
- **Sophisticated Shipping**: Fixed, percentage, weight-based, and piece-based shipping calculations
- **Bulk Operations**: High-performance bulk item addition with transaction support
- **User Integration**: HasCarts trait for seamless user-cart relationships
- **Cartable Items**: Cartable trait for models with cart-specific methods
- **Event System**: Comprehensive events for cart and item operations
- **Custom Callbacks**: Extensible callback system for item uniqueness and operations
- **Performance Optimizations**: Database transactions, query optimization, and caching
- **Automatic Cleanup**: Scheduled cleanup of expired carts
- **Provider Migration**: Seamless migration between storage providers
- **Cart Cloning**: Clone carts within or across providers
- **Free Shipping Thresholds**: Automatic free shipping based on cart totals
- **Multi-tenant Support**: Tenant-isolated cart configurations
- **Type Safety**: Full PHP 8.2+ type declarations and strict typing

### Features
- **Storage Providers**:
  - Session provider for guest carts
  - Database provider for persistent user carts with relationships
  - Cache provider for high-performance scenarios
  - Redis provider for distributed applications
  - File provider for simple deployments

- **Tax Calculations**:
  - Percentage-based tax with configurable rates
  - Fixed amount tax charges
  - Compound tax support for complex tax structures
  - Tax-inclusive and tax-exclusive pricing
  - Category-based tax configuration

- **Shipping Calculations**:
  - Fixed shipping rates
  - Percentage-based shipping (% of cart total)
  - Weight-based shipping with base rates and per-unit charges
  - Piece-based shipping with configurable grouping and maximum charges
  - Free shipping thresholds and qualification rules

- **Configuration System**:
  - Runtime configuration updates
  - Cart-specific configuration overrides
  - Global configuration management
  - Custom model support
  - Flexible callback system

- **User Experience**:
  - Guest to user cart migration
  - Multiple cart management per user
  - Cart sharing and collaboration features
  - Wishlist to cart conversion
  - Cart abandonment recovery

### Performance
- **Database Optimization**:
  - Proper indexing on all searchable columns
  - Efficient query patterns with eager loading
  - Bulk operations for large datasets
  - Transaction-wrapped operations for data integrity

- **Caching Strategy**:
  - Intelligent caching of expensive calculations
  - Provider-specific optimization
  - Memory usage optimization
  - Query result caching

- **Scalability**:
  - Tested with 1000+ items per cart
  - Concurrent user support
  - Distributed cache support
  - Load balancer compatible

### Security
- **Data Protection**:
  - Input validation and sanitization
  - SQL injection prevention
  - XSS protection
  - CSRF token validation

- **Access Control**:
  - User-based cart isolation
  - Session-based cart protection
  - Permission-based operations
  - Secure cart sharing

- **Privacy**:
  - Soft delete support for data recovery
  - Configurable data retention policies
  - GDPR compliance features
  - Audit trail support

### Testing
- **Comprehensive Test Suite**:
  - 183 tests with 545 assertions
  - 100% test coverage on core functionality
  - Integration tests for all providers
  - Performance benchmarking tests
  - Architecture tests for code quality

- **Test Categories**:
  - Unit tests for individual components
  - Integration tests for provider interactions
  - Feature tests for complete workflows
  - Performance tests for scalability
  - Security tests for vulnerability assessment

### Documentation
- **Complete Documentation**:
  - Comprehensive README with examples
  - API reference documentation
  - Usage examples for all features
  - Migration guides and best practices
  - Troubleshooting guide

- **Developer Resources**:
  - LLM training documentation (llms.txt)
  - Code examples and snippets
  - Architecture diagrams
  - Performance optimization guide
  - Deployment checklist

### Compatibility
- **System Requirements**:
  - PHP 8.2+ with modern features
  - Laravel 11+ framework
  - MySQL 8.0+, PostgreSQL 13+, SQLite 3.35+
  - Redis 6.0+ (for Redis provider)
  - Memcached 1.5+ (for cache provider)

- **Framework Integration**:
  - Laravel service provider auto-discovery
  - Artisan command integration
  - Event system integration
  - Queue system compatibility
  - Middleware support

### Commands
- **Artisan Commands**:
  - `cart:cleanup` - Clean up expired carts
  - `cart:migrate-provider` - Migrate between storage providers
  - `cart:publish-migrations` - Publish database migrations
  - Configuration publishing commands

### Breaking Changes
- None (initial release)

### Deprecated
- None (initial release)

### Removed
- None (initial release)

### Fixed
- None (initial release)

### Security
- Implemented comprehensive security measures from initial release
- Input validation and sanitization
- Protection against common vulnerabilities
- Secure session handling
- User authorization checks

---

## Development Notes

### Version Numbering
This package follows [Semantic Versioning](https://semver.org/):
- **MAJOR**: Incompatible API changes
- **MINOR**: New functionality in backward-compatible manner
- **PATCH**: Backward-compatible bug fixes

### Support Policy
- **Current Version**: Full support with new features and bug fixes
- **Previous Major**: Security fixes and critical bug fixes only
- **Older Versions**: End of life, upgrade recommended

### Contributing
See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:
- Reporting bugs
- Suggesting enhancements
- Submitting pull requests
- Code style requirements
- Testing requirements

### License
This package is open-source software licensed under the [MIT License](LICENSE.md).
