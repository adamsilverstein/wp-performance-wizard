## About
Performance Wizard employs an AI agent to analyze your site performance and offer recommendations.

## API Key
To use the Gemini agent currently included in the project, you need to obtain an API key by visiting https://aistudio.google.com/app/apikey. Paste the key in a file named `.keys/gemini-key.json`.

```
{
	"apikey": "XXXXXXXXXXXXXXXXXXXXXXXX"
}
```

## Analysis
The performance wizard will analyze the following data sources to make its recommendations:
* PageSpeed Insights API / Lighthouse - this remote API visits the site front end and performs a series of performance audits.
* Site HTML - this data source proved the source code of a front end page load for the home page, a single post and an archive page.
* Plugins / Theme - this data source provides a list of active plugins and the active theme, as well as meta data bout each plugin.

