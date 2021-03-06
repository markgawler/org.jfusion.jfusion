<?php

/**
 * This is the jfusion Plugins element file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();
/**
 * JFusion Element class Plugins
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldJFusionPlugins extends JFormField
{
    public $type = 'JFusionPlugins';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
	    try {
		    $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('name as id, name as name')
			    ->from('#__jfusion');

		    $db->setQuery($query);
		    $rows = $db->loadObjectList();
		    if (!empty($rows)) {
			    $output = JHTML::_('select.genericlist', $rows, $this->name, 'size="1" class="inputbox"', 'id', 'name', $this->value);
		    } else {
			    throw new RuntimeException(JText::_('NO_VALID_PLUGINS'));
		    }
	    } catch (Exception $e) {
		    $output = '<span style="float:left; margin: 5px 0; font-weight: bold;">' . $e->getMessage() . '</span>';
	    }
	    return $output;
    }
}
