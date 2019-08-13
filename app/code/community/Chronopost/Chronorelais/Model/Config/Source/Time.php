<?php
class Chronopost_Chronorelais_Model_Config_Source_Time
{
    /**
     * 07:00 / 07 :30 / 08:00 / 08 :30 / 09:00 / 09 :30 / 10:00 / 10 :30 / 11:00
     * 11:30 / 12:00 / 12 :30 / 13:00 / 13 :30 / 14:00 / 14 :30 / 15:00
     * @return type
     */
    public function toOptionArray()
    {
        return array(
            array('value'=>'07:00', 'label'=>Mage::helper('chronorelais')->__('7 AM')),
            array('value'=>'07:30', 'label'=>Mage::helper('chronorelais')->__('7.30 AM')),
            array('value'=>'08:00', 'label'=>Mage::helper('chronorelais')->__('8 AM')),
            array('value'=>'08:30', 'label'=>Mage::helper('chronorelais')->__('8.30 AM')),
            array('value'=>'09:00', 'label'=>Mage::helper('chronorelais')->__('9 AM')),
            array('value'=>'09:30', 'label'=>Mage::helper('chronorelais')->__('9.30 AM')),
            array('value'=>'10:00', 'label'=>Mage::helper('chronorelais')->__('10 AM')),
            array('value'=>'10:30', 'label'=>Mage::helper('chronorelais')->__('10.30 AM')),
            array('value'=>'11:00', 'label'=>Mage::helper('chronorelais')->__('11 AM')),
            array('value'=>'11:30', 'label'=>Mage::helper('chronorelais')->__('11.30 AM')),
            array('value'=>'12:00', 'label'=>Mage::helper('chronorelais')->__('12 AM')),
            array('value'=>'12:30', 'label'=>Mage::helper('chronorelais')->__('12.30 AM')),
            array('value'=>'13:00', 'label'=>Mage::helper('chronorelais')->__('1 PM')),
            array('value'=>'13:30', 'label'=>Mage::helper('chronorelais')->__('1.30 PM')),
            array('value'=>'14:00', 'label'=>Mage::helper('chronorelais')->__('2 PM')),
            array('value'=>'14:30', 'label'=>Mage::helper('chronorelais')->__('2.30 PM')),
            array('value'=>'15:00', 'label'=>Mage::helper('chronorelais')->__('3 PM'))
        );
    }
}
