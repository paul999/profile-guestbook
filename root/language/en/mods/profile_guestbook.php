<?php
/**
*
* profile_guestbook [English]
*
* @package profile guestbook
* @version 1.0.0
* @copyright (c) 2010 Paul Sohier
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
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
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

/**
 * UMIL lang vars.
 **/
$lang = array_merge($lang, array(
	'PROFILE_GUESTBOOK'			=> 'Profile Guestbook',
	'INSTALL_PROFILE_GUESTBOOK'		=> 'Install Profile Guestbook',
	'INSTALL_PROFILE_GUESTBOOK_CONFIRM'	=> 'Are you sure you want to install Profile Guestbook?',
	'UPDATE_PROFILE_GUESTBOOK'		=> 'Update Profile Guestbook',
	'UPDATE_PROFILE_GUESTBOOK_CONFIRM'	=> 'Are you sure you want to update Profile Guestbook?',
	'UNINSTALL_PROFILE_GUESTBOOK'		=> 'Uninstall Profile Guestbook',
	'UNINSTALL_PROFILE_GUESTBOOK_CONFIRM'	=> 'Are you sure you want to uninstall Profile Guestbook?',
));


$lang = array_merge($lang, array(
	'NO_POSTS_GUESTBOOK'	=> 'There are currently no posts in this guestbook',
	'GUESTBOOK'		=> 'Guestbook',
	'ABOUT_GUESTBOOK'	=> 'Welcome to this guestbook. Here you can write your personal thoughts. Have fun posting!',
	
	'NO_TOPIC_ICON'		=> 'No icon',
	'RETURN_PROFILE'	=> '%sReturn back to the profile',
	
	'GUESTBOOK_REPLY'	=> 'Post a comment',
	'POST_GUESTBOOK'	=> 'Post a guestbook comment',
	
	'NEW_GUESTBOOK_POST'	=> 'New guestbook post',
	'NEW_GUESTBOOK_POST_TXT'=> 'Hello,

A new post has been created in your guestbook:

%s

If you do not want to continue receiving PMs, you can change your settings in your profile.',
));

