# GitHub Actions for WP Performance Wizard

This project uses GitHub Actions to automatically run code quality checks on every pull request and push to the main branch.

## Workflow

### Code Quality Checks (`code-quality.yml`)
- **Purpose**: Comprehensive code quality checks including both PHPCS and PHPStan
- **Triggers**: Pull requests and pushes to main branch
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2
- **Analysis Level**: PHPStan Level 5 (strict analysis)
- **Features**:
  - WordPress-aware analysis using `szepeviktor/phpstan-wordpress`
  - Composer dependency caching for faster builds
  - Matrix testing across multiple PHP versions
  - Sequential execution of both quality tools
  - Detailed summary reporting

### Checks Performed
1. **PHPCS (WordPress Coding Standards)**
   - Enforces WordPress coding standards
   - Checks PHP compatibility
   - Validates code formatting and style

2. **PHPStan (Static Analysis)**
   - Level 5 strict static analysis
   - Catches potential bugs and type errors
   - WordPress-specific function and hook awareness

## Architecture Benefits

### Single Workflow Advantages
- **Simplicity**: One workflow file to maintain
- **Clarity**: All quality checks visible in one place
- **Efficiency**: Setup runs once, both tools share the same environment
- **Easy Maintenance**: Updates only need to be made in one file
- **Streamlined CI**: Faster execution with shared setup

### Workflow Structure
```
.github/workflows/
└── code-quality.yml    # Complete quality check workflow
```

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
7. **Simple Maintenance**: Single workflow file reduces complexity
8. **Clear Reporting**: Comprehensive summary of all checks performed

## Workflow Execution

The workflow runs the following steps for each PHP version:

1. **Environment Setup**
   - Checkout code
   - Setup PHP with specified version
   - Configure Composer caching
   - Install dependencies

2. **Quality Checks**
   - Run PHPCS for coding standards
   - Run PHPStan for static analysis
   - Generate summary report

3. **Results**
   - Both tools must pass for the workflow to succeed
   - Detailed error reporting for any failures
   - Summary of completed checks

## Troubleshooting

If the GitHub Action fails:

1. **PHPCS Failures**: Run `composer run format` to auto-fix formatting issues
2. **PHPStan Failures**: Check the specific errors and fix type-related issues
3. **Dependency Issues**: Ensure `composer.lock` is up to date

For local debugging, you can run the exact same commands that the CI uses:

```bash
composer install --prefer-dist --no-progress --no-suggest --optimize-autoloader
composer run lint
composer run phpstan
```

## Extending the Workflow

To add additional quality checks:

1. Add new Composer scripts to `composer.json`
2. Add corresponding steps to the workflow after the existing checks
3. Update the summary section to include the new checks

Example of adding a new check:
```yaml
- name: Run New Quality Tool
  run: composer run new-tool

- name: Quality Check Summary
  if: always()
  run: |
    echo "✅ Code quality checks completed for PHP ${{ matrix.php-version }}"
    echo "📋 Checks performed:"
    echo "   - PHPCS (WordPress Coding Standards)"
    echo "   - PHPStan (Static Analysis Level 5)"
    echo "   - New Tool (Description)"
