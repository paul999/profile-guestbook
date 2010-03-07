<?php
/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2010 Paul Sohier
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/
define ('GUESTBOOK_TABLE', 'phpbb_guestbook');
/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

class guestbook
{
	private $enabled	= false;
	private $user_id	= 0;
	private $mode		= '';
	private $member		= array();
	
	/**
	 * 
	 */
	public function __construct()
	{
		global $config, $auth, $member;
		
		$this->enabled = false;

		if (!$config['guestbook_enabled'])
		{
			return;
		}
		else if(false && !$auth->acl_get('u_guestbook_view'))// @TODO, auth check!
		{
			return;
		}
		else if($this->user_id == ANONYMOUS)
		{
			return;
		}
		$this->enabled = true;
		
		$this->mode = request_var('gbmode', 'display');
		
		if ($member)
		{
			$this->user_id	= (int)$member['user_id'];		
			$this->member	= $member;
		}
	}
	
	/**
	 *
	 * 
	 */
	public function run()
	{
		if (!$this->enabled)
		{
			return;
		}
		else if (!$this->user_id || !sizeof($this->member))
		{
			return;
		}

		switch ($this->mode)
		{
			case 'display':
				$this->display();	
			break;
			
			case 'edit':
			case 'delete':
			case 'post':
				$this->post();
			break;
		}
	}
	
	private function set_user($user_id)
	{
		if (!is_numeric($user_id))
		{
			return false;
		}
		
		global $db;
		$sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int)$user_id;
		$result = $db->sql_query($sql);
		
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult();
		
		if (!$row)
		{
			return false;	
		}
		
		$this->member	= $row;
		$this->user_id	= $user_id;
		return true;
	}
	
	private function display()
	{
		global $template, $user, $config;
		global $phpbb_root_path, $phpEx, $db;
		global $cache, $auth;
		
		$user->add_lang(array('viewtopic'));
		
		$template->assign_var('S_GUESTBOOK_ENABLED', true);

		$start		= request_var('start', 0);
		$view		= request_var('view', '');
		$post_id	= request_var('p', 0);
		
		$default_sort_days	= (!empty($user->data['user_post_show_days'])) ? $user->data['user_post_show_days'] : 0;
		$default_sort_key	= (!empty($user->data['user_post_sortby_type'])) ? $user->data['user_post_sortby_type'] : 't';
		$default_sort_dir	= (!empty($user->data['user_post_sortby_dir'])) ? $user->data['user_post_sortby_dir'] : 'a';

		$sort_days	= request_var('st', $default_sort_days);
		$sort_key	= request_var('sk', $default_sort_key);
		$sort_dir	= request_var('sd', $default_sort_dir);

		$update		= request_var('update', false);
		
		$hilit_words	= request_var('hilit', '', true);

		$template->assign_vars(array(
			'POST_IMG' 			=> (true) ? $user->img('button_topic_locked', 'FORUM_LOCKED') : $user->img('button_topic_new', 'POST_NEW_TOPIC'), //@TODO, correct button
			'QUOTE_IMG' 			=> $user->img('icon_post_quote', 'REPLY_WITH_QUOTE'),
			'REPLY_IMG'			=> (true) ? $user->img('button_topic_locked', 'TOPIC_LOCKED') : $user->img('button_topic_reply', 'REPLY_TO_TOPIC'),// @TODO, correct button
			'EDIT_IMG' 			=> $user->img('icon_post_edit', 'EDIT_POST'),
			'DELETE_IMG' 			=> $user->img('icon_post_delete', 'DELETE_POST'),
			'INFO_IMG' 			=> $user->img('icon_post_info', 'VIEW_INFO'),
			'PROFILE_IMG'			=> $user->img('icon_user_profile', 'READ_PROFILE'),
			'SEARCH_IMG' 			=> $user->img('icon_user_search', 'SEARCH_USER_POSTS'),
			'PM_IMG' 			=> $user->img('icon_contact_pm', 'SEND_PRIVATE_MESSAGE'),
			'EMAIL_IMG' 			=> $user->img('icon_contact_email', 'SEND_EMAIL'),
			'WWW_IMG' 			=> $user->img('icon_contact_www', 'VISIT_WEBSITE'),
			'ICQ_IMG' 			=> $user->img('icon_contact_icq', 'ICQ'),
			'AIM_IMG' 			=> $user->img('icon_contact_aim', 'AIM'),
			'MSN_IMG' 			=> $user->img('icon_contact_msnm', 'MSNM'),
			'YIM_IMG' 			=> $user->img('icon_contact_yahoo', 'YIM'),
			'JABBER_IMG'			=> $user->img('icon_contact_jabber', 'JABBER') ,
			'REPORT_IMG'			=> $user->img('icon_post_report', 'REPORT_POST'),
			'REPORTED_IMG'			=> $user->img('icon_topic_reported', 'POST_REPORTED'),
			'UNAPPROVED_IMG'		=> $user->img('icon_topic_unapproved', 'POST_UNAPPROVED'),
			'WARN_IMG'			=> $user->img('icon_user_warn', 'WARN_USER'),

			'S_IS_LOCKED'			=> (true) ? false : true, // @TODO, value correct
/*			'S_SELECT_SORT_DIR' 	=> $s_sort_dir,
			'S_SELECT_SORT_KEY' 	=> $s_sort_key,
			'S_SELECT_SORT_DAYS' 	=> $s_limit_days,*/
			'S_GUESTBOOK_ACTION' 		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "u={$this->user_id}&amp;gbmode=display&amp;mode=viewprofile"),

			'S_VIEWTOPIC'			=> true,

			'S_DISPLAY_POST_INFO'	=> true, //@TODO perm
			'S_DISPLAY_REPLY_INFO'	=> true, //@TODO perm
			
			'U_POST_REPLY_TOPIC' 	=> (true || $auth->acl_get('f_reply') || $user->data['user_id'] == ANONYMOUS) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "u={$this->user_id}&amp;gbmode=post&amp;mode=viewprofile") : '', // @TODO permission
		));

		// This rather complex gaggle of code handles querying for topics but
		// also allows for direct linking to a post (and the calculation of which
		// page the post is on and the correct display of viewtopic)
		$sql_array = array(
			'SELECT'	=> 'g.*',

			'FROM'		=> array(GUESTBOOK_TABLE => 'g'),
		);

		$sql_array['WHERE'] = 'g.user_id = ' . $this->user_id;
		if ($post_id)
		{
			$sql_array['WHERE'] .= " AND g.post_id = $post_id";
		}

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);
		$gb_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// link to unapproved post or incorrect link
		if (!$gb_data)
		{
			// If post_id was submitted, we try at least to display the normal profile as a last resort...
			if ($post_id)
			{
				redirect(append_sid("{$phpbb_root_path}memberlist.$phpEx", "u={$this->user_id}&amp;mode=viewprofile&amp;gbmode=display"));
			}
			
			$total_posts = 0;

			// Send vars to template
			$template->assign_vars(array(
				'PAGE_NUMBER' 	=> on_page($total_posts, $config['posts_per_page'], $start),
				'TOTAL_POSTS'	=> ($total_posts == 1) ? $user->lang['VIEW_TOPIC_POST'] : sprintf($user->lang['VIEW_TOPIC_POSTS'], $total_posts),
			));

			return;
		}

		// This is for determining where we are (page)
		if ($post_id)
		{
			if ($post_id == $this->member['user_guestbook_first_post_id'] || $post_id == $this->member['user_guestbook_last_post_id'])
			{
				$check_sort = ($post_id == $this->member['user_guestbook_first_post_id']) ? 'd' : 'a';

				if ($sort_dir == $check_sort)
				{
					$gb_data['prev_posts'] = $this->member['guestbook_posts'];
				}
				else
				{
					$gb_data['prev_posts'] = 0;
				}
			}
			else
			{
				$sql = 'SELECT COUNT(p1.post_id) AS prev_posts
					FROM ' . GUESTBOOK_TABLE . ' p1, ' . GUESTBOOK_TABLE . " p2
					WHERE p1.user_id = {$this->user_id}
						AND p2.post_id = {$post_id}
						AND " . (($sort_dir == 'd') ? 'p1.post_time >= p2.post_time' : 'p1.post_time <= p2.post_time');

				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$gb_data['prev_posts'] = $row['prev_posts'] - 1;
			}
		}

		//
		$gb_replies = $this->member['user_guestbook_posts'];

		// What is start equal to?
		if ($post_id)
		{
			$start = floor(($gb_data['prev_posts']) / $config['posts_per_page']) * $config['posts_per_page'];
		}

		// Post ordering options
		$limit_days = array(0 => $user->lang['ALL_POSTS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);

		$sort_by_text = array('a' => $user->lang['AUTHOR'], 't' => $user->lang['POST_TIME'], 's' => $user->lang['SUBJECT']);
		$sort_by_sql = array('a' => array('u.username_clean', 'g.post_id'), 't' => 'g.post_time', 's' => array('g.post_subject', 'g.post_id'));
		$join_user_sql = array('a' => true, 't' => false, 's' => false);

		$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';

		gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param, $default_sort_days, $default_sort_key, $default_sort_dir);

		// Obtain correct post count and ordering SQL if user has
		// requested anything different
		if ($sort_days)
		{
			$min_post_time = time() - ($sort_days * 86400);

			$sql = 'SELECT COUNT(post_id) AS num_posts
				FROM ' . GUESTBOOK_TABLE . "
				WHERE user_id = {$this->user_id}
					AND post_time >= $min_post_time";
			$result = $db->sql_query($sql);
			$total_posts = (int) $db->sql_fetchfield('num_posts');
			$db->sql_freeresult($result);

			$limit_posts_time = "AND p.post_time >= $min_post_time ";

			if (isset($_POST['sort']))
			{
				$start = 0;
			}
		}
		else
		{
			$total_posts = $gb_replies + 1;
			$limit_posts_time = '';
		}

		// Was a highlight request part of the URI?
		$highlight_match = $highlight = '';
		if ($hilit_words)
		{
			foreach (explode(' ', trim($hilit_words)) as $word)
			{
				if (trim($word))
				{
					$word = str_replace('\*', '\w+?', preg_quote($word, '#'));
					$word = preg_replace('#(^|\s)\\\\w\*\?(\s|$)#', '$1\w+?$2', $word);
					$highlight_match .= (($highlight_match != '') ? '|' : '') . $word;
				}
			}

			$highlight = urlencode($hilit_words);
		}

		// Make sure $start is set to the last page if it exceeds the amount
		if ($start < 0 || $start >= $total_posts)
		{
			$start = ($start < 0) ? 0 : floor(($total_posts - 1) / $config['posts_per_page']) * $config['posts_per_page'];
		}

		// General Viewtopic URL for return links
		$viewtopic_url = append_sid("{$phpbb_root_path}memberlist.$phpEx", "u={$this->user_id}&amp;mode=viewprofile&amp;gbmode=display&amp;start=$start" . ((strlen($u_sort_param)) ? "&amp;$u_sort_param" : '') . (($highlight_match) ? "&amp;hilit=$highlight" : ''));

		// Grab ranks
		$ranks = $cache->obtain_ranks();

		// Grab icons
		$icons = $cache->obtain_icons();

		// This is only used for print view so ...
		$server_path = (!$view) ? $phpbb_root_path : generate_board_url() . '/';
/**


**/		
	
		// If we've got a hightlight set pass it on to pagination.
		$pagination = generate_pagination(append_sid("{$phpbb_root_path}memberlist.$phpEx", "u={$this->user_id}&amp;gbmode=display&amp;mode=viewprofile"), $total_posts, $config['posts_per_page'], $start);

/**

**/		
		// Send vars to template
		$template->assign_vars(array(
			'PAGINATION' 	=> $pagination,
			'PAGE_NUMBER' 	=> on_page($total_posts, $config['posts_per_page'], $start),
			'TOTAL_POSTS'	=> ($total_posts == 1) ? $user->lang['VIEW_TOPIC_POST'] : sprintf($user->lang['VIEW_TOPIC_POSTS'], $total_posts),
		));



		// If the user is trying to reach the second half of the guestbook, fetch it starting from the end
		$store_reverse = false;
		$sql_limit = $config['posts_per_page'];
		$sql_sort_order = $direction = '';

		if ($start > $total_posts / 2)
		{
			$store_reverse = true;

			if ($start + $config['posts_per_page'] > $total_posts)
			{
				$sql_limit = min($config['posts_per_page'], max(1, $total_posts - $start));
			}

			// Select the sort order
			$direction = (($sort_dir == 'd') ? 'ASC' : 'DESC');
			$sql_start = max(0, $total_posts - $sql_limit - $start);
		}
		else
		{
			// Select the sort order
			$direction = (($sort_dir == 'd') ? 'DESC' : 'ASC');
			$sql_start = $start;
		}

		if (is_array($sort_by_sql[$sort_key]))
		{
			$sql_sort_order = implode(' ' . $direction . ', ', $sort_by_sql[$sort_key]) . ' ' . $direction;
		}
		else
		{
			$sql_sort_order = $sort_by_sql[$sort_key] . ' ' . $direction;
		}

		// Container for user details, only process once
		$post_list = $user_cache = $id_cache = $attachments = $attach_list = $rowset = $update_count = $post_edit_list = array();
		$has_attachments = $display_notice = false;
		$bbcode_bitfield = '';
		$i = $i_total = 0;

		// Go ahead and pull all data for this topic
		$sql = 'SELECT g.post_id
			FROM ' . GUESTBOOK_TABLE . ' g' . (($join_user_sql[$sort_key]) ? ', ' . USERS_TABLE . ' u': '') . "
			WHERE g.user_id = {$this->user_id}
				" . (($join_user_sql[$sort_key]) ? 'AND u.user_id = g.poster_id': '') . "
				$limit_posts_time
			ORDER BY $sql_sort_order";
		$result = $db->sql_query_limit($sql, $sql_limit, $sql_start);

		$i = ($store_reverse) ? $sql_limit - 1 : 0;
		while ($row = $db->sql_fetchrow($result))
		{
			$post_list[$i] = (int) $row['post_id'];
			($store_reverse) ? $i-- : $i++;
		}
		$db->sql_freeresult($result);

		if (!sizeof($post_list))
		{
			if ($sort_days)
			{
				trigger_error('NO_POSTS_TIME_FRAME');
			}
			else
			{
				return;
			}
		}

		// Holding maximum post time for marking topic read
		// We need to grab it because we do reverse ordering sometimes
		$max_post_time = 0;

		$sql = $db->sql_build_query('SELECT', array(
			'SELECT'	=> 'u.*, z.friend, z.foe, g.*',

			'FROM'		=> array(
				USERS_TABLE		=> 'u',
				GUESTBOOK_TABLE		=> 'g',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(ZEBRA_TABLE => 'z'),
					'ON'	=> 'z.user_id = ' . $user->data['user_id'] . ' AND z.zebra_id = g.poster_id'
				)
			),

			'WHERE'		=> $db->sql_in_set('g.post_id', $post_list) . '
				AND u.user_id = g.poster_id'
		));

		$result = $db->sql_query($sql);

		$now = getdate(time() + $user->timezone + $user->dst - date('Z'));

		// Posts are stored in the $rowset array while $attach_list, $user_cache
		// and the global bbcode_bitfield are built
		while ($row = $db->sql_fetchrow($result))
		{
			// Set max_post_time
			if ($row['post_time'] > $max_post_time)
			{
				$max_post_time = $row['post_time'];
			}

			$poster_id = (int) $row['poster_id'];

			$rowset[$row['post_id']] = array(
				'hide_post'			=> ($row['foe'] && ($view != 'show' || $post_id != $row['post_id'])) ? true : false,

				'post_id'			=> $row['post_id'],
				'post_time'			=> $row['post_time'],
				'user_id'			=> $row['user_id'],
				'username'			=> $row['username'],
				'user_colour'		=> $row['user_colour'],
//				'topic_id'			=> $row['topic_id'],
//				'forum_id'			=> $row['forum_id'],
//				'post_subject'		=> $row['post_subject'],
//				'post_edit_count'	=> $row['post_edit_count'],
//				'post_edit_time'	=> $row['post_edit_time'],
//				'post_edit_reason'	=> $row['post_edit_reason'],
//				'post_edit_user'	=> $row['post_edit_user'],
//				'post_edit_locked'	=> $row['post_edit_locked'],

				// Make sure the icon actually exists
//				'icon_id'			=> (isset($icons[$row['icon_id']]['img'], $icons[$row['icon_id']]['height'], $icons[$row['icon_id']]['width'])) ? $row['icon_id'] : 0,
				'post_approved'		=> true, //$row['post_approved'],
//				'post_reported'		=> $row['post_reported'],
//				'post_username'		=> $row['post_username'],
				'post_text'			=> $row['post_text'],
/*				'bbcode_uid'		=> $row['bbcode_uid'],
				'bbcode_bitfield'	=> $row['bbcode_bitfield'],
				'enable_smilies'	=> $row['enable_smilies'],
				'enable_sig'		=> $row['enable_sig'],*/
				'friend'			=> $row['friend'],
				'foe'				=> $row['foe'],
			);

			// Define the global bbcode bitfield, will be used to load bbcodes
//			$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['bbcode_bitfield']);

			// Is a signature attached? Are we going to display it?
/*			if ($row['enable_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
			{
				$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['user_sig_bbcode_bitfield']);
			}
*/
			// Cache various user specific data ... so we don't have to recompute
			// this each time the same user appears on this page
			if (!isset($user_cache[$poster_id]))
			{
				if ($poster_id == ANONYMOUS)
				{
					$user_cache[$poster_id] = array(
						'joined'		=> '',
						'posts'			=> '',
						'from'			=> '',

						'sig'					=> '',
						'sig_bbcode_uid'		=> '',
						'sig_bbcode_bitfield'	=> '',

						'online'			=> false,
						'avatar'			=> ($user->optionget('viewavatars')) ? get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']) : '',
						'rank_title'		=> '',
						'rank_image'		=> '',
						'rank_image_src'	=> '',
						'sig'				=> '',
						'profile'			=> '',
						'pm'				=> '',
						'email'				=> '',
						'www'				=> '',
						'icq_status_img'	=> '',
						'icq'				=> '',
						'aim'				=> '',
						'msn'				=> '',
						'yim'				=> '',
						'jabber'			=> '',
						'search'			=> '',
						'age'				=> '',

						'username'			=> $row['username'],
						'user_colour'		=> $row['user_colour'],

						'warnings'			=> 0,
						'allow_pm'			=> 0,
					);

					get_user_rank($row['user_rank'], false, $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);
				}
				else
				{
					$user_sig = '';

					// We add the signature to every posters entry because enable_sig is post dependant
					if ($row['user_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
					{
						$user_sig = $row['user_sig'];
					}

					$id_cache[] = $poster_id;

					$user_cache[$poster_id] = array(
						'joined'		=> $user->format_date($row['user_regdate']),
						'posts'			=> $row['user_posts'],
						'warnings'		=> (isset($row['user_warnings'])) ? $row['user_warnings'] : 0,
						'from'			=> (!empty($row['user_from'])) ? $row['user_from'] : '',

						'sig'					=> $user_sig,
						'sig_bbcode_uid'		=> (!empty($row['user_sig_bbcode_uid'])) ? $row['user_sig_bbcode_uid'] : '',
						'sig_bbcode_bitfield'	=> (!empty($row['user_sig_bbcode_bitfield'])) ? $row['user_sig_bbcode_bitfield'] : '',

						'viewonline'	=> $row['user_allow_viewonline'],
						'allow_pm'		=> $row['user_allow_pm'],

						'avatar'		=> ($user->optionget('viewavatars')) ? get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']) : '',
						'age'			=> '',

						'rank_title'		=> '',
						'rank_image'		=> '',
						'rank_image_src'	=> '',

						'username'			=> $row['username'],
						'user_colour'		=> $row['user_colour'],

						'online'		=> false,
						'profile'		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u=$poster_id"),
						'www'			=> $row['user_website'],
						'aim'			=> ($row['user_aim'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=aim&amp;u=$poster_id") : '',
						'msn'			=> ($row['user_msnm'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=msnm&amp;u=$poster_id") : '',
						'yim'			=> ($row['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($row['user_yim']) . '&amp;.src=pg' : '',
						'jabber'		=> ($row['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=jabber&amp;u=$poster_id") : '',
						'search'		=> ($auth->acl_get('u_search')) ? append_sid("{$phpbb_root_path}search.$phpEx", "author_id=$poster_id&amp;sr=posts") : '',

						'author_full'		=> get_username_string('full', $poster_id, $row['username'], $row['user_colour']),
						'author_colour'		=> get_username_string('colour', $poster_id, $row['username'], $row['user_colour']),
						'author_username'	=> get_username_string('username', $poster_id, $row['username'], $row['user_colour']),
						'author_profile'	=> get_username_string('profile', $poster_id, $row['username'], $row['user_colour']),
					);

					get_user_rank($row['user_rank'], $row['user_posts'], $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);

					if (!empty($row['user_allow_viewemail']) || $auth->acl_get('a_email'))
					{
						$user_cache[$poster_id]['email'] = ($config['board_email_form'] && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=email&amp;u=$poster_id") : (($config['board_hide_emails'] && !$auth->acl_get('a_email')) ? '' : 'mailto:' . $row['user_email']);
					}
					else
					{
						$user_cache[$poster_id]['email'] = '';
					}

					if (!empty($row['user_icq']))
					{
						$user_cache[$poster_id]['icq'] = 'http://www.icq.com/people/webmsg.php?to=' . $row['user_icq'];
						$user_cache[$poster_id]['icq_status_img'] = '<img src="http://web.icq.com/whitepages/online?icq=' . $row['user_icq'] . '&amp;img=5" width="18" height="18" alt="" />';
					}
					else
					{
						$user_cache[$poster_id]['icq_status_img'] = '';
						$user_cache[$poster_id]['icq'] = '';
					}

					if ($config['allow_birthdays'] && !empty($row['user_birthday']))
					{
						list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $row['user_birthday']));

						if ($bday_year)
						{
							$diff = $now['mon'] - $bday_month;
							if ($diff == 0)
							{
								$diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
							}
							else
							{
								$diff = ($diff < 0) ? 1 : 0;
							}

							$user_cache[$poster_id]['age'] = (int) ($now['year'] - $bday_year - $diff);
						}
					}
				}
			}
		}
		$db->sql_freeresult($result);

		// Load custom profile fields
		if ($config['load_cpf_viewtopic'])
		{
			if (!class_exists('custom_profile'))
			{
				include($phpbb_root_path . 'includes/functions_profile_fields.' . $phpEx);
			}
			$cp = new custom_profile();

			// Grab all profile fields from users in id cache for later use - similar to the poster cache
			$profile_fields_tmp = $cp->generate_profile_fields_template('grab', $id_cache);

			// filter out fields not to be displayed on viewtopic. Yes, it's a hack, but this shouldn't break any MODs.
			$profile_fields_cache = array();
			foreach ($profile_fields_tmp as $profile_user_id => $profile_fields)
			{
				$profile_fields_cache[$profile_user_id] = array();
				foreach ($profile_fields as $used_ident => $profile_field)
				{
					if ($profile_field['data']['field_show_on_vt'])
					{
						$profile_fields_cache[$profile_user_id][$used_ident] = $profile_field;
					}
				}
			}
			unset($profile_fields_tmp);
		}

		// Generate online information for user
		if ($config['load_onlinetrack'] && sizeof($id_cache))
		{
			$sql = 'SELECT session_user_id, MAX(session_time) as online_time, MIN(session_viewonline) AS viewonline
				FROM ' . SESSIONS_TABLE . '
				WHERE ' . $db->sql_in_set('session_user_id', $id_cache) . '
				GROUP BY session_user_id';
			$result = $db->sql_query($sql);

			$update_time = $config['load_online_time'] * 60;
			while ($row = $db->sql_fetchrow($result))
			{
				$user_cache[$row['session_user_id']]['online'] = (time() - $update_time < $row['online_time'] && (($row['viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
			}
			$db->sql_freeresult($result);
		}
		unset($id_cache);

		// Pull attachment data
		if (sizeof($attach_list))
		{
			if ($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id))
			{
				$sql = 'SELECT *
					FROM ' . ATTACHMENTS_TABLE . '
					WHERE ' . $db->sql_in_set('post_msg_id', $attach_list) . '
						AND in_message = 0
					ORDER BY filetime DESC, post_msg_id ASC';
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$attachments[$row['post_msg_id']][] = $row;
				}
				$db->sql_freeresult($result);

				// No attachments exist, but post table thinks they do so go ahead and reset post_attach flags
				if (!sizeof($attachments))
				{
					$sql = 'UPDATE ' . POSTS_TABLE . '
						SET post_attachment = 0
						WHERE ' . $db->sql_in_set('post_id', $attach_list);
					$db->sql_query($sql);

					// We need to update the topic indicator too if the complete topic is now without an attachment
					if (sizeof($rowset) != $total_posts)
					{
						// Not all posts are displayed so we query the db to find if there's any attachment for this topic
						$sql = 'SELECT a.post_msg_id as post_id
							FROM ' . ATTACHMENTS_TABLE . ' a, ' . POSTS_TABLE . " p
							WHERE p.topic_id = $topic_id
								AND p.post_approved = 1
								AND p.topic_id = a.topic_id";
						$result = $db->sql_query_limit($sql, 1);
						$row = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);

						if (!$row)
						{
							$sql = 'UPDATE ' . TOPICS_TABLE . "
								SET topic_attachment = 0
								WHERE topic_id = $topic_id";
							$db->sql_query($sql);
						}
					}
					else
					{
						$sql = 'UPDATE ' . TOPICS_TABLE . "
							SET topic_attachment = 0
							WHERE topic_id = $topic_id";
						$db->sql_query($sql);
					}
				}
				else if ($has_attachments && !$topic_data['topic_attachment'])
				{
					// Topic has approved attachments but its flag is wrong
					$sql = 'UPDATE ' . TOPICS_TABLE . "
						SET topic_attachment = 1
						WHERE topic_id = $topic_id";
					$db->sql_query($sql);

					$topic_data['topic_attachment'] = 1;
				}
			}
			else
			{
				$display_notice = true;
			}
		}

		// Instantiate BBCode if need be
		if ($bbcode_bitfield !== '')
		{
			$bbcode = new bbcode(base64_encode($bbcode_bitfield));
		}

		$i_total = sizeof($rowset) - 1;
		$prev_post_id = '';

		$template->assign_vars(array(
			'S_NUM_POSTS' => sizeof($post_list))
		);

		// Output the posts
		$first_unread = $post_unread = false;
		for ($i = 0, $end = sizeof($post_list); $i < $end; ++$i)
		{
			// A non-existing rowset only happens if there was no user present for the entered poster_id
			// This could be a broken posts table.
			if (!isset($rowset[$post_list[$i]]))
			{
				continue;
			}

			$row =& $rowset[$post_list[$i]];
			$poster_id = $row['user_id'];

			// End signature parsing, only if needed
			if ($user_cache[$poster_id]['sig'] && $row['enable_sig'] && empty($user_cache[$poster_id]['sig_parsed']))
			{
				$user_cache[$poster_id]['sig'] = censor_text($user_cache[$poster_id]['sig']);

				if ($user_cache[$poster_id]['sig_bbcode_bitfield'])
				{
					$bbcode->bbcode_second_pass($user_cache[$poster_id]['sig'], $user_cache[$poster_id]['sig_bbcode_uid'], $user_cache[$poster_id]['sig_bbcode_bitfield']);
				}

				$user_cache[$poster_id]['sig'] = bbcode_nl2br($user_cache[$poster_id]['sig']);
				$user_cache[$poster_id]['sig'] = smiley_text($user_cache[$poster_id]['sig']);
				$user_cache[$poster_id]['sig_parsed'] = true;
			}

			// Parse the message and subject
			$message = censor_text($row['post_text']);

			// Second parse bbcode here
			if (false && $row['bbcode_bitfield'])// @TODO fix bbcode.
			{
				$bbcode->bbcode_second_pass($message, $row['bbcode_uid'], $row['bbcode_bitfield']);
			}

			$message = bbcode_nl2br($message);
			$message = smiley_text($message);

			if (!empty($attachments[$row['post_id']]))
			{
				parse_attachments($forum_id, $message, $attachments[$row['post_id']], $update_count);
			}

			// Replace naughty words such as farty pants
//			$row['post_subject'] = censor_text($row['post_subject']); // @TODO fix me.

			// Highlight active words (primarily for search)
			if ($highlight_match)
			{
				$message = preg_replace('#(?!<.*)(?<!\w)(' . $highlight_match . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">\1</span>', $message);
				$row['post_subject'] = preg_replace('#(?!<.*)(?<!\w)(' . $highlight_match . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">\1</span>', $row['post_subject']);
			}

			// Editing information
/*			if (($row['post_edit_count'] && $config['display_last_edited']) || $row['post_edit_reason'])
			{
				// Get usernames for all following posts if not already stored
				if (!sizeof($post_edit_list) && ($row['post_edit_reason'] || ($row['post_edit_user'] && !isset($user_cache[$row['post_edit_user']]))))
				{
					// Remove all post_ids already parsed (we do not have to check them)
					$post_storage_list = (!$store_reverse) ? array_slice($post_list, $i) : array_slice(array_reverse($post_list), $i);

					$sql = 'SELECT DISTINCT u.user_id, u.username, u.user_colour
						FROM ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
						WHERE ' . $db->sql_in_set('p.post_id', $post_storage_list) . '
							AND p.post_edit_count <> 0
							AND p.post_edit_user <> 0
							AND p.post_edit_user = u.user_id';
					$result2 = $db->sql_query($sql);
					while ($user_edit_row = $db->sql_fetchrow($result2))
					{
						$post_edit_list[$user_edit_row['user_id']] = $user_edit_row;
					}
					$db->sql_freeresult($result2);

					unset($post_storage_list);
				}

				$l_edit_time_total = ($row['post_edit_count'] == 1) ? $user->lang['EDITED_TIME_TOTAL'] : $user->lang['EDITED_TIMES_TOTAL'];

				if ($row['post_edit_reason'])
				{
					// User having edited the post also being the post author?
					if (!$row['post_edit_user'] || $row['post_edit_user'] == $poster_id)
					{
						$display_username = get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']);
					}
					else
					{
						$display_username = get_username_string('full', $row['post_edit_user'], $post_edit_list[$row['post_edit_user']]['username'], $post_edit_list[$row['post_edit_user']]['user_colour']);
					}

					$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['post_edit_time'], false, true), $row['post_edit_count']);
				}
				else
				{
					if ($row['post_edit_user'] && !isset($user_cache[$row['post_edit_user']]))
					{
						$user_cache[$row['post_edit_user']] = $post_edit_list[$row['post_edit_user']];
					}

					// User having edited the post also being the post author?
					if (!$row['post_edit_user'] || $row['post_edit_user'] == $poster_id)
					{
						$display_username = get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']);
					}
					else
					{
						$display_username = get_username_string('full', $row['post_edit_user'], $user_cache[$row['post_edit_user']]['username'], $user_cache[$row['post_edit_user']]['user_colour']);
					}

					$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['post_edit_time'], false, true), $row['post_edit_count']);
				}
			}
			else
			{
				$l_edited_by = '';
			}
*/
			$cp_row = array();

			//
			if ($config['load_cpf_viewtopic'])
			{
				$cp_row = (isset($profile_fields_cache[$poster_id])) ? $cp->generate_profile_fields_template('show', false, $profile_fields_cache[$poster_id]) : array();
			}
			
/*			
			$post_unread = (isset($topic_tracking_info[$topic_id]) && $row['post_time'] > $topic_tracking_info[$topic_id]) ? true : false;

			$s_first_unread = false;
			if (!$first_unread && $post_unread)
			{
				$s_first_unread = $first_unread = true;
			}
*/
			$edit_allowed = ($user->data['is_registered'] && ($auth->acl_get('m_edit') || (
				$user->data['user_id'] == $poster_id &&
				$auth->acl_get('f_edit') &&
				!$row['post_edit_locked'] &&
				($row['post_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
			)));

			$delete_allowed = ($user->data['is_registered'] && ($auth->acl_get('m_delete') || (
				$user->data['user_id'] == $poster_id &&
				$auth->acl_get('f_delete') &&
				$topic_data['topic_last_post_id'] == $row['post_id'] &&
				($row['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
				// we do not want to allow removal of the last post if a moderator locked it!
				!$row['post_edit_locked']
			)));

			//
			$postrow = array(
				'POST_AUTHOR_FULL'		=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_full'] : get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'POST_AUTHOR_COLOUR'	=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_colour'] : get_username_string('colour', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'POST_AUTHOR'			=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_username'] : get_username_string('username', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'U_POST_AUTHOR'			=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_profile'] : get_username_string('profile', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),

				'RANK_TITLE'		=> $user_cache[$poster_id]['rank_title'],
				'RANK_IMG'			=> $user_cache[$poster_id]['rank_image'],
				'RANK_IMG_SRC'		=> $user_cache[$poster_id]['rank_image_src'],
				'POSTER_JOINED'		=> $user_cache[$poster_id]['joined'],
				'POSTER_POSTS'		=> $user_cache[$poster_id]['posts'],
				'POSTER_FROM'		=> $user_cache[$poster_id]['from'],
				'POSTER_AVATAR'		=> $user_cache[$poster_id]['avatar'],
				'POSTER_WARNINGS'	=> $user_cache[$poster_id]['warnings'],
				'POSTER_AGE'		=> $user_cache[$poster_id]['age'],

				'POST_DATE'			=> $user->format_date($row['post_time'], false, ($view == 'print') ? true : false),
//				'POST_SUBJECT'		=> $row['post_subject'], // fix me.
				'MESSAGE'			=> $message,
//				'SIGNATURE'			=> ($row['enable_sig']) ? $user_cache[$poster_id]['sig'] : '',
//				'EDITED_MESSAGE'	=> $l_edited_by,
//				'EDIT_REASON'		=> $row['post_edit_reason'],

				'MINI_POST_IMG'			=> ($post_unread) ? $user->img('icon_post_target_unread', 'NEW_POST') : $user->img('icon_post_target', 'POST'),
//				'POST_ICON_IMG'			=> ($gb_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['img'] : '',
//				'POST_ICON_IMG_WIDTH'	=> ($gb_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['width'] : '',
//				'POST_ICON_IMG_HEIGHT'	=> ($gb_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['height'] : '',
				'ICQ_STATUS_IMG'		=> $user_cache[$poster_id]['icq_status_img'],
				'ONLINE_IMG'			=> ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? '' : (($user_cache[$poster_id]['online']) ? $user->img('icon_user_online', 'ONLINE') : $user->img('icon_user_offline', 'OFFLINE')),
				'S_ONLINE'				=> ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? false : (($user_cache[$poster_id]['online']) ? true : false),

//				'U_EDIT'			=> ($edit_allowed) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=edit&amp;f=$forum_id&amp;p={$row['post_id']}") : '',
//				'U_QUOTE'			=> ($auth->acl_get('f_reply', $forum_id)) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=quote&amp;f=$forum_id&amp;p={$row['post_id']}") : '',
//				'U_INFO'			=> ($auth->acl_get('m_info', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "i=main&amp;mode=post_details&amp;f=$forum_id&amp;p=" . $row['post_id'], true, $user->session_id) : '',
//				'U_DELETE'			=> ($delete_allowed) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=delete&amp;f=$forum_id&amp;p={$row['post_id']}") : '',

				'U_PROFILE'		=> $user_cache[$poster_id]['profile'],
				'U_SEARCH'		=> $user_cache[$poster_id]['search'],
				'U_PM'			=> ($poster_id != ANONYMOUS && $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($user_cache[$poster_id]['allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'))) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;action=quotepost&amp;p=' . $row['post_id']) : '',
				'U_EMAIL'		=> $user_cache[$poster_id]['email'],
				'U_WWW'			=> $user_cache[$poster_id]['www'],
				'U_ICQ'			=> $user_cache[$poster_id]['icq'],
				'U_AIM'			=> $user_cache[$poster_id]['aim'],
				'U_MSN'			=> $user_cache[$poster_id]['msn'],
				'U_YIM'			=> $user_cache[$poster_id]['yim'],
				'U_JABBER'		=> $user_cache[$poster_id]['jabber'],

///				'U_REPORT'			=> ($auth->acl_get('f_report', $forum_id)) ? append_sid("{$phpbb_root_path}report.$phpEx", 'f=' . $forum_id . '&amp;p=' . $row['post_id']) : '',
//				'U_MCP_REPORT'		=> ($auth->acl_get('m_report', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=reports&amp;mode=report_details&amp;f=' . $forum_id . '&amp;p=' . $row['post_id'], true, $user->session_id) : '',
//				'U_MCP_APPROVE'		=> ($auth->acl_get('m_approve', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=approve_details&amp;f=' . $forum_id . '&amp;p=' . $row['post_id'], true, $user->session_id) : '',
//				'U_MINI_POST'		=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $row['post_id']) . (($gb_data['topic_type'] == POST_GLOBAL) ? '&amp;f=' . $forum_id : '') . '#p' . $row['post_id'],
				'U_NEXT_POST_ID'	=> ($i < $i_total && isset($rowset[$post_list[$i + 1]])) ? $rowset[$post_list[$i + 1]]['post_id'] : '',
				'U_PREV_POST_ID'	=> $prev_post_id,
				'U_NOTES'			=> ($auth->acl_getf_global('m_')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=notes&amp;mode=user_notes&amp;u=' . $poster_id, true, $user->session_id) : '',
				'U_WARN'			=> ($auth->acl_get('m_warn') && $poster_id != $user->data['user_id'] && $poster_id != ANONYMOUS) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=warn&amp;mode=warn_post&amp;f=' . $forum_id . '&amp;p=' . $row['post_id'], true, $user->session_id) : '',

				'POST_ID'			=> $row['post_id'],
				'POSTER_ID'			=> $poster_id,

				'S_HAS_ATTACHMENTS'	=> (!empty($attachments[$row['post_id']])) ? true : false,
				'S_POST_UNAPPROVED'	=> false,
//				'S_POST_REPORTED'	=> ($row['post_reported'] && $auth->acl_get('m_report', $forum_id)) ? true : false,
				'S_DISPLAY_NOTICE'	=> $display_notice && $row['post_attachment'],
				'S_FRIEND'			=> ($row['friend']) ? true : false,
				'S_UNREAD_POST'		=> $post_unread,
//				'S_FIRST_UNREAD'	=> $s_first_unread,
				'S_CUSTOM_FIELDS'	=> (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
//				'S_TOPIC_POSTER'	=> ($gb_data['topic_poster'] == $poster_id) ? true : false,

				'S_IGNORE_POST'		=> ($row['hide_post']) ? true : false,
				'L_IGNORE_POST'		=> ($row['hide_post']) ? sprintf($user->lang['POST_BY_FOE'], get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']), '<a href="' . $viewtopic_url . "&amp;p={$row['post_id']}&amp;view=show#p{$row['post_id']}" . '">', '</a>') : '',
			);

			if (isset($cp_row['row']) && sizeof($cp_row['row']))
			{
				$postrow = array_merge($postrow, $cp_row['row']);
			}

			// Dump vars into template
			$template->assign_block_vars('postrow', $postrow);

			if (!empty($cp_row['blockrow']))
			{
				foreach ($cp_row['blockrow'] as $field_data)
				{
					$template->assign_block_vars('postrow.custom_fields', $field_data);
				}
			}

			// Display not already displayed Attachments for this post, we already parsed them. ;)
			if (!empty($attachments[$row['post_id']]))
			{
				foreach ($attachments[$row['post_id']] as $attachment)
				{
					$template->assign_block_vars('postrow.attachment', array(
						'DISPLAY_ATTACHMENT'	=> $attachment)
					);
				}
			}

			$prev_post_id = $row['post_id'];

			unset($rowset[$post_list[$i]]);
			unset($attachments[$row['post_id']]);
		}
		unset($rowset, $user_cache);

		// Update topic view and if necessary attachment view counters ... but only for humans and if this is the first 'page view'
/*		if (isset($user->data['session_page']) && !$user->data['is_bot'] && (strpos($user->data['session_page'], '&t=' . $topic_id) === false || isset($user->data['session_created'])))
		{
			$sql = 'UPDATE ' . TOPICS_TABLE . '
				SET topic_views = topic_views + 1, topic_last_view_time = ' . time() . "
				WHERE topic_id = $topic_id";
			$db->sql_query($sql);

			// Update the attachment download counts
			if (sizeof($update_count))
			{
				$sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
					SET download_count = download_count + 1
					WHERE ' . $db->sql_in_set('attach_id', array_unique($update_count));
				$db->sql_query($sql);
			}
		}*/


		// Only mark topic if it's currently unread. Also make sure we do not set topic tracking back if earlier pages are viewed.
/*		if (isset($topic_tracking_info[$topic_id]) && $topic_data['topic_last_post_time'] > $topic_tracking_info[$topic_id] && $max_post_time > $topic_tracking_info[$topic_id])
		{
			markread('topic', (($topic_data['topic_type'] == POST_GLOBAL) ? 0 : $forum_id), $topic_id, $max_post_time);

			// Update forum info
			$all_marked_read = update_forum_tracking_info((($topic_data['topic_type'] == POST_GLOBAL) ? 0 : $forum_id), $topic_data['forum_last_post_time'], (isset($topic_data['forum_mark_time'])) ? $topic_data['forum_mark_time'] : false, false);
		}
		else
		{
			$all_marked_read = true;
		}

		// If there are absolutely no more unread posts in this forum and unread posts shown, we can savely show the #unread link
		if ($all_marked_read)
		{
			if ($post_unread)
			{
				$template->assign_vars(array(
					'U_VIEW_UNREAD_POST'	=> '#unread',
				));
			}
			else if (isset($topic_tracking_info[$topic_id]) && $topic_data['topic_last_post_time'] > $topic_tracking_info[$topic_id])
			{
				$template->assign_vars(array(
					'U_VIEW_UNREAD_POST'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;view=unread") . '#unread',
				));
			}
		}
		else if (!$all_marked_read)
		{
			$last_page = ((floor($start / $config['posts_per_page']) + 1) == max(ceil($total_posts / $config['posts_per_page']), 1)) ? true : false;

			// What can happen is that we are at the last displayed page. If so, we also display the #unread link based in $post_unread
			if ($last_page && $post_unread)
			{
				$template->assign_vars(array(
					'U_VIEW_UNREAD_POST'	=> '#unread',
				));
			}
			else if (!$last_page)
			{
				$template->assign_vars(array(
					'U_VIEW_UNREAD_POST'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;view=unread") . '#unread',
				));
			}
		}
*/
		// let's set up quick_reply
		$s_quick_reply = false;
		if ($user->data['is_registered'] && $config['allow_quick_reply'] /*&& ($topic_data['forum_flags'] & FORUM_FLAG_QUICK_REPLY)*/ /* @TODO PERMISSION@ &&$auth->acl_get('f_reply', $forum_id)*/)
		{
			// Quick reply enabled forum
//			$s_quick_reply = (($topic_data['forum_status'] == ITEM_UNLOCKED && $topic_data['topic_status'] == ITEM_UNLOCKED) || $auth->acl_get('m_edit', $forum_id)) ? true : false;
			$s_quick_reply = true;//enable just for now.
		}

		if ($s_quick_reply)
		{
			add_form_key('posting');

			$s_attach_sig	= $config['allow_sig'] && $user->optionget('attachsig') && $auth->acl_get('f_sigs') && $auth->acl_get('u_sig');
			$s_smilies		= $config['allow_smilies'] && $user->optionget('smilies') && $auth->acl_get('f_smilies');
			$s_bbcode		= $config['allow_bbcode'] && $user->optionget('bbcode') && $auth->acl_get('f_bbcode');
			$s_notify		= $config['allow_topic_notify'] && ($user->data['user_notify'] );

			$qr_hidden_fields = array(
				'topic_cur_post_id'		=> (int) $this->member['user_guestbook_last_post_id'],
				'lastclick'				=> (int) time(),
				//'topic_id'				=> (int) $topic_data['topic_id'],
				//'forum_id'				=> (int) $forum_id,
			);

			// Originally we use checkboxes and check with isset(), so we only provide them if they would be checked
			(!$s_bbcode)					? $qr_hidden_fields['disable_bbcode'] = 1		: true;
			(!$s_smilies)					? $qr_hidden_fields['disable_smilies'] = 1		: true;
			(!$config['allow_post_links'])	? $qr_hidden_fields['disable_magic_url'] = 1	: true;
			($s_attach_sig)					? $qr_hidden_fields['attach_sig'] = 1			: true;
			($s_notify)						? $qr_hidden_fields['notify'] = 1				: true;
//				($gb_data['topic_status'] == ITEM_LOCKED) ? $qr_hidden_fields['lock_topic'] = 1 : true;

			$template->assign_vars(array(
				'S_QUICK_REPLY'			=> true,
//					'U_QR_ACTION'			=> append_sid("{$phpbb_root_path}posting.$phpEx", "mode=reply&amp;f=$forum_id&amp;t=$topic_id"),
				'QR_HIDDEN_FIELDS'		=> build_hidden_fields($qr_hidden_fields),
				'SUBJECT'				=> 'Re: ' . censor_text($gb_data['post_subject']),
			));
		}
		// now I have the urge to wash my hands :(
	}
	
	private function post()
	{
		global $phpbb_root_path, $phpEx, $template, $db, $auth;
		global $config, $user;
		
		if (!function_exists('generate_smilies'))
		{
			include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
		}
		
		if (!function_exists('submit_gb_post'))
		{
			include($phpbb_root_path . 'includes/functions_guestbook.' . $phpEx);
		}

		include($phpbb_root_path . 'includes/message_parser.' . $phpEx);


		$user->add_lang('posting');
		// Grab only parameters needed here
		$post_id	= request_var('p', 0);
		$lastclick	= request_var('lastclick', 0);

		$submit		= (isset($_POST['post'])) ? true : false;
		$preview	= (isset($_POST['preview'])) ? true : false;
		$delete		= (isset($_POST['delete'])) ? true : false;

		$refresh	= (isset($_POST['add_file']) || isset($_POST['delete_file']) || isset($_POST['full_editor'])) ? true : false;
		$mode		= ($delete && !$preview && !$refresh && $submit) ? 'delete' : request_var('gbmode', '');

		$error = $post_data = array();
		$current_time = time();

		// Was cancel pressed? If so then redirect to the appropriate page
		if (/*$cancel || */($current_time - $lastclick < 2 && $submit))
		{
		
			$redirect = append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u={$this->user_id}&amp;gbmode=display&amp;{$post_id}#p{$post_id}");
			redirect($redirect);
		}


		// We need to know some basic information in all cases before we do anything.
		switch ($mode)
		{
			case 'quote':
			case 'edit':
			case 'delete':
				if (!$post_id)
				{
					$user->setup('posting');
					trigger_error('NO_POST');
				}
				/*$forum_id = (!$f_id) ? $forum_id : $f_id;

				$sql = 'SELECT f.*, t.*, p.*, u.username, u.username_clean, u.user_sig, u.user_sig_bbcode_uid, u.user_sig_bbcode_bitfield
					FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f, ' . USERS_TABLE . " u
					WHERE p.post_id = $post_id
						AND t.topic_id = p.topic_id
						AND u.user_id = p.poster_id
						AND (f.forum_id = t.forum_id
							OR f.forum_id = $forum_id)" .
						(($auth->acl_get('m_approve', $forum_id)) ? '' : 'AND p.post_approved = 1');*/
			break;

			case 'smilies':
				$sql = '';
				generate_smilies('window', $forum_id);
			break;

			case 'popup':
				upload_popup();

			break;

			default:
				$sql = '';
			break;
		}

		if ($sql)
		{

			$result = $db->sql_query($sql);
			$post_data = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$post_data)
			{
				$user->setup('posting');
				trigger_error('NO_POST');
			}
		}
		// Not able to reply to unapproved posts/topics
		// TODO: add more descriptive language key
		// @TODO unnub permission
/*		if (false || $auth->acl_get('m_approve') && ((($mode == 'reply' || $mode == 'bump') && !$post_data['topic_approved']) || ($mode == 'quote' && !$post_data['post_approved'])))
		{
			trigger_error(($mode == 'reply' || $mode == 'bump') ? 'TOPIC_UNAPPROVED' : 'POST_UNAPPROVED');
		}
*/
		if ($mode == 'popup')
		{
			upload_popup($post_data['forum_style']);
			return;
		}


		if ($config['enable_post_confirm'] && !$user->data['is_registered'])
		{
			include($phpbb_root_path . 'includes/captcha/captcha_factory.' . $phpEx);
			$captcha =& phpbb_captcha_factory::get_instance($config['captcha_plugin']);
			$captcha->init(CONFIRM_POST);
		}

		// Use post_row values in favor of submitted ones...
		$post_id	= (!empty($post_data['post_id'])) ? (int) $post_data['post_id'] : (int) $post_id;

		// Check permissions
		if ($user->data['is_bot'])
		{
			redirect(append_sid("{$phpbb_root_path}index.$phpEx"));
		}

		// Is the user able to read within this forum?
		if (false && !$auth->acl_get('f_read', $forum_id))// @TODO auth.
		{
			if ($user->data['user_id'] != ANONYMOUS)
			{
				trigger_error('USER_CANNOT_READ');
			}

			login_box('', $user->lang['LOGIN_EXPLAIN_POST']);
		}

		// Permission to do the action asked?
		$is_authed = false;
		
		$is_authed = true;//@TODO auth
/*
		switch ($mode)
		{
			case 'post':
				if ($auth->acl_get('f_post', $forum_id))
				{
					$is_authed = true;
				}
			break;

			case 'bump':
				if ($auth->acl_get('f_bump', $forum_id))
				{
					$is_authed = true;
				}
			break;

			case 'quote':

				$post_data['post_edit_locked'] = 0;

			// no break;

			case 'reply':
				if ($auth->acl_get('f_reply', $forum_id))
				{
					$is_authed = true;
				}
			break;

			case 'edit':
				if ($user->data['is_registered'] && $auth->acl_gets('f_edit', 'm_edit', $forum_id))
				{
					$is_authed = true;
				}
			break;

			case 'delete':
				if ($user->data['is_registered'] && $auth->acl_gets('f_delete', 'm_delete', $forum_id))
				{
					$is_authed = true;
				}
			break;
		}
*/
		if (!$is_authed)
		{
			$check_auth = ($mode == 'quote') ? 'reply' : $mode;

			if ($user->data['is_registered'])
			{
				trigger_error('USER_CANNOT_' . strtoupper($check_auth));
			}

			login_box('', $user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)]);
		}

		// Can we edit this post ... if we're a moderator with rights then always yes
		// else it depends on editing times, lock status and if we're the correct user
		if ($mode == 'edit' && !$auth->acl_get('m_edit', $forum_id))
		{
			if ($user->data['user_id'] != $post_data['poster_id'])
			{
				trigger_error('USER_CANNOT_EDIT');
			}

			if (!($post_data['post_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time']))
			{
				trigger_error('CANNOT_EDIT_TIME');
			}

			if ($post_data['post_edit_locked'])
			{
				trigger_error('CANNOT_EDIT_POST_LOCKED');
			}
		}

		// Handle delete mode...
		if ($mode == 'delete')
		{
			handle_gb_post_delete($forum_id, $topic_id, $post_id, $post_data);
			return;
		}



		// Subject length limiting to 60 characters if first post...
		if ($mode == 'post' || ($mode == 'edit' && $post_data['topic_first_post_id'] == $post_data['post_id']))
		{
			$template->assign_var('S_NEW_MESSAGE', true);
		}

		// Determine some vars
		if (isset($post_data['poster_id']) && $post_data['poster_id'] == ANONYMOUS)
		{
			$post_data['quote_username'] = (!empty($post_data['post_username'])) ? $post_data['post_username'] : $user->lang['GUEST'];
		}
		else
		{
			$post_data['quote_username'] = isset($post_data['username']) ? $post_data['username'] : '';
		}

		$post_data['post_edit_locked']	= (isset($post_data['post_edit_locked'])) ? (int) $post_data['post_edit_locked'] : 0;
		$post_data['post_subject_md5']	= (isset($post_data['post_subject']) && $mode == 'edit') ? md5($post_data['post_subject']) : '';
		$post_data['post_subject']		= (in_array($mode, array('quote', 'edit'))) ? $post_data['post_subject'] : ((isset($post_data['topic_title'])) ? $post_data['topic_title'] : '');
		$post_data['topic_time_limit']	= (isset($post_data['topic_time_limit'])) ? (($post_data['topic_time_limit']) ? (int) $post_data['topic_time_limit'] / 86400 : (int) $post_data['topic_time_limit']) : 0;
		$post_data['poll_length']		= (!empty($post_data['poll_length'])) ? (int) $post_data['poll_length'] / 86400 : 0;
		$post_data['poll_start']		= (!empty($post_data['poll_start'])) ? (int) $post_data['poll_start'] : 0;
		$post_data['icon_id']			= (!isset($post_data['icon_id']) || in_array($mode, array('quote', 'reply'))) ? 0 : (int) $post_data['icon_id'];
		$post_data['poll_options']		= array();

		// Get Poll Data
		if ($post_data['poll_start'])
		{
			$sql = 'SELECT poll_option_text
				FROM ' . POLL_OPTIONS_TABLE . "
				WHERE topic_id = $topic_id
				ORDER BY poll_option_id";
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$post_data['poll_options'][] = trim($row['poll_option_text']);
			}
			$db->sql_freeresult($result);
		}

		$orig_poll_options_size = sizeof($post_data['poll_options']);

		$message_parser = new parse_message();

		if (isset($post_data['post_text']))
		{
			$message_parser->message = &$post_data['post_text'];
			unset($post_data['post_text']);
		}

		// Set some default variables
		$uninit = array('post_attachment' => 0, 'poster_id' => $user->data['user_id'], 'enable_magic_url' => 0, 'topic_status' => 0, 'topic_type' => POST_NORMAL, 'post_subject' => '', 'topic_title' => '', 'post_time' => 0, 'post_edit_reason' => '', 'notify_set' => 0);

		foreach ($uninit as $var_name => $default_value)
		{
			if (!isset($post_data[$var_name]))
			{
				$post_data[$var_name] = $default_value;
			}
		}
		unset($uninit);


		if ($post_data['poster_id'] == ANONYMOUS)
		{
			$post_data['username'] = ($mode == 'quote' || $mode == 'edit') ? trim($post_data['post_username']) : '';
		}
		else
		{
			$post_data['username'] = ($mode == 'quote' || $mode == 'edit') ? trim($post_data['username']) : '';
		}

		$post_data['enable_urls'] = $post_data['enable_magic_url'];

		if ($mode != 'edit')
		{
			$post_data['enable_sig']		= ($config['allow_sig'] && $user->optionget('attachsig')) ? true: false;
			$post_data['enable_smilies']	= ($config['allow_smilies'] && $user->optionget('smilies')) ? true : false;
			$post_data['enable_bbcode']		= ($config['allow_bbcode'] && $user->optionget('bbcode')) ? true : false;
			$post_data['enable_urls']		= true;
		}

		$post_data['enable_magic_url'] = $post_data['drafts'] = false;

		$check_value = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);


		// Do we want to edit our post ?
		if ($mode == 'edit' && $post_data['bbcode_uid'])
		{
			$message_parser->bbcode_uid = $post_data['bbcode_uid'];
		}

		// HTML, BBCode, Smilies, Images and Flash status
		// @TODO auth
		$bbcode_status	= ($config['allow_bbcode'] && $auth->acl_get('f_bbcode')) ? true : false;
		$smilies_status	= ($config['allow_smilies'] && $auth->acl_get('f_smilies')) ? true : false;
		$img_status		= ($bbcode_status && $auth->acl_get('f_img')) ? true : false;
		$url_status		= ($config['allow_post_links']) ? true : false;
		$flash_status	= ($bbcode_status && $auth->acl_get('f_flash') && $config['allow_post_flash']) ? true : false;
		$quote_status	= true;


		if ($submit || $preview || $refresh)
		{
			$post_data['topic_cur_post_id']	= request_var('topic_cur_post_id', 0);
			$post_data['post_subject']		= utf8_normalize_nfc(request_var('subject', '', true));
			$message_parser->message		= utf8_normalize_nfc(request_var('message', '', true));

			$post_data['username']			= utf8_normalize_nfc(request_var('username', $post_data['username'], true));
			$post_data['post_edit_reason']	= (!empty($_POST['edit_reason']) && $mode == 'edit' && $auth->acl_get('m_edit', $forum_id)) ? utf8_normalize_nfc(request_var('edit_reason', '', true)) : '';

			$post_data['orig_topic_type']	= $post_data['topic_type'];
			$post_data['topic_type']		= request_var('topic_type', (($mode != 'post') ? (int) $post_data['topic_type'] : POST_NORMAL));
			$post_data['topic_time_limit']	= request_var('topic_time_limit', (($mode != 'post') ? (int) $post_data['topic_time_limit'] : 0));

			if (false && $post_data['enable_icons'] && $auth->acl_get('f_icons', $forum_id))//@TODO auth
			{
				$post_data['icon_id'] = request_var('icon', (int) $post_data['icon_id']);
			}

			$post_data['enable_bbcode']		= (!$bbcode_status || isset($_POST['disable_bbcode'])) ? false : true;
			$post_data['enable_smilies']	= (!$smilies_status || isset($_POST['disable_smilies'])) ? false : true;
			$post_data['enable_urls']		= (isset($_POST['disable_magic_url'])) ? 0 : 1;
			$post_data['enable_sig']		= (!$config['allow_sig'] || !$auth->acl_get('f_sigs') || !$auth->acl_get('u_sig')) ? false : ((isset($_POST['attach_sig']) && $user->data['is_registered']) ? true : false);// @todo auth

			if ($config['allow_topic_notify'] && $user->data['is_registered'])
			{
				$notify = (isset($_POST['notify'])) ? true : false;
			}
			else
			{
				$notify = false;
			}

			$topic_lock			= (isset($_POST['lock_topic'])) ? true : false;
			$post_lock			= (isset($_POST['lock_post'])) ? true : false;
			$poll_delete		= (isset($_POST['poll_delete'])) ? true : false;

			if ($submit)
			{
				$status_switch = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);
				$status_switch = ($status_switch != $check_value);
			}
			else
			{
				$status_switch = 1;
			}

			// If replying/quoting and last post id has changed
			// give user option to continue submit or return to post
			// notify and show user the post made between his request and the final submit
			if (($mode == 'reply' || $mode == 'quote') && $post_data['topic_cur_post_id'] && $post_data['topic_cur_post_id'] != $post_data['topic_last_post_id'])
			{
				// Only do so if it is allowed forum-wide
				if ($post_data['forum_flags'] & FORUM_FLAG_POST_REVIEW)
				{
					if (topic_review($topic_id, $forum_id, 'post_review', $post_data['topic_cur_post_id']))
					{
						$template->assign_var('S_POST_REVIEW', true);
					}

					$submit = false;
					$refresh = true;
				}
			}

			// Grab md5 'checksum' of new message
			$message_md5 = md5($message_parser->message);

			// If editing and checksum has changed we know the post was edited while we're editing
			// Notify and show user the changed post
			if ($mode == 'edit' && $post_data['forum_flags'] & FORUM_FLAG_POST_REVIEW)
			{
				$edit_post_message_checksum = request_var('edit_post_message_checksum', '');
				$edit_post_subject_checksum = request_var('edit_post_subject_checksum', '');

				// $post_data['post_checksum'] is the checksum of the post submitted in the meantime
				// $message_md5 is the checksum of the post we're about to submit
				// $edit_post_message_checksum is the checksum of the post we're editing
				// ...

				// We make sure nobody else made exactly the same change
				// we're about to submit by also checking $message_md5 != $post_data['post_checksum']
				if (($edit_post_message_checksum !== '' && $edit_post_message_checksum != $post_data['post_checksum'] && $message_md5 != $post_data['post_checksum'])
				 || ($edit_post_subject_checksum !== '' && $edit_post_subject_checksum != $post_data['post_subject_md5'] && md5($post_data['post_subject']) != $post_data['post_subject_md5']))
				{
					if (topic_review($topic_id, $forum_id, 'post_review_edit', $post_id))
					{
						$template->assign_vars(array(
							'S_POST_REVIEW'			=> true,

							'L_POST_REVIEW'			=> $user->lang['POST_REVIEW_EDIT'],
							'L_POST_REVIEW_EXPLAIN'	=> $user->lang['POST_REVIEW_EDIT_EXPLAIN'],
						));
					}

					$submit = false;
					$refresh = true;
				}
			}

			// Check checksum ... don't re-parse message if the same
			$update_message = ($mode != 'edit' || $message_md5 != $post_data['post_checksum'] || $status_switch || strlen($post_data['bbcode_uid']) < BBCODE_UID_LEN) ? true : false;

			// Also check if subject got updated...
			$update_subject = $mode != 'edit' || ($post_data['post_subject_md5'] && $post_data['post_subject_md5'] != md5($post_data['post_subject']));

			// Parse message
			if ($update_message)
			{
				if (sizeof($message_parser->warn_msg))
				{
					$error[] = implode('<br />', $message_parser->warn_msg);
					$message_parser->warn_msg = array();
				}

				$message_parser->parse($post_data['enable_bbcode'], ($config['allow_post_links']) ? $post_data['enable_urls'] : false, $post_data['enable_smilies'], $img_status, $flash_status, $quote_status, $config['allow_post_links']);

				// On a refresh we do not care about message parsing errors
				if (sizeof($message_parser->warn_msg) && $refresh)
				{
					$message_parser->warn_msg = array();
				}
			}
			else
			{
				$message_parser->bbcode_bitfield = $post_data['bbcode_bitfield'];
			}

			if ($mode != 'edit' && !$preview && !$refresh && $config['flood_interval'] && !$auth->acl_get('f_ignoreflood'))
			{
				// Flood check
				$last_post_time = 0;

				if ($user->data['is_registered'])
				{
					$last_post_time = $user->data['user_lastpost_time'];
				}
				else
				{
					$sql = 'SELECT post_time AS last_post_time
						FROM ' . POSTS_TABLE . "
						WHERE poster_ip = '" . $user->ip . "'
							AND post_time > " . ($current_time - $config['flood_interval']);
					$result = $db->sql_query_limit($sql, 1);
					if ($row = $db->sql_fetchrow($result))
					{
						$last_post_time = $row['last_post_time'];
					}
					$db->sql_freeresult($result);
				}

				if ($last_post_time && ($current_time - $last_post_time) < intval($config['flood_interval']))
				{
					$error[] = $user->lang['FLOOD_ERROR'];
				}
			}

			// Validate username
			if (($post_data['username'] && !$user->data['is_registered']) || ($mode == 'edit' && $post_data['poster_id'] == ANONYMOUS && $post_data['username'] && $post_data['post_username'] && $post_data['post_username'] != $post_data['username']))
			{
				include($phpbb_root_path . 'includes/functions_user.' . $phpEx);

				if (($result = validate_username($post_data['username'], (!empty($post_data['post_username'])) ? $post_data['post_username'] : '')) !== false)
				{
					$user->add_lang('ucp');
					$error[] = $user->lang[$result . '_USERNAME'];
				}
			}

			if ($config['enable_post_confirm'] && !$user->data['is_registered'] && in_array($mode, array('quote', 'post', 'reply')))
			{
				$captcha_data = array(
					'message'	=> utf8_normalize_nfc(request_var('message', '', true)),
					'subject'	=> utf8_normalize_nfc(request_var('subject', '', true)),
					'username'	=> utf8_normalize_nfc(request_var('username', '', true)),
				);
				$vc_response = $captcha->validate($captcha_data);
				if ($vc_response)
				{
					$error[] = $vc_response;
				}
			}

			// check form
			if (($submit || $preview) && !check_form_key('posting'))
			{
				$error[] = $user->lang['FORM_INVALID'];
			}

			// Parse subject
			if (!$preview && !$refresh && utf8_clean_string($post_data['post_subject']) === '' && ($mode == 'post' || ($mode == 'edit' && $post_data['topic_first_post_id'] == $post_id)))
			{
				$error[] = $user->lang['EMPTY_SUBJECT'];
			}

			$post_data['poll_last_vote'] = (isset($post_data['poll_last_vote'])) ? $post_data['poll_last_vote'] : 0;


			if (sizeof($message_parser->warn_msg))
			{
				$error[] = implode('<br />', $message_parser->warn_msg);
			}

			// DNSBL check
			if ($config['check_dnsbl'] && !$refresh)
			{
				if (($dnsbl = $user->check_dnsbl('post')) !== false)
				{
					$error[] = sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]);
				}
			}

			// Store message, sync counters
			if (!sizeof($error) && $submit)
			{

				if ($submit)
				{
					$data = array(
						'topic_title'			=> (empty($post_data['topic_title'])) ? $post_data['post_subject'] : $post_data['topic_title'],
						'post_id'				=> (int) $post_id,
						'icon_id'				=> (int) $post_data['icon_id'],
						'poster_id'				=> (int) $post_data['poster_id'],
						'enable_sig'			=> (bool) $post_data['enable_sig'],
						'enable_bbcode'			=> (bool) $post_data['enable_bbcode'],
						'enable_smilies'		=> (bool) $post_data['enable_smilies'],
						'enable_urls'			=> (bool) $post_data['enable_urls'],
						'message_md5'			=> (string) $message_md5,
						'post_time'				=> (isset($post_data['post_time'])) ? (int) $post_data['post_time'] : $current_time,
						'post_checksum'			=> (isset($post_data['post_checksum'])) ? (string) $post_data['post_checksum'] : '',
						'post_edit_reason'		=> $post_data['post_edit_reason'],
						'post_edit_user'		=> ($mode == 'edit') ? $user->data['user_id'] : ((isset($post_data['post_edit_user'])) ? (int) $post_data['post_edit_user'] : 0),
						'poster_ip'				=> (isset($post_data['poster_ip'])) ? $post_data['poster_ip'] : $user->ip,
						'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
						'bbcode_uid'			=> $message_parser->bbcode_uid,
						'message'				=> $message_parser->message,
					);

					if ($mode == 'edit')
					{
						$data['topic_replies_real'] = $post_data['topic_replies_real'];
						$data['topic_replies'] = $post_data['topic_replies'];
					}

					// The last parameter tells submit_post if search indexer has to be run
					submit_gb_post($mode, $post_data['post_subject'], $post_data['username'], $post_data['topic_type'], $poll, $data, $update_message, ($update_message || $update_subject) ? true : false);
					
					$redirect_url = append_sid("{$phpbb_root_path}memberlist.php{$phpEx}", "mode=viewprofile&amp;gbmode=display&amp;u={$this->user_id}");

					if ($config['enable_post_confirm'] && !$user->data['is_registered'] && (isset($captcha) && $captcha->is_solved() === true) && ($mode == 'post' || $mode == 'reply' || $mode == 'quote'))
					{
						$captcha->reset();
					}

					// Check the permissions for post approval. Moderators are not affected.
					if ((!$auth->acl_get('f_noapprove', $data['forum_id']) && !$auth->acl_get('m_approve', $data['forum_id']) && empty($data['force_approved_state'])) || (isset($data['force_approved_state']) && !$data['force_approved_state']))
					{
						meta_refresh(10, $redirect_url);
						$message = ($mode == 'edit') ? $user->lang['POST_EDITED_MOD'] : $user->lang['POST_STORED_MOD'];
						$message .= (($user->data['user_id'] == ANONYMOUS) ? '' : ' '. $user->lang['POST_APPROVAL_NOTIFY']);
					}
					else
					{
						meta_refresh(3, $redirect_url);

						$message = ($mode == 'edit') ? 'POST_EDITED' : 'POST_STORED';
						$message = $user->lang[$message] . '<br /><br />' . sprintf($user->lang['VIEW_MESSAGE'], '<a href="' . $redirect_url . '">', '</a>');
					}

					$message .= '<br /><br />' . sprintf($user->lang['RETURN_FORUM'], '<a href="' . append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $data['forum_id']) . '">', '</a>');
					trigger_error($message);
				}
			}
		}

		// Preview
		if (!sizeof($error) && $preview)
		{
			$post_data['post_time'] = ($mode == 'edit') ? $post_data['post_time'] : $current_time;

			$preview_message = $message_parser->format_display($post_data['enable_bbcode'], $post_data['enable_urls'], $post_data['enable_smilies'], false);

			$preview_signature = ($mode == 'edit') ? $post_data['user_sig'] : $user->data['user_sig'];
			$preview_signature_uid = ($mode == 'edit') ? $post_data['user_sig_bbcode_uid'] : $user->data['user_sig_bbcode_uid'];
			$preview_signature_bitfield = ($mode == 'edit') ? $post_data['user_sig_bbcode_bitfield'] : $user->data['user_sig_bbcode_bitfield'];

			// Signature
			if ($post_data['enable_sig'] && $config['allow_sig'] && $preview_signature && $auth->acl_get('f_sigs', $forum_id))
			{
				$parse_sig = new parse_message($preview_signature);
				$parse_sig->bbcode_uid = $preview_signature_uid;
				$parse_sig->bbcode_bitfield = $preview_signature_bitfield;

				// Not sure about parameters for bbcode/smilies/urls... in signatures
				$parse_sig->format_display($config['allow_sig_bbcode'], true, $config['allow_sig_smilies']);
				$preview_signature = $parse_sig->message;
				unset($parse_sig);
			}
			else
			{
				$preview_signature = '';
			}

			$preview_subject = censor_text($post_data['post_subject']);


			if (!sizeof($error))
			{
				$template->assign_vars(array(
					'PREVIEW_SUBJECT'		=> $preview_subject,
					'PREVIEW_MESSAGE'		=> $preview_message,
					'PREVIEW_SIGNATURE'		=> $preview_signature,

					'S_DISPLAY_PREVIEW'		=> true)
				);
			}
		}

		// Decode text for message display
		$post_data['bbcode_uid'] = ($mode == 'quote' && !$preview && !$refresh && !sizeof($error)) ? $post_data['bbcode_uid'] : $message_parser->bbcode_uid;
		$message_parser->decode_message($post_data['bbcode_uid']);

		if ($mode == 'quote' && !$submit && !$preview && !$refresh)
		{
			if ($config['allow_bbcode'])
			{
				$message_parser->message = '[quote=&quot;' . $post_data['quote_username'] . '&quot;]' . censor_text(trim($message_parser->message)) . "[/quote]\n";
			}
			else
			{
				$offset = 0;
				$quote_string = "&gt; ";
				$message = censor_text(trim($message_parser->message));
				// see if we are nesting. It's easily tricked but should work for one level of nesting
				if (strpos($message, "&gt;") !== false)
				{
					$offset = 10;
				}
				$message = utf8_wordwrap($message, 75 + $offset, "\n");

				$message = $quote_string . $message;
				$message = str_replace("\n", "\n" . $quote_string, $message);
				$message_parser->message =  $post_data['quote_username'] . " " . $user->lang['WROTE'] . " :\n" . $message . "\n";
			}
		}

		if (($mode == 'reply' || $mode == 'quote') && !$submit && !$preview && !$refresh)
		{
			$post_data['post_subject'] = ((strpos($post_data['post_subject'], 'Re: ') !== 0) ? 'Re: ' : '') . censor_text($post_data['post_subject']);
		}

		$attachment_data = $message_parser->attachment_data;
		$filename_data = $message_parser->filename_data;
		$post_data['post_text'] = $message_parser->message;

		// MAIN POSTING PAGE BEGINS HERE


		// Generate smiley listing
		generate_smilies('inline', 0);


		// Do show topic type selection only in first post.
		$topic_type_toggle = false;

		$s_topic_icons = false;
		if (true || $post_data['enable_icons'] && $auth->acl_get('f_icons'))////@todo fix me.
		{
			$s_topic_icons = posting_gen_topic_icons($mode, $post_data['icon_id']);
		}

		$bbcode_checked		= (isset($post_data['enable_bbcode'])) ? !$post_data['enable_bbcode'] : (($config['allow_bbcode']) ? !$user->optionget('bbcode') : 1);
		$smilies_checked	= (isset($post_data['enable_smilies'])) ? !$post_data['enable_smilies'] : (($config['allow_smilies']) ? !$user->optionget('smilies') : 1);
		$urls_checked		= (isset($post_data['enable_urls'])) ? !$post_data['enable_urls'] : 0;
		$sig_checked		= $post_data['enable_sig'];
		$lock_topic_checked	= (isset($topic_lock) && $topic_lock) ? $topic_lock : (($post_data['topic_status'] == ITEM_LOCKED) ? 1 : 0);
		$lock_post_checked	= (isset($post_lock)) ? $post_lock : $post_data['post_edit_locked'];

		// If the user is replying or posting and not already watching this topic but set to always being notified we need to overwrite this setting
		$notify_set			= ($mode != 'edit' && $config['allow_topic_notify'] && $user->data['is_registered'] && !$post_data['notify_set']) ? $user->data['user_notify'] : $post_data['notify_set'];
		$notify_checked		= (isset($notify)) ? $notify : (($mode == 'post') ? $user->data['user_notify'] : $notify_set);

		// Page title & action URL, include session_id for security purpose
		$s_action = append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u={$this->user_id}&amp;gbmode=$mode", true, $user->session_id);
		$s_action .= ($post_id) ? "&amp;p=$post_id" : '';

		switch ($mode)
		{
			case 'post':
				$page_title = $user->lang['POST_TOPIC'];
			break;

			case 'quote':
			case 'reply':
				$page_title = $user->lang['POST_REPLY'];
			break;

			case 'delete':
			case 'edit':
				$page_title = $user->lang['EDIT_POST'];
			break;
		}

		// Posting uses is_solved for legacy reasons. Plugins have to use is_solved to force themselves to be displayed.
		if ($config['enable_post_confirm'] && !$user->data['is_registered'] && (isset($captcha) && $captcha->is_solved() === false) && ($mode == 'post' || $mode == 'reply' || $mode == 'quote'))
		{

			$template->assign_vars(array(
				'S_CONFIRM_CODE'			=> true,
				'CAPTCHA_TEMPLATE'			=> $captcha->get_template(),
			));
		}

		$s_hidden_fields = ($mode == 'reply' || $mode == 'quote') ? '<input type="hidden" name="topic_cur_post_id" value="' . $post_data['topic_last_post_id'] . '" />' : '';
		$s_hidden_fields .= '<input type="hidden" name="lastclick" value="' . $current_time . '" />';

		if ($mode == 'edit')
		{
			$s_hidden_fields .= build_hidden_fields(array(
				'edit_post_message_checksum'	=> $post_data['post_checksum'],
				'edit_post_subject_checksum'	=> $post_data['post_subject_md5'],
			));
		}

		// Add the confirm id/code pair to the hidden fields, else an error is displayed on next submit/preview
		if (isset($captcha) && $captcha->is_solved() !== false)
		{
			$s_hidden_fields .= build_hidden_fields($captcha->get_hidden_fields());
		}

		$form_enctype = (@ini_get('file_uploads') == '0' || strtolower(@ini_get('file_uploads')) == 'off' || !$config['allow_attachments'] || !$auth->acl_get('u_attach') || !$auth->acl_get('f_attach', $forum_id)) ? '' : ' enctype="multipart/form-data"';
		add_form_key('posting');


		// Start assigning vars for main posting page ...
		$template->assign_vars(array(
			'L_POST_A'					=> $page_title,
			'L_ICON'					=> ($mode == 'reply' || $mode == 'quote' || ($mode == 'edit' && $post_id != $post_data['topic_first_post_id'])) ? $user->lang['POST_ICON'] : $user->lang['TOPIC_ICON'],
			'L_MESSAGE_BODY_EXPLAIN'	=> (intval($config['max_post_chars'])) ? sprintf($user->lang['MESSAGE_BODY_EXPLAIN'], intval($config['max_post_chars'])) : '',

			'TOPIC_TITLE'			=> censor_text($post_data['topic_title']),
			'USERNAME'				=> ((!$preview && $mode != 'quote') || $preview) ? $post_data['username'] : '',
			'SUBJECT'				=> $post_data['post_subject'],
			'MESSAGE'				=> $post_data['post_text'],
			'BBCODE_STATUS'			=> ($bbcode_status) ? sprintf($user->lang['BBCODE_IS_ON'], '<a href="' . append_sid("{$phpbb_root_path}faq.$phpEx", 'mode=bbcode') . '">', '</a>') : sprintf($user->lang['BBCODE_IS_OFF'], '<a href="' . append_sid("{$phpbb_root_path}faq.$phpEx", 'mode=bbcode') . '">', '</a>'),
			'IMG_STATUS'			=> ($img_status) ? $user->lang['IMAGES_ARE_ON'] : $user->lang['IMAGES_ARE_OFF'],
			'FLASH_STATUS'			=> ($flash_status) ? $user->lang['FLASH_IS_ON'] : $user->lang['FLASH_IS_OFF'],
			'SMILIES_STATUS'		=> ($smilies_status) ? $user->lang['SMILIES_ARE_ON'] : $user->lang['SMILIES_ARE_OFF'],
			'URL_STATUS'			=> ($bbcode_status && $url_status) ? $user->lang['URL_IS_ON'] : $user->lang['URL_IS_OFF'],
			'MAX_FONT_SIZE'			=> (int) $config['max_post_font_size'],
			'MINI_POST_IMG'			=> $user->img('icon_post_target', $user->lang['POST']),
			'POST_DATE'				=> ($post_data['post_time']) ? $user->format_date($post_data['post_time']) : '',
			'ERROR'					=> (sizeof($error)) ? implode('<br />', $error) : '',
			'TOPIC_TIME_LIMIT'		=> (int) $post_data['topic_time_limit'],
			'EDIT_REASON'			=> $post_data['post_edit_reason'],
//			'U_VIEW_FORUM'			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", "f=$forum_id"),
//			'U_VIEW_TOPIC'			=> ($mode != 'post') ? append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id") : '',
//			'U_PROGRESS_BAR'		=> append_sid("{$phpbb_root_path}posting.$phpEx", "f=$forum_id&amp;mode=popup"),
//			'UA_PROGRESS_BAR'		=> addslashes(append_sid("{$phpbb_root_path}posting.$phpEx", "f=$forum_id&amp;mode=popup")),

			'S_PRIVMSGS'				=> false,
			'S_CLOSE_PROGRESS_WINDOW'	=> (isset($_POST['add_file'])) ? true : false,
			'S_EDIT_POST'				=> ($mode == 'edit') ? true : false,
			'S_EDIT_REASON'				=> ($mode == 'edit' && $auth->acl_get('m_edit', $forum_id)) ? true : false,
			'S_DISPLAY_USERNAME'		=> (!$user->data['is_registered'] || ($mode == 'edit' && $post_data['poster_id'] == ANONYMOUS)) ? true : false,
			'S_SHOW_TOPIC_ICONS'		=> $s_topic_icons,
			'S_DELETE_ALLOWED'			=> ($mode == 'edit' && (($post_id == $post_data['topic_last_post_id'] && $post_data['poster_id'] == $user->data['user_id'] && $auth->acl_get('f_delete', $forum_id) && !$post_data['post_edit_locked'] && ($post_data['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time'])) || $auth->acl_get('m_delete', $forum_id))) ? true : false,
			'S_BBCODE_ALLOWED'			=> $bbcode_status,
			'S_BBCODE_CHECKED'			=> ($bbcode_checked) ? ' checked="checked"' : '',
			'S_SMILIES_ALLOWED'			=> $smilies_status,
			'S_SMILIES_CHECKED'			=> ($smilies_checked) ? ' checked="checked"' : '',
			'S_SIG_ALLOWED'				=> ($auth->acl_get('f_sigs') && $config['allow_sig'] && $user->data['is_registered']) ? true : false,
			'S_SIGNATURE_CHECKED'		=> ($sig_checked) ? ' checked="checked"' : '',
			'S_NOTIFY_ALLOWED'			=> (!$user->data['is_registered'] || ($mode == 'edit' && $user->data['user_id'] != $post_data['poster_id']) || !$config['allow_topic_notify'] || !$config['email_enable']) ? false : true,
			'S_NOTIFY_CHECKED'			=> ($notify_checked) ? ' checked="checked"' : '',
			'S_LOCK_TOPIC_ALLOWED'		=> (($mode == 'edit' || $mode == 'reply' || $mode == 'quote') && ($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && !empty($post_data['topic_poster']) && $user->data['user_id'] == $post_data['topic_poster'] && $post_data['topic_status'] == ITEM_UNLOCKED))) ? true : false,
			'S_LOCK_TOPIC_CHECKED'		=> ($lock_topic_checked) ? ' checked="checked"' : '',
			'S_LOCK_POST_ALLOWED'		=> ($mode == 'edit' && $auth->acl_get('m_edit', $forum_id)) ? true : false,
			'S_LOCK_POST_CHECKED'		=> ($lock_post_checked) ? ' checked="checked"' : '',
			'S_LINKS_ALLOWED'			=> $url_status,
			'S_MAGIC_URL_CHECKED'		=> ($urls_checked) ? ' checked="checked"' : '',
			'S_TYPE_TOGGLE'				=> $topic_type_toggle,
			'S_SAVE_ALLOWED'			=> ($auth->acl_get('u_savedrafts') && $user->data['is_registered'] && $mode != 'edit') ? true : false,
			'S_HAS_DRAFTS'				=> ($auth->acl_get('u_savedrafts') && $user->data['is_registered'] && $post_data['drafts']) ? true : false,
			'S_FORM_ENCTYPE'			=> $form_enctype,

			'S_BBCODE_IMG'			=> $img_status,
			'S_BBCODE_URL'			=> $url_status,
			'S_BBCODE_FLASH'		=> $flash_status,
			'S_BBCODE_QUOTE'		=> $quote_status,

			'S_POST_ACTION'			=> $s_action,
			'S_HIDDEN_FIELDS'		=> $s_hidden_fields)
		);

		// Build custom bbcodes array
		display_custom_bbcodes();

		$template->set_filenames(array(
			'body' => 'posting_body.html')
		);

		make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));

		// Topic review
		if ($mode == 'reply' || $mode == 'quote')
		{
			if (topic_review($topic_id, $forum_id))
			{
				$template->assign_var('S_DISPLAY_REVIEW', true);
			}
		}

	}
}
?>
