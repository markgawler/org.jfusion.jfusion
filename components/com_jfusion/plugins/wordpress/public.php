<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage wordpress
 * @author     JFusion Team -- Henk Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Public Class for phpBB3
 * For detailed descriptions on these functions please check JFusionPublic
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team -- Henk Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_wordpress extends JFusionPublic
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'wordpress';
    }

    /**
     * @return string
     */
    function getRegistrationURL() {
        return 'wp-login.php?action=register';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return 'wp-login.php?action=lostpassword';
    }

    /**
     * @return string
     */
    function getLostUsernameURL() {
        return 'wp-login.php?action=lostpassword';
    }
 }
