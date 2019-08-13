<?php
class Chronopost_Chronorelais_Model_Config_Source_FieldDelimiter
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'none', 'label'=>Mage::helper('chronorelais')->__('None')),
            array('value'=>'simple_quote', 'label'=>Mage::helper('chronorelais')->__('Simple Quote')),
            array('value'=>'double_quotes', 'label'=>Mage::helper('chronorelais')->__('Double Quotes'))
        );
    }
}