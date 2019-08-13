<?php
class Chronopost_Chronorelais_IndexController extends Mage_Core_Controller_Front_Action {

	public function shippingmethodimageAction() {
		$this->loadLayout();
        echo $this->getLayout()
                ->createBlock("checkout/onepage_shipping_method_available")
                ->setTemplate("chronorelais/checkout/onepage/shipping_method_images.phtml")
                ->toHtml();
	}
}