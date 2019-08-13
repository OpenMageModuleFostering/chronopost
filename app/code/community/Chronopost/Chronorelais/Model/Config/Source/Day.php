<?php
class Chronopost_Chronorelais_Model_Config_Source_Day
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'monday', 'label'=>Mage::helper('chronorelais')->__('Monday')),
            array('value'=>'tuesday', 'label'=>Mage::helper('chronorelais')->__('Tuesday')),
            array('value'=>'wednesday', 'label'=>Mage::helper('chronorelais')->__('Wednesday')),
            array('value'=>'thursday', 'label'=>Mage::helper('chronorelais')->__('Thursday')),
            array('value'=>'friday', 'label'=>Mage::helper('chronorelais')->__('Friday')),
            array('value'=>'saturday', 'label'=>Mage::helper('chronorelais')->__('Saturday')),
            array('value'=>'sunday', 'label'=>Mage::helper('chronorelais')->__('Sunday'))
        );
    }
}
