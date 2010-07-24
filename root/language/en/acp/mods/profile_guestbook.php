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
	
	'VERSION_UP_TO_DATE'		=> 'Your version is uptodate.',
	'VERSION_NOT_UP_TO_DATE_TITLE'	=> 'Your version is not uptodate. You should update as soon as possible.',
	'VERSIONCHECK_FAIL'		=> 'Error connecting to the server.',
	

));


?>
