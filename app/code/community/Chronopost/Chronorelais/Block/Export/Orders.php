<?php
/**
 * Chronopost Chronorelais module
 *
 * @category   Chronopost
 * @package    Chronopost_Chronorelais
 * @license    http://www.opensource.org/licenses/gpl-3.0.html GNU General Public License version 3
 */
class Chronopost_Chronorelais_Block_Export_Orders extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'chronorelais';
        $this->_controller = 'export_orders';
        $this->_headerText = Mage::helper('chronorelais')->__('Exporter vers ChronoShip Office ou ChronoShip Station');
        parent::__construct();
        $this->_removeButton('add');
    }

}
