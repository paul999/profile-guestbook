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
		
		$submit = (isset($_POST['submit'])) ? true : false;
		$error = $data = array();
		$s_hidden_fields = '';
		
		add_form_key('ucp_pg');
		
		if ($submit)
		{
			$data = array(
				'notifymethod'	=> request_var('notifymethod', (int)$user->data['user_gb_notification']),
				'notify'		=> request_var('enable_notification', (int)$user->data['user_gb_notification_enabled']),
			);	
				
			if (!check_form_key('ucp_pg'))
			{
				$error[] = 'FORM_INVALID';
			}
			
			if (!$config['jab_enabled'] && in_array($data['notifymethod'], array(GB_NOTIFY_IM, GB_NOTIFY_IM_PM, GB_NOTIFY_ALL)))
			{
				$error[] = 'GB_JABBER_DISABLED';
			}
			
			if (!$config['email_enabled'] && in_array($data['notifymethod'], array(GB_NOTIFY_EMAIL, GB_NOTIFY_EMAIL_PM, GB_NOTIFY_ALL)))
			{
				$error[] = 'GB_EMAIL_DISABLED';
			}			

			if (!sizeof($error))
			{
				$sql_ary = array(
					'user_gb_notification_enabled'	=> $data['notify'],
					'user_gb_notification' 		=> $data['notifymethod'],
				);

				$sql = 'UPDATE ' . USERS_TABLE . '
					SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
					WHERE user_id = ' . (int)$user->data['user_id'];
				$db->sql_query($sql);

				meta_refresh(3, $this->u_action);
				$message = $user->lang['PREFERENCES_UPDATED'] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');
				trigger_error($message);
			}

			// Replace "error" strings with their real, localised form
			$error = preg_replace('#^([A-Z_]+)$#e', "(!empty(\$user->lang['\\1'])) ? \$user->lang['\\1'] : '\\1'", $error);
		}		
				
		$template->assign_vars(array(
			'L_TITLE'			=> $user->lang['UCP_PROFILE_GUESTBOOK_SETTINGS'],
			'S_SELECT_NOTIFY'	=> ($config['profile_guestbook_notification']) ? true : false,
			'S_NOTIFY_EMAIL'	=> ($user->data['user_gb_notification'] == GB_NOTIFY_EMAIL) ? true : false,
			'S_NOTIFY_IM'		=> ($user->data['user_gb_notification'] == GB_NOTIFY_IM) ? true : false,
			'S_NOTIFY_PM'		=> ($user->data['user_gb_notification'] == GB_NOTIFY_PM) ? true : false,
			'S_NOTIFY_EMAIL_PM'	=> ($user->data['user_gb_notification'] == GB_NOTIFY_EMAIL_PM) ? true : false,
			'S_NOTIFY_IM_PM'	=> ($user->data['user_gb_notification'] == GB_NOTIFY_IM_PM) ? true : false,
			'S_NOTIFY_ALL'		=> ($user->data['user_gb_notification'] == GB_NOTIFY_ALL) ? true : false,
			
			'S_JABBER'			=> $config['jab_enable'],
			'S_EMAIL'			=> $config['email_enable'],
			
			'S_ENABLE_NOTIFICATION'	=> ($user->data['user_gb_notification_enabled']) ? true : false,
			
			'ERROR'			=> (sizeof($error)) ? implode('<br />', $error) : '',
		));
	}	
}

