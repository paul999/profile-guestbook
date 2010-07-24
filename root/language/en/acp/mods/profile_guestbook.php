<?php
/**
* acp_profile_guestbook (phpBB Permission Set) [English]
*
* @package language
* @version $Id$
* @copyright (c) 2010 Paul Sohier
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


// User Permissions
$lang = array_merge($lang, array(
	'ACP_PROFILE_GUESTBOOK_SETTINGS'		=> 'General guestbook settings',
	'ACP_PROFILE_GUESTBOOK_SETTINGS_EXPLAIN'	=> 'General settings for the profile guestbook MOD',
	
	'PROFILE_GUESTBOOK_ENABLED'	=> 'Profile Guestbook MOD enabled',
	

	'WELCOME_GUESTBOOK'	=> 'Profile Guestbook overview',
	'ADMIN_GUESTBOOK'	=> 'You can find here a basic overview of the Profile Guestbook MOD with some basics stats and some options. <br />If you find any bugs, you should report these at the bugtracker. This bugtracker can be found <a href="http://phpbbguestbook.com/viewforum.php?f=5">here</a>. Support for the MOD can be found at the same board',
	'GUESTBOOK_STATS'	=> 'Basic guestbook statistics',
	
	'GB_OPTIONS'		=> 'Basic guestbook actions',
	'DELETE_ALL'		=> 'Delete <strong>all</strong posts from <ul>all</ul> users.',

	'VERSION_UP_TO_DATE'		=> 'Your version is uptodate.',
	'VERSION_NOT_UP_TO_DATE_TITLE'	=> 'Your version is not uptodate. You should update as soon as possible.',
	'VERSIONCHECK_FAIL'		=> 'Error connecting to the server.',
	
	'NUMBER_OF_GB'		=> 'Number of users with a guestbook',
	'NUMBER_OF_POSTS'	=> 'Number of guestbook posts',

	

));


?>
