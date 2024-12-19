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

After installing and activating the plugin, navigate to the 'Performance Wizard' settings page in your WordPress admin dashboard.  Here, you can enter your API key for the Gemini agent.  You can then initiate a performance analysis of your site. The results and recommendations will be displayed on the same page.

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
