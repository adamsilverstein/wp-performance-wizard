=== WP Performance Wizard ===
Contributors: adamsilverstein
Tags: performance, ai, optimization, lighthouse, web-vitals
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use AI to analyze your WordPress site's performance and get actionable recommendations grounded in expert playbooks.

== Description ==

WP Performance Wizard employs an AI agent to analyze your site's performance and offer prioritized, actionable recommendations. It combines real measurement data (PageSpeed Insights / Lighthouse, your site's HTML, script attribution, active themes and plugins) with bundled expert playbooks so suggestions are grounded in well-known patterns - not just the model's prior knowledge.

= Key features =

* **Multi-source analysis** - pulls data from Lighthouse, your live page HTML, script attribution, and your active themes and plugins.
* **Choose your AI provider** - works with Google Gemini, OpenAI, or Anthropic Claude via the core Connectors API (no API keys stored in this plugin).
* **Expert playbooks** - bundled performance, Core Web Vitals, WordPress, and best-practice skills augment each analysis step.
* **Actionable checklist** - recommendations are tracked as a checklist so you can mark progress and re-test.
* **History** - past analysis runs are saved so you can compare runs over time.

= Connecting an AI provider =

This plugin uses WordPress 7.0's Connectors API for all provider credentials - it does not store API keys itself.

1. In wp-admin, open the core **Connectors** screen.
2. Find the connector that matches the AI provider you want to use (Google Gemini, OpenAI, or Anthropic) and add your API key.
3. Return to **Performance Wizard** - any connector with a configured key is automatically offered as an analysis model.

Credentials can also be supplied via environment variable or PHP constant using the Connectors API convention, e.g. `GEMINI_API_KEY`, `OPENAI_API_KEY`, or `ANTHROPIC_API_KEY`. Values defined this way take precedence over database-stored keys.

= Data sources analyzed =

* **PageSpeed Insights / Lighthouse** - runs Lighthouse audits against the front end for First Contentful Paint, Largest Contentful Paint, Cumulative Layout Shift, and more.
* **Site HTML** - inspects the source of home, single post, and archive pages for issues like excessive DOM size or render-blocking resources.
* **Script Attribution** - identifies every script loaded on the front end and attributes it to a plugin, theme, or core.
* **Themes and Plugins** - gathers metadata on active themes and plugins to surface known performance pitfalls.

= Privacy =

This plugin sends data about your site (HTML, script attribution, theme/plugin metadata, Lighthouse results) to whichever AI provider you have configured. No data is sent until you start an analysis run. No analytics or telemetry are sent to the plugin author.

== Installation ==

1. Upload the `wp-performance-wizard` folder to your `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Open the core **Connectors** screen and add an API key for at least one supported AI provider (Google Gemini, OpenAI, or Anthropic Claude).
4. Navigate to **Performance Wizard** in your WordPress admin dashboard.
5. Choose the data sources to include, pick an AI model, and run the analysis.

== Frequently Asked Questions ==

= Do I need a paid AI account? =

You need an API key for at least one of the supported providers (Google Gemini, OpenAI, or Anthropic Claude). Pricing depends on the provider and the number of analyses you run.

= Where are my API keys stored? =

This plugin does not store API keys. Credentials live in the core Connectors API and can be supplied via the wp-admin Connectors screen, environment variables, or PHP constants.

= What WordPress version do I need? =

WordPress 7.0 or later. This plugin depends on the core Connectors API, which ships in WordPress 7.0.

= Can I run this on a staging site? =

Yes - and we recommend it for the first run, since the analysis sends data about your site to the configured AI provider.

= Does it modify my site automatically? =

No. The plugin only analyzes and recommends. Any changes are up to you.

== Screenshots ==

1. Run an analysis from the Performance Wizard admin screen, picking the data sources and AI model.
2. Review recommendations as an actionable checklist, with links back to the underlying data.

== Changelog ==

= 2.0.0 =
* Require WordPress 7.0.
* Removed built-in API key UI - credentials are now supplied via the core Connectors API.
* Migrated all AI agents to the WordPress 7.0 AI Client API.
* Added longer timeouts and exponential-backoff retries to AI agent calls.
* Recommendations are now tracked as an actionable, read-on-replay checklist.

= 1.3.1 =
* Added Gemini key entry in admin.

= 1.3.0 =
* Added checkboxes to select which data sources to use.

= 1.2.0 =
* Added the Script Attribution data source.

= 1.1.0 =
* Added experimental support for running comparisons between data points.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Requires WordPress 7.0. API keys are no longer stored in this plugin - move them to the core Connectors screen before upgrading.
