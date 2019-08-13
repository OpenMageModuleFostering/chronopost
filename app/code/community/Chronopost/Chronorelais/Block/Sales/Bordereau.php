<?php

class Chronopost_Chronorelais_Block_Sales_Bordereau extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'chronorelais';
        $this->_controller = 'sales_bordereau';
        $this->_headerText = Mage::helper('chronorelais')->__('Bordereau de fin de journée');
        parent::__construct();
        $this->_removeButton('add');
    }

}