<?php
/**
 * A class describingn the lighthouse data source.
 */

 class Performance_Wizard_Data_Source_Lighthouse {
		// Properties
	
		/**
		* The name of the data source.
		* 
		* @var string
		*/
		private $name = "Lighthouse";
	
		/**
		* The description of the data source.
		* 
		* @var string
		*/
		private $description = "Lighthouse is an open-source, automated tool for improving the quality of web pages. You can run it against any web page, public or requiring authentication. It has audits for performance, accessibility, progressive web apps, SEO and more.";
	
		/**
		* The prompt to use when passing the data to the AI agent.
		* 
		* @var string
		*/
		private $prompt = "Please provide the Lighthouse data for the site.";
	
		/**
		* A plain text description of the shape of the data returned from the data source.
		*/
		private $data_shape = "The data returned from Lighthouse is a JSON object with the following properties: performance, accessibility, best-practices, seo, and pwa.";
	
		/**
		* The cache for data retrieved from the data source.
		*/
		private $cache;
	
		/**
		* The cache length for this data source.
		*/
		private $cache_length = 3600;
	
		// Constructor
	
		// Methods
	
		/**
		* Get the data from the data source.
		* 
		* @return mixed The data from the data source.
		*/
		public function get_data() {
			// To be implemented by subclasses.
		}
	
		/**
		* Get the name of the data source.
		* 
		* @return string The name of the data source.
		*/
		public function get_name() {
			return $this->name;
		}
	
		/**
		* Get the description of the data source.
		* 
		* @return string The description of the data source.
		*/
		public function get_description() {
			return $this->description;
		}
	
		/**
		* Get the prompt to use when passing the data to the AI agent.
		* 
		* @return string The prompt to use when passing the data to the AI agent.
		*/
		public function get_prompt() {
			return $this->prompt;
		}
	
		/**
		* Get the shape of the data returned from the data source.
		* 
		* @return string The shape of the data returned from the data source.
		*/
		public function get
 }