# GitHub Actions for WP Performance Wizard

This project uses GitHub Actions to automatically run code quality checks on every pull request and push to the main branch.

## Workflows

### 1. Reusable Setup Workflow (`_setup-php.yml`)
- **Purpose**: Shared workflow that handles PHP environment setup and runs quality checks
- **Type**: Reusable workflow (called by other workflows)
- **Features**:
  - Configurable PHP versions via input parameters
  - Composer dependency caching for faster builds
  - Conditional execution of PHPCS and PHPStan
  - Matrix testing across multiple PHP versions
  - WordPress-aware analysis using `szepeviktor/phpstan-wordpress`

### 2. PHPStan Static Analysis (`phpstan.yml`)
- **Purpose**: Runs only PHPStan static analysis to catch potential bugs and type errors
- **Triggers**: Pull requests and pushes to main branch
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2
- **Analysis Level**: Level 5 (strict analysis)
- **Implementation**: Calls the reusable workflow with PHPStan-only configuration

### 3. Code Quality (`code-quality.yml`)
- **Purpose**: Comprehensive code quality checks including both PHPCS and PHPStan
- **Triggers**: Pull requests and pushes to main branch
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2
- **Checks**:
  - PHPCS with WordPress Coding Standards
  - PHPStan static analysis (Level 5)
- **Implementation**: Calls the reusable workflow with both PHPCS and PHPStan enabled

## Architecture Benefits

### Reusable Workflow Advantages
- **Single Source of Truth**: All PHP setup logic is centralized in one file
- **Maintainability**: Updates to PHP setup only need to be made in one place
- **Consistency**: Guaranteed identical setup across all workflows
- **Extensibility**: Easy to add new workflows that need PHP setup
- **Configurability**: Input parameters allow customization per workflow

### Reduced Duplication
- **Before**: ~80 lines across 2 workflows with significant duplication
- **After**: ~60 lines across 3 workflows with no duplication
- **Maintenance**: Changes to PHP setup require editing only one file

## Local Development

To run the same checks locally before pushing:

```bash
# Install dependencies
composer install

# Run PHPCS (coding standards)
composer run lint

# Run PHPStan (static analysis)
composer run phpstan

# Auto-fix PHPCS issues (where possible)
composer run format
```

## Configuration Files

- **PHPStan**: `phpstan.neon.dist`
  - Level 5 analysis
  - WordPress-specific rules
  - Excludes JS/CSS files

- **PHPCS**: `phpcs.xml.dist`
  - WordPress Coding Standards
  - PHP compatibility checks

## Workflow Structure

```
.github/workflows/
├── _setup-php.yml      # Reusable workflow (setup + quality checks)
├── phpstan.yml         # PHPStan-only workflow
└── code-quality.yml    # Combined PHPCS + PHPStan workflow
```

## Customization

The reusable workflow accepts several input parameters:

- `php-versions`: JSON array of PHP versions to test (default: `["7.4", "8.0", "8.1", "8.2"]`)
- `fail-fast`: Whether to fail fast on matrix builds (default: `false`)
- `run-phpcs`: Whether to run PHPCS (default: `false`)
- `run-phpstan`: Whether to run PHPStan (default: `true`)

## Benefits

1. **Automated Quality Assurance**: Every PR gets automatically checked
2. **Multi-PHP Support**: Ensures compatibility across PHP 7.4-8.2
3. **Early Bug Detection**: PHPStan catches potential issues before they reach production
4. **Consistent Standards**: Enforces WordPress coding standards across all contributions
5. **Fast Feedback**: Cached dependencies make checks run quickly
6. **PR Integration**: Results appear directly in pull request reviews
7. **Maintainable**: Centralized setup logic reduces maintenance overhead
8. **Extensible**: Easy to add new quality check workflows

## Troubleshooting

If a GitHub Action fails:

1. **PHPCS Failures**: Run `composer run format` to auto-fix formatting issues
2. **PHPStan Failures**: Check the specific errors and fix type-related issues
3. **Dependency Issues**: Ensure `composer.lock` is up to date

For local debugging, you can run the exact same commands that the CI uses:

```bash
composer install --prefer-dist --no-progress --no-suggest --optimize-autoloader
composer run lint
composer run phpstan
```

## Adding New Quality Check Workflows

To add a new workflow that needs PHP setup:

1. Create a new workflow file in `.github/workflows/`
2. Use the reusable workflow:
   ```yaml
   jobs:
     my-checks:
       uses: ./.github/workflows/_setup-php.yml
       with:
         run-phpcs: false
         run-phpstan: false
         # Add custom steps in the reusable workflow if needed
   ```
3. Extend the reusable workflow with additional input parameters if needed
