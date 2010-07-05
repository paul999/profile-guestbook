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
			 WHERE user_id = ' . $member['user_id'];
	}
	else if ($member['user_guestbook_first_post_id'] == $post_id)
	{
		// New first post
		$sql = 'SELECT post_id FROM ' . GUESTBOOK_TABLE . ' WHERE user_id = ' . $member['user_id'] . ' ORDER BY post_time ASC';
		$result = $db->sql_query_limit($sql, 1);
		$post_id2 = (int)$db->sql_fetchfield('post_id');
		$db->sql_freeresult($result);
		
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_guestbook_posts = user_guestbook_posts -1, user_guestbook_first_post_id = ' . $post_id2 . ' WHERE user_id = ' . $member['user_id'];
	}
	else if ($member['user_guestbook_last_post_id'] == $post_id)
	{
		// New last post
		$sql = 'SELECT post_id FROM ' . GUESTBOOK_TABLE . ' WHERE user_id = ' . $member['user_id'] . ' ORDER BY post_time DESC';
		$result = $db->sql_query_limit($sql, 1);
		$post_id2 = (int)$db->sql_fetchfield('post_id');
		$db->sql_freeresult($result);		
		
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_guestbook_posts = user_guestbook_posts -1, user_guestbook_last_post_id = ' . $post_id2 . ' WHERE user_id = ' . $member['user_id'];		
	}
	else
	{
		// Enough posts in guestbook, don't need to update last post, just decrement counters.
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_guestbook_posts = user_guestbook_posts -1 WHERE user_id = ' . $member['user_id'];
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

//			add_log('mod', $forum_id, $topic_id, 'LOG_GB_DELETE_POST', $post_data['post_subject'], $post_username);

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
	if ($post_data['poster_id'] != $user->data['user_id'] && $auth->acl_get('u_gb_delete', $forum_id))
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
				'poster_id'			=> (int) $user->data['user_id'],
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


			// If the person editing this post is different to the one having posted then we will add a log entry stating the edit
			// Could be simplified by only adding to the log if the edit is not tracked - but this may confuse admins/mods
			if ($user->data['user_id'] != $poster_id)
			{
				$log_subject = ($GUESTBOOKsubject) ? $subject : $data['topic_title'];
				add_log('mod', $data['forum_id'], $data['topic_id'], 'LOG_POST_EDITED', $log_subject, (!empty($username)) ? $username : $user->lang['GUEST']);
			}

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
			$first .= ', user_guestbook_first_post_id = ' . $data['post_id'];
		}
		
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_guestbook_posts = user_guestbook_posts + 1, user_guestbook_last_post_id = ' . $data['post_id'] . $first . '  WHERE user_id = ' . $data['user_id'];
		$db->sql_query($sql);
	}

	$make_global = false;


	// Update the posts table
	if (isset($sql_data[GUESTBOOK_TABLE]['sql'])) 
	{
		$sql = 'UPDATE ' . GUESTBOOK_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $sql_data[GUESTBOOK_TABLE]['sql']) . '
			WHERE post_id = ' . $data['post_id'];
		$db->sql_query($sql);
	}

	// Committing the transaction before updating search index
	$db->sql_transaction('commit');

	// Send Notifications
	if ($mode != 'edit' && $mode != 'delete')
	{
		//gb_user_notification($mode, $subject, $data['topic_title'], $data['forum_name'], $data['forum_id'], $data['topic_id'], $data['post_id']);
	}

	$params = $add_anchor = '';

	return true;
}

?>
