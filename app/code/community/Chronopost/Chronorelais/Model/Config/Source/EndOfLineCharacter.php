<?php
class Chronopost_Chronorelais_Model_Config_Source_EndOfLineCharacter
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'lf', 'label'=>Mage::helper('chronorelais')->__('LF')),
            array('value'=>'cr', 'label'=>Mage::helper('chronorelais')->__('CR')),
            array('value'=>'crlf', 'label'=>Mage::helper('chronorelais')->__('CR+LF'))
        );
    }
}
