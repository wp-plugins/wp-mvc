<?php

class MvcLoader {

	private $admin_controller_names = array();
	private $app_directory = '';
	private $core_directory = '';
	private $dispatcher = null;
	private $file_includer = null;
	private $model_names = array();
	private $public_controller_names = array();
	private $query_vars = array();

	function __construct() {
	
		if (!defined('MVC_CORE_PATH')) {
			define('MVC_CORE_PATH', MVC_PLUGIN_PATH.'core/');
		}
	
		$this->core_directory = MVC_CORE_PATH;
	
		$this->load_core();
		
		if (defined('MVC_APP_PATH')) {
		
			$this->app_directory = MVC_APP_PATH;
		
		} else {
			
			$abspath = rtrim(ABSPATH, '/').'/';
		
			$theme_filepath = $abspath.get_theme_root().'/'.get_template().'/';
			
			if (is_dir($theme_filepath.'app/')) {
				$this->app_directory = $theme_filepath.'app/';
			} else {
				$this->app_directory = MVC_PLUGIN_PATH.'app/';
			}
			
			define('MVC_APP_PATH', $this->app_directory);
		
		}
		
		$this->file_includer = new MvcFileIncluder();
		
		$this->file_includer->require_app_file_if_exists('config/bootstrap.php');
		$this->file_includer->require_app_file_if_exists('config/routes.php');
		
		$this->dispatcher = new MvcDispatcher();
		
	}
	
	private function load_core() {
		
		$files = array(
			'mvc_error',
			'mvc_configuration',
			'mvc_directory',
			'mvc_dispatcher',
			'mvc_file',
			'mvc_file_includer',
			'mvc_model_registry',
			'mvc_object_registry',
			'mvc_templater',
			'inflector',
			'router',
			'controllers/mvc_controller',
			'controllers/mvc_admin_controller',
			'controllers/mvc_public_controller',
			'models/mvc_database_adapter',
			'models/mvc_database',
			'models/mvc_data_validation_error',
			'models/mvc_data_validator',
			'models/mvc_model',
			'helpers/mvc_helper',
			'helpers/mvc_form_helper',
			'helpers/mvc_html_helper',
			'shells/mvc_shell',
			'shells/mvc_shell_dispatcher'
		);
		
		foreach($files as $file) {
			require_once $this->core_directory.$file.'.php';
		}
		
	}
	
	public function init() {
	
		$this->load_controllers();
		$this->load_models();
		$this->load_functions();
	
	}
	
	private function load_controllers() {
	
		$this->file_includer->require_app_or_core_file('controllers/admin_controller.php');
		$this->file_includer->require_app_or_core_file('controllers/public_controller.php');
		
		$admin_controller_filenames = $this->file_includer->require_php_files_in_directory($this->app_directory.'controllers/admin/');
		$public_controller_filenames = $this->file_includer->require_php_files_in_directory($this->app_directory.'controllers/');
		
		foreach($admin_controller_filenames as $filename) {
			if (preg_match('/admin_([^\/]+)_controller\.php/', $filename, $match)) {
				$this->admin_controller_names[] = $match[1];
			}
		}
		
		foreach($public_controller_filenames as $filename) {
			if (preg_match('/([^\/]+)_controller\.php/', $filename, $match)) {
				$this->public_controller_names[] = $match[1];
			}
		}
		
	}
	
	private function load_models() {
		
		$this->file_includer->require_app_or_core_file('models/app_model.php');
		
		$model_filenames = $this->file_includer->require_php_files_in_directory($this->app_directory.'models/');
		
		$models = array();
		
		foreach($model_filenames as $filename) {
			$models[] = Inflector::class_name_from_filename($filename);
		}
		
		$this->model_names = array();
		
		foreach($models as $model) {
			$this->model_names[] = $model;			
			$model_class = Inflector::camelize($model);
			$model_instance = new $model_class();
			MvcModelRegistry::add_model($model, &$model_instance);
		}
		
	}
	
	private function load_functions() {
	
		$this->file_includer->require_php_files_in_directory($this->core_directory.'functions/');
	
	}
	
	public function admin_init() {
		
		// To do: determine whether this plugin page is being generated by WP MVC and return here if it isn't
	
		global $plugin_page;
		
		$plugin_page_split = explode('-', $plugin_page, 2);
		$controller = $plugin_page_split[0];
		
		if (!empty($controller)) {
			
			// Necessary for flash()-related functionality
			session_start();
		 
			$action = empty($plugin_page_split[1]) ? 'index' : $plugin_page_split[1];
			
			$mvc_admin_init_args = array(
				'controller' => $controller,
				'action' => $action
			);
			do_action('mvc_admin_init', $mvc_admin_init_args);
		
		}
		
	}
	
	public function add_menu_pages() {
	
		$menu_position = 12;
		
		$menu_position = apply_filters('mvc_menu_position', $menu_position);
		
		foreach($this->model_names as $model_name) {
		
			$model = MvcModelRegistry::get_model($model_name);
			$tableized = Inflector::tableize($model_name);
			$pluralized = Inflector::pluralize($model_name);
			$titleized = Inflector::titleize($model_name);
		
			$controller_name = 'admin_'.$tableized;
			
			$top_level_handle = $tableized;
			
			$admin_pages = $model->admin_pages;
			
			$method = $controller_name.'_index';
			$this->dispatcher->{$method} = create_function('', 'MvcDispatcher::dispatch(array("controller" => "'.$controller_name.'", "action" => "index"));');
			add_menu_page(
				$pluralized,
				$pluralized,
				'administrator',
				$top_level_handle,
				array($this->dispatcher, $method),
				null,
				$menu_position
			);
			
			foreach($admin_pages as $key => $admin_page) {
				
				$method = $controller_name.'_'.$admin_page['action'];
				
				if (!method_exists($this->dispatcher, $method)) {
					$this->dispatcher->{$method} = create_function('', 'MvcDispatcher::dispatch(array("controller" => "'.$controller_name.'", "action" => "'.$admin_page['action'].'"));');
				}
				
				if ($admin_page['in_menu']) {
					add_submenu_page(
						$top_level_handle,
						$admin_page['label'].' &lsaquo; '.$pluralized,
						$admin_page['label'],
						$admin_page['capability'],
						$top_level_handle.'-'.$key,
						array($this->dispatcher, $method)
					);
				} else {
					add_options_page(
						$admin_page['label'],
						$admin_page['label'],
						$admin_page['capability'],
						$top_level_handle.'-'.$key,
						array($this->dispatcher, $method)
					);
				}
			
			}
			
			$menu_position++;

		}
	
	}
	
	public function flush_rewrite_rules($rules) {
		global $wp_rewrite;
		
		$wp_rewrite->flush_rules();
	}
	
	public function add_rewrite_rules($rules) {
		global $wp_rewrite;
		
		$new_rules = array();
		
		$routes = Router::get_public_routes();
		
		// Use default routes if none have been defined
		if (empty($routes)) {
			Router::public_connect('{:controller}', array('action' => 'index'));
			Router::public_connect('{:controller}/{:id:[\d]+}', array('action' => 'show'));
			Router::public_connect('{:controller}/{:action}/{:id:[\d]+}');
			$routes = Router::get_public_routes();
		}
		
		foreach($routes as $route) {
			
			$route_path = $route[0];
			$route_defaults = $route[1];
			
			if (strpos($route_path, '{:controller}') !== false) {
				foreach($this->public_controller_names as $controller) {

					add_rewrite_tag('%'.$controller.'%', '(.+)');
					
					$rewrite_path = $route_path;
					$query_vars = array();
					$query_var_counter = 0;
					$query_var_match_string = '';
					
					// Add any route params from the route path (e.g. '{:controller}/{:id:[\d]+}') to $query_vars
					// and append them to the match string for use in a WP rewrite rule
					preg_match_all('/{:(.+?)(:.*?)?}/', $rewrite_path, $matches);
					foreach($matches[1] as $query_var) {
						$query_var = 'mvc_'.$query_var;
						if ($query_var != 'mvc_controller') {
							$query_var_match_string .= '&'.$query_var.'=$matches['.$query_var_counter.']';
						}
						$query_vars[] = $query_var;
						$query_var_counter++;
					}
					
					// Do the same as above for route params that defined as route defaults (e.g. array('action' => 'show'))
					if (!empty($route_defaults)) {
						foreach($route_defaults as $query_var => $value) {
							$query_var = 'mvc_'.$query_var;
							if ($query_var != 'mvc_controller') {
								$query_var_match_string .= '&'.$query_var.'='.$value;
								$query_vars[] = $query_var;
							}
						}
					}
					
					$this->query_vars = array_unique(array_merge($this->query_vars, $query_vars));
					$rewrite_path = str_replace('{:controller}', $controller, $route_path);
					
					// Replace any route params (e.g. {:param_name}) in the route path with the default pattern ([^/]+)
					$rewrite_path = preg_replace('/({:[\w_-]+})/', '([^/]+)', $rewrite_path);
					// Replace any route params with defined patterns (e.g. {:param_name:[\d]+}) in the route path with
					// their pattern (e.g. ([\d]+))
					$rewrite_path = preg_replace('/({:[\w_-]+:)(.*?)}/', '(\2)', $rewrite_path);
					$rewrite_path = '^'.$rewrite_path.'/?$';
					
					$controller_value = empty($route_defaults['controller']) ? $controller : $route_defaults['controller'];
					$controller_rules = array();
					$controller_rules[$rewrite_path] = 'index.php?mvc_controller='.$controller_value.$query_var_match_string;
					
					$new_rules = array_merge($new_rules, $controller_rules);
				
				}
			}
		}
		
		$rules = array_merge($new_rules, $rules);
		
		$rules = apply_filters('mvc_public_rewrite_rules', $rules);
		
		return $rules;
	}
	
	public function add_query_vars($vars) {
		$vars = array_merge($vars, $this->query_vars);
		return $vars;
	}
	
	public function get_routing_params() {
		global $wp_query;
		
		$controller = $wp_query->get('mvc_controller');
		
		if ($controller) {
			$query_params = $wp_query->query;
			$params = array();
			foreach($query_params as $key => $value) {
				$key = preg_replace('/^(mvc_)/', '', $key);
				$params[$key] = $value;
			}
			return $params;
		}
		
		return false;
	}
	
	public function template_redirect() {
		global $wp_query;
		
		$routing_params = $this->get_routing_params();
		
		if ($routing_params) {
			$this->dispatcher->dispatch($routing_params);
		}
	}

}

?>