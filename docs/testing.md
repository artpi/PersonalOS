# PHP Unit Testing Guide

This repository includes comprehensive PHP unit testing infrastructure for the PersonalOS WordPress plugin.

## Available Test Commands

### Package.json Scripts

- `npm run test:unit` - Run only unit tests
- `npm run test:integration` - Run only integration tests  
- `npm run test` - Run all tests with comprehensive reporting
- `npm run test:all` - Alias for `npm run test`

### Direct Script Execution

```bash
./run-tests.sh
```

## Test Structure

### Unit Tests (`tests/unit/`)
- `TodoModuleTest.php` - Tests for TODO module functionality
- `EvernoteModuleTest.php` - Tests for Evernote module functionality

### Integration Tests (`tests/integration/`)
- `EvernoteModuleIntegrationTest.php` - Integration tests requiring external API

## Test Environment

Tests run in a WordPress environment using `wp-env` (WordPress Docker environment):

- **Development site**: http://localhost:8901
- **Test site**: http://localhost:8902
- **Database**: MySQL/MariaDB containers managed by wp-env

## Test Results

When running tests, results are saved in the `test-results/` directory:

- `summary.md` - Markdown formatted test report
- `summary.txt` - Plain text test summary
- `unit-output.txt` - Raw unit test output
- `integration-output.txt` - Raw integration test output

## GitHub Actions Integration

The repository includes a GitHub Actions workflow (`.github/workflows/php-tests.yml`) that:

1. **Automatically runs** on pushes to `main` and pull requests
2. **Tests multiple PHP versions** (currently 8.2)
3. **Posts results** as PR comments when running on pull requests
4. **Uploads artifacts** with detailed test results
5. **Handles failures gracefully** and provides debugging information

### Manual Workflow Trigger

You can manually trigger the test workflow from the GitHub Actions tab by using the "workflow_dispatch" trigger.

## Requirements

- **PHP 8.2+**
- **Node.js 20+**
- **Docker** (for wp-env)
- **Composer** (for PHP dependencies)

## Troubleshooting

### wp-env Issues

If `wp-env` fails to start:

1. Ensure Docker is running
2. Try: `npm run wp-env destroy && npm run wp-env start`
3. Check network connectivity for WordPress downloads

### Database Connection Issues

If tests fail with database errors:

1. Wait longer for wp-env to fully initialize
2. Check that all Docker containers are running: `docker ps`
3. Verify database connectivity: `npm run wp-env run tests-cli -- wp db check`

### Network Connectivity

In environments with restricted network access, tests may fail during wp-env setup. In such cases:

1. The GitHub Actions workflow should work in CI environments
2. Results will still be captured and reported even if tests fail to run

## Configuration Files

- `phpunit.xml.dist` - PHPUnit configuration
- `.wp-env.json` - WordPress environment configuration
- `tests/bootstrap.php` - Test bootstrap file
- `composer.json` - PHP dependencies including PHPUnit

## Development

When adding new tests:

1. **Unit tests**: Add to `tests/unit/` with `Test.php` suffix
2. **Integration tests**: Add to `tests/integration/` with `IntegrationTest.php` suffix
3. **Follow WordPress coding standards** and extend `WP_UnitTestCase`
4. **Test locally** with `./run-tests.sh` before committing