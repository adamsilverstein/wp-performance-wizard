## About
Performance Wizard employs an AI agent to analyze your site performance and offer recommendations.

## Installation

This plugin can be installed like any other WordPress plugin.

1. Upload the `wp-performance-wizard` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## API Key
To use the Gemini agent currently included in the project, you need to obtain an API key by visiting https://aistudio.google.com/app/apikey.

Visit Performance Wizard -> Gemini to enter your API key, or manually create a file in the `.keys` directory dnamed `.keys/gemini-key.json` with this content:

```
{
	"apikey": "[YOUR_API_KEY]"
}
```
## Usage

After installing and activating the plugin, navigate to Performance Wizard -> Gemini in your WordPress admin dashboard.  Here, you can enter your API key for the Gemini agent.  You can then initiate a performance analysis of your site. The results and recommendations will be displayed on the same page.

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
* **Summary step** → `core-web-vitals`, `wp-performance`

Sources:
* [addyosmani/web-quality-skills](https://github.com/addyosmani/web-quality-skills) — MIT
* [WordPress/agent-skills](https://github.com/WordPress/agent-skills) — GPL-2.0-or-later

Attribution and the pinned upstream commits are recorded in [`includes/skills/NOTICE.md`](includes/skills/NOTICE.md).

The step-to-skill mapping can be filtered via `wp_performance_wizard_skill_slugs_for_step`, and the feature can be toggled in **Performance Wizard → Settings** or with the `wp_performance_wizard_use_expert_skills` filter.
