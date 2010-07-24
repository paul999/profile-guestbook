<?php
/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2010 Paul Sohier
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
 * Profile guestbook hook for phpBB.
 *
 * @author Paul Sohier <paul999@phpbb.com>
 * @param phpbb_hook $hook
 * @return void
 */
function hook_profile_guestbook(&$hook)
{
	global $template, $user;
	
	// Make sure we only run this hook once, else we get the most weird errors :).
	if (defined('HOOK_RUNNED'))
	{
		return;
	}
	
	define('HOOK_RUNNED', true);
	
	global $phpbb_root_path, $phpEx;

	
	if (!class_exists('guestbook'))
	{
		include("{$phpbb_root_path}includes/class_guestbook.$phpEx");
	}
	
	$gb = new guestbook();
	$gb->run();
}

/**
 * Only register this hook if the profile guestbook MOD is enabled, and the mode is viewprofile (page is checked later,
 * as some more code is needed for that.
 */
if (request_var('mode', '') == 'viewprofile' && $config['profile_guestbook_enabled'])
{
	/**
	 * Codes based from includes/sessions.php
	 * Cant use the user vars (Where this is from as well), as the session is not started.
	 */
	// First of all, get the request uri...
	$script_name = (!empty($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');

	// If we are unable to get the script name we use REQUEST_URI as a failover and note it within the page array for easier support...
	if (!$script_name)
	{
		$script_name = (!empty($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : getenv('REQUEST_URI');
		$script_name = (($pos = strpos($script_name, '?')) !== false) ? substr($script_name, 0, $pos) : $script_name;
	}

	// Replace backslashes and doubled slashes (could happen on some proxy setups)
	$script_name = str_replace(array('\\', '//'), '/', $script_name);

	// basenamed page name (for example: index.php)
	$page_name = basename($script_name);
	$page_name = urlencode(htmlspecialchars($page_name));

	/**
	 * Only register the hook for normal pages, not administration pages.
	 */
	if ($page_name == 'memberlist.' . $phpEx)
	{
		$phpbb_hook->register(array('template', 'display'), 'hook_profile_guestbook');
	}
}
