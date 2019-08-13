<?php

class Chronopost_Chronorelais_Block_Adminhtml_System_Config_Form_Field_Enabled extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
	{
            /* si mode de livraison pas dans son contrat => on le désactive */
            $id = $element->getId();
            $carrier = explode('_', $id);
            $carrier = $carrier[1];
            if(!Mage::helper('chronorelais')->shippingMethodEnabled($carrier))
            {
                $element->setDisabled('disabled');
                $element->setValue(0);
            }

            return $element->getElementHtml();
	}
}
