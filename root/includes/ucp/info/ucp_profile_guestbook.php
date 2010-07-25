<?php
/**
*
* @package ucp
* @version $Id$
* @copyright (c) 2010 Paul Sohier
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class ucp_profile_guestbook_info
{
	function module()
	{
		return array(
			'filename'	=> 'ucp_profile_guestbook',
			'title'		=> 'UCP_PROFILE_GUESTBOOK',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'settings'		=> array('title' => 'UCP_PROFILE_GUESTBOOK_SETTINGS', 'auth' => '', 'cat' => array('UCP_PROFILE_GUESTBOOK')),

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
