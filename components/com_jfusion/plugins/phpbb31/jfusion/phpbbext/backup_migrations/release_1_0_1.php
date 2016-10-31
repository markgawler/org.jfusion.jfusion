<?php
/**
*
* @package phpBB Extension - JFusion phpBB Extension
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace jfusion\phpbbext\migrations;

/**
 * Class release_1_0_0
 * @package jfusion\phpbbext\migrations
 */

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['jfusion_phpbbext_frameless']);
	}

	public function update_data()
	{
		return array(
				array('config.add', array('jfusion_phpbbext_frameless', '0')),
		);
	}
}