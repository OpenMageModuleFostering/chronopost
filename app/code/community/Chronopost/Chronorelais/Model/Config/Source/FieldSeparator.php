<?php
class Chronopost_Chronorelais_Model_Config_Source_FieldSeparator
{
    public function toOptionArray()
    {
        return array(
            array('value'=>';', 'label'=>Mage::helper('chronorelais')->__('Semicolon')),
            array('value'=>',', 'label'=>Mage::helper('chronorelais')->__('Comma'))
        );
    }
}
