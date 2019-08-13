<?php
class Chronopost_Chronorelais_Block_Sales_Order_Shipment_View extends Mage_Adminhtml_Block_Sales_Order_Shipment_View
{

    public function __construct()
    {
        parent::__construct();

        //Ajout de l'impression de l'étiquette Chronopost
        $_order = $this->getShipment()->getOrder();
        $_shippingMethod = explode("_",$_order->getShippingMethod());
        if (($_shippingMethod[0] == 'chronorelais' || $_shippingMethod[0] == 'chronopost' || $_shippingMethod[0] == 'chronoexpress'))  {
            $this->_addButton('etiquette', array(
                'label'     => Mage::helper('chronorelais')->__('Etiquette Chronopost'),
                'class'     => 'save',
                'onclick'   => 'setLocation(\'' . $this->getPrintChronopostUrl() . '\')'
                )
            );
        }
    }

    public function getPrintChronopostUrl()
    {
        return $this->getUrl('adminhtml/chronorelais_sales_impression/print', array(
            'shipment_id' => $this->getShipment()->getId()
        ));
    }
}