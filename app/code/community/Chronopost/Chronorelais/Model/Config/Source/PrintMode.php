<?php
class Chronopost_Chronorelais_Model_Config_Source_PrintMode
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'PDF', 'label'=>Mage::helper('chronorelais')->__('Print PDF Laser with proof')),
            array('value'=>'SPD', 'label'=>Mage::helper('chronorelais')->__('Print PDF laser without proof')),
            array('value'=>'THE', 'label'=>Mage::helper('chronorelais')->__('Print PDF thermal'))
        );
    }
}
