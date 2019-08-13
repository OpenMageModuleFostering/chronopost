<?php

class Chronopost_Chronorelais_Block_Sales_Impression extends Mage_Adminhtml_Block_Widget_Grid_Container
{


    public function __construct()
    {
        $this->_blockGroup = 'chronorelais';
        $this->_controller = 'sales_shipment';
        $this->_headerText = Mage::helper('chronorelais')->__('Impressions des étiquettes Chronopost');
        parent::__construct();
        $this->_removeButton('add');
    }

}