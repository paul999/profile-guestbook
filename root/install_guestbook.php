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
$mod_name = 'PROFILE_GUESTBOOK_MOD';

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
$language_file = 'umil';

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
	'0.0.1-a1' => array(
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
	),
/*
		'table_add' => array(
			array('phpbb_test', array(
					'COLUMNS'		=> array(
						'test_id'		=> array('UINT', NULL, 'auto_increment'),
						'test_text'		=> array('VCHAR_UNI', ''),
						'test_bool'		=> array('BOOL', 0),
					),
					'PRIMARY_KEY'	=> 'test_id',
					'KEYS'			=> array(
						'test_bool'		=> array('INDEX', 'test_bool'),
					),
				),
			),
		),*/


);

// Include the UMIF Auto file and everything else will be handled automatically.
include($phpbb_root_path . 'umil/umil_auto.' . $phpEx);

?>
