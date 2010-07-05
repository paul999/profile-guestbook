<?php
/**
* acp_permissions_profile_guestbook (phpBB Permission Set) [English]
*
* @package language
* @version $Id: permissions_phpbb.php 9686 2009-06-26 array('lang' => '', 'cat' => 'pg')array('lang' => '', 'cat' => 'pg'):52:54Z rxu $
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %array('lang' => '', 'cat' => 'pg')$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

/**
*	MODDERS PLEASE NOTE
*
*	You are able to put your permission sets into a separate file too by
*	prefixing the new file with permissions_ and putting it into the acp
*	language folder.
*
*	An example of how the file could look like:
*
*	<code>
*
*	if (empty($lang) || !is_array($lang))
*	{
*		$lang = array();
*	}
*
*	// Adding new category
*	$lang['permission_cat']['bugs'] = 'Bugs';
*
*	// Adding new permission set
*	$lang['permission_type']['bug_'] = 'Bug Permissions';
*
*	// Adding the permissions
*	$lang = array_merge($lang, array(
*		'acl_bug_view'		=> array('lang' => 'Can view bug reports', 'cat' => 'bugs'),
*		'acl_bug_post'		=> array('lang' => 'Can post bugs', 'cat' => 'post'), // Using a phpBB category here
*	));
*
*	</code>
*/


$lang['permission_cat']['pg'] = 'Profile Guestbook';


// User Permissions
$lang = array_merge($lang, array(
	'acl_u_gb_post' =>	array('lang' => 'Can post in Profile Guestbook', 'cat' => 'pg'),
	'acl_u_gb_edit' => 	array('lang' => 'Can edit own posts', 'cat' => 'pg'),
	'acl_m_gb_edit' => 	array('lang' => 'Can edit posts', 'cat' => 'pg'),
	'acl_u_gb_delete' => 	array('lang' => 'Can delete own posts', 'cat' => 'pg'),
	'acl_m_gb_delete' => 	array('lang' => 'Can delete posts', 'cat' => 'pg'),
	'acl_u_gb_view' => 	array('lang' => 'Can view Profile Guestbook', 'cat' => 'pg'),
	'acl_u_gb_sig' => 	array('lang' => 'Can use signature', 'cat' => 'pg'),
	'acl_u_gb_smilies' => 	array('lang' => 'Can use smilies', 'cat' => 'pg'),
	'acl_u_gb_bbcode' => 	array('lang' => 'Can use BBCode', 'cat' => 'pg'),
	'acl_u_gb_img' => 	array('lang' => 'Can use [img] BBcode', 'cat' => 'pg'),
	'acl_u_gb_flash' => 	array('lang' => 'Can use [flash] BBcode', 'cat' => 'pg'),
	'acl_u_gb_icons' => 	array('lang' => 'Can use icons', 'cat' => 'pg'),
	'acl_u_gb_ignoreflood' 	=> array('lang' => 'Can ignore flood', 'cat' => 'pg'),		
));


?>
