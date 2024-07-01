<?php
/**
 * A class that encapulates the strategy to be used by the AI agent when
 * analyzing the performance of a WordPress site. Describes the steps the agernt will use
 * and the prompts it will use to gather data.
 */

class Performance_Wizard_Analysis_Plan {
	// Properties

	/**
	 * The name of the analysis plan.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The description of the analysis plan.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * The data sources to be used by the AI agent when analyzing the performance of a WordPress site.
	 *
	 * @var array
	 */
	private $sources = array (
		'class-performance-wizard-data-source-lighthouse.php',
		'class-performance-wizard-data-source-html.php',
		'class-performance-wizard-data-source-themes-and-plugins.php',
	);

	private $user_prompts = array(
		'Welcome to the Performance Wizard. I will analyze the performance of your WordPress site.',
		'The analysis will follow a series of steps and I will report on each step as I progress.',
	);

	/**
	 * The primary prompt is used to set up the LLM, instructing it on its goals, behaviour and the steps it will use.
	 */
	private $primary_prompt = "You will play the role of a web performance expert. You will receive a sries of data points about
	the website you are analyzing. For each data point, you will provide a summary of the information received and how it reflects
	on the perfomance of the site. For each step, you will offer reccomendations for hoe the performsance might be improved.
	You will rememeber the results of each step and at the end of the process, you will provide an overall summary and set of recommendations
	for how the site performance might be improved. You will assist the user in making thrdse changes, then repeat the performance
	analysis steps, comparing the new results with the previous results."

}