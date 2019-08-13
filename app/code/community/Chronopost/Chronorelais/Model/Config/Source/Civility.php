<?php
class Chronopost_Chronorelais_Model_Config_Source_Civility
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'E', 'label'=>Mage::helper('chronorelais')->__('Madame')),
            array('value'=>'L', 'label'=>Mage::helper('chronorelais')->__('Mademoiselle')),
            array('value'=>'M', 'label'=>Mage::helper('chronorelais')->__('Monsieur'))
        );
    }
}