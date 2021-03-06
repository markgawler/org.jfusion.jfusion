<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team -- Henk Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion User Class for Wordpress 3+
 * For detailed descriptions on these functions please check JFusionUser
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team -- Henk Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */


/**
 *
 */
class JFusionUser_wordpress extends JFusionUser {
   /**
    * @var $helper JFusionHelper_wordpress
    */
   var $helper;

   /**
    * returns the name of this JFusion plugin
    *
    * @return string name of current JFusion plugin
    */
   function getJname() {
      return 'wordpress';
   }

   /**
    * @param object $userinfo
    *
    * @return null|object
    */
   function getUser($userinfo) {
      $result = null;
      try {
         //get the identifier
         list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'user_login', 'user_email');
         // Get a database object
         $db = JFusionFactory::getDatabase($this->getJname());
         //make the username case insensitive
         if ($identifier_type == 'user_login') {
            $identifier = $this->filterUsername($identifier);
         }
         // internal note: working toward the JFusion 2.0 plugin system, we read all available userdata into the user object
         // conversion to the JFusion user object will be done at the end for JFusion 1.x
         // we add an local user field to keep the original data
         // will be further developed for 2.0 allowing centralized registration

         $query = $db->getQuery(true)
            ->select('*')
            ->from('#__users')
            ->where($identifier_type . ' = ' . $db->quote($identifier));

         $db->setQuery($query);
         $result = $db->loadObject();
         if ($result) {
            // get the meta userdata
            $query = $db->getQuery(true)
               ->select('*')
               ->from('#__usermeta')
               ->where('user_id = ' . $db->quote($result->ID));

            $db->setQuery($query);
            $result1 = $db->loadObjectList();
            if ($result1) {
               foreach ($result1 as $metarecord) {
                  $result->{$metarecord->meta_key} = $metarecord->meta_value;
               }
            }
            $jFusionUserObject = $this->convertUserobjectToJFusion($result);
            $jFusionUserObject->{$this->getJname() . '_UserObject'} = $result;
            $result = $jFusionUserObject;
         }
      } catch (Exception $e) {
         JFusionFunction::raiseError($e, $this->getJname());
      }
      return $result;
   }

   /**
    * Routine to convert userobject to standardized JFusion version
    *
    * @param $user
    *
    * @return \stdClass
    */
   function convertUserobjectToJFusion($user) {
      $result = new stdClass;

      $result->userid = $user->ID;
      // have to figure out what to use a s the name. Guess display name will do.
      //     $result->name         = $user->first_name;
      //     if (user->last_name) { $result->name .= $user_last_name;}
      $result->name = $user->display_name;
      $result->username = $user->user_login;
      $result->email = $user->user_email;
      $result->password = $user->user_pass;
      $result->password_salt = null;

      // usergroup (actually role) is in a serialized field of the user metadata table
      // unserialize. Gives an array with capabilities

      $database_prefix = $this->params->get('database_prefix');
      $capabilities = $database_prefix . 'capabilities';
      $capabilities = unserialize($user->$capabilities);
      // make sure we only have activated capabilities
      $x = array_keys($capabilities, '1');
      // get the values to test
      $y = array_values($x);
      // now find out what we have
      $groupid = 4; // default to subscriber
      $groupname = 'subscriber';

      $groups = $this->helper->getUsergroupListWP();

      $result->groups = array();
      $result->groupnames = array();
      // find the most capable one
      foreach ($y as $cap) {
         foreach ($groups as $group) {
            if (strtolower($group->name) == strtolower($cap)) {
               $groupid = $group->id;
               $groupname = $cap;

               $result->groups[] = $groupid;
               $result->groupnames[] = $groupname;
            }
         }
      }
      if (empty($result->groups)) {
         $result->groups[] = $groupid;
         $result->groupnames[] = $groupname;
      }
      // fill the userobject
      $result->group_id = $groupid;
      $result->group_name = $groupname;
      $result->registerdate = $user->user_registered;
      $result->activation = $user->user_activation_key;
      $result->block = 0;

      // todo get to find out where user status stands for. As far as I can see we have also two additional fields
      // in a multi site, one of the spam. This maybe linked to block.

      return $result;
   }

   /**
    * @param object $userinfo
    * @param array $options
    *
    * @return array|bool|string
    */
   /*
      IMPORTANT: These cookies are based upon the data from the default plugable functions. If you changed these you probably know
      what you do and you should change the cookie stuff below as appropiate.
    */
   function destroySession($userinfo, $options) {
      require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';

      $status = array('error' => array(), 'debug' => array());
      $cookie_stuff = unserialize($this->params->get('cookie_stuff'));

      $cookie_domain = $this->params->get('cookie_domain');
      $cookie_path = $this->params->get('cookie_path');
      $cookie_path_admin = $cookie_stuff['ADMIN_COOKIE_PATH'];
      $cookie_path_plugins = $cookie_stuff['PLUGINS_COOKIE_PATH'];
      $cookie_path_site = $cookie_stuff['SITECOOKIEPATH'];
      $cookie_path_wp = $cookie_stuff['COOKIEPATH']; // make sure we also delete old cookies when the plugin param has been changed


      $cookie_secure = $this->params->get('secure');
      $cookie_httponly = $this->params->get('httponly');
      $cookie_time = time() - 24 * 60 * 60 * 5;

      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['AUTH_COOKIE'], "", $cookie_time, $cookie_path_admin, $cookie_domain);
      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['SECURE_AUTH_COOKIE'], "", $cookie_time, $cookie_path_admin, $cookie_domain);
      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['AUTH_COOKIE'], "", $cookie_time, $cookie_path_plugins, $cookie_domain);
      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['SECURE_AUTH_COOKIE'], "", $cookie_time, $cookie_path_plugins, $cookie_domain);
      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['LOGGED_IN_COOKIE'], "", $cookie_time, $cookie_path, $cookie_domain);

      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['AUTH_COOKIE'], "", $cookie_time, $cookie_path, $cookie_domain);
      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['SECURE_AUTH_COOKIE'], "", $cookie_time, $cookie_path, $cookie_domain);

      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['USER_COOKIE'], "", $cookie_time, $cookie_path, $cookie_domain);
      $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['PASS_COOKIE'], "", $cookie_time, $cookie_path, $cookie_domain);

      if ($cookie_path_wp != $cookie_path) {
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['LOGGED_IN_COOKIE'], "", $cookie_time, $cookie_path_wp, $cookie_domain);
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['AUTH_COOKIE'], "", $cookie_time, $cookie_path_wp, $cookie_domain);
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['SECURE_AUTH_COOKIE'], "", $cookie_time, $cookie_path_wp, $cookie_domain);
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['USER_COOKIE'], "", $cookie_time, $cookie_path_wp, $cookie_domain);
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['PASS_COOKIE'], "", $cookie_time, $cookie_path_wp, $cookie_domain);
      }
      if ($cookie_path_site != $cookie_path) {
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['LOGGED_IN_COOKIE'], "", $cookie_time, $cookie_path_site, $cookie_domain);
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['AUTH_COOKIE'], "", $cookie_time, $cookie_path_site, $cookie_domain);
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['SECURE_AUTH_COOKIE'], "", $cookie_time, $cookie_path_site, $cookie_domain);
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['USER_COOKIE'], "", $cookie_time, $cookie_path_site, $cookie_domain);
         $status['debug'][] = JFusionCurl::addCookie($cookie_stuff['PASS_COOKIE'], "", $cookie_time, $cookie_path_site, $cookie_domain);
      }
      return $status;
   }


   /**
    * @param object $userinfo
    * @param array $options
    * @return array|string
    */
   function createSession($userinfo, $options) {
      $status = array('error' => array(), 'debug' => array());
      //do not create sessions for blocked users
      if (!empty($userinfo->block) || !empty($userinfo->activation)) {
         $status['error'][] = JText::_('FUSION_BLOCKED_USER');
      } else {
         $status = $this->curlLogin($userinfo, $options, $this->params->get('brute_force'));
      }
      return $status;
   }

   /**
    * @param string $username
    *
    * @return mixed|string
    */
   function filterUsername($username) {
      // strip all tags
      $username = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $username);
      $username = strip_tags($username);
      $username = preg_replace('/[\r\n\t ]+/', ' ', $username);
      $username = trim($username);
      // remove accents
      $username = $this->helper->remove_accentsWP($username);
      // Kill octets
      $username = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '', $username);
      $username = preg_replace('/&.+?;/', '', $username); // Kill entities

      // If strict, reduce to ASCII for max portability.
      $strict = true; // default behaviour of WP 3, can be moved to params if we need i to be choice
      if ($strict) {
         $username = preg_replace('|[^a-z0-9 _.\-@]|i', '', $username);
      }
      // Consolidate contiguous whitespace
      $username = preg_replace('|\s+|', ' ', $username);
      return $username;
   }

   /**
    * @param object $userinfo
    * @param object $existinguser
    * @param array $status
    *
    * @return void
    */
   function updatePassword($userinfo, &$existinguser, &$status) {
      // get the encryption PHP file
      if (!class_exists('PasswordHashOrg')) {
         require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'PasswordHashOrg.php';
      }
      $t_hasher = new PasswordHashOrg(8, true);
      $existinguser->password = $t_hasher->HashPassword($userinfo->password_clear);
      unset($t_hasher);
      $db = JFusionFactory::getDatabase($this->getJname());

      $query = $db->getQuery(true)
         ->update('#__users')
         ->set('user_pass = ' . $db->quote($existinguser->password))
         ->where('ID = ' . (int)$existinguser->userid);

      $db->setQuery($query);
      $db->execute();

      $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
   }

   /**
    * @param object $userinfo
    * @param object $existinguser
    * @param array $status
    *
    * @return void
    */
   function updateUsername($userinfo, &$existinguser, &$status) {
      // not implemented in jFusion 1.x
   }

   /**
    * @param object $userinfo
    * @param object $existinguser
    * @param array $status
    *
    * @return void
    */
   function updateEmail($userinfo, &$existinguser, &$status) {
      //we need to update the email
      $db = JFusionFactory::getDatabase($this->getJname());

      $query = $db->getQuery(true)
         ->update('#__users')
         ->set('user_email = ' . $db->quote($userinfo->email))
         ->where('ID = ' . (int)$existinguser->userid);

      $db->setQuery($query);
      $db->execute();

      $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
   }

   /**
    * @param object $userinfo
    * @param object $existinguser
    * @param array $status
    *
    * @throws RuntimeException
    * @return void
    */
   function blockUser($userinfo, &$existinguser, &$status) {
      // not supported for Wordpress
      throw new RuntimeException('Blocking not supported by Wordpress');
   }

   /**
    * @param object $userinfo
    * @param object $existinguser
    * @param array $status
    *
    * @return void
    */
   function unblockUser($userinfo, &$existinguser, &$status) {
   }

   /**
    * @param object $userinfo
    * @param object $existinguser
    * @param array $status
    *
    * @return void
    */
   function activateUser($userinfo, &$existinguser, &$status) {
      //activate the user
      $db = JFusionFactory::getDatabase($this->getJname());

      $query = $db->getQuery(true)
         ->update('#__users')
         ->set('user_activation_key = ' . $db->quote(''))
         ->where('ID = ' . (int)$existinguser->userid);

      $db->setQuery($query);
      $db->execute();
      $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
   }

   /**
    * @param object $userinfo
    * @param object $existinguser
    * @param array $status
    *
    * @return void
    */
   function inactivateUser($userinfo, &$existinguser, &$status) {
      //set activation key
      $db = JFusionFactory::getDatabase($this->getJname());

      $query = $db->getQuery(true)
         ->update('#__users')
         ->set('user_activation_key = ' . $db->quote($userinfo->activation))
         ->where('ID = ' . (int)$existinguser->userid);

      $db->setQuery($query);
      $db->execute();

      $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
   }

   /**
    * @param object $userinfo
    * @param array $status
    *
    * @return void
    */
   function createUser($userinfo, &$status) {
      try {
         //find out what usergroup should be used
         $db = JFusionFactory::getDatabase($this->getJname());
         $usergroups = $this->getCorrectUserGroups($userinfo);
         if (empty($usergroups)) {
            throw new RuntimeException(JText::_('USERGROUP_MISSING'));
         } else {
            $update_activation = $this->params->get('update_activation');
            $default_role_id = $usergroups[0];
            $default_role_name = strtolower($this->helper->getUsergroupNameWP($default_role_id));
            $default_role = array();
            $default_role[$default_role_name] = 1;

            $default_userlevel = $this->helper->WP_userlevel_from_role(0, $default_role_name);
            if (isset($userinfo->password_clear)) {
               //we can update the password
               if (!class_exists('PasswordHashOrg')) {
                  require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'PasswordHashOrg.php';
               }
               $t_hasher = new PasswordHashOrg(8, true);
               $user_password = $t_hasher->HashPassword($userinfo->password_clear);
               unset($t_hasher);
            } else {
               $user_password = $userinfo->password;
            }
            if (!empty($userinfo->activation) && $update_activation) {
               $user_activation_key = $userinfo->activation;
            } else {
               $user_activation_key = '';
            }

            //prepare the variables
            $user = new stdClass;
            $user->ID = null;
            $user->user_login = $this->filterUsername($userinfo->username);
            $user->user_pass = $user_password;
            $user->user_nicename = strtolower($userinfo->username);
            $user->user_email = strtolower($userinfo->email);
            $user->user_url = '';
            $user->user_registered = date('Y-m-d H:i:s', time()); // seems WP has a switch to use GMT. Could not find that
            $user->user_activation_key = $user_activation_key;
            $user->user_status = 0;
            $user->display_name = $userinfo->username;
            //now append the new user data
            $db->insertObject('#__users', $user, 'ID');

            // get new ID
            $user_id = $db->insertid();

            // have to set user metadata
            $metadata = array();

            $parts = explode(' ', $userinfo->name);
            $metadata['first_name'] = trim($parts[0]);
            if ($parts[(count($parts) - 1)]) {
               for ($i = 1; $i < (count($parts)); $i++) {
                  if (isset($metadata['last_name'])) {
                     $metadata['last_name'] .= ' ' . trim($parts[$i]);
                  } else {
                     $metadata['last_name'] = trim($parts[$i]);
                  }
               }
            }

            $database_prefix = $this->params->get('database_prefix');

            $metadata['nickname'] = $userinfo->username;
            $metadata['description'] = '';
            $metadata['rich_editing'] = 'true';
            $metadata['comment_shortcuts'] = 'false';
            $metadata['admin_color'] = 'fresh';
            $metadata['use_ssl'] = '0';
            $metadata['aim'] = '';
            $metadata['yim'] = '';
            $metadata['jabber'] = '';
            $metadata[$database_prefix . 'capabilities'] = serialize($default_role);
            $metadata[$database_prefix . 'user_level'] = sprintf('%u', $default_userlevel);
            //		$metadata['default_password_nag'] = '0'; //no nag! can be omitted

            $meta = new stdClass;
            $meta->umeta_id = null;
            $meta->user_id = $user_id;

            $keys = array_keys($metadata);
            foreach ($keys as $key) {
               $meta->meta_key = $key;
               $meta->meta_value = $metadata[$key];
               $meta->umeta_id = null;
               $db->insertObject('#__usermeta', $meta, 'umeta_id');
            }
            //return the good news
            $status['userinfo'] = $this->getUser($userinfo);
            $status['debug'][] = JText::_('USER_CREATION');
         }
      } catch (Exception $e) {
         $status['error'][] = JText::_('USER_CREATION_ERROR') . $e->getMessage();
      }
   }

   /**
    * @param object $userinfo
    * @return array
    */
   function deleteUser($userinfo) {
      //setup status array to hold debug info and errors
      $status = array('error' => array(), 'debug' => array());
      try {
         if (!is_object($userinfo)) {
            throw new RuntimeException(JText::_('NO_USER_DATA_FOUND'));
         }

         $db = JFusionFactory::getDatabase($this->getJname());
         $reassign = $this->params->get('reassign_blogs');
         $reassign_to = $this->params->get('reassign_username');
         $user_id = $userinfo->userid;

         // decide if we need to reassign
         if (($reassign == '1') && (trim($reassign_to))) {
            // see if we have a valid user
            $query = $db->getQuery(true)
               ->select('*')
               ->from('#__users')
               ->where('user_login = ' . $db->quote($reassign_to));

            $db->setQuery($query);
            $result = $db->loadObject();
            if (!$result) {
               $reassign = '';
            } else {
               $reassign = $result->ID;
            }
         } else {
            $reassign = '';
         }

         // handle posts and links
         if ($reassign) {
            $query = $db->getQuery(true)
               ->select('ID')
               ->from('#__posts')
               ->where('post_author = ' . $user_id);

            $db->setQuery($query);
            if ($db->execute()) {
               $results = $db->loadObjectList();
               if ($results) {
                  foreach ($results as $row) {
                     $query = $db->getQuery(true)
                        ->update('#__posts')
                        ->set('post_author = ' . $reassign)
                        ->where('ID = ' . (int)$row->ID);

                     $db->setQuery($query);
                     $db->execute();
                  }
                  $status['debug'][] = 'Reassigned posts from user with id ' . $user_id . ' to user ' . $reassign;
               }

               $query = $db->getQuery(true)
                  ->select('link_id')
                  ->from('#__links')
                  ->where('link_owner = ' . $user_id);

               $db->setQuery($query);
               if ($db->execute()) {
                  $results = $db->loadObjectList();
                  if ($results) {
                     foreach ($results as $row) {
                        $query = $db->getQuery(true)
                           ->update('#__links')
                           ->set('link_owner = ' . $reassign)
                           ->where('link_id = ' . $row->link_id);

                        $db->setQuery($query);
                        $db->execute();
                     }
                     $status['debug'][] = 'Reassigned links from user with id ' . $user_id . ' to user ' . $reassign;
                  }
               }
            }
         } else {
            $query = $db->getQuery(true)
               ->delete('#__posts')
               ->where('post_author = ' . $user_id);

            $db->setQuery($query);
            $db->execute();
            $status['debug'][] = 'Deleted posts from user with id ' . $user_id;

            $query = $db->getQuery(true)
               ->delete('#__links')
               ->where('link_owner = ' . $user_id);

            $db->setQuery($query);
            $db->execute();
            $status['debug'][] = 'Deleted links from user ' . $user_id;
         }
         // now delete the user
         $query = $db->getQuery(true)
            ->delete('#__users')
            ->where('ID = ' . $user_id);

         $db->setQuery($query);
         $db->execute();
         $status['debug'][] = 'Deleted userrecord of user with userid ' . $user_id;

         // delete usermeta
         $query = $db->getQuery(true)
            ->delete('#__usermeta')
            ->where('user_id = ' . $user_id);

         $db->setQuery($query);
         $db->execute();
         $status['debug'][] = 'Deleted usermetarecord of user with userid ' . $user_id;
      } catch (Exception $e) {
         $status['error'][] = $e->getMessage();
      }
      return $status;
   }

   /**
    * @param object $userinfo
    * @param object $existinguser
    * @param array $status
    *
    * @throws RuntimeException
    * @return void
    */
   public function updateUsergroup($userinfo, &$existinguser, &$status) {
      $usergroups = $this->getCorrectUserGroups($userinfo);
      if (empty($usergroups)) {
         throw new RuntimeException(JText::_('USERGROUP_MISSING'));
      } else {
         $db = JFusionFactory::getDatabase($this->getJname());

         $database_prefix = $this->params->get('database_prefix');

         $caps = array();
         foreach ($usergroups as $usergroup) {
            $newgroupname = strtolower($this->helper->getUsergroupNameWP($usergroup));
            $caps[$newgroupname] = '1';
         }

         $capsfield = serialize($caps);

         $query = $db->getQuery(true)
            ->update('#__usermeta')
            ->set('meta_value = ' . $db->quote($capsfield))
            ->where('meta_key = ' . $db->quote($database_prefix . 'capabilities'))
            ->where('user_id = ' . (int)$existinguser->userid);

         $db->setQuery($query);
         $db->execute();

         $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . implode(' , ', $usergroups);
      }
   }
}