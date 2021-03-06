<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Authentication Class for an external Joomla database
 * For detailed descriptions on these functions please check JFusionAuth
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_joomla_ext extends JFusionAuth
{
	/**
	 * @var $helper JFusionHelper_joomla_ext
	 */
	var $helper;

	/**
	 * returns the name of this JFusion plugin
	 *
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'joomla_ext';
	}

	/**
	 * Generates an encrypted password based on the userinfo passed to this function
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return string Returns generated password
	 */
	public function generateEncryptedPassword($userinfo)
	{
		if (jimport('phpass.passwordhash')) {
			$testcrypt = $this->helper->hashPassword($userinfo);
		} else {
			$testcrypt = $this->helper->getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'md5-hex');
		}
		return $testcrypt;
	}

	/**
	 * used by framework to ensure a password test
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return boolean
	 */
	function checkPassword($userinfo) {
		$rehash = false;
		$match = false;

		// If we are using phpass
		if (strpos($userinfo->password, '$P$') === 0) {
			// Use PHPass's portable hashes with a cost of 10.
			$phpass = new PasswordHash(10, true);

			$match = $phpass->CheckPassword($userinfo->password_clear, $userinfo->password);
		} elseif ($userinfo->password[0] == '$') {
			// JCrypt::hasStrongPasswordSupport() includes a fallback for us in the worst case
			JCrypt::hasStrongPasswordSupport();
			$match = password_verify($userinfo->password_clear, $userinfo->password);

			// Uncomment this line if we actually move to bcrypt.
			// $rehash = password_needs_rehash($hash, PASSWORD_DEFAULT);
			$rehash = true;
		} elseif (substr($userinfo->password, 0, 8) == '{SHA256}') {
			// Check the password
			$testcrypt = $this->helper->getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'sha256', true);

			$match = $this->comparePassword($userinfo->password, $testcrypt);

			$rehash = true;
		} else {
			$rehash = true;

			$testcrypt = $this->helper->getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'md5-hex', false);

			$match = $this->comparePassword($userinfo->password, $testcrypt);
		}

		// If we have a match and rehash = true, rehash the password with the current algorithm.
		if ($match && $rehash) {
			$user = JFusionFactory::getUser($this->getJname());
			$old = $user->getUser($userinfo);
			if ($old) {
				$status = array('error' => array(), 'debug' => array());
				$user->updatePassword($userinfo, $old, $status);
			}
		}
		return $match;
	}

	/**
	 * Hashes a password using the current encryption.
	 *
	 * @param   object  $userinfo  The plaintext password to encrypt.
	 *
	 * @return  string  The encrypted password.
	 *
	 * @since   3.2.1
	 */
	public function hashPassword($userinfo)
	{
		if (jimport('phpass.passwordhash')) {
			$password = $this->helper->hashPassword($userinfo->password_clear);
		} else {
			$salt = $this->helper->genRandomPassword(32);
			$password = $this->helper->getCryptedPassword($userinfo->password_clear, $salt, 'md5-hex') . ':' . $salt;
		}
		return $password;
	}
}
