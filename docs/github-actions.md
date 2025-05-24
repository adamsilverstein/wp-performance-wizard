# GitHub Actions for WP Performance Wizard

This project uses a single GitHub Actions workflow to automatically run comprehensive code quality checks on every pull request and push to the main branch.

## Single Workflow Architecture

### Code Quality Checks (`code-quality.yml`)
- **Purpose**: Complete code quality validation including both PHPCS and PHPStan
- **Triggers**: Pull requests and pushes to main branch
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2 (matrix testing)
- **Analysis Level**: PHPStan Level 5 (strict analysis)
- **Execution**: Sequential - PHPCS first, then PHPStan

## Quality Checks Performed

### 1. PHPCS (WordPress Coding Standards)
- Enforces WordPress coding standards
- Validates PHP compatibility across versions
- Checks code formatting and style consistency
- Command: `composer run lint`

### 2. PHPStan (Static Analysis)
- Level 5 strict static analysis
- Catches potential bugs and type errors before runtime
- WordPress-specific function and hook awareness
- Uses `szepeviktor/phpstan-wordpress` for WordPress compatibility
- Command: `composer run phpstan`

## Workflow Features

- **Matrix Testing**: Runs on PHP 7.4, 8.0, 8.1, and 8.2
- **Dependency Caching**: Composer cache for faster builds
- **Fail-Safe**: Continues testing all PHP versions even if one fails
- **Clear Reporting**: Detailed summary of all checks performed
- **Error Annotations**: GitHub annotations for easy issue identification

### Workflow Structure
```
.github/workflows/
└── code-quality.yml    # Complete quality check workflow
```

## Local Development

Run the same checks locally before pushing:

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
  - PHP 7.4+ compatibility

- **PHPCS**: `phpcs.xml.dist`
  - WordPress Coding Standards
  - PHP compatibility checks
  - Custom ruleset configuration

## Workflow Execution Steps

For each PHP version in the matrix:

1. **Environment Setup**
   - Checkout repository code
   - Setup PHP with specified version
   - Configure Composer dependency caching
   - Install all Composer dependencies

2. **Code Standards Check**
   - Run PHPCS with WordPress Coding Standards
   - Validate formatting, style, and compatibility
   - Fail if any violations found

3. **Static Analysis**
   - Run PHPStan at Level 5
   - Analyze for potential bugs and type issues
   - Check WordPress-specific patterns

4. **Results Summary**
   - Generate completion report
   - List all checks performed
   - Provide clear success/failure status

## Benefits

1. **Automated Quality Assurance**: Every PR automatically validated
2. **Multi-PHP Compatibility**: Ensures code works across PHP 7.4-8.2
3. **Early Bug Detection**: Catches issues before they reach production
4. **Consistent Standards**: Enforces WordPress coding standards
5. **Fast Feedback**: Cached dependencies for quick execution
6. **PR Integration**: Results appear directly in pull request reviews
7. **Simple Maintenance**: Single workflow file reduces complexity
8. **Comprehensive Coverage**: Both style and logic validation

## Troubleshooting

### Common Issues and Solutions

**PHPCS Failures:**
```bash
# Auto-fix formatting issues
composer run format

# Check specific violations
composer run lint
```

**PHPStan Failures:**
```bash
# Run analysis locally
composer run phpstan

# Check configuration
cat phpstan.neon.dist
```

**Dependency Issues:**
```bash
# Update dependencies
composer install

# Clear cache if needed
composer clear-cache && composer install
```

### Local Debugging
Run the exact same commands as CI:
```bash
composer install --prefer-dist --no-progress --no-suggest --optimize-autoloader
composer run lint
composer run phpstan
```

## Extending Quality Checks

To add new quality tools:

1. **Add Composer Script**
   ```json
   {
     "scripts": {
       "new-tool": "path/to/new-tool"
     }
   }
   ```

2. **Add Workflow Step**
   ```yaml
   - name: Run New Quality Tool
     run: composer run new-tool
   ```

3. **Update Summary**
   ```yaml
   - name: Quality Check Summary
     if: always()
     run: |
       echo "✅ Code quality checks completed for PHP ${{ matrix.php-version }}"
       echo "📋 Checks performed:"
       echo "   - PHPCS (WordPress Coding Standards)"
       echo "   - PHPStan (Static Analysis Level 5)"
       echo "   - New Tool (Description)"
   ```

## Performance Optimizations

- **Composer Caching**: Dependencies cached between runs
- **Matrix Strategy**: Parallel execution across PHP versions
- **Optimized Dependencies**: `--optimize-autoloader` for faster loading
- **Minimal Output**: `--no-progress` for cleaner logs

This streamlined approach provides comprehensive code quality validation while maintaining simplicity and ease of maintenance.
