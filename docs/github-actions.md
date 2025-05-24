# GitHub Actions for WP Performance Wizard

This project uses GitHub Actions to automatically run code quality checks on every pull request and push to the main branch.

## Workflows

### 1. PHPStan Static Analysis (`phpstan.yml`)
- **Purpose**: Runs PHPStan static analysis to catch potential bugs and type errors
- **Triggers**: Pull requests and pushes to main branch
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2
- **Analysis Level**: Level 5 (strict analysis)
- **Features**:
  - WordPress-aware analysis using `szepeviktor/phpstan-wordpress`
  - Composer dependency caching for faster builds
  - Error annotations in PR diffs

### 2. Code Quality (`code-quality.yml`)
- **Purpose**: Comprehensive code quality checks including both PHPCS and PHPStan
- **Triggers**: Pull requests and pushes to main branch
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2
- **Checks**:
  - PHPCS with WordPress Coding Standards
  - PHPStan static analysis (Level 5)
- **Features**:
  - Matrix testing across multiple PHP versions
  - Composer dependency caching
  - Detailed summary reporting

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

## Benefits

1. **Automated Quality Assurance**: Every PR gets automatically checked
2. **Multi-PHP Support**: Ensures compatibility across PHP 7.4-8.2
3. **Early Bug Detection**: PHPStan catches potential issues before they reach production
4. **Consistent Standards**: Enforces WordPress coding standards across all contributions
5. **Fast Feedback**: Cached dependencies make checks run quickly
6. **PR Integration**: Results appear directly in pull request reviews

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
