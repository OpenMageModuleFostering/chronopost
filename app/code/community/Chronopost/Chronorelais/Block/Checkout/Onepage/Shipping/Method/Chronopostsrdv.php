<?php

class Chronopost_Chronorelais_Block_Checkout_Onepage_Shipping_Method_Chronopostsrdv extends Mage_Checkout_Block_Onepage_Abstract {

    protected function getCarrierModel() {
        return Mage::getSingleton("chronorelais/carrier_chronopostsrdv");
    }
    public function getSearchDeliverySlot() {
        /* appel WS SearchDeliverySlot pour récupérer les créneaux de livraison permettant de construire le semainier */
        $helper = Mage::helper('chronorelais/webservice');
        return $helper->getSearchDeliverySlot($this->getSrdvConfig());
    }

    public function getSrdvConfig() {
        $carrierModel = $this->getCarrierModel();
        return Mage::getStoreConfig('carriers/'.$carrierModel->getCarrierCode().'/rdv_config');
    }
}
