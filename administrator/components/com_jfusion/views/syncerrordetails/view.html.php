<?php

/**
 * This is view file for syncErrordetails
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage SyncErrordetails
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage SyncErrordetails
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewsyncerrordetails extends JViewLegacy
{
	/**
	 * @var $syncdata array
	 */
	var $syncdata;

	/**
	 * @var $synclog array
	 */
	var $synclog;

	/**
	 * @var $syncid string
	 */
	var $syncid;

    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed|string html output of view
     */
    function display($tpl = null)
    {
        $mainframe = JFactory::getApplication();
        // add the JFusion CSS
        $document = JFactory::getDocument();
        $template = $mainframe->getTemplate();
        $document->addStyleSheet('templates/' . $template . '/css/general.css');
        $css = 'table.jfusionlist, table.jfusiontable{ font-size:11px; }';
        $document->addStyleDeclaration($css);

        //check to see if the sync has already started
        $syncid = JFactory::getApplication()->input->get('syncid');
        $syncdata = JFusionUsersync::getSyncdata($syncid);
        $synclog = JFusionUsersync::getLogData($syncid, 'error');
	    $this->syncdata = $syncdata;
	    $this->synclog = $synclog;
	    $this->syncid = $syncid;
        parent::display($tpl);
    }
}
