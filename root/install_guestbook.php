<?php
/**
*
* @author Username (Joe Smith) joesmith@example.org
* @package umil
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
define('UMIL_AUTO', true);
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
$user->session_begin();
$auth->acl($user->data);
$user->setup();

if (!file_exists($phpbb_root_path . 'umil/umil_auto.' . $phpEx))
{
	trigger_error('Please download the latest UMIL (Unified MOD Install Library) from: <a href="http://www.phpbb.com/mods/umil/">phpBB.com/mods/umil</a>', E_USER_ERROR);
}

// The name of the mod to be displayed during installation.
$mod_name = 'PROFILE_GUESTBOOK';

/*
* The name of the config variable which will hold the currently installed version
* UMIL will handle checking, setting, and updating the version itself.
*/
$version_config_name = 'pg_version';

/*
* The language file which will be included when installing
* Language entries that should exist in the language file for UMIL (replace $mod_name with the mod's name you set to $mod_name above)
* $mod_name
* 'INSTALL_' . $mod_name
* 'INSTALL_' . $mod_name . '_CONFIRM'
* 'UPDATE_' . $mod_name
* 'UPDATE_' . $mod_name . '_CONFIRM'
* 'UNINSTALL_' . $mod_name
* 'UNINSTALL_' . $mod_name . '_CONFIRM'
*/
$language_file = 'mods/profile_guestbook';

/*
* Options to display to the user (this is purely optional, if you do not need the options you do not have to set up this variable at all)
* Uses the acp_board style of outputting information, with some extras (such as the 'default' and 'select_user' options)
*/
$options = array(
);

/*
* Optionally we may specify our own logo image to show in the upper corner instead of the default logo.
* $phpbb_root_path will get prepended to the path specified
* Image height should be 50px to prevent cut-off or stretching.
*/
//$logo_img = 'styles/prosilver/imageset/site_logo.gif';

/*
* The array of versions and actions within each.
* You do not need to order it a specific way (it will be sorted automatically), however, you must enter every version, even if no actions are done for it.
*
* You must use correct version numbering.  Unless you know exactly what you can use, only use X.X.X (replacing X with an integer).
* The version numbering must otherwise be compatible with the version_compare function - http://php.net/manual/en/function.version-compare.php
*/
$versions = array(
	'0.0.1-dev1' => array(
		'config_add'	=> array(
			array('profile_guestbook_enabled', 1),
			
		),
		'permission_add' => array(
			array('u_gb_post', 1),
			array('u_gb_edit', 1),
			array('m_gb_edit', 1),
			array('u_gb_delete', 1),
			array('m_gb_delete', 1),
			array('u_gb_view', 1),
			array('u_gb_sig', 1),
			array('u_gb_smilies', 1),
			array('u_gb_bbcode', 1),
			array('u_gb_img', 1),
			array('u_gb_flash', 1),
			array('u_gb_icons', 1),
			array('u_gb_ignoreflood', 1),		
		),
		'permission_set' => array(
			array('ROLE_USER_STANDARD', 
				array(
					'u_gb_post',
					'u_gb_edit',
					'u_gb_delete',
					'u_gb_view',
					'u_gb_sig',
					'u_gb_smilies',
					'u_gb_bbcode',
					'u_gb_img',
					'u_gb_flash',
					'u_gb_icons',
				),
			),
			array('ROLE_USER_LIMITED',
				array(
					'u_gb_view',
				),					
			),
			array('ROLE_USER_FULL',
				array(
					'u_gb_post',
					'u_gb_edit',
					'u_gb_delete',
					'u_gb_view',
					'u_gb_sig',
					'u_gb_smilies',
					'u_gb_bbcode',
					'u_gb_img',
					'u_gb_flash',
					'u_gb_icons',
					'u_gb_ignoreflood',		
				),	
			),
			array('ROLE_USER_NOAVATAR',
				array(
					'u_gb_post',
					'u_gb_edit',
					'u_gb_delete',
					'u_gb_view',
					'u_gb_sig',
					'u_gb_smilies',
					'u_gb_bbcode',
					'u_gb_img',
					'u_gb_flash',
					'u_gb_icons',
				),
			),	
			array('ROLE_USER_NEW_MEMBER',
				array(
					'u_gb_view',
				),
			),	
			array('GUESTS', 'u_gb_view', 'group'),	
			array('ROLE_MOD_STANDARD',
				array(
					'm_gb_delete',
					'm_gb_edit',
				),
			),
			array('ROLE_MOD_SIMPLE',
				array(
					'm_gb_delete',
					'm_gb_edit',
				),
			),
			array('ROLE_MOD_FULL',
				array(
					'm_gb_delete',
					'm_gb_edit',
				),
			),
		),
		'table_column_add' => array(
			array('phpbb_users', 'user_guestbook_posts', array('UINT', 0)),
			array('phpbb_users', 'user_guestbook_first_post_id', array('UINT', 0)),			
			array('phpbb_users', 'user_guestbook_last_post_id', array('UINT', 0)),			
		),
		'table_add' => array(
			array('phpbb_guestbook', array(
					'COLUMNS'		=> array(
						'post_id'				=> array('UINT', NULL, 'auto_increment'),
						'user_id'				=> array('UINT', 0),
						'poster_id'				=> array('UINT', 0),
						'icon_id'				=> array('UINT', 0),
						'poster_ip'				=> array('VCHAR:40', ''),
						'post_time'				=> array('TIMESTAMP', 0),
						'enable_bbcode'			=> array('BOOL', 1),
						'enable_smilies'		=> array('BOOL', 1),
						'enable_magic_url'		=> array('BOOL', 1),
						'enable_sig'			=> array('BOOL', 1),
						'post_username'			=> array('VCHAR_UNI:255', ''),
						'post_subject'			=> array('STEXT_UNI', '', 'true_sort'),
						'post_text'				=> array('MTEXT_UNI', ''),
						'post_checksum'			=> array('VCHAR:32', ''),
						'bbcode_bitfield'		=> array('VCHAR:255', ''),
						'bbcode_uid'			=> array('VCHAR:8', ''),
						'post_postcount'		=> array('BOOL', 1),
					),
					'PRIMARY_KEY'	=> 'post_id',
					'KEYS'			=> array(
						'poster_ip'				=> array('INDEX', 'poster_ip'),
						'poster_id'				=> array('INDEX', 'poster_id'),
						'post_username'			=> array('INDEX', 'post_username'),
						'tid_post_time'			=> array('INDEX', array('post_id', 'post_time')),
					),

				),
			),
		),
	),
	'0.0.1-dev2'	=> array(),
	'0.0.1'		=> array(),
	'0.0.2-dev1'	=> array(),
	'0.0.2-dev2'	=> array(),
	'0.0.2'		=> array(),
	'0.0.3-dev1'	=> array(),
	'0.1.0-dev1'	=> array(
		'permission_add' => array(
			array('a_gb', 1),
			array('a_gb_settings', 1),
		),	
		'module_add' => array(
			array('acp', 'ACP_CAT_DOT_MODS', 'ACP_CAT_PROFILE_GUESTBOOK'),
			
			array('acp', 'ACP_CAT_PROFILE_GUESTBOOK', array(
					'module_basename'		=> 'profile_guestbook',
					'module_langname'		=> 'ACP_PROFILE_GUESTBOOK',
					'module_mode'			=> 'overview',
					'module_auth'			=> 'acl_a_gb',
				),
			),
			array('acp', 'ACP_CAT_PROFILE_GUESTBOOK', array(
					'module_basename'		=> 'profile_guestbook',
					'module_langname'		=> 'ACP_PROFILE_GUESTBOOK_SETTINGS',
					'module_mode'			=> 'settings',
					'module_auth'			=> 'acl_a_gb_settings',
				),
							
			),
		),		
	),
	'0.1.0-dev2'	=> array(
		'module_add'	=> array(
			array('ucp', '', 'UCP_CAT_PROFILE_GUESTBOOK'),
			
			array('ucp', 'UCP_CAT_PROFILE_GUESTBOOK', array(
					'module_basename'		=> 'profile_guestbook',
					'module_langname'		=> 'UCP_PROFILE_GUESTBOOK_SETTINGS',
					'module_mode'			=> 'settings',
				),
			),	
		),
	),	
	'0.1.0-dev3'	=> array(
		'config_add' => array(
			array('profile_guestbook_notification', 0),
		),
	),
	'0.1.0-dev4'	=> array(
		'table_column_add' => array(
			array('phpbb_users', 'user_gb_notification_enabled', array('BOOL', 1)),
			array('phpbb_users', 'user_gb_notification', array('UINT', 0)),					
		),		
	),
	'0.1.0'		=> array(),
	'0.1.1'		=> array(),
	'1.0.0-rc1-dev'	=> array(),
	'1.0.0-rc1'	=> array(),
	'1.0.0-rc2-dev'	=> array(),
	'1.0.0-rc2'	=> array(),
	'1.0.0-rc3-dev'	=> array(),	
	'1.0.0'		=> array(),
);

// Include the UMIF Auto file and everything else will be handled automatically.
include($phpbb_root_path . 'umil/umil_auto.' . $phpEx);

?>
