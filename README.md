## About
Performance Wizard employs an AI agent to analyze your site performance and offer recommendations.

## API Key
To use the Gemini agent currently included in the project, you need to obtain an API key by visiting https://aistudio.google.com/app/apikey.

Visit Performance Wizard -> Gemini to enter your API key, or manually create a file in the `.keys` directory dnamed `.keys/gemini-key.json` with this content:

```
{
	"apikey": "[YOUR_API_KEY]"
}
```
## Versions
* 1.3.1 - Added Gemini key entry in admin.
* 1.3.0 - Added checkboxes to select which data sources to use.
* 1.2.0 - Added the Script Attribution data source.
* 1.1.0 - Added experimental support for running comparisons between data points.
* 1.0.0 - Initial release.

## Analysis
The performance wizard will analyze the following data sources to make its recommendations:
* PageSpeed Insights API / Lighthouse - this remote API visits the site front end and performs a series of performance audits.
* Site HTML - this data source proved the source code of a front end page load for the home page, a single post and an archive page.
* Script Attribution - this data source provides a list of all scripts loaded on the site and identifies their source: plugin, theme or core.
* Plugins / Theme - this data source provides a list of active plugins and the active theme, as well as meta data bout each plugin.
