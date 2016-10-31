<?php
/**
*
* @package phpBB Extension - JFusion phpBB Extension
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace jfusion\phpbbext\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class main_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		if (defined('IN_MOBIQUO')){
			return array();
		}else{
			return array(
				'core.user_setup' => 'core_user_setup',
				'core.auth_login_session_create_before' => 'auth_login_session_create_before',
				'core.session_kill_after' => 'session_kill_after',
			);
		}
	}

	/* @var \phpbb\config\db */
	protected $config;

	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\request\request */
	protected $request;
	
	/**
	* Constructor
	*
	* @param \phpbb\config\db	$config		Controller helper object
	* @param \phpbb\user			$user	Template object
	*/
	public function __construct(\phpbb\config\db $config, \phpbb\user $user, \phpbb\request\request $request, $root_path,  $php_ext)
	{
		$this->config = $config;
		$this->user = $user;
		$this->request = $request;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;		
	}

	
	/**
	 * @param \Symfony\Component\EventDispatcher\Event $event
	 */
	public function auth_login_session_create_before($event)
	{		        	
		global $JFusionActive;
		
		if (isset($event['login']) && isset($event['login']['status']) && $event['login']['status'] == LOGIN_SUCCESS && !$event['admin'] && empty($JFusionActive))
		{		
			$joomla = $this->startJoomla();
				
			//backup phpbb globals
			$joomla->backupGlobal();
			$this->request->enable_super_globals();

			$username = $event['username']; // This is empty when using Oauth login (Facebook)				
			
			// The password in $event['login']['user_row']['user_password'] is hashed, use password from request
			// instead, but this still dosn't work for Oauth logins (Facebook).
			$password = $this->request->untrimmed_variable('password', '', false, \phpbb\request\request_interface::POST);
			
			if (empty($username) || empty($password))
			{
				if (empty($username))
				{
					error_log('No username ');
					$username = $event['login']['user_row']['username'];
				}
				if (empty($password))
				{
					error_log('No password ');
				}					
			}
			
			else 
			{
				//detect if the session should be remembered
				if (!empty($event['autologin'])) {
					$remember = 1;
				} else {
					$remember = 0;
				}
				$joomla->setActivePlugin($this->config['jfusion_phpbbext_jname']);
		
				$joomla->login($username, $password, $remember);
			}					
			//backup phpbb globals
			$joomla->restoreGlobal();
			$this->request->disable_super_globals();
		}
	}
	
	/**
	 * @param \Symfony\Component\EventDispatcher\Event $event
	 */
	public function session_kill_after($event)
	{		
		//check to see if JFusion is not active
		global $JFusionActive;
		if (empty($JFusionActive))
		{
			$joomla = $this->startJoomla();
		
			//backup phpbb globals
			$joomla->backupGlobal();
			$this->request->enable_super_globals();
		
			//define that the phpBB3 JFusion plugin needs to be excluded
			$joomla->setActivePlugin($this->config['jfusion_phpbbext_jname']);
		
			$joomla->logout();
			
			//backup phpbb globals
			$joomla->restoreGlobal();
			$this->request->disable_super_globals();
		}
	}
	
	/**
	 * @param \Symfony\Component\EventDispatcher\Event $event
	 */
	public function core_user_setup($event)
	{		
		$page = $this->user->page['page'];
		$url = $this->config['jfusion_phpbbext_redirect_url'];
		if (strpos($page, 'feed.php') !== 0 && !empty($url) && $this->config['jfusion_phpbbext_frameless'] == 1)
		{
			$direct_access = array();
			if ($this->config['jfusion_phpbbext_direct_access']) {
				
				if (!function_exists('group_memberships'))
				{
					include($this->root_path . 'includes/functions_user.' . $this->php_ext);
				}
				
				$memberships = array();
				foreach (group_memberships(false, $this->user->data['user_id']) as $grp)
				{
					$memberships[] = $grp["group_id"];
				}
				$groups = explode(',', $this->config['jfusion_phpbbext_direct_access_groups']);
				$direct_access = array_intersect($groups, $memberships);
			}

			if (empty($direct_access)) {
				if (!defined('_JEXEC') && !defined('ADMIN_START')) {
					if (strpos('?', $url) !== false && strpos('?', $page) !== false) {
						$page = str_replace('?', '&', $page);
					}
					header('Location: ' . $url . $page);
					exit();
				}
			}
		}
	}
		
	
	/**
	 * @return \JFusionAPIInternal
	 */
	function startJoomla() {
		define('_JFUSIONAPI_INTERNAL', true);
		$apipath = $this->config['jfusion_phpbbext_apipath'];
		require_once $apipath . DIRECTORY_SEPARATOR  . 'jfusionapi.php';
		return \JFusionAPIInternal::getInstance();
	}
}
