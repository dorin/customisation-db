<?php
/**
*
* @package Titania
* @version $Id$
* @copyright (c) 2008 phpBB Customisation Database Team
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_TITANIA'))
{
	exit;
}

/**
 * titania class and functions for use within titania pages and apps.
 */
class titania
{
	/**
	 * Current viewing page location
	 *
	 * @var string
	 */
	public static $page;

	/**
	 * Titania configuration member
	 *
	 * @var object titania_config
	 */
	public static $config;

	/**
	 * Instance of titania_cache class
	 *
	 * @var titania_cache
	 */
	public static $cache;

	/**
	 * Request time (unix timestamp)
	 *
	 * @var int
	 */
	public static $time;

	/**
	* Current User's Access level
	*
	* @var int $access_level Check TITANIA_ACCESS_ constants
	*/
	public static $access_level = 2;

	/**
	 * Absolute Titania, Board, Style, Template, and Theme Path
	 *
	 * @var string
	 */
	public static $absolute_path;
	public static $absolute_board;
	public static $style_path;
	public static $template_path;
	public static $theme_path;

	/**
	* Hold our main contribution/author object for the currently loaded author/contribution
	*
	* @var object
	*/
	public static $contrib;
	public static $author;

	/*
	 * Initialise titania:
	 *	Session management, Cache, Language ...
	 *
	 * @return void
	 */
	public static function initialise()
	{
		global $starttime;

		self::$page = htmlspecialchars(phpbb::$user->page['script_path'] . phpbb::$user->page['page_name']);
		self::$time = (int) $starttime;

		// Instantiate cache
		if (!class_exists('titania_cache'))
		{
			include TITANIA_ROOT . 'includes/core/cache.' . PHP_EXT;
		}
		self::$cache = new titania_cache();

		// Set the absolute titania/board path
		self::$absolute_path = generate_board_url(true) . '/' . self::$config->titania_script_path;
		self::$absolute_board = generate_board_url() . '/';

		// Set the style path, template path, and template name
		self::$style_path = self::$absolute_path . 'styles/' . self::$config->style . '/';
		self::$template_path = self::$style_path . 'template';
		self::$theme_path = self::$style_path . 'theme';

		// Set the paths for phpBB
		phpbb::$template->set_custom_template(TITANIA_ROOT . 'styles/' . self::$config->style . '/' . 'template', 'titania_' . self::$config->style);
		phpbb::$user->theme['template_storedb'] = false;

		// Inherit from the boards prosilver (currently required for the Captcha)
		phpbb::$user->theme['template_inherits_id'] = 1; // Doesn't seem to matter what number I put in here...
		phpbb::$template->inherit_root = PHPBB_ROOT_PATH . 'styles/prosilver/template';

		// Setup the Access Level
		self::$access_level = TITANIA_ACCESS_PUBLIC;
		if (in_array(phpbb::$user->data['group_id'], self::$config->team_groups))
		{
			self::$access_level = TITANIA_ACCESS_TEAMS;
		}

		// Add common titania language file
		self::add_lang('common');

		// Load the contrib types
		self::_include('types/base');
		titania_types::load_types();

		// Initialise the URL class
		titania_url::$root_url = self::$absolute_path;
		titania_url::decode_url(self::$config->titania_script_path);

		// Generate the root breadcrumb that displays on every page
		self::generate_breadcrumbs(array(
			'CUSTOMISATION_DATABASE'	=> titania_url::build_url(''),
		));
	}

	/**
	 * Reads a configuration file with an assoc. config array
	 *
	 * @param string $file	Path to configuration file
	 */
	public static function read_config_file($file)
	{
		if (!file_exists($file) || !is_readable($file))
		{
			die('<p>The Titania configuration file could not be found or is inaccessible. Check your configuration.</p>');
		}

		require($file);

		self::$config = new titania_config();

		if (!is_array($config))
		{
			$config = array();
		}

		self::$config->__set_array($config);
	}

	/**
	 * Autoload any objects, tools, or overlords.
	 * This autoload function does not handle core classes right now however it will once the naming of them is the same.
	 *
	 * @param $class_name
	 *
	 */
	public static function autoload($class_name)
	{
		// Remove titania/overlord from the class name
		$class_name = str_replace(array('titania_', '_overlord'), '', $class_name);

		$directories = array(
			'objects',
			'tools',
			'overlords',
			'core',
		);

		foreach ($directories as $dir)
		{
			if (file_exists(TITANIA_ROOT . 'includes/' . $dir . '/' . $class_name . '.' . PHP_EXT))
			{
				include(TITANIA_ROOT . 'includes/' . $dir . '/' . $class_name . '.' . PHP_EXT);
				return;
			}
		}

		// No error if file cant be found!
	}

	/**
	 * Add a Titania language file
	 *
	 * @param mixed $lang_set
	 * @param bool $use_db
	 * @param bool $use_help
	 */
	public static function add_lang($lang_set, $use_db = false, $use_help = false)
	{
		// Store so we can reset it back
		$old_path = phpbb::$user->lang_path;

		// Set the custom language path to our working language directory
		phpbb::$user->set_custom_lang_path(self::$config->language_path);

		phpbb::$user->add_lang($lang_set, $use_db, $use_help);

		// Reset the custom language path to the original directory
		phpbb::$user->set_custom_lang_path($old_path);
	}

	/**
	 * Titania page_header
	 *
	 * @param string $page_title
	 * @param bool $display_online_list
	 */
	public static function page_header($page_title = '')
	{
		if (defined('HEADER_INC'))
		{
			return;
		}

		define('HEADER_INC', true);

		// Do the phpBB page header stuff first
		phpbb::page_header($page_title);

		// Generate logged in/logged out status
		if (phpbb::$user->data['user_id'] != ANONYMOUS)
		{
			$u_login_logout = phpbb::append_sid(self::$absolute_path . 'index.' . PHP_EXT, 'mode=logout', true, phpbb::$user->session_id);
			$l_login_logout = sprintf(phpbb::$user->lang['LOGOUT_USER'], phpbb::$user->data['username']);
		}
		else
		{
			$u_login_logout = phpbb::append_sid('ucp', 'mode=login&amp;redirect=' . self::$page);
			$l_login_logout = phpbb::$user->lang['LOGIN'];
		}

		phpbb::$template->assign_vars(array(
			'U_LOGIN_LOGOUT'			=> $u_login_logout,
			'L_LOGIN_LOGOUT'			=> $l_login_logout,
			'LOGIN_REDIRECT'			=> self::$page,

			'PHPBB_ROOT_PATH'			=> self::$absolute_board,
			'TITANIA_ROOT_PATH'			=> self::$absolute_path,

			'U_BASE_URL'				=> self::$absolute_path,
			'U_SITE_ROOT'				=> self::$absolute_board,
			'U_MANAGE'					=> (sizeof(titania_types::find_authed()) || phpbb::$auth->acl_get('titania_contrib_mod') || phpbb::$auth->acl_get('titania_post_mod')) ? titania_url::build_url('manage') : '',
			'U_MY_CONTRIBUTIONS'		=> (phpbb::$user->data['is_registered'] && !phpbb::$user->data['is_bot']) ? titania_url::build_url('author/' . phpbb::$user->data['username_clean']) : '',

			'T_TITANIA_TEMPLATE_PATH'	=> self::$template_path,
			'T_TITANIA_THEME_PATH'		=> self::$theme_path,
			'T_TITANIA_STYLESHEET'		=> self::$absolute_path . '/style.php?style=' . self::$config->style,
			'T_STYLESHEET_LINK'			=> (!phpbb::$user->theme['theme_storedb']) ? self::$absolute_board . '/styles/' . phpbb::$user->theme['theme_path'] . '/theme/stylesheet.css' : self::$absolute_board . 'style.' . PHP_EXT . '?sid=' . phpbb::$user->session_id . '&amp;id=' . phpbb::$user->theme['style_id'] . '&amp;lang=' . phpbb::$user->data['user_lang'],
			'T_STYLESHEET_NAME'			=> phpbb::$user->theme['theme_name'],
		));
	}

	/**
	 * Titania page_footer
	 *
	 * @param cron $run_cron
	 * @param bool|string $template_body For those lazy like me, send the template body name you want to load (or leave default to ignore and assign it yourself)
	 */
	public static function page_footer($run_cron = true, $template_body = false)
	{
		// Because I am lazy most of the time...
		if ($template_body !== false)
		{
			phpbb::$template->set_filenames(array(
				'body' => $template_body,
			));
		}

		// Output page creation time (can not move phpBB side because of a hack we do in here)
		if (defined('DEBUG'))
		{
			global $starttime;
			$mtime = explode(' ', microtime());
			$totaltime = $mtime[0] + $mtime[1] - $starttime;

			if (!empty($_REQUEST['explain']) && phpbb::$auth->acl_get('a_') && defined('DEBUG_EXTRA') && method_exists(phpbb::$db, 'sql_report'))
			{
				// gotta do a rather nasty hack here, but it works and the page is killed after the display output, so no harm to anything else
				$GLOBALS['phpbb_root_path'] = self::$absolute_board;
				phpbb::$db->sql_report('display');
			}

			$debug_output = sprintf('Time : %.3fs | ' . phpbb::$db->sql_num_queries() . ' Queries | GZIP : ' . ((phpbb::$config['gzip_compress'] && @extension_loaded('zlib')) ? 'On' : 'Off') . ((phpbb::$user->load) ? ' | Load : ' . phpbb::$user->load : ''), $totaltime);

			if (phpbb::$auth->acl_get('a_') && defined('DEBUG_EXTRA'))
			{
				if (function_exists('memory_get_usage'))
				{
					if ($memory_usage = memory_get_usage())
					{
						global $base_memory_usage;
						$memory_usage -= $base_memory_usage;
						$memory_usage = get_formatted_filesize($memory_usage);

						$debug_output .= ' | Memory Usage: ' . $memory_usage;
					}
				}

				$debug_output .= ' | <a href="' . titania_url::append_url(titania_url::$current_page, array_merge(titania_url::$params, array('explain' => 1))) . '">Explain</a>';
			}
		}

		phpbb::$template->assign_vars(array(
			'DEBUG_OUTPUT'			=> (defined('DEBUG')) ? $debug_output : '',
			'U_PURGE_CACHE'			=> (phpbb::$auth->acl_get('a_')) ? titania_url::append_url(titania_url::$current_page, array_merge(titania_url::$params, array('cache' => 'purge'))) : '',
		));

		// Call the phpBB footer function
		phpbb::page_footer($run_cron);
	}

	/**
	* Generate the navigation tabs/menu for display
	*
	* @param array $nav_ary The array of data to output
	* @param string $current_page The current page
	* @param string $block Optionally specify a custom template block loop name
	*/
	public static function generate_nav($nav_ary, $current_page, $block = 'nav_menu')
	{
		foreach ($nav_ary as $page => $data)
		{
			// If they do not have authorization, skip.
			if (isset($data['auth']) && !$data['auth'])
			{
				continue;
			}

			phpbb::$template->assign_block_vars($block, array(
				'L_TITLE'		=> (isset(phpbb::$user->lang[$data['title']])) ? phpbb::$user->lang[$data['title']] : $data['title'],
				'U_TITLE'		=> $data['url'],
				'S_SELECTED'	=> ($page == $current_page) ? true : false,
			));
		}
	}

	/**
	* Generate the breadcrumbs for display
	*
	* @param array $breadcrumbs The array of data to output
	* @param string $block Optionally specify a custom template block loop name
	*/
	public static function generate_breadcrumbs($breadcrumbs, $block = 'nav_header')
	{
		foreach ($breadcrumbs as $title => $url)
		{
			phpbb::$template->assign_block_vars($block, array(
				'L_TITLE'		=> (isset(phpbb::$user->lang[$title])) ? phpbb::$user->lang[$title] : $title,
				'U_TITLE'		=> $url,
			));
		}
	}

	/**
	 * Titania Logout method to redirect the user to the Titania root instead of the phpBB Root
	 *
	 * @param bool $return if we are within a method, we can use the error_box instead of a trigger_error on the redirect.
	 */
	public static function logout($return = false)
	{
		if (phpbb::$user->data['user_id'] != ANONYMOUS && isset($_GET['sid']) && !is_array($_GET['sid']) && $_GET['sid'] === phpbb::$user->session_id)
		{
			phpbb::$user->session_kill();
			phpbb::$user->session_begin();
			$message = phpbb::$user->lang['LOGOUT_REDIRECT'];
		}
		else
		{
			$message = (phpbb::$user->data['user_id'] == ANONYMOUS) ? phpbb::$user->lang['LOGOUT_REDIRECT'] : phpbb::$user->lang['LOGOUT_FAILED'];
		}

		if ($return)
		{
			return $message;
		}

		meta_refresh(3, titania_url::build_url());

		$message = $message . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_INDEX'], '<a href="' . titania_url::build_url() . '">', '</a> ');
		trigger_error($message);
	}

	/**
	 * Show the errorbox or successbox
	 *
	 * @param string $l_title message title - custom or user->lang defined
	 * @param mixed $l_message message string or array of strings
	 * @param int $error_type TITANIA_SUCCESS or TITANIA_ERROR constant
	 * @param int $status_code an HTTP status code
	 */
	public static function error_box($l_title, $l_message, $error_type = TITANIA_SUCCESS, $status_code = NULL)
	{
		if ($status_code)
		{
			self::set_header_status($status_code);
		}

		$block = ($error_type == TITANIA_ERROR) ? 'errorbox' : 'successbox';

		if ($l_title)
		{
			$title = (isset(phpbb::$user->lang[$l_title])) ? phpbb::$user->lang[$l_title] : $l_title;

			phpbb::$template->assign_var(strtoupper($block) . '_TITLE', $title);
		}

		if (!is_array($l_message))
		{
			$l_message = array($l_message);
		}

		foreach ($l_message as $message)
		{
			if (!$message)
			{
				continue;
			}

			phpbb::$template->assign_block_vars($block, array(
				'MESSAGE'	=> (isset(phpbb::$user->lang[$message])) ? phpbb::$user->lang[$message] : $message,
			));
		}

		// Setup the error box to hide.
		phpbb::$template->assign_vars(array(
			'S_HIDE_ERROR_BOX'		=> true,
			'ERRORBOX_CLASS'		=> $block,
		));
	}

	/**
	* Build Confirm box
	* @param boolean $check True for checking if confirmed (without any additional parameters) and false for displaying the confirm box
	* @param string $title Title/Message used for confirm box.
	*		message text is _CONFIRM appended to title.
	*		If title cannot be found in user->lang a default one is displayed
	*		If title_CONFIRM cannot be found in user->lang the text given is used.
	* @param string $u_action Form action
	* @param string $post Hidden POST variables
	* @param string $html_body Template used for confirm box
	*/
	public static function confirm_box($check, $title = '', $u_action = '', $post = array(), $html_body = 'confirm_body.html')
	{
		$hidden = build_hidden_fields($post);

		if (isset($_POST['cancel']))
		{
			return false;
		}

		$confirm = false;
		if (isset($_POST['confirm']))
		{
			// language frontier
			if ($_POST['confirm'] === phpbb::$user->lang['YES'])
			{
				$confirm = true;
			}
		}

		if ($check && $confirm)
		{
			$user_id = request_var('confirm_uid', 0);
			$session_id = request_var('sess', '');
			$confirm_key = request_var('confirm_key', '');

			if ($user_id != phpbb::$user->data['user_id'] || $session_id != phpbb::$user->session_id || !$confirm_key || !phpbb::$user->data['user_last_confirm_key'] || $confirm_key != phpbb::$user->data['user_last_confirm_key'])
			{
				return false;
			}

			// Reset user_last_confirm_key
			$sql = 'UPDATE ' . USERS_TABLE . " SET user_last_confirm_key = ''
				WHERE user_id = " . phpbb::$user->data['user_id'];
			phpbb::$db->sql_query($sql);

			return true;
		}
		else if ($check)
		{
			return false;
		}

		// generate activation key
		$confirm_key = gen_rand_string(10);

		$s_hidden_fields = build_hidden_fields(array(
			'confirm_uid'	=> phpbb::$user->data['user_id'],
			'confirm_key'	=> $confirm_key,
			'sess'			=> phpbb::$user->session_id,
			'sid'			=> phpbb::$user->session_id,
		));

		self::page_header((!isset(phpbb::$user->lang[$title])) ? phpbb::$user->lang['CONFIRM'] : phpbb::$user->lang[$title]);

		// If activation key already exist, we better do not re-use the key (something very strange is going on...)
		if (request_var('confirm_key', ''))
		{
			// This should not occur, therefore we cancel the operation to safe the user
			return false;
		}

		// re-add sid / transform & to &amp; for user->page (user->page is always using &)
		// @todo look into the urls we are generating here
		if ($u_action)
		{
			$u_action = titania_url::build_url($u_action);
		}
		else
		{
			$u_action = reapply_sid(PHPBB_ROOT_PATH . str_replace('&', '&amp;', phpbb::$user->page['page']));
			$u_action .= ((strpos($u_action, '?') === false) ? '?' : '&amp;') . 'confirm_key=' . $confirm_key;
		}

		phpbb::$template->assign_vars(array(
			'MESSAGE_TITLE'		=> (!isset(phpbb::$user->lang[$title])) ? phpbb::$user->lang['CONFIRM'] : phpbb::$user->lang[$title],
			'MESSAGE_TEXT'		=> (!isset(phpbb::$user->lang[$title . '_CONFIRM'])) ? $title : phpbb::$user->lang[$title . '_CONFIRM'],

			'YES_VALUE'			=> phpbb::$user->lang['YES'],
			'S_CONFIRM_ACTION'	=> $u_action,
			'S_HIDDEN_FIELDS'	=> $hidden . $s_hidden_fields,
		));

		$sql = 'UPDATE ' . USERS_TABLE . " SET user_last_confirm_key = '" . phpbb::$db->sql_escape($confirm_key) . "'
			WHERE user_id = " . phpbb::$user->data['user_id'];
		phpbb::$db->sql_query($sql);

		self::page_footer(true, $html_body);
	}

	/**
	 * Set proper page header status
	 *
	 * @param int $status_code
	 */
	public static function set_header_status($status_code = NULL)
	{
		// Send the appropriate HTTP status header
		static $status = array(
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			204 => 'No Content',
			205 => 'Reset Content',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found', // Moved Temporarily
			303 => 'See Other',
			304 => 'Not Modified',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			406 => 'Not Acceptable',
			409 => 'Conflict',
			410 => 'Gone',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
		);

		if ($status_code && isset($status[$status_code]))
		{
			$header = $status_code . ' ' . $status[$status_code];
			header('HTTP/1.1 ' . $header, false, $status_code);
			header('Status: ' . $header, false, $status_code);
		}
	}

	/**
	* Include a Titania includes file
	*
	* @param string $file The name of the file
	* @param string|bool $function_check Bool false to ignore; string function name to check if the function exists (and not load the file if it does)
	* @param string|bool $class_check Bool false to ignore; string class name to check if the class exists (and not load the file if it does)
	*/
	public static function _include($file, $function_check = false, $class_check = false)
	{
		if ($function_check !== false)
		{
			if (function_exists($function_check))
			{
				return;
			}
		}

		if ($class_check !== false)
		{
			if (class_exists($class_check))
			{
				return;
			}
		}

		include(TITANIA_ROOT . 'includes/' . $file . '.' . PHP_EXT);
	}
}
