<?php
class Chronopost_Chronorelais_Block_Adminhtml_Form_Renderer_Config_Date extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $element->setStyle('width:70px;')
            ->setName($element->getName() . '[]');

        if ($element->getValue()) {
            $values = explode(':', $element->getValue());
        } else {
            $values = array();
        }

        $date = $element->setValues(Mage::getSingleton('chronorelais/config_source_day')->toOptionArray())->setValue(isset($values[0]) ? $values[0] : null)->getElementHtml();
        $heure = $element->setValues(Mage::getSingleton('chronorelais/config_source_hour')->toOptionArray())->setValue(isset($values[1]) ? $values[1] : null)->getElementHtml();
        $minutes = $element->setValues(Mage::getSingleton('chronorelais/config_source_minute')->toOptionArray())->setValue(isset($values[2]) ? $values[2] : null)->getElementHtml();
        return Mage::helper('adminhtml')->__('Date') . ' : ' . $date
            . ' '
            . Mage::helper('adminhtml')->__('Heure') . ' : ' . $heure.' '.$minutes;
    }
}
