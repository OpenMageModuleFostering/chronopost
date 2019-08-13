<?php
class Chronopost_Chronorelais_RelaisController extends Mage_Core_Controller_Front_Action {

	public function filterAction() {
		$this->loadLayout();
		$this->renderLayout();
	}

	public function detailAction() {
		$this->loadLayout();
		$this->renderLayout();
	}

	/**
	* Go to the tacking page
	* external function for mail link "Track Your Order" in shipment mail
	*/
    public function trackingAction()
    {
		if($this->getRequest()->isGet()) {
			if($hash = $this->getRequest()->getParam('hash')) {
				$req_values = Mage::helper('shipping')->decodeTrackingHash($hash);
				if($req_values) {
					$order = Mage::getModel('sales/order')->load($req_values['id']);
					$popup_url = Mage::helper('shipping')->getTrackingPopupUrlBySalesModel($order);
					header('location: '.$popup_url);
                    exit();
				}
			}
		}
	}

	/**
     * Get payment method step html
     *
     * @return string
     */
    protected function _getChronoRelaisHtml($shippingMethodCode = 'chronorelais') {
        return $this->getLayout()->getBlock('root')->setMethodCode($shippingMethodCode)->toHtml();
    }

	/**
     * Get relais
     */
    public function getRelaisAction() {
        $result = array();
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $address = $quote->getShippingAddress();
        $shippingMethod = $this->getRequest()->getPost('shipping_method');

        if (extension_loaded('soap')) {

            $shippingMethodCode = explode("_", $shippingMethod);
            $shippingMethodCode = $shippingMethodCode[0];

            $helper = Mage::helper('chronorelais/webservice');
            $webservbt = $helper->getPointRelaisByAddress($shippingMethodCode);

            if ($webservbt) {
                $this->loadLayout('checkout_onepage_shippingchronorelais');
                $result['goto_section'] = 'shipping-method';
                $result['update_section'] = array(
                    'name' => 'shipping-method-'.$shippingMethod,
                    'html' => $this->_getChronoRelaisHtml($shippingMethodCode)
                );
                $result['relaypoints'] = $webservbt;
            } else {
                $result['error'] = true;
                $result['message'] = $this->__('No point relay is associated with this postcode');
            }
        } else {
            $result['error'] = true;
            $result['message'] = $this->__('Sorry for inconvenience, The SOAP extension is not installed in the server. Please contact the site administrator.');
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    /**
     * Change shipping postal code
     */
    public function changePostalCodeAction() {

        $result = array();
        $webservbt = array();
        $postcode = $this->getRequest()->getPost('mappostalcode');
        $shippingMethod = $this->getRequest()->getPost('shipping_method');

        $shippingMethodCode = explode("_", $shippingMethod);
        $shippingMethodCode = $shippingMethodCode[0];

        if ($postcode) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $address = $quote->getShippingAddress();
            $address->setPostcode($postcode)
                    ->save()
                    ->setCollectShippingRates(true);

            $helper = Mage::helper('chronorelais/webservice');
            $webservbt =  $helper->getPointsRelaisByCp($postcode);

        }
        if ($webservbt) {
            $this->loadLayout('checkout_onepage_shippingchronorelais');
            $result['goto_section'] = 'shipping-method';
            $result['update_section'] = array(
                'name' => 'shipping-method-'.$shippingMethod,
                'html' => $this->_getChronoRelaisHtml($shippingMethodCode)
            );
            $result['relaypoints'] = $webservbt;
        } else {
            $result['error'] = true;
            $result['message'] = $this->__('No point relay is associated with this postcode');
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function resetSessionRelaiAction() {
        unset($_SESSION['chronopost_relais_id']);
    }

}