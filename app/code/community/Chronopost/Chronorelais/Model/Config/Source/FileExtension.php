<?php
class Chronopost_Chronorelais_Model_Config_Source_FileExtension
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'.txt', 'label'=>Mage::helper('chronorelais')->__('.txt')),
            array('value'=>'.csv', 'label'=>Mage::helper('chronorelais')->__('.csv')),
            array('value'=>'.chr', 'label'=>Mage::helper('chronorelais')->__('.chr'))
        );
    }
}
