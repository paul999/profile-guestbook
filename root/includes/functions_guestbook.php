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
* Do the various checks required for removing posts as well as removing it
*/
function handle_gb_post_delete($forum_id, $topic_id, $post_id, &$post_data)
{
	global $user, $db, $auth, $config;
	global $phpbb_root_path, $phpEx;

	// If moderator removing post or user itself removing post, present a confirmation screen
	if ($auth->acl_get('m_delete', $forum_id) || ($post_data['poster_id'] == $user->data['user_id'] && $user->data['is_registered'] && $auth->acl_get('f_delete', $forum_id) && $post_id == $post_data['topic_last_post_id'] && !$post_data['post_edit_locked'] && ($post_data['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time'])))
	{
		$s_hidden_fields = build_hidden_fields(array(
			'p'		=> $post_id,
			'f'		=> $forum_id,
			'mode'	=> 'delete')
		);

		if (confirm_box(true))
		{
			$data = array(
				'topic_first_post_id'	=> $post_data['topic_first_post_id'],
				'topic_last_post_id'	=> $post_data['topic_last_post_id'],
				'topic_replies_real'	=> $post_data['topic_replies_real'],
				'topic_approved'		=> $post_data['topic_approved'],
				'topic_type'			=> $post_data['topic_type'],
				'post_approved'			=> $post_data['post_approved'],
				'post_reported'			=> $post_data['post_reported'],
				'post_time'				=> $post_data['post_time'],
				'poster_id'				=> $post_data['poster_id'],
				'post_postcount'		=> $post_data['post_postcount']
			);

			$next_post_id = delete_post($forum_id, $topic_id, $post_id, $data);
			$post_username = ($post_data['poster_id'] == ANONYMOUS && !empty($post_data['post_username'])) ? $post_data['post_username'] : $post_data['username'];

			if ($next_post_id === false)
			{
				add_log('mod', $forum_id, $topic_id, 'LOG_DELETE_TOPIC', $post_data['topic_title'], $post_username);

				$meta_info = append_sid("{$phpbb_root_path}viewforum.$phpEx", "f=$forum_id");
				$message = $user->lang['POST_DELETED'];
			}
			else
			{
				add_log('mod', $forum_id, $topic_id, 'LOG_DELETE_POST', $post_data['post_subject'], $post_username);

				$meta_info = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;p=$next_post_id") . "#p$next_post_id";
				$message = $user->lang['POST_DELETED'] . '<br /><br />' . sprintf($user->lang['RETURN_TOPIC'], '<a href="' . $meta_info . '">', '</a>');
			}

			meta_refresh(3, $meta_info);
			$message .= '<br /><br />' . sprintf($user->lang['RETURN_FORUM'], '<a href="' . append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id) . '">', '</a>');
			trigger_error($message);
		}
		else
		{
			confirm_box(false, 'DELETE_POST', $s_hidden_fields);
		}
	}

	// If we are here the user is not able to delete - present the correct error message
	if ($post_data['poster_id'] != $user->data['user_id'] && $auth->acl_get('f_delete', $forum_id))
	{
		trigger_error('DELETE_OWN_POSTS');
	}

	if ($post_data['poster_id'] == $user->data['user_id'] && $auth->acl_get('f_delete', $forum_id) && $post_id != $post_data['topic_last_post_id'])
	{
		trigger_error('CANNOT_DELETE_REPLIED');
	}

	trigger_error('USER_CANNOT_DELETE');
}


/**
* Submit Post
* @todo Split up and create lightweight, simple API for this.
*/
function submit_gb_post($mode, $subject, $username, $topic_type, &$data, $update_message = true)
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
		$post_mode = ($data['topic_replies_real'] == 0) ? 'edit_topic' : (($data['topic_first_post_id'] == $data['post_id']) ? 'edit_first_post' : (($data['topic_last_post_id'] == $data['post_id']) ? 'edit_last_post' : 'edit'));
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
			$sql_data[POSTS_TABLE]['sql'] = array(
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
				'post_attachment'	=> (!empty($data['attachment_data'])) ? 1 : 0,
				'bbcode_bitfield'	=> $data['bbcode_bitfield'],
				'bbcode_uid'		=> $data['bbcode_uid'],
				'post_postcount'	=> ($auth->acl_get('f_postcount', $data['forum_id'])) ? 1 : 0,
				'post_edit_locked'	=> $data['post_edit_locked']
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
				$log_subject = ($subject) ? $subject : $data['topic_title'];
				add_log('mod', $data['forum_id'], $data['topic_id'], 'LOG_POST_EDITED', $log_subject, (!empty($username)) ? $username : $user->lang['GUEST']);
			}

			if (!isset($sql_data[POSTS_TABLE]['sql']))
			{
				$sql_data[POSTS_TABLE]['sql'] = array();
			}

			$sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
				'forum_id'			=> ($topic_type == POST_GLOBAL) ? 0 : $data['forum_id'],
				'poster_id'			=> $data['poster_id'],
				'icon_id'			=> $data['icon_id'],
				'post_approved'		=> (!$post_approval) ? 0 : $data['post_approved'],
				'enable_bbcode'		=> $data['enable_bbcode'],
				'enable_smilies'	=> $data['enable_smilies'],
				'enable_magic_url'	=> $data['enable_urls'],
				'enable_sig'		=> $data['enable_sig'],
				'post_username'		=> ($username && $data['poster_id'] == ANONYMOUS) ? $username : '',
				'post_subject'		=> $subject,
				'post_checksum'		=> $data['message_md5'],
				'post_attachment'	=> (!empty($data['attachment_data'])) ? 1 : 0,
				'bbcode_bitfield'	=> $data['bbcode_bitfield'],
				'bbcode_uid'		=> $data['bbcode_uid'],
				'post_edit_locked'	=> $data['post_edit_locked'])
			);

			if ($update_message)
			{
				$sql_data[POSTS_TABLE]['sql']['post_text'] = $data['message'];
			}

		break;
	}

	$topic_row = array();

	// And the topic ladies and gentlemen
	switch ($post_mode)
	{
		case 'post':

		case 'reply':
/*			$sql_data[TOPICS_TABLE]['stat'][] = 'topic_last_view_time = ' . $current_time . ',
				topic_replies_real = topic_replies_real + 1,
				topic_bumped = 0,
				topic_bumper = 0' .
				(($post_approval) ? ', topic_replies = topic_replies + 1' : '') .
				((!empty($data['attachment_data']) || (isset($data['topic_attachment']) && $data['topic_attachment'])) ? ', topic_attachment = 1' : '');

			$sql_data[USERS_TABLE]['stat'][] = "user_lastpost_time = $current_time" . (($auth->acl_get('f_postcount', $data['forum_id']) && $post_approval) ? ', user_posts = user_posts + 1' : '');
*/
		break;

		case 'edit_topic':
		case 'edit_first_post':
			$sql_data[TOPICS_TABLE]['sql'] = array(
				'forum_id'					=> ($topic_type == POST_GLOBAL) ? 0 : $data['forum_id'],
				'icon_id'					=> $data['icon_id'],
				'topic_approved'			=> (!$post_approval) ? 0 : $data['topic_approved'],
				'topic_title'				=> $subject,
				'topic_first_poster_name'	=> $username,
				'topic_type'				=> $topic_type,
				'topic_time_limit'			=> ($topic_type == POST_STICKY || $topic_type == POST_ANNOUNCE) ? ($data['topic_time_limit'] * 86400) : 0,
				'poll_title'				=> (isset($poll['poll_options'])) ? $poll['poll_title'] : '',
				'poll_start'				=> (isset($poll['poll_options'])) ? $poll_start : 0,
				'poll_max_options'			=> (isset($poll['poll_options'])) ? $poll['poll_max_options'] : 1,
				'poll_length'				=> (isset($poll['poll_options'])) ? $poll_length : 0,
				'poll_vote_change'			=> (isset($poll['poll_vote_change'])) ? $poll['poll_vote_change'] : 0,
				'topic_last_view_time'		=> $current_time,

				'topic_attachment'			=> (!empty($data['attachment_data'])) ? 1 : (isset($data['topic_attachment']) ? $data['topic_attachment'] : 0),
			);

		break;

		case 'edit':
		case 'edit_last_post':


		break;
	}

	// Submit new post
	if ($post_mode == 'post' || $post_mode == 'reply')
	{
		if ($post_mode == 'reply')
		{
			$sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
				'topic_id' => $data['topic_id'])
			);
		}

		$sql = 'INSERT INTO ' . POSTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data[POSTS_TABLE]['sql']);
		$db->sql_query($sql);
		$data['post_id'] = $db->sql_nextid();

		if ($post_mode == 'post')
		{
			$sql_data[TOPICS_TABLE]['sql'] = array(
				'topic_first_post_id'		=> $data['post_id'],
				'topic_last_post_id'		=> $data['post_id'],
				'topic_last_post_time'		=> $current_time,
				'topic_last_poster_id'		=> (int) $user->data['user_id'],
				'topic_last_poster_name'	=> (!$user->data['is_registered'] && $username) ? $username : (($user->data['user_id'] != ANONYMOUS) ? $user->data['username'] : ''),
				'topic_last_poster_colour'	=> $user->data['user_colour'],
				'topic_last_post_subject'	=> (string) $subject,
			);
		}

		unset($sql_data[POSTS_TABLE]['sql']);
	}

	$make_global = false;


	// Update the topics table
	if (isset($sql_data[TOPICS_TABLE]['sql']))
	{
		$sql = 'UPDATE ' . TOPICS_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $sql_data[TOPICS_TABLE]['sql']) . '
			WHERE topic_id = ' . $data['topic_id'];
		$db->sql_query($sql);
	}

	// Update the posts table
	if (isset($sql_data[POSTS_TABLE]['sql']))
	{
		$sql = 'UPDATE ' . POSTS_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $sql_data[POSTS_TABLE]['sql']) . '
			WHERE post_id = ' . $data['post_id'];
		$db->sql_query($sql);
	}

	// topic sync time!
	// simply, we update if it is a reply or the last post is edited
	if ($post_approved)
	{
		// reply requires the whole thing
		if ($post_mode == 'reply')
		{
			$sql_data[TOPICS_TABLE]['stat'][] = 'topic_last_post_id = ' . (int) $data['post_id'];
			$sql_data[TOPICS_TABLE]['stat'][] = 'topic_last_poster_id = ' . (int) $user->data['user_id'];
			$sql_data[TOPICS_TABLE]['stat'][] = "topic_last_poster_name = '" . $db->sql_escape((!$user->data['is_registered'] && $username) ? $username : (($user->data['user_id'] != ANONYMOUS) ? $user->data['username'] : '')) . "'";
			$sql_data[TOPICS_TABLE]['stat'][] = "topic_last_poster_colour = '" . (($user->data['user_id'] != ANONYMOUS) ? $db->sql_escape($user->data['user_colour']) : '') . "'";
			$sql_data[TOPICS_TABLE]['stat'][] = "topic_last_post_subject = '" . $db->sql_escape($subject) . "'";
			$sql_data[TOPICS_TABLE]['stat'][] = 'topic_last_post_time = ' . (int) $current_time;
		}
		else if ($post_mode == 'edit_last_post' || $post_mode == 'edit_topic' || ($post_mode == 'edit_first_post' && !$data['topic_replies']))
		{
			// only the subject can be changed from edit
			$sql_data[TOPICS_TABLE]['stat'][] = "topic_last_post_subject = '" . $db->sql_escape($subject) . "'";

			// Maybe not only the subject, but also changing anonymous usernames. ;)
			if ($data['poster_id'] == ANONYMOUS)
			{
				$sql_data[TOPICS_TABLE]['stat'][] = "topic_last_poster_name = '" . $db->sql_escape($username) . "'";
			}
		}
	}
	else if (!$data['post_approved'] && ($post_mode == 'edit_last_post' || $post_mode == 'edit_topic' || ($post_mode == 'edit_first_post' && !$data['topic_replies'])))
	{
		// like having the rug pulled from under us
		$sql = 'SELECT MAX(post_id) as last_post_id
			FROM ' . POSTS_TABLE . '
			WHERE topic_id = ' . (int) $data['topic_id'] . '
				AND post_approved = 1';
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// any posts left in this forum?
		if (!empty($row['last_post_id']))
		{
			$sql = 'SELECT p.post_id, p.post_subject, p.post_time, p.poster_id, p.post_username, u.user_id, u.username, u.user_colour
				FROM ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
				WHERE p.poster_id = u.user_id
					AND p.post_id = ' . (int) $row['last_post_id'];
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			// salvation, a post is found! jam it into the topics table
			$sql_data[TOPICS_TABLE]['stat'][] = 'topic_last_post_id = ' . (int) $row['post_id'];
			$sql_data[TOPICS_TABLE]['stat'][] = "topic_last_post_subject = '" . $db->sql_escape($row['post_subject']) . "'";
			$sql_data[TOPICS_TABLE]['stat'][] = 'topic_last_post_time = ' . (int) $row['post_time'];
			$sql_data[TOPICS_TABLE]['stat'][] = 'topic_last_poster_id = ' . (int) $row['poster_id'];
			$sql_data[TOPICS_TABLE]['stat'][] = "topic_last_poster_name = '" . $db->sql_escape(($row['poster_id'] == ANONYMOUS) ? $row['post_username'] : $row['username']) . "'";
			$sql_data[TOPICS_TABLE]['stat'][] = "topic_last_poster_colour = '" . $db->sql_escape($row['user_colour']) . "'";
		}
	}

	// Update total post count, do not consider moderated posts/topics
	if ($post_approval)
	{
		if ($post_mode == 'post')
		{
			set_config_count('num_topics', 1, true);
			set_config_count('num_posts', 1, true);
		}

		if ($post_mode == 'reply')
		{
			set_config_count('num_posts', 1, true);
		}
	}

	// Update forum stats
	$where_sql = array(POSTS_TABLE => 'post_id = ' . $data['post_id'], TOPICS_TABLE => 'topic_id = ' . $data['topic_id'], FORUMS_TABLE => 'forum_id = ' . $data['forum_id'], USERS_TABLE => 'user_id = ' . $poster_id);

	foreach ($sql_data as $table => $update_ary)
	{
		if (isset($update_ary['stat']) && implode('', $update_ary['stat']))
		{
			$sql = "UPDATE $table SET " . implode(', ', $update_ary['stat']) . ' WHERE ' . $where_sql[$table];
			$db->sql_query($sql);
		}
	}

	// Committing the transaction before updating search index
	$db->sql_transaction('commit');

	// Send Notifications
	if ($mode != 'edit' && $mode != 'delete' && $post_approval)
	{
		gb_user_notification($mode, $subject, $data['topic_title'], $data['forum_name'], $data['forum_id'], $data['topic_id'], $data['post_id']);
	}

	$params = $add_anchor = '';

	return true;
}

?>
