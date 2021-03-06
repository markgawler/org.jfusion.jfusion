<?php

 /**
 * This is the jfusion user plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage User
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * Load the JFusion framework
 */
jimport('joomla.plugin.plugin');
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
/**
 * JFusion User class
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage User
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class plgUserJfusion extends JPlugin
{
    /**
     * Constructor
     *
     * For php4 compatibility we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
     * This causes problems with cross-referencing necessary for the observer design pattern.
     *
     * @param object &$subject The object to observe
     * @param array  $config   An array that holds the plugin configuration
     *
     * @since 1.5
     * @return void
     */
    function plgUserJfusion(&$subject, $config)
    {
        parent::__construct($subject, $config);
        //load the language
        $this->loadLanguage('com_jfusion', JPATH_BASE);
    }

    /**
     * This method is called after user is stored
     *
     * @param array   $user   holds the user data
     * @param boolean $isnew  is new user
     * @param boolean $success was it a success
     * @param string  $msg    Message
     *
     * @access public
     * @return boolean False on Error
     */
    function onAfterStoreUser($user, $isnew, $success, $msg)
    {
        if (!$success) {
            $result = false;
            return $result;
        }
        //create an array to store the debug info
        $debug_info = array();
	    $error_info = array();
	    $master_userinfo = null;
        //prevent any output by the plugins (this could prevent cookies from being passed to the header)
        ob_start();
        $Itemid_backup = JFactory::getApplication()->input->getInt('Itemid', 0);
        global $JFusionActive;
        if (!$JFusionActive) {
            //A change has been made to a user without JFusion knowing about it

	        //convert the user array into a user object
	        $JoomlaUser = new stdClass();
	        foreach($user as $key => $value) {
		        $JoomlaUser->$key = $value;
	        }

	        if (!isset($JoomlaUser->group_id) && !empty($JoomlaUser->groups)) {
		        $JoomlaUser->group_id = $JoomlaUser->groups[0];
	        }
            //check to see if we need to update the master
            $master = JFusionFunction::getMaster();
            // Recover the old data of the user
            // This is then used to determine if the username was changed
            $session = JFactory::getSession();
            $JoomlaUser->olduserinfo = (object)$session->get('olduser');
            $session->clear('olduser');
            $updateUsername = (!$isnew && $JoomlaUser->olduserinfo->username != $JoomlaUser->username) ? true : false;
            //retrieve the username stored in jfusion_users if it exists
            $db = JFactory::getDBO();

	        $query = $db->getQuery(true)
		        ->select('username')
		        ->from('#__jfusion_users')
		        ->where('id = ' . (int)$JoomlaUser->id);

            $db->setQuery($query);
            $storedUsername = $db->loadResult();
            if ($updateUsername) {
	            try {
		            $update = new stdClass();
		            $update->id = $JoomlaUser->id;
		            $update->username = $JoomlaUser->username;
		            if ($storedUsername) {
			            $db->updateObject('#__jfusion_users', $update, 'id');
		            } else {
			            $db->insertObject('#__jfusion_users', $update);
		            }
	            } catch ( Exception $e ) {
		            JFusionFunction::raiseError($e);
	            }

                //if we had a username stored in jfusion_users, update the olduserinfo with that username before passing it into the plugins so they will find the intended user
                if (!empty($storedUsername)) {
                    $JoomlaUser->olduserinfo->username = $storedUsername;
                }
            } else {
                if (!empty($JoomlaUser->original_username)) {
                    //the user was created by JFusion's JFusionJoomlaUser::createUser and we have the original username which must be used as the jfusion_user table has not been updated yet
                    $JoomlaUser->username = $JoomlaUser->original_username;
                } elseif (!empty($storedUsername)) {
                    //the username is not being updated but if there is a username stored in jfusion_users table, it must be used instead to prevent user duplication
                    $JoomlaUser->username = $storedUsername;
                }
            }
	        try {
	            $JFusionMaster = JFusionFactory::getUser($master->name);
	            //update the master user if not joomla_int
	            if ($master->name != 'joomla_int') {
			            $master_userinfo = $JFusionMaster->getUser($JoomlaUser->olduserinfo);
			            //if the username was updated, call the updateUsername function before calling updateUser
			            if ($updateUsername) {
				            if (!empty($master_userinfo)) {
					            try {
						            $updateUsernameStatus = array();
						            $JFusionMaster->debugger->set(null, $updateUsernameStatus);
						            $JFusionMaster->updateUsername($JoomlaUser, $master_userinfo, $updateUsernameStatus);
						            $JFusionMaster->mergeStatus($updateUsernameStatus);
						            if (!$JFusionMaster->debugger->isEmpty('error')) {
							            $error_info[$master->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $JFusionMaster->debugger->get('error');
						            }
						            if (!$JFusionMaster->debugger->isEmpty('debug')) {
							            $debug_info[$master->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG') ] = $JFusionMaster->debugger->get('debug');
						            }
					            } catch (Exception $e) {
						            $status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
					            }
				            } else {
					            $error_info[$master->name] = JText::_('NO_USER_DATA_FOUND');
				            }
			            }
			            try {
				            //run the update user to ensure any other userinfo is updated as well
				            $MasterUser = $JFusionMaster->updateUser($JoomlaUser, 1);
				            if (!empty($MasterUser['error'])) {
					            $error_info[$master->name] = $MasterUser['error'];
				            }
				            if (!empty($MasterUser['debug'])) {
					            $debug_info[$master->name] = $MasterUser['debug'];
				            }
				            //make sure the userinfo is available
				            if (empty($MasterUser['userinfo'])) {
					            $userinfo = $JFusionMaster->getUser($JoomlaUser);
				            } else {
					            $userinfo = $MasterUser['userinfo'];
				            }
				            //update the jfusion_users_plugin table
				            JFusionFunction::updateLookup($userinfo, $JoomlaUser->id, $master->name);
			            } catch (Exception $e) {
				            $error_info[$master->name] = array($e->getMessage());
			            }
	            } else {
		            //Joomla is master
	// commented out because we should use the joomla use object (in out plugins)
	//	            $master_userinfo = $JoomlaUser;
		            $master_userinfo = $JFusionMaster->getUser($JoomlaUser);
	            }
	        } catch (Exception $e) {
		        $error_info[$master->name] = array($e->getMessage());
	        }

			if ($master_userinfo) {
				if ( !empty($JoomlaUser->password_clear) ) {
					$master_userinfo->password_clear = $JoomlaUser->password_clear;
				}
				//update the user details in any JFusion slaves
				$slaves = JFusionFactory::getPlugins('slave');
				foreach ($slaves as $slave) {
					try {
						$JFusionSlave = JFusionFactory::getUser($slave->name);
						//if the username was updated, call the updateUsername function before calling updateUser
						if ($updateUsername) {
							$slave_userinfo = $JFusionSlave->getUser($JoomlaUser->olduserinfo);
							if (!empty($slave_userinfo)) {
								try {
									$updateUsernameStatus = array();
									$JFusionSlave->debugger->set(null, $updateUsernameStatus);
									$JFusionSlave->updateUsername($master_userinfo, $slave_userinfo, $updateUsernameStatus);
									$JFusionSlave->mergeStatus($updateUsernameStatus);
									if (!$JFusionSlave->debugger->isEmpty('error')) {
										$error_info[$slave->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $JFusionSlave->debugger->get('error');
									}
									if (!$JFusionSlave->debugger->isEmpty('debug')) {
										$debug_info[$slave->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG') ] = $JFusionSlave->debugger->get('debug');
									}
								}  catch (Exception $e) {
									$status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
								}
							} else {
								$error_info[$slave->name] = JText::_('NO_USER_DATA_FOUND');
							}
						}
						$SlaveUser = $JFusionSlave->updateUser($master_userinfo, 1);
						if (!empty($SlaveUser['error'])) {
							if (!is_array($SlaveUser['error'])) {
								$error_info[$slave->name] = array($SlaveUser['error']);
							} else {
								$error_info[$slave->name] = $SlaveUser['error'];
							}
						}
						if (!empty($SlaveUser['debug'])) {
							if (!is_array($SlaveUser['debug'])) {
								$debug_info[$slave->name] = array($SlaveUser['debug']);
							} else {
								$debug_info[$slave->name] = $SlaveUser['debug'];
							}
						}
						if (empty($SlaveUser['userinfo'])) {
							$userinfo = $JFusionSlave->getUser($master_userinfo);
						} else {
							$userinfo = $SlaveUser['userinfo'];
						}

						//update the jfusion_users_plugin table
						JFusionFunction::updateLookup($userinfo, $JoomlaUser->id, $slave->name);
					} catch (Exception $e) {
						$error_info[$slave->name] = $debug_info[$slave->name] + array($e->getMessage());
					}
				}
			}

	        //check to see if the Joomla database is still connected in case the plugin messed it up
	        JFusionFunction::reconnectJoomlaDb();
        }
        if ($Itemid_backup!=0) {
	        //reset the global $Itemid so that modules are not repeated
	        global $Itemid;
    	    $Itemid = $Itemid_backup;
	        //reset Itemid so that it can be obtained via getVar
	        JFactory::getApplication()->input->set('Itemid', $Itemid_backup);
        }
        //return output if allowed
        $isAdministrator = JFusionFunction::isAdministrator();
        if ($isAdministrator === true) {
	        $this->raise('notice', $debug_info);
	        $this->raise('error', $error_info);
        }
        //stop output buffer
        ob_end_clean();
        return true;
    }

    /*
     * joomla 1.6 compatibility code
     *
     * @param $user
     * @param array $options
     * @return bool
     */
    /**
     * @param $user
     * @param array $options
     * @return bool
     */
    public function onUserLogin($user, $options = array()){

	    //prevent any output by the plugins (this could prevent cookies from being passed to the header)
	    ob_start();
	    $success = 0;
	    $mainframe = JFactory::getApplication();
	    //prevent a login if AEC denied a user
	    if (defined('AEC_AUTH_ERROR_UNAME')) {
		    $success = -1;
	    } else {
		    jimport('joomla.user.helper');
		    global $JFusionActive, $JFusionLoginCheckActive;

		    $JFusionActive = true;

		    //php 5.3 does not allow plugins to contain pass by references
		    //use a global for the login checker instead

		    $password = JFactory::getApplication()->input->post->get('password', null, 'raw');

		    $debugger = JFusionFactory::getDebugger('jfusion-loginchecker');
		    $debugger->set(null, array());
		    $debugger->set('init', array());

		    //determine if overwrites are allowed
		    $isAdministrator = JFusionFunction::isAdministrator();
		    if (!empty($options['overwrite']) && $isAdministrator === true) {
			    $overwrite = 1;
		    } else {
			    $overwrite = 0;
		    }
		    //allow for the detection of external mods to exclude jfusion plugins
		    global $JFusionActivePlugin;
		    jimport('joomla.environment.request');
		    $jnodeid = strtolower(JFactory::getApplication()->input->get('jnodeid'));
		    if (!empty($jnodeid)){
			    $JFusionActivePlugin = $jnodeid;
		    }
		    //get the JFusion master
		    $master = JFusionFunction::getMaster();
		    //if we are in the admin and no master is selected, make joomla_int master to prevent lockouts
		    if (empty($master) && $mainframe->isAdmin()) {
			    $master = new stdClass();
			    $master->name = 'joomla_int';
			    $master->joomlaAuth = true;
		    }
		    //setup JFusionUser object for Joomla
		    $JFusionJoomla = JFusionFactory::getUser('joomla_int');
		    if (!empty($master)) {
			    $JFusionMaster = JFusionFactory::getUser($master->name);
			    //check to see if userinfo is already present
			    if (!empty($user['userinfo'])) {
				    //the jfusion auth plugin is enabled
				    $debugger->add('init', JText::_('USING_JFUSION_AUTH'));

				    $userinfo = $user['userinfo'];
			    } else {
				    //other auth plugin enabled get the userinfo again
				    //temp userinfo to see if the user exists in the master
				    $auth_userinfo = new stdClass();
				    $auth_userinfo->username = $user['username'];
				    $auth_userinfo->email = $user['email'];
				    $auth_userinfo->password_clear = $password;
				    $auth_userinfo->name = $user['fullname'];
				    //get the userinfo for real
				    try {
					    $userinfo = $JFusionMaster->getUser($auth_userinfo);
				    } catch (Exception $e) {
					    $userinfo = null;
				    }
				    if (isset($master->joomlaAuth)) {
					    $debugger->add('init', JText::_('USING_JOOMLA_AUTH'));
				    } else {
					    $debugger->add('init', JText::_('USING_OTHER_AUTH'));
				    }
				    if (empty($userinfo)) {
					    //are we in Joomla backend?  Let's check internal Joomla for the user if joomla_int isn't already the master to prevent lockouts
					    if ($master->name != 'joomla_int' && $mainframe->isAdmin()) {
						    $JFusionJoomla = JFusionFactory::getUser('joomla_int');
						    try {
							    $JoomlaUserinfo = $JFusionJoomla->getUser($auth_userinfo);
						    } catch (Exception $e) {
							    $JoomlaUserinfo = null;
						    }
						    if (!empty($JoomlaUserinfo)) {
							    //user found in Joomla, let them pass just to be able to login to the backend
							    $userinfo = $JoomlaUserinfo;
						    } else {
							    //user not found in Joomla, return an error
							    $debugger->add('init', JText::_('COULD_NOT_FIND_USER'));
							    $success = -1;
						    }
					    } else {
						    //should be auto-create users?
						    $params = JFusionFactory::getParams('joomla_int');
						    $autoregister = $params->get('autoregister', 0);
						    if ($autoregister == 1) {
							    try {
								    $debugger->add('init', JText::_('CREATING_MASTER_USER'));
								    $status = array('error' => array(), 'debug' => array());
								    //try to create a Master user
								    $JFusionMaster->createUser($auth_userinfo, $status);
								    $JFusionMaster->mergeStatus($status);
								    $status = $JFusionMaster->debugger->get();

								    if (empty($status['error'])) {
									    //success
									    //make sure the userinfo is available
									    if (!empty($status['userinfo'])) {
										    $userinfo = $status['userinfo'];
									    } else {
										    $userinfo = $JFusionMaster->getUser($auth_userinfo);
									    }

									    $debugger->add('init', JText::_('MASTER') . ' ' . JText::_('USER') . ' ' . JText::_('CREATE') . ' ' . JText::_('SUCCESS'));
								    } else {
									    //could not create user
									    $debugger->add('init', $master->name . ' ' . JText::_('USER') . ' ' . JText::_('CREATE') . ' ' . JText::_('ERROR') . ' ' . $status['error']);
									    $this->raise('error', $status['error'], $master->name . ': ' . JText::_('USER') . ' ' . JText::_('CREATE'));
									    $success = -1;
								    }
							    } catch (Exception $e) {
								    JfusionFunction::raiseError($e, $JFusionMaster->getJname());
								    $debugger->add('error', $e->getMessage());
								    $success = -1;
							    }
						    } else {
							    //return an error
							    $debugger->add('init', JText::_('COULD_NOT_FIND_USER'));
							    $success = -1;
						    }
					    }
				    }
			    }

			    if ($success === 0) {
				    //apply the clear text password to the user object
				    $userinfo->password_clear = $password;

				    //if logging in via Joomla backend, create a Joomla session and do nothing else to prevent lockouts
				    if (empty($JFusionLoginCheckActive) && $mainframe->isAdmin()) {
					    try {
					        $JoomlaUserinfo = (empty($JoomlaUserinfo)) ? $JFusionJoomla->getUser($userinfo) : $JoomlaUserinfo;

						    $JoomlaSession = $JFusionJoomla->createSession($JoomlaUserinfo, $options);
						    if (!empty($JoomlaSession['error'])) {
							    //no Joomla session could be created -> deny login
							    $this->raise('error', $JoomlaSession['error'], 'joomla_int: ' . JText::_('SESSION') . ' ' . JText::_('CREATE'));
							    $success = -1;
						    } else {
							    //make sure we have the clear password
							    if (!empty($userinfo->password_clear)) {
								    $status = array('error' => array(), 'debug' => array());
								    try {
									    $JFusionJoomla->updatePassword($userinfo, $JoomlaUserinfo, $status);
								    } catch (Exception $e) {
									    $JFusionJoomla->debugger->add('error', JText::_('PASSWORD_UPDATE_ERROR') . ' ' . $e->getMessage());
								    }
								    $JFusionJoomla->mergeStatus($status);
								    $debugger->merge($JFusionJoomla->debugger->get());
							    }
							    $success = 1;
						    }
					    } catch (Exception $e) {
						    JfusionFunction::raiseError($e, $JFusionJoomla->getJname());
						    $debugger->add('error', $e->getMessage());
						    $success = -1;
					    }
				    } else  {
					    // See if the user has been blocked or is not activated
					    if (!empty($userinfo->block) || !empty($userinfo->activation)) {
						    //make sure the block is also applied in slave software
						    $slaves = JFusionFunction::getSlaves();
						    foreach ($slaves as $slave) {
							    try {
								    if ($JFusionActivePlugin != $slave->name) {
									    $JFusionSlave = JFusionFactory::getUser($slave->name);
									    $SlaveUser = $JFusionSlave->updateUser($userinfo, $overwrite);
									    //make sure the userinfo is available
									    if (empty($SlaveUser['userinfo'])) {
										    $SlaveUser['userinfo'] = $JFusionSlave->getUser($userinfo);
									    }
									    if (!empty($SlaveUser['error'])) {
										    $debugger->set($slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR'), $SlaveUser['error']);
									    }
									    if (!empty($SlaveUser['debug'])) {
										    $debugger->set($slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG'), $SlaveUser['debug']);
									    }

									    $debugger->set($slave->name . ' ' . JText::_('USERINFO'), $SlaveUser['userinfo']);
								    }
							    } catch (Exception $e) {
								    JfusionFunction::raiseError($e, $slave->name);
								    $debugger->add('error', $e->getMessage());
							    }
						    }
						    if (!empty($userinfo->block)) {
							    $debugger->add('error', JText::_('FUSION_BLOCKED_USER'));
							    $this->raise('warning', JText::_('FUSION_BLOCKED_USER'));
							    $success = -1;
						    } else {
							    $debugger->add('error', JText::_('FUSION_INACTIVE_USER'));
							    $this->raise('warning', JText::_('FUSION_INACTIVE_USER'));
							    $success = -1;
						    }
					    } else {
						    $JoomlaUser = array('userinfo' => null, 'error' => '');
						    //check to see if we need to setup a Joomla session
						    if ($master->name != 'joomla_int') {
							    try {
								    //setup the Joomla user
								    $JoomlaUser = $JFusionJoomla->updateUser($userinfo, $overwrite);
								    if (!empty($JoomlaUser['debug'])) {
									    $debugger->set('joomla_int ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG'), $JoomlaUser['debug']);
								    }
								    if (!empty($JoomlaUser['error'])) {
									    //no Joomla user could be created, fatal error
									    $debugger->set('joomla_int ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR'), $JoomlaUser['error']);
									    $this->raise('error', $JoomlaUser['error'], 'joomla_int: ' . JText::_('USER') . ' ' . JText::_('UPDATE'));
									    $success = -1;
								    } else {
									    if (isset($options['show_unsensored'])) {
										    $details = $JoomlaUser['userinfo'];
									    } else {
										    $details = JFusionFunction::anonymizeUserinfo($JoomlaUser['userinfo']);
									    }
									    $debugger->set('joomla_int ' . JText::_('USER') . ' ' . JText::_('DETAILS'), $details);

									    //create a Joomla session
									    if ($JFusionActivePlugin != 'joomla_int') {
										    try {
											    $JoomlaSession = $JFusionJoomla->createSession($JoomlaUser['userinfo'], $options);
											    if (!empty($JoomlaSession['error'])) {
												    //no Joomla session could be created -> deny login
												    $debugger->set('joomla_int ' . JText::_('SESSION') . ' ' . JText::_('ERROR'), $JoomlaSession['error']);
												    $this->raise('error', $JoomlaSession['error'], 'joomla_int: ' . JText::_('SESSION') . ' ' . JText::_('CREATE'));
												    $success = -1;
											    }
											    if (!empty($JoomlaSession['debug'])) {
												    $debugger->set('joomla_int ' . JText::_('SESSION') . ' ' . JText::_('DEBUG'), $JoomlaSession['debug']);
											    }
										    } catch (Exception $e) {
											    JfusionFunction::raiseError($e, $JFusionJoomla->getJname());
											    $debugger->set('joomla_int ' . JText::_('SESSION') . ' ' . JText::_('ERROR'), $e->getMessage());
											    $success = -1;
										    }
									    }
								    }
							    } catch (Exception $e) {
								    JfusionFunction::raiseError($e, $JFusionJoomla->getJname());
								    $debugger->add('error', $e->getMessage());
							    }
						    } else {
							    //joomla already setup, we can copy its details from the master
							    $JoomlaUser['userinfo'] = $userinfo;
						    }
						    if ($success === 0) {
							    //setup the master session if
							    //a) The master is not joomla_int and the user is logging into Joomla frontend only
							    //b) The master is joomla_int and the user is logging into either Joomla frontend or backend
							    if ($JFusionActivePlugin != $master->name && $master->dual_login == 1 && (!isset($options['group']) || $master->name == 'joomla_int')) {
								    try {
									    $MasterSession = $JFusionMaster->createSession($userinfo, $options);

									    if (!empty($MasterSession['error'])) {
										    $debugger->set($master->name . ' ' . JText::_('SESSION') . ' ' . JText::_('ERROR'), $MasterSession['error']);
										    $this->raise('error', $MasterSession['error'], $master->name . ': ' . JText::_('SESSION') . ' ' . JText::_('CREATE'));
										    if ($master->name == 'joomla_int') {
											    $success = -1;
										    }
									    }
									    if (!empty($MasterSession['debug'])) {
										    $debugger->set($master->name . ' ' . JText::_('SESSION') . ' ' . JText::_('DEBUG'), $MasterSession['debug']);
										    //report the error back
									    }
								    } catch (Exception $e) {
									    $debugger->set($master->name . ' ' . JText::_('SESSION') . ' ' . JText::_('ERROR'), $e->getMessage());
									    JfusionFunction::raiseError($e, $master->name . ': ' . JText::_('SESSION') . ' ' . JText::_('CREATE'));
									    if ($master->name == 'joomla_int') {
										    $success = -1;
									    }
								    }
							    }
							    if ($success === 0) {
								    //allow for joomlaid retrieval in the loginchecker
								    $debugger->set('joomlaid', $JoomlaUser['userinfo']->userid);
								    if ($master->name != 'joomla_int') {
									    JFusionFunction::updateLookup($userinfo, $JoomlaUser['userinfo']->userid, $master->name);
								    }
								    //setup the other slave JFusion plugins
								    $slaves = JFusionFactory::getPlugins('slave');
								    foreach ($slaves as $slave) {
									    try {
										    $JFusionSlave = JFusionFactory::getUser($slave->name);
										    $SlaveUser = $JFusionSlave->updateUser($userinfo, $overwrite);
										    if (!empty($SlaveUser['debug'])) {
											    $debugger->set($slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG'), $SlaveUser['debug']);
										    }
										    if (!empty($SlaveUser['error'])) {
											    $debugger->set($slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR'), $SlaveUser['error']);
											    $this->raise('error', $SlaveUser['error'], $slave->name . ': ' . JText::_('USER') . ' ' . JText::_('UPDATE'));
										    } else {
											    //make sure the userinfo is available
											    if (empty($SlaveUser['userinfo'])) {
												    $SlaveUser['userinfo'] = $JFusionSlave->getUser($userinfo);
											    }

											    if (isset($options['show_unsensored'])) {
												    $details = $SlaveUser['userinfo'];
											    } else {
												    $details = JFusionFunction::anonymizeUserinfo($SlaveUser['userinfo']);
											    }

											    $debugger->set($slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE'), $details);

											    //apply the clear text password to the user object
											    $SlaveUser['userinfo']->password_clear = $user['password'];
											    JFusionFunction::updateLookup($SlaveUser['userinfo'], $JoomlaUser['userinfo']->userid, $slave->name);
											    if (!isset($options['group']) && $slave->dual_login == 1 && $JFusionActivePlugin != $slave->name) {
												    try {
													    $SlaveSession = $JFusionSlave->createSession($SlaveUser['userinfo'], $options);
													    if (!empty($SlaveSession['error'])) {
														    $debugger->set($slave->name . ' ' . JText::_('SESSION') . ' ' . JText::_('ERROR'), $SlaveSession['error']);
														    $this->raise('error', $SlaveSession['error'], $slave->name . ': ' . JText::_('SESSION') . ' ' . JText::_('CREATE'));
													    }
													    if (!empty($SlaveSession['debug'])) {
														    $debugger->set($slave->name . ' ' . JText::_('SESSION') . ' ' . JText::_('DEBUG'), $SlaveSession['debug']);
													    }
												    } catch (Exception $e) {
													    $debugger->set($slave->name . ' ' . JText::_('SESSION') . ' ' . JText::_('ERROR'), $e->getMessage());
													    JfusionFunction::raiseError($e, $JFusionSlave->getJname());
												    }
											    }
										    }
									    } catch (Exception $e) {
										    JfusionFunction::raiseError($e, $slave->name);
										    $debugger->add('error', $e->getMessage());
									    }
								    }
								    $success = 1;
							    }
						    }
					    }
				    }
			    }
		    } else {
			    $success = -1;
		    }
	    }
	    ob_end_clean();
	    if ($success === 1) {
		    //Clean up the joomla session table
		    $conf = JFactory::getConfig();
		    $expire = ($conf->get('lifetime')) ? $conf->get('lifetime') * 60 : 900;

		    try {
			    $db =  JFactory::getDbo();
			    $query = $db->getQuery(true)
				    ->delete('#__session')
				    ->where('time < ' . (int) (time() - $expire));
			    $db->setQuery($query);

			    $db->execute();
		    } catch (Exception $e) {
		    }

		    if (!$mainframe->isAdmin()) {
			    $params = JFusionFactory::getParams('joomla_int');
			    $allow_redirect_login = $params->get('allow_redirect_login', 0);
			    $redirecturl_login = $params->get('redirecturl_login', '');
			    $source_url = $params->get('source_url', '');
			    $jfc = JFusionFactory::getCookies();
			    if ($allow_redirect_login && !empty($redirecturl_login)) {
				    // only redirect if we are in the frontend and allowed and have an URL
				    $jfc->executeRedirect($source_url, $redirecturl_login);
			    } else {
				    $jfc->executeRedirect($source_url);
			    }
		    }
	    }
	    return ($success === 1);
 	}

    /**
     * @param $user
     * @param array $options
     * @return object
     */
    public function onUserLogout($user, $options = array())	{
	    //initialise some vars
	    global $JFusionActive;
	    $JFusionActive = true;
	    $userinfo = JFactory::getUser($user['id']);
	    //allow for the detection of external mods to exclude jfusion plugins
	    global $JFusionActivePlugin;
	    jimport('joomla.environment.request');
	    $jnodeid = strtolower(JFactory::getApplication()->input->get('jnodeid'));
	    if (!empty($jnodeid)){
		    $JFusionActivePlugin = $jnodeid;
	    }

	    //prevent any output by the plugins (this could prevent cookies from being passed to the header)
	    ob_start();
	    //logout from the JFusion plugins if done through frontend
	    if (empty($options['clientid'][0])) {
		    $debugger = JFusionFactory::getDebugger('jfusion-loginchecker');

		    //get the JFusion master
		    $master = JFusionFunction::getMaster();
		    if ($master->name && $master->name != 'joomla_int' && $JFusionActivePlugin != $master->name) {
			    $JFusionMaster = JFusionFactory::getUser($master->name);
			    $userlookup = JFusionFunction::lookupUser($master->name, $userinfo->id);
			    $debugger->set('userlookup', $userlookup);
			    $MasterUser = $JFusionMaster->getUser($userlookup);
			    if (isset($options['show_unsensored'])) {
				    $details = $MasterUser;
			    } else {
				    $details = JFusionFunction::anonymizeUserinfo($MasterUser);
			    }
			    $debugger->set('masteruser', $details);

			    //check if a user was found
			    if (!empty($MasterUser)) {
				    try {
					    $MasterSession = $JFusionMaster->destroySession($MasterUser, $options);
					    if (!empty($MasterSession['error'])) {
						    $this->raise('error', $MasterSession['error'], $master->name . ': ' . JText::_('SESSION') . ' ' . JText::_('DESTROY'));
					    }
					    if (!empty($MasterSession['debug'])) {
						    $debugger->set($master->name . ' logout', $MasterSession['debug']);
					    }
				    } catch (Exception $e) {
					    JFusionFunction::raiseError($e, $JFusionMaster->getJname());
				    }
			    } else {
				    $this->raise('notice', JText::_('LOGOUT') . ' ' . JText::_('COULD_NOT_FIND_USER'), $master->name);
			    }
		    }
		    $slaves = JFusionFactory::getPlugins('slave');
		    foreach ($slaves as $slave) {
			    //check if sessions are enabled
			    if ($slave->dual_login == 1 && $JFusionActivePlugin != $slave->name) {
				    $JFusionSlave = JFusionFactory::getUser($slave->name);
				    $userlookup = JFusionFunction::lookupUser($slave->name, $userinfo->id);
				    try {
					    $SlaveUser = $JFusionSlave->getUser($userlookup);
				    } catch (Exception $e) {
					    $SlaveUser = null;
				    }
				    if (isset($options['show_unsensored'])) {
					    $info = $SlaveUser;
				    } else {
					    $info = JFusionFunction::anonymizeUserinfo($SlaveUser);
				    }

				    $debugger->set($slave->name . ' ' . JText::_('USER') . ' ' . JText::_('DETAILS') , $info);

				    //check if a user was found
				    if (!empty($SlaveUser)) {
					    $SlaveSession = array();
					    try {
						    $SlaveSession = $JFusionSlave->destroySession($SlaveUser, $options);
						    if (!empty($SlaveSession['error'])) {
							    $this->raise('error', $SlaveSession['error'], $slave->name . ': ' . JText::_('SESSION') . ' ' . JText::_('DESTROY'));
						    }
						    if (!empty($SlaveSession['debug'])) {
							    $debugger->set($slave->name . ' logout', $SlaveSession['debug']);
						    }
					    } catch (Exception $e) {
						    JFusionFunction::raiseError($e, $JFusionSlave->getJname());
					    }
				    } else {
					    $this->raise('notice', JText::_('LOGOUT') . ' ' . JText::_('COULD_NOT_FIND_USER'), $slave->name);
				    }
			    }
		    }
	    }

	    //destroy the joomla session itself
	    if ($JFusionActivePlugin != 'joomla_int') {
		    $JoomlaUser = JFusionFactory::getUser('joomla_int');
		    try {
			    $JoomlaUser->destroySession($userinfo, $options);
		    } catch (Exception $e) {
			    JFusionFunction::raiseError($e, $JoomlaUser->getJname());
		    }
	    }

	    $params = JFusionFactory::getParams('joomla_int');
	    $allow_redirect_logout = $params->get('allow_redirect_logout', 0);
	    $redirecturl_logout = $params->get('redirecturl_logout', '');
	    $source_url = $params->get('source_url', '');
	    ob_end_clean();
	    $jfc = JFusionFactory::getCookies();
	    if ($allow_redirect_logout && !empty($redirecturl_logout)) // only redirect if we are in the frontend and allowed and have an URL
	    {
		    $jfc->executeRedirect($source_url, $redirecturl_logout);
	    } else {
		    $jfc->executeRedirect($source_url);
	    }

	    $result = true;
	    return $result;
	}

    /**
     * @param $user
     * @param $success
     * @param $msg
     * @return bool
     */
    public function onUserAfterDelete($user, $success, $msg) {
	    $result = true;
	    if (!$success) {
		    $result = false;
	    } else {
		    //create an array to store the debug info
		    $debug_info = array();
		    $error_info = array();
		    //convert the user array into a user object
		    $userinfo = (object)$user;
		    //delete the master user if it is not Joomla
		    $master = JFusionFunction::getMaster();
		    if ($master->name != 'joomla_int') {
			    $params = JFusionFactory::getParams($master->name);
			    $deleteEnabled = $params->get('allow_delete_users', 0);
			    $JFusionMaster = JFusionFactory::getUser($master->name);
			    try {
				    $MasterUser = $JFusionMaster->getUser($userinfo);
			    } catch (Exception $e) {
				    $MasterUser = null;
			    }
			    if (!empty($MasterUser) && $deleteEnabled) {
				    try {
					    $status = $JFusionMaster->deleteUser($MasterUser);
					    if (!empty($status['error'])) {
						    $error_info[$master->name . ' ' . JText::_('ERROR') ] = $status['error'];
					    }
					    if (!empty($status['debug'])) {
						    $debug_info[$master->name] = $status['debug'];
					    }
				    } catch (Exception $e) {
					    JFusionFunction::raiseError($e, $JFusionMaster->getJname());
				    }
			    } elseif ($deleteEnabled) {
				    $debug_info[$master->name] = JText::_('NO_USER_DATA_FOUND');
			    } else {
				    $debug_info[$master->name] = JText::_('DELETE_DISABLED');
			    }
		    }
		    //delete the user in the slave plugins
		    $slaves = JFusionFactory::getPlugins('slave');
		    foreach ($slaves as $slave) {
			    $params = JFusionFactory::getParams($slave->name);
			    $deleteEnabled = $params->get('allow_delete_users', 0);
			    $JFusionSlave = JFusionFactory::getUser($slave->name);

			    try {
				    $SlaveUser = $JFusionSlave->getUser($userinfo);
			    } catch (Exception $e) {
				    $SlaveUser = null;
			    }

			    if (!empty($SlaveUser) && $deleteEnabled) {
				    try {
					    $status = $JFusionSlave->deleteUser($SlaveUser);
					    if (!empty($status['error'])) {
						    $error_info[$slave->name . ' ' . JText::_('ERROR') ] = $status['error'];
					    }
					    if (!empty($status['debug'])) {
						    $debug_info[$slave->name] = $status['debug'];
					    }
				    } catch (Exception $e) {
					    JFusionFunction::raiseError($e, $JFusionSlave->getJname());
				    }
			    } elseif ($deleteEnabled) {
				    $debug_info[$slave->name] = JText::_('NO_USER_DATA_FOUND');
			    } else {
				    $debug_info[$slave->name] = JText::_('DELETE') . ' ' . JText::_('DISABLED');
			    }
		    }
		    //remove userlookup data
		    JFusionFunction::removeUser($userinfo);
		    //delete any sessions that the user could have active
		    $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->delete('#__session')
			    ->where('userid = ' . $db->quote($user['id']));

		    $db->setQuery($query);
		    $db->execute();
		    //return output if allowed
		    $isAdministrator = JFusionFunction::isAdministrator();
		    if ($isAdministrator === true) {
			    $this->raise('notice', $debug_info);
			    $this->raise('error', $error_info);
		    }
	    }
	    return $result;
	}

    /**
     * @param $olduser
     * @param $isnew
     * @param $new
     * @return bool
     */
    public function onUserBeforeSave($olduser, $isnew, $new){
	    global $JFusionActive;
	    if (!$JFusionActive) {
		    // Recover old data from user before to save it. The purpose is to provide it to the plugins if needed
		    $session = JFactory::getSession();
		    $session->set('olduser', $olduser);
	    }
	    $result = true;
	    return $result;
	}

    /**
     * @param $user
     * @param $isnew
     * @param $success
     * @param $msg
     * @return bool
     */
    public function onUserAfterSave($user, $isnew, $success, $msg) {
        if (!JPluginHelper::isEnabled('user', 'joomla')) {
	        $master = JFusionFunction::getMaster();
	        if ($master->name == 'joomla_int') {
		        // Initialise variables.
		        $app    = JFactory::getApplication();
		        $config = JFactory::getConfig();
		        $mail_to_user = $this->params->get('mail_to_user', 0); // change default to 0 to prevent user email spam! while running sync
		        if ($isnew) {
			        /**
			         * @TODO Suck in the frontend registration emails here as well. Job for a rainy day.
			         */

			        if ($app->isAdmin()) {
				        if ($mail_to_user) {

					        // Load user_joomla plugin language (not done automatically).
					        JFactory::getLanguage()->load('plg_user_joomla', JPATH_ADMINISTRATOR);

					        // Compute the mail subject.
					        $emailSubject = JText::sprintf(
						        'PLG_USER_JOOMLA_NEW_USER_EMAIL_SUBJECT',
						        $user['name'],
						        $config->get('sitename')
					        );

					        // Compute the mail body.
					        $emailBody = JText::sprintf(
						        'PLG_USER_JOOMLA_NEW_USER_EMAIL_BODY',
						        $user['name'],
						        $config->get('sitename'),
						        JUri::root(),
						        $user['username'],
						        $user['password_clear']
					        );

					        // Assemble the email data...the sexy way!
					        $mail = JFactory::getMailer()
						        ->setSender(
							        array(
								        $config->get('mailfrom'),
								        $config->get('fromname')
							        )
						        )
						        ->addRecipient($user['email'])
						        ->setSubject($emailSubject)
						        ->setBody($emailBody);


					        if (!$mail->Send()) {
						        /**
						         * @TODO Probably should raise a plugin error but this event is not error checked.
						         */
						        JFusionFunction::raiseWarning(JText::_('ERROR_SENDING_EMAIL'));
					        }
				        }
			        }
		        } else {
			        // Existing user - nothing to do...yet.
		        }
	        }
        }
 	    $result = $this->onAfterStoreUser($user, $isnew, $success, $msg);
 	    return $result;
	}

	/**
	 * Raise warning function that can handle arrays
	 *
	 * @param        $type
	 * @param array  $message   message itself
	 * @param string $jname
	 *
	 * @return string nothing
	 */
	public function raise($type, $message, $jname = '') {
		global $JFusionLoginCheckActive;
		if (!$JFusionLoginCheckActive) {
			JFusionFunction::raise($type, $message, $jname);
		}
	}
}