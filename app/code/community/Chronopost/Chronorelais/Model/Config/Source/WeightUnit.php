<?php
class Chronopost_Chronorelais_Model_Config_Source_WeightUnit
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'kg', 'label'=>Mage::helper('chronorelais')->__('Kilogramme')),
            array('value'=>'g', 'label'=>Mage::helper('chronorelais')->__('gramme')),
        );
    }
}
