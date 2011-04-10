<?php

class MvcConfiguration {

	function &getInstance($boot = true) {
		static $instance = array();
		
		if (!$instance) {
			$instance[0] =& new MvcConfiguration();
		}
		
		return $instance[0];
	}

	function set($config, $value = null) {
		$_this =& MvcConfiguration::getInstance();

		if (!is_array($config)) {
			$config = array($config => $value);
		}

		foreach ($config as $name => $value) {
			if (strpos($name, '.') === false) {
				$_this->{$name} = $value;
			} else {
				$names = explode('.', $name, 2);
				if (count($names) == 2) {
					$_this->{$names[0]}[$names[1]] = $value;
				}
			}
		}
		
		return true;
	}

	function get($config) {
		$_this =& MvcConfiguration::getInstance();

		if (strpos($config, '.') !== false) {
			$names = explode('.', $config, 2);
			$config = $names[0];
		}
		if (!isset($_this->{$config})) {
			return null;
		}
		if (!isset($names[1])) {
			return $_this->{$config};
		}
		if (count($names) == 2) {
			if (isset($_this->{$config}[$names[1]])) {
				return $_this->{$config}[$names[1]];
			}
		}
		return null;
	}

}

?>