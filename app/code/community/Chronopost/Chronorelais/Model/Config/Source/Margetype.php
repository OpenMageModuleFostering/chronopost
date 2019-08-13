<?php
class Chronopost_Chronorelais_Model_Config_Source_Margetype
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'prcent', 'label'=>Mage::helper('chronorelais')->__('Percentage')." (%)"),
            array('value'=>'amount', 'label'=>Mage::helper('chronorelais')->__('Amount')." (€)")
        );
    }
}
