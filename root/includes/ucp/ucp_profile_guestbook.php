<?php
/**
*
* @package ucp
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
* @package ucp
*/
class ucp_profile_guestbook
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $db, $user, $auth, $template;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;
		global $cache;
		
		$this->tpl_name = 'ucp_profile_guestbook';
		$this->page_title = 'UCP_PROFILE_GUESTBOOK';
		
		$user->add_lang('mods/ucp_profile_guestbook');
		
		if (!defined('GUESTBOOK_TABLE'))
		{
			include("{$phpbb_root_path}includes/constants_guestbook.$phpEx");
		}		
		
		$template->assign_vars(array(
			'L_TITLE'		=> $user->lang['UCP_PROFILE_GUESTBOOK_SETTINGS'],
			'S_SELECT_NOTIFY'	=> ($config['profile_guestbook_notification']) ? true : false,
			'S_NOTIFY_EMAIL'	=> ($user->data['user_gb_notification'] == GB_NOTIFY_EMAIL) ? true : false,
			'S_NOTIFY_IM'		=> ($user->data['user_gb_notification'] == GB_NOTIFY_IM) ? true : false,
			'S_NOTIFY_PM'		=> ($user->data['user_gb_notification'] == GB_NOTIFY_PM) ? true : false,
			'S_NOTIFY_EMAIL_PM'	=> ($user->data['user_gb_notification'] == GB_NOTIFY_EMAIL_PM) ? true : false,
			'S_NOTIFY_IM_PM'	=> ($user->data['user_gb_notification'] == GB_NOTIFY_IM_PM) ? true : false,
			'S_NOTIFY_ALL'		=> ($user->data['user_gb_notification'] == GB_NOTIFY_ALL) ? true : false,
		));
	}	
}

?>
