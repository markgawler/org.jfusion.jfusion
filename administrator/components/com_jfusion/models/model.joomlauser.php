<?php

/**
 * Model for joomla actions
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';

/**
 * Common Class for Joomla JFusion plugins
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionJoomlaUser extends JFusionUser
{
    /**
     * gets the userinfo from the JFusion integrated software. Definition of object:
     *
     * @param object $userinfo contains the object of the user
     *
     * @return null|object userinfo Object containing the user information
     */
    public function getUser($userinfo)
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $JFusionUser = JFusionFactory::getUser($this->getJname());
		    list($identifier_type, $identifier) = $JFusionUser->getUserIdentifier($userinfo, 'username', 'email');
		    if ($this->getJname() == 'joomla_int' && $identifier_type == 'username') {
			    $params = JFusionFactory::getParams($this->getJname());

			    $query = $db->getQuery(true)
				    ->select('b.id as userid, b.activation, a.username, b.name, b.password, b.email, b.block, b.params')
				    ->from('#__users as b')
			        ->innerJoin('#__jfusion_users as a ON a.id = b.id');

			    if ($params->get('case_insensitive')) {
				    $query->where('LOWER(a.' . $identifier_type . ') = ' . $db->Quote(strtolower($identifier)));
			    } else {
				    $query->where('a.' . $identifier_type . ' = ' . $db->Quote($identifier));
			    }
			    //first check the JFusion user table if the identifier_type = username
			    $db->setQuery($query);

			    $result = $db->loadObject();
			    if (!$result) {
				    $query = $db->getQuery(true)
					    ->select('id as userid, activation, username, name, password, email, block, params')
					    ->from('#__users');

				    if ($params->get('case_insensitive')) {
					    $query->where('LOWER(' . $identifier_type . ') = ' . $db->Quote(strtolower($identifier)));
				    } else {
					    $query->where($identifier_type . ' = ' . $db->Quote($identifier));
				    }
				    //check directly in the joomla user table
				    $db->setQuery($query);

				    $result = $db->loadObject();
				    if ($result) {
					    //update the lookup table so that we don't have to do a double query next time
					    $query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $result->userid . ', ' . $db->Quote($identifier) . ')';
					    $db->setQuery($query);
					    try {
						    $db->execute();
					    } catch (Exception $e) {
						    JFusionFunction::raiseWarning($e, $this->getJname());
					    }
				    }
			    }
		    } else {
			    $query = $db->getQuery(true)
				    ->select('id as userid, activation, username, name, password, email, block, params')
				    ->from('#__users')
				    ->where($identifier_type . ' = ' . $db->Quote($identifier));

			    $db->setQuery($query);
			    $result = $db->loadObject();
		    }
		    if ($result) {
			    $query = $db->getQuery(true)
				    ->select('a.group_id, b.title as name')
				    ->from('#__user_usergroup_map as a')
				    ->innerJoin('#__usergroups as b ON a.group_id = b.id')
				    ->where('a.user_id = ' . $db->Quote($result->userid));

			    $db->setQuery($query);
			    $groupList = $db->loadObjectList();
			    if ($groupList) {
				    foreach ($groupList as $group) {
					    $result->groups[] = $group->group_id;
					    $result->groupnames[] = $group->name;

					    if ( !isset($result->group_id) || $group->group_id > $result->group_id) {
						    $result->group_id = $group->group_id;
						    $result->group_name =  $group->name;
					    }
				    }
			    } else {
				    $result->groups = array();
				    $result->groupnames = array();
			    }
			    //split up the password if it contains a salt
			    //note we cannot use explode as a salt from another software may contain a colon which messes Joomla up
			    if (strpos($result->password, ':') !== false) {
				    $saltStart = strpos($result->password, ':');
				    $result->password_salt = substr($result->password, $saltStart + 1);
				    $result->password = substr($result->password, 0, $saltStart);
			    } else {
				    //prevent php notices
				    $result->password_salt = '';
			    }
			    // Get the language of the user and store it as variable in the user object
			    $user_params = new JRegistry($result->params);

			    $result->language = $user_params->get('language', JFactory::getLanguage()->getTag());

			    //unset the activation status if not blocked
			    if ($result->block == 0) {
				    $result->activation = '';
			    }
			    //unset the block if user is inactive
			    if (!empty($result->block) && !empty($result->activation)) {
				    $result->block = 0;
			    }

			    //check to see if CB is installed and activated and if so update the activation and ban accordingly
			    $query = $db->getQuery(true)
				    ->select('enabled')
				    ->from('#__extensions')
				    ->where('name LIKE ' . $db->quote('%com_comprofiler%'));

			    $db->setQuery($query);
			    $cbenabled = $db->loadResult();

			    if (!empty($cbenabled)) {
				    $query = $db->getQuery(true)
					    ->select('confirmed, approved, cbactivation')
					    ->from('#__comprofiler')
					    ->where('user_id = ' . $result->userid);

				    $db->setQuery($query);
				    $cbresult = $db->loadObject();

				    if (!empty($cbresult)) {
					    if (empty($cbresult->confirmed) && !empty($cbresult->cbactivation)) {
						    $result->activation = $cbresult->cbactivation;
						    $result->block = 0;
					    } elseif (empty($cbresult->confirmed) || empty($cbresult->approved)) {
						    $result->block = 1;
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = null;
	    }
        return $result;
    }

    /**
     * Function that updates the user email
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function updateEmail($userinfo, &$existinguser, &$status)
    {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('email = '.$db->quote($userinfo->email))
			    ->where('id = '.$db->quote($existinguser->userid));

	        $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that updates the user password
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function updatePassword($userinfo, &$existinguser, &$status)
    {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());
		    jimport( 'joomla.user.helper' );
	        $userinfo->password_salt = JUserHelper::genRandomPassword(32);
	        $userinfo->password = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt);
	        $new_password = $userinfo->password . ':' . $userinfo->password_salt;

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('password = '.$db->quote($new_password))
			    ->where('id = '.$db->quote($existinguser->userid));

	        $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that blocks user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function blockUser($userinfo, &$existinguser, &$status)
    {
	    try {
	        //do not block super administrators
	        if ($existinguser->group_id != 25) {
	            //block the user
	            $db = JFusionFactory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->update('#__users')
			        ->set('block = 1')
			        ->where('id = '.$db->quote($existinguser->userid));

	            $db->setQuery($query);
		        $db->execute();

		        $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	        } else {
	            $status['debug'][] = JText::_('BLOCK_UPDATE_ERROR') . ': ' . JText::_('CANNOT_BLOCK_SUPERADMINS');
	        }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that unblocks user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function unblockUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    //unblock the user
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('block = 0')
			    ->where('id = '.$db->quote($existinguser->userid));

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that activates user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function activateUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    //unblock the user
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('block = 0')
			    ->set('activation = '.$db->quote(''))
			    ->where('id = '.$db->quote($existinguser->userid));

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that inactivates user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function inactivateUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    if ($existinguser->group_id != 25) {
			    //unblock the user
			    $db = JFusionFactory::getDatabase($this->getJname());

			    $query = $db->getQuery(true)
				    ->update('#__users')
				    ->set('block = 1')
				    ->set('activation = '.$db->quote($userinfo->activation))
				    ->where('id = '.$db->quote($existinguser->userid));

			    $db->setQuery($query);
			    $db->execute();

			    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		    } else {
			    $status['debug'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . JText::_('CANNOT_INACTIVATE_SUPERADMINS');
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * filters the username to remove invalid characters
     *
     * @param string $username contains username
     *
     * @return string filtered username
     */
    public function filterUsername($username)
    {
	    //check to see if additional username filtering need to be applied
	    $params = JFusionFactory::getParams($this->getJname());
	    $added_filter = $params->get('username_filter');
	    if ($added_filter && $added_filter != $this->getJname()) {
		    $JFusionPlugin = JFusionFactory::getUser($added_filter);
		    if (method_exists($JFusionPlugin, 'filterUsername')) {
			    $filteredUsername = $JFusionPlugin->filterUsername($username);
		    }
	    }
	    //make sure the filtered username isn't empty
	    $username = (!empty($filteredUsername)) ? $filteredUsername : $username;
	    //define which characters which Joomla forbids in usernames
	    $trans = array('&#60;' => '_', '&lt;' => '_', '&#62;' => '_', '&gt;' => '_', '&#34;' => '_', '&quot;' => '_', '&#39;' => '_', '&#37;' => '_', '&#59;' => '_', '&#40;' => '_', '&#41;' => '_', '&amp;' => '_', '&#38;' => '_', '<' => '_', '>' => '_', '"' => '_', '\'' => '_', '%' => '_', ';' => '_', '(' => '_', ')' => '_', '&' => '_');
	    //remove forbidden characters for the username
	    $username = strtr($username, $trans);
	    //make sure the username is at least 2 characters long
	    while (strlen($username) < 2) {
		    $username.= '_';
	    }
        return $username;
    }

    /**
     * Function that updates username
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function updateUsername($userinfo, &$existinguser, &$status)
    {
	    try {
		    //generate the filtered integration username
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $username_clean = $this->filterUsername($userinfo->username);
		    $status['debug'][] = JText::_('USERNAME') . ': ' . $userinfo->username . ' -> ' . JText::_('FILTERED_USERNAME') . ':' . $username_clean;

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('username = '.$db->quote($username_clean))
			    ->where('id = '.$db->quote($existinguser->userid));

		    $db->setQuery($query);
		    try {
			    $db->execute();
			    $status['debug'][] = JText::_('USERNAME_UPDATE') . ': ' . $username_clean;
		    } catch (Exception $e) {
			    $status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
		    }

		    if ($this->getJname() == 'joomla_int') {
			    //update the lookup table
			    $query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $existinguser->userid . ', ' . $db->Quote($userinfo->username) . ')';
			    $db->setQuery($query);
			    $db->execute();

			    $status['debug'][] = JText::_('USERNAME_UPDATE') . ': ' . $username_clean;
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
	    }
    }

    /**
     * Function that creates a new user account
     *
     * @param object $userinfo Object containing the new userinfo
     * @param array  &$status  Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function createUser($userinfo, &$status)
    {
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
	    try {
		    //get the default user group and determine if we are using simple or advanced
		    //check to make sure that if using the advanced group mode, $userinfo->group_id exists
		    if (empty($usergroups)) {
			    throw new RuntimeException(JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING'));
		    } else {
			    //load the database
			    $db = JFusionFactory::getDatabase($this->getJname());
			    //joomla does not allow duplicate email addresses, check to see if the email is unique
			    $query = $db->getQuery(true)
				    ->select('id as userid, username, email')
				    ->from('#__users')
				    ->where('email = ' . $db->Quote($userinfo->email));

			    $db->setQuery($query);
			    $existinguser = $db->loadObject();
			    if (empty($existinguser)) {
				    //apply username filtering
				    $username_clean = $this->filterUsername($userinfo->username);
				    //now we need to make sure the username is unique in Joomla

				    $query = $db->getQuery(true)
					    ->select('id')
					    ->from('#__users')
				        ->where('username=' . $db->Quote($username_clean));

				    $db->setQuery($query);
				    while ($db->loadResult()) {
					    $username_clean.= '_';
					    $query = $db->getQuery(true)
						    ->select('id')
						    ->from('#__users')
						    ->where('username=' . $db->Quote($username_clean));

					    $db->setQuery($query);
				    }
				    $status['debug'][] = JText::_('USERNAME') . ':' . $userinfo->username . ' ' . JText::_('FILTERED_USERNAME') . ':' . $username_clean;
				    //create a Joomla password hash if password_clear is available
				    if (!empty($userinfo->password_clear)) {
					    jimport( 'joomla.user.helper' );
					    $userinfo->password_salt = JUserHelper::genRandomPassword(32);
					    $userinfo->password = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt);
					    $password = $userinfo->password . ':' . $userinfo->password_salt;
				    } else {
					    //if password_clear is not available, store hashed password as is and also store the salt if present
					    if (isset($userinfo->password_salt)) {
						    $password = $userinfo->password . ':' . $userinfo->password_salt;
					    } else {
						    $password = $userinfo->password;
					    }
				    }
				    $instance = new JUser();
				    $instance->set('name', $userinfo->name);
				    $instance->set('username', $username_clean);
				    $instance->set('password', $password);
				    $instance->set('email', $userinfo->email);
				    $instance->set('block', $userinfo->block);
				    $instance->set('activation', $userinfo->activation);
				    $instance->set('sendEmail', 0);
				    //find out what usergroup the new user should have
				    //the $userinfo object was probably reconstructed in the user plugin and autoregister = 1
				    $isadmin = false;
				    if (isset($usergroups[0])) {
					    $isadmin = (in_array (7 , $usergroups, true) || in_array (8 , $usergroups, true)) ? true : false;
				    } else {
					    $usergroups = array(2);
				    }

				    //work around the issue where joomla will not allow the creation of an admin or super admin if the logged in user is not a super admin
				    if ($isadmin && $this->getJname() == 'joomla_int') {
					    $usergroups = array(2);
				    }

				    $instance->set('usertype', 'deprecated');
				    $instance->set('groups', $usergroups);
				    if ($this->getJname() == 'joomla_int') {
					    //store the username passed into this to prevent the user plugin from attempting to recreate users
					    $instance->set('original_username', $userinfo->username);
					    // save the user
					    global $JFusionActive;
					    $JFusionActive = 1;
					    $instance->save(false);

					    $createdUser = $instance->getProperties();
					    $createdUser = (object)$createdUser;
					    //update the user's group to the correct group if they are an admin
					    if ($isadmin) {
						    $createdUser->userid = $createdUser->id;
						    $this->updateUsergroup($userinfo, $createdUser, $status, false);
					    }
					    //create a new entry in the lookup table
					    //if the credentialed username is available (from the auth plugin), store it; otherwise store the $userinfo username
					    $username = (!empty($userinfo->credentialed_username)) ? $userinfo->credentialed_username : $userinfo->username;
					    $query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $createdUser->id . ', ' . $db->Quote($username) . ')';
					    $db->setQuery($query);
					    try {
						    $db->execute();
					    } catch (Exception $e) {
						    JFusionFunction::raiseWarning($e, $this->getJname());
					    }
				    } else {
					    // joomla_ext
					    // convert the Joomla userobject to a std object
					    $user = $instance->getProperties();
					    // get rid of internal properties
					    unset($user['password_clear']);
					    unset($user['aid']);
					    unset($user['guest']);
					    // set the creation time and last access time
					    $user['registerDate'] = date('Y-m-d H:i:s', time());
					    $user = (object)$user;
					    $user->id = null;

					    $db->insertObject('#__users', $user, 'id');

					    foreach ($usergroups as $group) {
						    $newgroup = new stdClass;
						    $newgroup->group_id = (int)$group;
						    $newgroup->user_id = (int)$user->user_id;
						    $newgroup->group_leader = 0;
						    $newgroup->user_pending = 0;

						    $db->insertObject('#__user_usergroup_map', $newgroup);
					    }
				    }
				    //check to see if the user exists now
				    $joomla_user = $this->getUser($userinfo);
				    if ($joomla_user) {
					    //report back success
					    $status['userinfo'] = $joomla_user;
					    $status['debug'][] = JText::_('USER_CREATION');
				    } else {
					    $status['error'][] = JText::_('COULD_NOT_CREATE_USER');
				    }
			    } else {
				    //Joomla does not allow duplicate emails report error
				    $status['debug'][] = JText::_('USERNAME') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->username . ' -> ' . $userinfo->username;
				    $status['error'][] = JText::_('EMAIL_CONFLICT') . '. UserID: ' . $existinguser->userid . ' JFusionPlugin: ' . $this->getJname();
				    $status['userinfo'] = $existinguser;
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USER_CREATION_ERROR') . $e->getMessage();
	    }
        return $status;
    }

    /**
     * Updates or creates a user for the integrated software. This allows JFusion to have external software as slave for user management
     *
     * @param object $userinfo  contains the userinfo
     * @param int    $overwrite determines if the userinfo can be overwritten
     *
     * @return array result Array containing the result of the user update
     */
    public function updateUser($userinfo, $overwrite = 0)
    {
	    $status = array('error' => array(),'debug' => array());
	    try {
		    // Initialise some variables
		    $params = JFusionFactory::getParams($this->getJname());
		    $update_block = $params->get('update_block');
		    $update_activation = $params->get('update_activation');
		    $update_email = $params->get('update_email');
		    //check to see if a valid $userinfo object was passed on
		    if (!is_object($userinfo)) {
			    throw new RuntimeException(JText::_('NO_USER_DATA_FOUND'));
		    } else {
			    //check to see if user exists
			    $existinguser = $this->getUser($userinfo);
			    if (!empty($existinguser)) {
				    $changed = false;
				    //a matching user has been found
				    $status['debug'][] = JText::_('USER_DATA_FOUND');
				    // email update?
				    if (strtolower($existinguser->email) != strtolower($userinfo->email)) {
					    $status['debug'][] = JText::_('EMAIL_CONFLICT');
					    if ($update_email || $overwrite) {
						    $status['debug'][] = JText::_('EMAIL_CONFLICT_OVERWITE_ENABLED');
						    $this->updateEmail($userinfo, $existinguser, $status);
						    $changed = true;
					    } else {
						    //return a email conflict
						    $status['debug'][] = JText::_('EMAIL_CONFLICT_OVERWITE_DISABLED');
						    $status['userinfo'] = $existinguser;
						    throw new RuntimeException(JText::_('EMAIL') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
					    }
				    }
				    // password update ?
				    if (!empty($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
					    //if not salt set, update the password
					    $existinguser->password_clear = $userinfo->password_clear;
					    //check if the password needs to be updated
					    $model = JFusionFactory::getAuth($this->getJname());
					    $testcrypt = $model->generateEncryptedPassword($existinguser);
					    //if the passwords are not the same or if Joomla salt has inherited a colon which will confuse Joomla without JFusion; generate a new password hash
					    if ($testcrypt != $existinguser->password || strpos($existinguser->password_salt, ':') !== false) {
						    $this->updatePassword($userinfo, $existinguser, $status);
						    $changed = true;
					    } else {
						    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_VALID');
					    }
				    } else {
					    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
				    }
				    //block status update?
				    if ($existinguser->block != $userinfo->block) {
					    if ($update_block || $overwrite) {
						    if ($userinfo->block) {
							    //block the user
							    $this->blockUser($userinfo, $existinguser, $status);
							    $changed = true;
						    } else {
							    //unblock the user
							    $this->unblockUser($userinfo, $existinguser, $status);
							    $changed = true;
						    }
					    } else {
						    //return a debug to inform we skipped this step
						    $status['debug'][] = JText::_('SKIPPED_BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
					    }
				    }
				    //activation status update?
				    if ($existinguser->activation != $userinfo->activation) {
					    if ($update_activation || $overwrite) {
						    if ($userinfo->activation) {
							    //inactive the user
							    $this->inactivateUser($userinfo, $existinguser, $status);
							    $changed = true;
						    } else {
							    //activate the user
							    $this->activateUser($userinfo, $existinguser, $status);
							    $changed = true;
						    }
					    } else {
						    //return a debug to inform we skipped this step
						    $status['debug'][] = JText::_('SKIPPED_EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
					    }
				    }
				    //check for advanced usergroup sync
				    if (!$userinfo->block && empty($userinfo->activation)) {
					    if (JFusionFunction::updateUsergroups($this->getJname())) {
						    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
						    if (!$this->compareUserGroups($existinguser,$usergroups)) {
							    $this->updateUsergroup($userinfo, $existinguser, $status);
							    $changed = true;
						    } else {
							    $status['debug'][] = JText::_('SKIPPED_GROUP_UPDATE') . ':' . JText::_('GROUP_VALID');
						    }
					    }
				    }

				    //Update the user language in the one existing from an other plugin
				    if (!empty($userinfo->language) && !empty($existinguser->language) && $userinfo->language != $existinguser->language) {
					    $this->updateUserLanguage($userinfo, $existinguser, $status);
					    $existinguser->language = $userinfo->language;
					    $changed = true;
				    } else {
					    //return a debug to inform we skipped this step
					    $status['debug'][] = JText::_('LANGUAGE_NOT_UPDATED');
				    }

				    if (empty($status['error'])) {
					    if ($changed == true) {
						    $status['action'] = 'updated';
						    $status['userinfo'] = $this->getUser($userinfo);
					    } else {
						    $status['action'] = 'unchanged';
						    $status['userinfo'] = $existinguser;
					    }
				    }
			    } else {
				    $status['debug'][] = JText::_('NO_USER_FOUND_CREATING_ONE');
				    $this->createUser($userinfo, $status);
				    if (empty($status['error'])) {
					    $status['action'] = 'created';
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * Function that updates usergroup
     *
     * @param object $userinfo          Object containing the new userinfo
     * @param object &$existinguser     Object containing the old userinfo
     * @param array  &$status           Array containing the errors and result of the function
     * @param bool $fire_user_plugins needs more detail
     *
     * @return string updates are passed on into the $status array
     */
    public function updateUsergroup($userinfo, &$existinguser, &$status, $fire_user_plugins = true)
    {
	    try {
		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
		    //make sure the group exists
		    if (empty($usergroups)) {
			    throw new RuntimeException(JText::_('GROUP_UPDATE_ERROR') . ': ' . JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
		    } else {
			    $db = JFusionFactory::getDatabase($this->getJname());
			    $dispatcher = JEventDispatcher::getInstance();

			    //Fire the user plugin functions for joomla_int
			    if ($this->getJname() == 'joomla_int' && $fire_user_plugins) {
				    // Get the old user
				    $old = new JUser($existinguser->userid);
				    //Fire the onBeforeStoreUser event.
				    JPluginHelper::importPlugin('user');
				    $dispatcher->trigger('onBeforeStoreUser', array($old->getProperties(), false));
			    }

			    jimport('joomla.user.helper');

			    $query = $db->getQuery(true)
				    ->delete('#__user_usergroup_map')
				    ->where('user_id = '. $db->Quote($existinguser->userid));

			    $db->setQuery($query);

			    $db->execute();

			    foreach ($usergroups as $group) {
				    $temp = new stdClass;
				    $temp->user_id = $existinguser->userid;
				    $temp->group_id = $group;

				    $db->insertObject('#__user_usergroup_map', $temp);
			    }
			    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode(',', $existinguser->groups) . ' -> ' .implode(',', $usergroups);
			    //Fire the user plugin functions for joomla_int
			    if ($this->getJname() == 'joomla_int' && $fire_user_plugins) {
				    //Fire the onAfterStoreUser event
				    $updated = new JUser($existinguser->userid);
				    $dispatcher->trigger('onAfterStoreUser', array($updated->getProperties(), false, true, ''));
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $e->getMessage();
	    }
        return $status;
    }
    /************************************************
    * Functions For JFusion Who's Online Module
    ***********************************************/



    /**
     * Update the language user in his account when he logs in Joomla or
     * when the language is changed in the frontend
     *
     * @see JFusionJoomlaUser::updateUser
     * @see JFusionJoomlaPublic::setLanguageFrontEnd
     *
	 * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    public function updateUserLanguage($userinfo, &$existinguser, &$status)
    {
	    try {
		    /**
		     * @TODO joomla 1.5/1.6 if we are talking to external joomla since joomla 1.5 store params in json
		     */
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $params = new JRegistry($existinguser->params);
		    $params->set('language', $userinfo->language);

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('params = '.$db->quote($params->toString()))
			    ->where('id = '.$db->quote($existinguser->userid));

		    $db->setQuery($query);

		    $db->execute();
		    $status['debug'][] = JText::_('LANGUAGE_UPDATE') . ' ' . $existinguser->language;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('LANGUAGE_UPDATE_ERROR') . $e->getMessage();
	    }
    }
}