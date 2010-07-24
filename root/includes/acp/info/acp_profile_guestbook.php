<?php
/**
*
* @package acp
* @version $Id$
* @copyright (c) 2010 Paul Sohier
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class acp_profile_guestbook_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_board',
			'title'		=> 'ACP_PROFILE_GUESTBOOK',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'overview'		=> array('title' => 'ACP_PROFILE_GUESTBOOK', 'auth' => 'acl_a_gb', 'cat' => array('ACP_PROFILE_GUESTBOOK')),
				'settings'		=> array('title' => 'ACP_PROFILE_GUESTBOOK_SETTINGS', 'auth' => 'acl_a_gb_settings', 'cat' => array('ACP_PROFILE_GUESTBOOK')),
			),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>
