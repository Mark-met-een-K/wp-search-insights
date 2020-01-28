<?php
defined('ABSPATH') or die("you do not have acces to this page!");

if (!class_exists("WPSI_GRID")) {
	class WPSI_GRID
	{
		public $id = false;
		public $banner_version = 0;
		public $title;
		public $default = false;
		public $archived = false;
		public $statistics;

		function __construct($id = false)
		{

			$this->id = $id;

			if ($this->id!==FALSE) {
				//initialize the cookiebanner settings with this id.
				$this->get();
			}
		}

		private function get() {

		}

	}
}

