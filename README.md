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

## Releasing to WordPress.org

The plugin ships to the [WordPress.org plugin directory](https://wordpress.org/plugins/) via the [10up plugin deploy action](https://github.com/10up/action-wordpress-plugin-deploy).

### One-time setup (after initial wp.org approval)
1. Add two secrets to the GitHub repo: `SVN_USERNAME` and `SVN_PASSWORD` (your WordPress.org credentials).

### Cutting a release
1. Bump the `Version:` header in [`wp-performance-wizard.php`](wp-performance-wizard.php) and the matching `Stable tag` in [`readme.txt`](readme.txt). Add a changelog entry to `readme.txt`.
2. Commit and merge to `main`.
3. Publish a [GitHub Release](https://github.com/adamsilverstein/wp-performance-wizard/releases/new) with a tag like `v2.0.1`. The [`Deploy to WordPress.org` workflow](.github/workflows/deploy.yml) will push the tag to SVN and attach the built zip to the release.

### Building a zip locally
Used for the initial wp.org submission (before SVN access exists) or for local testing:

```bash
npm install
npm run build:release-zip
```

The zip is written to `releases/wp-performance-wizard-<version>.zip`. The build honors [`.distignore`](.distignore), which is the same exclude list the deploy action uses.

### Graphical assets
Icon and banner PNGs for the wp.org listing live in [`.wordpress-org/`](.wordpress-org/). The deploy action syncs that directory to the SVN `assets/` folder automatically.

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

## Expert reference skills
Each analysis step is augmented with bundled expert playbooks so recommendations are grounded in well-known patterns rather than relying solely on the model's prior knowledge.

Bundled skills live under `includes/skills/` and map to steps as follows:
* **Lighthouse** → `performance`, `core-web-vitals`
* **HTML** → `best-practices`, `performance`
* **Script Attribution** → `performance`, `best-practices`
* **Themes and Plugins** → `wp-performance`, `wp-plugin-development`
* **Summarize Results** → `core-web-vitals`, `wp-performance`

Sources:
* [addyosmani/web-quality-skills](https://github.com/addyosmani/web-quality-skills) — MIT
* [WordPress/agent-skills](https://github.com/WordPress/agent-skills) — GPL-2.0-or-later

Attribution and the pinned upstream commits are recorded in [`includes/skills/NOTICE.md`](includes/skills/NOTICE.md).

The step-to-skill mapping can be filtered via `wp_performance_wizard_skill_slugs_for_step`, and the feature can be toggled in **Performance Wizard → Settings** or with the `wp_performance_wizard_use_expert_skills` filter.
