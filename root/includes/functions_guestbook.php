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

function gb_delete_post($post_id, &$data, &$guestbook)
{
	global $db;
	$sql = 'DELETE FROM ' . GUESTBOOK_TABLE . ' WHERE post_id = ' . (int)$post_id;
	$db->sql_query($sql);
	
	$member = $guestbook->getmember();
	
	// I don't use $db->sql_build_array as it doesnt support field = field - X.
	if ($member['user_guestbook_posts'] == 1)
	{
		// User has no longer any posts in guestbook.
		$sql = 'UPDATE ' . USERS_TABLE . ' SET 
				user_guestbook_posts = 0, 
				user_guestbook_first_post_id = 0, 
				user_guestbook_last_post_id = 0
			 WHERE user_id = ' . (int)$member['user_id'];
	}
	else if ($member['user_guestbook_first_post_id'] == $post_id)
	{
		// New first post
		$sql = 'SELECT post_id FROM ' . GUESTBOOK_TABLE . ' WHERE user_id = ' . (int)$member['user_id'] . ' ORDER BY post_time ASC';
		$result = $db->sql_query_limit($sql, 1);
		$post_id2 = (int)$db->sql_fetchfield('post_id');
		$db->sql_freeresult($result);
		
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_guestbook_posts = user_guestbook_posts -1, user_guestbook_first_post_id = ' . (int)$post_id2 . ' WHERE user_id = ' . (int)$member['user_id'];
	}
	else if ($member['user_guestbook_last_post_id'] == $post_id)
	{
		// New last post
		$sql = 'SELECT post_id FROM ' . GUESTBOOK_TABLE . ' WHERE user_id = ' . (int)$member['user_id'] . ' ORDER BY post_time DESC';
		$result = $db->sql_query_limit($sql, 1);
		$post_id2 = (int)$db->sql_fetchfield('post_id');
		$db->sql_freeresult($result);
		
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_guestbook_posts = user_guestbook_posts -1, user_guestbook_last_post_id = ' . (int)$post_id2 . ' WHERE user_id = ' . (int)$member['user_id'];
	}
	else
	{
		// Enough posts in guestbook, don't need to update last post, just decrement counters.
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_guestbook_posts = user_guestbook_posts -1 WHERE user_id = ' . (int)$member['user_id'];
	}
	$db->sql_query($sql);
}

/**
* Do the various checks required for removing posts as well as removing it
*/
function handle_gb_post_delete($post_id, &$post_data, &$guestbook)
{
	global $user, $db, $auth, $config;
	global $phpbb_root_path, $phpEx;

	// If moderator removing post or user itself removing post, present a confirmation screen
	if ($auth->acl_get('m_gb_delete') || ($post_data['poster_id'] == $user->data['user_id'] && $user->data['is_registered'] && $auth->acl_get('u_gb_delete') &&  ($post_data['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time'])))
	{
		$s_hidden_fields = build_hidden_fields(array(
			'p'		=> $post_id,
			'gbmode'	=> 'delete')
		);

		if (confirm_box(true))
		{
			$data = array(
				'post_time'				=> $post_data['post_time'],
				'poster_id'				=> $post_data['poster_id'],
			);

			$next_post_id = gb_delete_post($post_id, $data, $guestbook);
			$post_username = ($post_data['poster_id'] == ANONYMOUS && !empty($post_data['post_username'])) ? $post_data['post_username'] : $post_data['username'];

			add_log('mod', 0, 0, 'LOG_GB_DELETE_POST', $post_username);

			$member = $guestbook->getmember();

			$meta_info = append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u=" . $member['user_id']);
			unset($member);
			$message = $user->lang['POST_DELETED'] . '<br /><br />' . sprintf($user->lang['RETURN_PROFILE'], '<a href="' . $meta_info . '">', '</a>');

			meta_refresh(3, $meta_info);

			trigger_error($message);
		}
		else
		{
			confirm_box(false, 'DELETE_POST', $s_hidden_fields);
		}
	}

	// If we are here the user is not able to delete - present the correct error message
	if ($post_data['poster_id'] != $user->data['user_id'] && $auth->acl_get('u_gb_delete'))
	{
		trigger_error('DELETE_OWN_POSTS');
	}

	trigger_error('USER_CANNOT_DELETE');
}

/**
* Submit Post
* @todo Split up and create lightweight, simple API for this.
*/
function submit_gb_post($mode, $subject, $username, &$data, $update_message = true)
{
	global $db, $auth, $user, $config, $phpEx, $template, $phpbb_root_path;

	// We do not handle erasing posts here
	if ($mode == 'delete')
	{
		return false;
	}

	$current_time = time();

	if ($mode == 'post')
	{
		$post_mode = 'post';
		$update_message = true;
	}
	else if ($mode != 'edit')
	{
		$post_mode = 'reply';
		$update_message = true;
	}
	else if ($mode == 'edit')
	{
		$post_mode = 'edit';
	}

	// First of all make sure the subject and topic title are having the correct length.
	// To achieve this without cutting off between special chars we convert to an array and then count the elements.
	$subject = truncate_string($subject);
	$data['topic_title'] = truncate_string($data['topic_title']);

	// Collect some basic information about which tables and which rows to update/insert
	$sql_data = $topic_row = array();
	$poster_id = ($mode == 'edit') ? $data['poster_id'] : (int) $user->data['user_id'];

	// Start the transaction here
	$db->sql_transaction('begin');

	// Collect Information
	switch ($post_mode)
	{
		case 'post':
		case 'reply':
			$sql_data[GUESTBOOK_TABLE]['sql'] = array(
				'user_id'			=> (int) $data['user_id'],
				'poster_id'			=> (int) ((isset($data['poster_id'])) ? $data['poster_id'] : $user->data['user_id']),
				'icon_id'			=> $data['icon_id'],
				'poster_ip'			=> $user->ip,
				'post_time'			=> $current_time,
				'enable_bbcode'		=> $data['enable_bbcode'],
				'enable_smilies'	=> $data['enable_smilies'],
				'enable_magic_url'	=> $data['enable_urls'],
				'enable_sig'		=> $data['enable_sig'],
				'post_username'		=> (!$user->data['is_registered']) ? $username : '',
				'post_subject'		=> $subject,
				'post_text'			=> $data['message'],
				'post_checksum'		=> $data['message_md5'],
				'bbcode_bitfield'	=> $data['bbcode_bitfield'],
				'bbcode_uid'		=> $data['bbcode_uid'],
			);
		break;

		case 'edit_first_post':
		case 'edit':

		case 'edit_last_post':
		case 'edit_topic':
			if (!isset($sql_data[GUESTBOOK_TABLE]['sql']))
			{
				$sql_data[GUESTBOOK_TABLE]['sql'] = array();
			}

			$sql_data[GUESTBOOK_TABLE]['sql'] = array_merge($sql_data[GUESTBOOK_TABLE]['sql'], array(
				'poster_id'			=> $data['poster_id'],
				'icon_id'			=> $data['icon_id'],
				'enable_bbcode'		=> $data['enable_bbcode'],
				'enable_smilies'	=> $data['enable_smilies'],
				'enable_magic_url'	=> $data['enable_urls'],
				'enable_sig'		=> $data['enable_sig'],
				'post_username'		=> ($username && $data['poster_id'] == ANONYMOUS) ? $username : '',
				'post_subject'		=> $subject,
				'post_checksum'		=> $data['message_md5'],
				'bbcode_bitfield'	=> $data['bbcode_bitfield'],
				'bbcode_uid'		=> $data['bbcode_uid'],
			));

			if ($update_message)
			{
				$sql_data[GUESTBOOK_TABLE]['sql']['post_text'] = $data['message'];
			}

		break;
	}

	$topic_row = array();

	// Submit new post
	if ($post_mode == 'post' || $post_mode == 'reply')
	{
		$sql = 'INSERT INTO ' . GUESTBOOK_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data[GUESTBOOK_TABLE]['sql']);
		$db->sql_query($sql);
		$data['post_id'] = $db->sql_nextid();


		unset($sql_data[GUESTBOOK_TABLE]['sql']);
		
		$mb = $data['guestbook']->getmember();
		$first = '';
		if ($mb['user_guestbook_posts'] == 0)
		{
			$first .= ', user_guestbook_first_post_id = ' . (int)$data['post_id'];
		}
		
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_guestbook_posts = user_guestbook_posts + 1, user_guestbook_last_post_id = ' . (int)$data['post_id'] . $first . '  WHERE user_id = ' . (int)$data['user_id'];
		$db->sql_query($sql);
	}

	$make_global = false;

	// Update the posts table
	if (isset($sql_data[GUESTBOOK_TABLE]['sql'])) 
	{
		$sql = 'UPDATE ' . GUESTBOOK_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $sql_data[GUESTBOOK_TABLE]['sql']) . '
			WHERE post_id = ' . (int)$data['post_id'];
		$db->sql_query($sql);
	}

	// Committing the transaction before updating search index
	$db->sql_transaction('commit');

	// Send Notifications
	if ($mode != 'edit' && $mode != 'delete')
	{
		gb_user_notification($data);
	}

	$params = $add_anchor = '';

	return true;
}

function gb_user_notification ($data)
{
	global $db, $config;
	
	// First, make sure notifications are enabled	
	if (!$config['profile_guestbook_notification'] || $data['user_id'] == ANONYMOUS)
	{
		return false;
	}
	
	// Get banned User ID's
	$sql = 'SELECT ban_userid
		FROM ' . BANLIST_TABLE . '
		WHERE ban_userid <> 0
			AND ban_exclude <> 1';
	$result = $db->sql_query($sql);

	$sql_ignore_users = array(ANONYMOUS/*, $user->data['user_id']*/);
	while ($row = $db->sql_fetchrow($result))
	{
		$sql_ignore_users[] = (int) $row['ban_userid'];
	}
	$db->sql_freeresult($result);	
	
	$sql = 'SELECT u.user_gb_notification, u.user_gb_notification_enabled, u.user_id, u.username, u.user_email, u.user_lang, u.user_notify_type, u.user_jabber
		FROM ' . USERS_TABLE . ' u
		WHERE ' . $db->sql_in_set('user_id', $sql_ignore_users, true) . '
			AND u.user_type IN (' . USER_NORMAL . ', ' . USER_FOUNDER . ')		
			AND user_id = ' . (int)$data['user_id'];
		
	$result = $db->sql_query($sql);
	
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult();
	
	if (!$row || !$row['user_gb_notification_enabled'])
	{
		return false;
	}
	
	$send = array(
		'pm'	=> false,
		'im'	=> false,
		'mail'	=> false,
	);
	
	switch ($row['user_gb_notification'])
	{
		case GB_NOTIFY_EMAIL:
			if (!$config['email_enable'])
			{
				// Email disabled, and only email selected, return
				return false;
			}
			$send['mail'] = true;
		break;
		case GB_NOTIFY_IM:
			if (!$config['jab_enable'])
			{
				// IM disabled, and only IM selected, return
				return false;
			}
			$send['im'] = true;
		break;
		case GB_NOTIFY_PM:
			$send['pm'] = true;
		break;
		case GB_NOTIFY_EMAIL_PM:
			if ($config['email_enable'])
			{
				$send['mail'] = true;
			}
			$send['pm'] = true;
		break;
		case GB_NOTIFY_IM_PM:
			if ($config['jab_enable'])
			{
				$send['im'] = true;
			}
			$send['pm'] = true;		
		break;
		case GB_NOTIFY_ALL:		
			if ($config['email_enable'])
			{
				$send['mail'] = true;
			}
			
			if ($config['jab_enable'])
			{
				$send['im'] = true;
			}
			$send['pm'] = true;				
		break;
		default: 
			return false;
	}
	if ($send['mail'] || $send['im'])
	{
		global $phpEx, $phpbb_root_path;
		if (!class_exists('messenger'))
		{
			include($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
		}
		$messenger = new messenger();
		
		switch (true)
		{
			case $send['mail'] && !$send['im']:
				$method = NOTIFY_EMAIL;
			break;
			
			case !$send['mail'] && $send['im']:
				$method = NOTIFY_IM;
			break;
			
			case $send['mail'] && $send['im']:
				$method = NOTIFY_BOTH;
			break;
		}

		$addr = array(
			'method'	=> $method,
			'email'		=> $row['user_email'],
			'jabber'	=> $row['user_jabber'],
			'name'		=> $row['username'],
			'lang'		=> $row['user_lang'],
			'user_id'	=> $row['user_id'],
		);

		$messenger->template('guestbook_notification', $addr['lang']);

		$messenger->to($addr['email'], $addr['name']);
		$messenger->im($addr['jabber'], $addr['name']);

		$messenger->assign_vars(array(
			'USERNAME'		=> htmlspecialchars_decode($addr['name']),
			'U_POST'		=> generate_board_url() . "/memberlist.$phpEx?mode=viewprofile&u={$data['user_id']}&p={$data['post_id']}#p{$data['post_id']}",

		));

		$messenger->send($addr['method']);

		unset($msg_list_ary);

		$messenger->save_queue();
	}
	
	if ($send['pm'])
	{
		global $user;
		
		if (!function_exists('submit_pm'))
		{
			global $phpbb_root_path, $phpEx;
			include("{$phpbb_root_path}includes/functions_privmsgs.$phpEx");
		}
		
		// note that multibyte support is enabled here
		$my_subject = $user->lang['NEW_GUESTBOOK_POST'];
		$my_text    = sprintf($user->lang['NEW_GUESTBOOK_POST_TXT'], '[url]' . generate_board_url() . "/memberlist.$phpEx?mode=viewprofile&u={$data['user_id']}&p={$data['post_id']}#p{$data['post_id']}[/url]");
		 
		// variables to hold the parameters for submit_pm
		$poll = $uid = $bitfield = $options = '';
		generate_text_for_storage($my_subject, $uid, $bitfield, $options, false, false, false);
		generate_text_for_storage($my_text, $uid, $bitfield, $options, true, true, true);
		 
		$data = array(
		    'address_list'      => array ('u' => array($data['user_id'] => 'to')),
		    'from_user_id'      => $user->data['user_id'],
		    'from_username'     => $user->data['username'],
		    'icon_id'           => 0,
		    'from_user_ip'      => $user->data['user_ip'],
		      
		    'enable_bbcode'     => true,
		    'enable_smilies'    => true,
		    'enable_urls'       => true,
		    'enable_sig'        => true,
		 
		    'message'           => $my_text,
		    'bbcode_bitfield'   => $bitfield,
		    'bbcode_uid'        => $uid,
		);
		 
		submit_pm('post', $my_subject, $data, false);	
	}
}

