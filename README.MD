# Wicket Memberships

Wicket memberships addon providing comprehensive membership functionality for WordPress, WooCommerce, and WooCommerce Subscriptions.

## Description

This plugin integrates Wicket's member data platform with WordPress, providing membership management, subscription handling, and member data synchronization.

## Requirements

- **WordPress**: 6.5+
- **PHP**: 8.0+
- **Wicket Base Plugin**: Required (active)
- **WooCommerce**: Required (active)
- **WooCommerce Subscriptions**: Required (active)
- **Composer**: For dependency management

## Installation

This plugin is not available in the WordPress.org plugin repository. It is distributed to Wicket clients for implementation by a developer.

```bash
cd wp-content/plugins
git clone https://github.com/industrialdev/wicket-wp-memberships.git
cd wicket-wp-memberships
composer install
```

Then activate through WordPress admin.

## Development

### Setup

```bash
# Install dependencies
composer install

# Install WordPress testing instance (if using Docker)
docker compose exec php bash -c "cd /var/www/html/web/app/plugins/wicket-wp-memberships && bin/install-wp-tests.sh wordpress_test root 'root' mariadb latest"
```

### ⚠️ IMPORTANT: Before Tagging a New Version

**Always run `composer production` before tagging a new version.** This command:
- Removes development dependencies
- Optimizes autoloader for production
- Generates a clean build without dev packages

```bash
composer production
```

Without this step, the plugin will include unnecessary dev dependencies in the release.

### Running Tests

The plugin uses **PEST** and **PHPUnit** for testing.

```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run tests with coverage report
composer test:coverage

# Run browser tests (requires local WordPress instance with Wicket Docker setup)
composer test:browser

# Run specific test file
./vendor/bin/pest tests/YourTest.php
```

**Note:** Browser tests require a local WordPress instance running with the Wicket Docker setup. Ensure you have a site (e.g., PACE or any other) running locally before executing browser tests.

**Playwright Setup:** Install Playwright globally if desired: `npm install -g playwright@latest && npx playwright install`

### Writing New Tests

1. **Create test file** in `tests/` with pattern `*Test.php`
2. **Extend AbstractTestCase** for WordPress function mocking

```php
<?php

declare(strict_types=1);

namespace Wicket_Memberships\Tests;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('Wicket_Memberships\YourClass')]
class MyNewTest extends AbstractTestCase
{
    public function test_something(): void
    {
        $this->assertTrue(true);
    }
}
```

3. **Use Brain Monkey** to mock WordPress functions:

```php
\Brain\Monkey\Functions\stubs([
    'get_option' => 'value',
    'add_action' => null,
    'add_filter' => null,
]);
```

4. **Run tests** - PHPUnit auto-discovers test files matching `*Test.php`

**Important:** Don't forget to cover complex logic parts of your code with tests. Test it, or trust it to fail.

### Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap with WordPress mocks
└── unit/
    └── ...Test.php            # Unit tests
```

### Code Style

```bash
# Check code style
composer lint

# Fix code style automatically
composer format
```

### Available Composer Scripts

```bash
composer production       # Build for production (remove dev deps, optimize autoload)
composer test            # Run all tests
composer test:unit       # Run unit tests only
composer test:coverage   # Run tests with HTML coverage report
composer test:browser    # Run browser tests
composer lint            # Check code style
composer format          # Fix code style
composer check           # Run lint + test
```

## Features

- **Membership Management**: Create and manage membership configurations
- **Subscription Integration**: Full WooCommerce Subscriptions integration
- **Member Data Sync**: Synchronize member data with Wicket platform
- **Custom Post Types**: Membership configurations and tiers
- **REST API**: Endpoints for membership operations
- **CSV Import**: Bulk membership data import
- **Admin Interface**: Comprehensive admin panels for membership management

## Architecture

### Main Components

- **Membership_Controller** - Core membership logic
- **Membership_Subscription_Controller** - Subscription handling
- **Membership_Post_Types** - Custom post type registration
- **Admin_Controller** - Admin interface management
- **Import_Controller** - CSV import functionality
- **Settings** - Plugin configuration
- **Utilities** - Helper functions

### Custom Post Types

- **Membership Config** - Membership configuration settings
- **Membership Tier** - Membership tier definitions

## Hooks & Filters

### Actions

```php
// Fired during plugin initialization
do_action('wicket_memberships_init');
```

### Filters

```php
// Modify membership data
apply_filters('wicket_memberships_data', $data);
```

## Support

- **Issues**: [GitHub Issues](https://github.com/industrialdev/wicket-wp-memberships/issues)
- **Documentation**: [Wicket Developer Docs](https://wicket.io/docs)

## License

GPL v2 or later

## Credits

Developed by [Wicket](https://wicket.io)
