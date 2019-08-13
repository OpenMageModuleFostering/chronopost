<?php
class Chronopost_Chronorelais_Block_Adminhtml_System_Config_Rdvconfig extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate('chronorelais/config/rdvconfig.phtml');
        }
        return $this;
    }

    /**
     * Get the button and scripts contents
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $html = parent::_getElementHtml($element);
        $this->addData(array(
            'html_id' => $element->getHtmlId(),
            'value' => $element->getValue()
        ));

        return $html.$this->_toHtml();
    }
}
