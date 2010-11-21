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
define ('GUESTBOOK_TABLE', $table_prefix . 'guestbook');

// Constants for notification				
define('GB_NOTIFY_EMAIL'	, 0);
define('GB_NOTIFY_IM'		, 1);
define('GB_NOTIFY_PM'		, 2);
define('GB_NOTIFY_EMAIL_PM'	, 3);
define('GB_NOTIFY_IM_PM'	, 4);
define('GB_NOTIFY_ALL'		, 5);
?>
