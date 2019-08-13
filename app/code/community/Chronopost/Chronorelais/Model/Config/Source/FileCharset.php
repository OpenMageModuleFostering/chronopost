<?php
class Chronopost_Chronorelais_Model_Config_Source_FileCharset
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'ISO-8859-1', 'label'=>Mage::helper('chronorelais')->__('ISO-8859-1')),
            array('value'=>'UTF-8', 'label'=>Mage::helper('chronorelais')->__('UTF-8')),
            array('value'=>'ASCII-7', 'label'=>Mage::helper('chronorelais')->__('ASCII-7 Bits'))
        );
    }
}