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
		
		$template->assign_vars(array(
			'L_TITLE'	=> $user->lang['UCP_PROFILE_GUESTBOOK_SETTINGS'],
		));
	}	
}

?>
