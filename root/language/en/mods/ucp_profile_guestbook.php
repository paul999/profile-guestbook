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
	'ENABLE_NOTIFICATION'	=> 'Enable notification for new guestbook posts',
	'NO_NOTIFICATION'	=> 'Notification is disabled by the board administrator',
	'NO_NOTIFY'		=> 'Notification is disabled by the board administrator', // @TODO: Check if above lang ite is used as well.
	
	'NOTIFY_METHOD_PM'		=> 'PM only',
	'NOTIFY_METHOD_EMAIL_PM'	=> 'PM and e-mail',
	'NOTIFY_METHOD_IM_PM'		=> 'PM and jabber',
	'NOTIFY_METHOD_ALL'		=> 'PM, jabber and e-mail',
	
	'GB_JABBER_DISABLED'	=> 'Jabber is disabled, select a different notification option',
	'GB_EMAIL_DISABLED'		=> 'Email is disabled, select a different notification option',	
));


