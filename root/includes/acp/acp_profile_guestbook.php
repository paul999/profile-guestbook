<?php
/**
*
* @package acp
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
* @package acp
*/
class acp_profile_guestbook
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $db, $user, $auth, $template;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;
		global $cache;

		$user->add_lang('acp/mods/profile_guestbook');

		$action	= request_var('action', '');
		$submit = (isset($_POST['submit'])) ? true : false;

		$form_key = 'acp_pg';
		add_form_key($form_key);
		
		if (!defined('GUESTBOOK_TABLE'))
		{
			include("{$phpbb_root_path}includes/constants_guestbook.$phpEx");
		}		

		/**
		*	Validation types are:
		*		string, int, bool,
		*		script_path (absolute path in url - beginning with / and no trailing slash),
		*		rpath (relative), rwpath (realtive, writable), path (relative path, but able to escape the root), wpath (writable)
		*/
		switch ($mode)
		{
			case 'settings':
				$display_vars = array(
					'title'	=> 'ACP_PROFILE_GUESTBOOK_SETTINGS',
					'vars'	=> array(
						'legend1'				=> 'ACP_PROFILE_GUESTBOOK_SETTINGS',
						'profile_guestbook_enabled'		=> array('lang' => 'PROFILE_GUESTBOOK_ENABLED',	'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => false),
						'profile_guestbook_notification'	=> array('lang' => 'PROFILE_GUESTBOOK_NOTIFICATION', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true),

						'legend3'					=> 'ACP_SUBMIT_CHANGES',
					)
				);
			break;
			
			case 'overview':
				$this->tpl_name = 'acp_profile_guestbook';
				$this->page_title = 'ACP_PROFILE_GUESTBOOK';		
				
				$action = request_var('action', '');

				if ($action)
				{
					if (!confirm_box(true))
					{
						switch ($action)
						{
							case 'delete_all':
								$confirm = true;
								$confirm_lang = 'CONFIRM_GB_DELETE_ALL';
							break;
							
							default:
								$confirm = true;
								$confirm_lang = 'CONFIRM_OPERATION';
						}

						if ($confirm)
						{
							confirm_box(false, $user->lang[$confirm_lang], build_hidden_fields(array(
								'i'		=> $id,
								'mode'		=> $mode,
								'action'	=> $action,
							)));
						}
					}
					else
					{
						switch ($action)
						{
							case 'delete_all':							
								if ($user->data['user_type'] != USER_FOUNDER)
								{
									trigger_error('NO_PERMISSION');
								}
								
								/**
								 * Truncate cant be used for sqlite/firebird.
								 * Code from acp_main.php
								 **/
								switch ($db->sql_layer)
								{
									case 'sqlite':
									case 'firebird':
										$db->sql_query("DELETE FROM " . GUESTBOOK_TABLE);
									break;

									default:
										$db->sql_query("TRUNCATE TABLE " . GUESTBOOK_TABLE);
									break;
								}
								$sql_ary = array(
									'user_guestbook_first_post_id'	=> 0,
									'user_guestbook_last_post_id'	=> 0,
									'user_guestbook_posts'		=> 0,
								);
								
								// No WHERE here, we deleted all posts, so we need to reset all users data!
								$sql = 'UPDATE ' . USERS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary);
								$db->sql_query($sql);
								
								add_log('admin', 'LOG_GB_DELETE_ALL_POSTS');
							break;
							case 'resync':
								// First of all, select all users. We also select bots, because inactive users (Besides bots)
								// can have a guestbook as well.
								// This can take some time, sorry...
								
								$sql = 'SELECT user_id FROM ' . USERS_TABLE .  ' ORDER BY user_id';
								$result = $db->sql_query($sql);
								
								$data		= array(); // This will contain the data that needs to be updated (Number of posts) with 0 posts
								$users		= array(); // All users with a guestbook
								$posts		= array(); // Number of guestbook posts.
								$fist_post 	= array(); // First posts
								$last_post	= array(); // Last posts
								
								// Select all users who have guestbook posts.
								$sql = 'SELECT user_id FROM ' . GUESTBOOK_TABLE . ' ORDER BY user_id';
								$result2 = $db->sql_query($sql);
								
								while ($row = $db->sql_fetchrow($result2))
								{
									$users[] = $row['user_id'];
								}
								$db->sql_freeresult($result2);
								
								$sql = 'SELECT user_id, COUNT(post_id) as total FROM ' . GUESTBOOK_TABLE . ' GROUP BY user_id';
								$result3 = $db->sql_query($sql);
								
								while($row = $db->sql_fetchrow($result3))
								{
									$posts[$row['user_id']] = (int)$row['total'];
								}
								$db->sql_freeresult($result3);

								$sql = 'SELECT g1.user_id, g1.post_id FROM ' . GUESTBOOK_TABLE . ' g1 
									LEFT JOIN ' . GUESTBOOK_TABLE . ' g2 ON (g1.user_id = g2.user_id AND g1.post_time > g2.post_time) 
										WHERE g2.user_id IS NULL';
								$result3 = $db->sql_query($sql);	
								while ($row = $db->sql_fetchrow($result3))
								{
									$first[$row['user_id']] = (int)$row['post_id'];
								}
								$db->sql_freeresult($result3);
								
								$sql = 'SELECT g1.user_id, g1.post_id FROM ' . GUESTBOOK_TABLE . ' g1 
									LEFT JOIN ' . GUESTBOOK_TABLE . ' g2 ON (g1.user_id = g2.user_id AND g1.post_time < g2.post_time) 
										WHERE g2.user_id IS NULL';
								$result3 = $db->sql_query($sql);	
								while ($row = $db->sql_fetchrow($result3))
								{
									$last[$row['user_id']] = (int)$row['post_id'];
								}
								$db->sql_freeresult($result3);
								
								$db->sql_transaction('begin');
								
								while ($row = $db->sql_fetchrow($result))
								{
									$uid = (int)$row['user_id'];
									
									if (!in_array($uid, $users))
									{
										// This user does not have any posts in his guestbook. 
										$data[] = $uid;
										// We don't need to set the other fields, 0 is a special case, and handled later with 1 query
										// To have a bit less query count, it is already high.
										continue; // Dont need to do any other work here now.
									}
									$total = (int)$posts[$uid];
									
									if ($total == 0)
									{
										// Something very bad happened. Die.
										$db->sql_transaction('rollback');
										trigger_error('ERROR_NOT_HAPPEN');
									}
									
									$first_id = $first[$uid];
									$last_id = $last[$uid];
									
									// Write queries for this user :)
									$sql_ary = array(
										'user_guestbook_first_post_id'	=> $first_id,
										'user_guestbook_last_post_id'	=> $last_id,
										'user_guestbook_posts'		=> $total,
									);
								
									$sql = 'UPDATE ' . USERS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
											WHERE user_id = ' . (int)$uid;
									$db->sql_query($sql);
								}
								$db->sql_freeresult($result);
								
								// Time to update all users with 0 posts.
								if (sizeof($data) > 0)
								{
									$sql_ary = array(
										'user_guestbook_first_post_id'	=> 0,
										'user_guestbook_last_post_id'	=> 0,
										'user_guestbook_posts'		=> 0,
									);
								
									$sql = 'UPDATE ' . USERS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
										WHERE ' . $db->sql_in_set('user_id', $data);
									$db->sql_query($sql);
								}
								$db->sql_transaction('commit');

								add_log('admin', 'LOG_GB_SYNC_ALL_POSTS');
							break;
						
							default:
								trigger_error('NO_MODE');
						}
					}
				}
				
				$latest_version_info = $latest_version = false;
				if (($latest_version_info = $this->obtain_latest_version_info(request_var('versioncheck_force', false))) === false)
				{
					$template->assign_var('S_VERSIONCHECK_FAIL', true);
				}
				else
				{
					$latest_version_info = explode("\n", $latest_version_info);

					$latest_version = str_replace('rc', 'RC', strtolower(trim($latest_version_info[0])));
					$current_version = str_replace('rc', 'RC', strtolower($config['pg_version']));

					$template->assign_vars(array(
						'S_VERSION_UP_TO_DATE'	=> version_compare($current_version, $latest_version, '<') ? false : true,
					));
				}	
				
				// Select how many users have a guestbook with posts :)
				
				if ($db->sql_layer == 'sqlite')
				{
					// SQLite does not understand the query below, not sure why yet,
					// But not that many people use sqlite anyway, and if they use it,
					// it is on a small database.
					// Do the counting with php instead.
					
					$sql = 'SELECT DISTINCT user_id FROM ' . GUESTBOOK_TABLE;
					$result = $db->sql_query($sql);
					
					$row = $db->sql_fetchrowset($result);
					$db->sql_freeresult($result);
					
					$total_guestbooks = sizeof($row);
				}
				else
				{				
					$sql = 'SELECT COUNT(DISTINCT user_id) as total FROM ' . GUESTBOOK_TABLE;
					$result = $db->sql_query($sql);  
					$total_guestbooks = $db->sql_fetchfield('total');
					$db->sql_freeresult($result);
				}
				
				$sql = 'SELECT COUNT(post_id) as total FROM ' . GUESTBOOK_TABLE;
				$result = $db->sql_query($sql);  
				$total_posts = $db->sql_fetchfield('total');
				$db->sql_freeresult($result);
				
				$template->assign_vars(array(
					'U_VERSIONCHECK_FORCE'	=> $this->u_action . '&amp;versioncheck_force=1',		
					'S_ACTION_OPTIONS'	=> $auth->acl_get('a_gb_settings') ? true : false, 	
					'U_ACTION'			=> $this->u_action,	
					'S_FOUNDER'			=> ($user->data['user_type'] == USER_FOUNDER),
					
					'NUMBER_OF_GB'		=> $total_guestbooks,
					'NUMBER_OF_POSTS'	=> $total_posts,
					'CUR_VERSION'		=> $config['pg_version'],
					'LATEST_VERSION'	=> ($latest_version_info && $latest_version) ? $latest_version : false,
				));				
				return;
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}

		if (isset($display_vars['lang']))
		{
			$user->add_lang($display_vars['lang']);
		}

		$this->new_config = $config;
		$cfg_array = (isset($_REQUEST['config'])) ? utf8_normalize_nfc(request_var('config', array('' => ''), true)) : $this->new_config;
		$error = array();

		// We validate the complete config if whished
		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		if ($submit && !check_form_key($form_key))
		{
			$error[] = $user->lang['FORM_INVALID'];
		}
		// Do not write values if there is an error
		if (sizeof($error))
		{
			$submit = false;
		}

		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $null)
		{
			if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];

			if ($submit)
			{
				set_config($config_name, $config_value);
			}
		}

		if ($submit)
		{
			add_log('admin', 'LOG_CONFIG_PROFILE_GUESTBOOK');

			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
		}

		$this->tpl_name = 'acp_board';
		$this->page_title = $display_vars['title'];

		$template->assign_vars(array(
			'L_TITLE'			=> $user->lang[$display_vars['title']],
			'L_TITLE_EXPLAIN'	=> $user->lang[$display_vars['title'] . '_EXPLAIN'],

			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),

			'U_ACTION'			=> $this->u_action)
		);

		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$template->assign_block_vars('options', array(
					'S_LEGEND'		=> true,
					'LEGEND'		=> (isset($user->lang[$vars])) ? $user->lang[$vars] : $vars)
				);

				continue;
			}

			$type = explode(':', $vars['type']);

			$l_explain = '';
			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($user->lang[$vars['lang_explain']])) ? $user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			}
			else if ($vars['explain'])
			{
				$l_explain = (isset($user->lang[$vars['lang'] . '_EXPLAIN'])) ? $user->lang[$vars['lang'] . '_EXPLAIN'] : '';
			}

			$content = build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars);

			if (empty($content))
			{
				continue;
			}

			$template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> (isset($user->lang[$vars['lang']])) ? $user->lang[$vars['lang']] : $vars['lang'],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $l_explain,
				'CONTENT'		=> $content,
				)
			);

			unset($display_vars['vars'][$config_key]);
		}
	}
	
	/**
	 * Obtains the latest version information
	 *
	 * @param bool $force_update Ignores cached data. Defaults to false.
	 * @param bool $warn_fail Trigger a warning if obtaining the latest version information fails. Defaults to false.
	 * @param int $ttl Cache version information for $ttl seconds. Defaults to 86400 (24 hours).
	 *
	 * @return string | false Version info on success, false on failure.
	 */
	private function obtain_latest_version_info($force_update = false, $warn_fail = false, $ttl = 86400)
	{
		global $cache;

		$info = $cache->get('pg_versioncheck');

		if ($info === false || $force_update)
		{
			$errstr = '';
			$errno = 0;

			$info = get_remote_file('www.phpbbguestbook.com', '/updatecheck', 'norm.txt', $errstr, $errno);

			if ($info === false)
			{
				$cache->destroy('versioncheck');
				if ($warn_fail)
				{
					trigger_error($errstr, E_USER_WARNING);
				}
				return false;
			}

			$cache->put('pg_versioncheck', $info, $ttl);
		}

		return $info;
	}
	
}

