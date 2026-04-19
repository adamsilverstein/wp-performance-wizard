## About
Performance Wizard employs an AI agent to analyze your site performance and offer recommendations.

## Requirements

* WordPress 7.0 or later (required for the core Connectors API).
* PHP 7.4 or later.

## Installation

This plugin can be installed like any other WordPress plugin.

1. Upload the `wp-performance-wizard` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Connecting an AI provider

Performance Wizard uses WordPress 7.0's Connectors API for all provider credentials. It no longer stores API keys itself.

1. In wp-admin, open the core **Connectors** screen.
2. Find the connector that matches the AI provider you want to use (Google Gemini, OpenAI, or Anthropic) and add your API key.
3. Return to **Performance Wizard** — any connector with a configured key is automatically offered as an analysis model.

Credentials can also be supplied via environment variable or PHP constant using the Connectors API convention, e.g. `GEMINI_API_KEY`, `OPENAI_API_KEY`, or `ANTHROPIC_API_KEY`. Values defined this way take precedence over database-stored keys.

## Usage

After installing and activating the plugin and connecting at least one AI provider, navigate to **Performance Wizard** in your WordPress admin dashboard. Choose the data sources to include, pick a configured AI model if more than one is available, and run the analysis. Results and recommendations are rendered inline.

## Development

### Code Quality
This project uses automated code quality checks via GitHub Actions:

- **PHPStan**: Static analysis at level 5 with WordPress-specific rules
- **PHPCS**: WordPress Coding Standards compliance
- **Multi-PHP Testing**: Compatibility testing across PHP 7.4-8.2

#### Local Development Commands
```bash
# Install dependencies
composer install

# Run coding standards check
composer run lint

# Run static analysis
composer run phpstan

# Auto-fix coding standards issues
composer run format
```

See [docs/github-actions.md](docs/github-actions.md) for detailed information about the CI/CD setup.

## Versions
* 2.0.0 - Require WordPress 7.0. Removed built-in API key UI; credentials are now supplied via the core Connectors API.
* 1.3.1 - Added Gemini key entry in admin.
* 1.3.0 - Added checkboxes to select which data sources to use.
* 1.2.0 - Added the Script Attribution data source.
* 1.1.0 - Added experimental support for running comparisons between data points.
* 1.0.0 - Initial release.

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository.
2. Create a new branch for your changes.
3. Make your changes, including tests if applicable.
4. Submit a pull request.

## Analysis
The performance wizard will analyze the following data sources to make its recommendations:
* **PageSpeed Insights API / Lighthouse:** This uses the PageSpeed Insights API to run Lighthouse audits against the site's front end.  Lighthouse provides a comprehensive performance analysis, including metrics like First Contentful Paint, Largest Contentful Paint, and Cumulative Layout Shift.
* **Site HTML:** This analyzes the source code of front-end page loads for the home page, a single post, and an archive page, looking for potential performance bottlenecks in the HTML structure itself, such as excessive DOM size or render-blocking resources.
* **Script Attribution:** This identifies all scripts loaded on the site and attributes them to their source (plugin, theme, or core). This helps pinpoint scripts that might be contributing to slow page load times.
* **Plugins / Theme:** This gathers information about active plugins and the active theme, including metadata. This data can help identify potential performance issues related to specific plugins or themes.
