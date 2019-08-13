<?php
class Chronopost_Chronorelais_Block_Sales_Shipment_Grid_Renderer_Retoursav
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{

    public function render(Varien_Object $row)
    {
        $actions = $this->getColumn()->getActions();
        if ( empty($actions) || !is_array($actions) ) {
            return '&nbsp;';
        }
        $countryId = $row->getShippingAddress()->getCountryId();

        if($countryId != 'FR')
            return '';
        $out = '';
        $i = 0;
        foreach ($actions as $action){
            if (is_array($action) ) {
                if($i > 0)
                    $out .= '<br />';
                $out .= $this->_toLinkHtml($action, $row);
                $i++;
            }
        }
        return $out;
    }
}
