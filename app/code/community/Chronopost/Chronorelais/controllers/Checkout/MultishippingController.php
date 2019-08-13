<?php

require_once 'Mage/Checkout/controllers/MultishippingController.php';

class Chronopost_Chronorelais_Checkout_MultishippingController extends Mage_Checkout_MultishippingController {

    /**
     * Get payment method step html
     *
     * @return string
     */
    protected function _getChronoRelaisHtml() {
        return $this->getLayout()->getBlock('root')->toHtml();
    }

    public function shippingPostAction() {
        $shippingMethods = $this->getRequest()->getPost('shipping_method');
        try {
            Mage::dispatchEvent(
                    'checkout_controller_multishipping_shipping_post', array('request' => $this->getRequest(), 'quote' => $this->_getCheckout()->getQuote())
            );
            $this->_getCheckout()->setShippingMethods($shippingMethods);

            //WEC chronorelais
            $addresses = $this->_getCheckout()->getQuote()->getAllShippingAddresses();
            $relays = $this->getRequest()->getParam('shipping_method_chronorelais');
            foreach ($addresses as $address) {
                if (isset($shippingMethods[$address->getId()]) && substr($shippingMethods[$address->getId()], 0, 12) == "chronorelais" ) {
                    $relaisId = $relays[$address->getId()];
                    if ($relaisId != "") {

                        $helper = Mage::helper('chronorelais/webservice');
                        $relais = $helper->getDetailRelaisPoint($relaisId);

                        if ($relais) {
                            $address->setCity($relais->localite)
                                    ->setPostcode($relais->codePostal)
                                    ->setStreet(trim($relais->adresse1 . "\n" . $relais->adresse2 . " " . $relais->adresse3))
                                    ->setCompany($relais->nomEnseigne)
                                    ->setWRelayPointCode($relais->identifiantChronopostPointA2PAS)
                                    ->save()
                                    ->setCollectShippingRates(true);
                        }
                    }
                }
            }
            //ENDWEC chronorelais

            $this->_getState()->setActiveStep(
                    Mage_Checkout_Model_Type_Multishipping_State::STEP_BILLING
            );
            $this->_getState()->setCompleteStep(
                    Mage_Checkout_Model_Type_Multishipping_State::STEP_SHIPPING
            );
            $this->_redirect('*/*/billing');
        } catch (Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
            $this->_redirect('*/*/shipping');
        }
    }

    /**
     * Get relais
     */
    public function getMultiRelaisAction() {
        $result = array();
        $postcode = $this->getRequest()->get('zip', '');

        if (extension_loaded('soap')) {
            $addresses = $this->_getCheckout()->getQuote()->getAllShippingAddresses();
            foreach ($addresses as $address) {
                if ($address->getId() == $this->getRequest()->get('index')) {

                    $helper = Mage::helper('chronorelais/webservice');
                    $webservbt =  $helper->getPointsRelaisByCp($postcode);

                    if ($webservbt) {
                        Mage::getSingleton('core/session')->setMultiPostcode($postcode);
                        $this->loadLayout('checkout_multishipping_shippingchronorelais');
                        $result['update_section'] = array(
                            'name' => 'checkout-shipping-method-chronorelais-load_' . $this->getRequest()->get('index'),
                            'html' => str_replace("%%id%%", $this->getRequest()->get('index'), $this->_getChronoRelaisHtml())
                        );
                        $result['relaypoints'] = $webservbt;
                    } else {
                        $result['error'] = true;
                        $result['message'] = $this->__('No point relay is associated with this postcode');
                    }

                    break;
                }
            }
            if (count($result) == 0) {
                $result['error'] = true;
                $result['message'] = $this->__('Issue with addresses');
            }
        } else {
            $result['error'] = true;
            $result['message'] = $this->__('Sorry for inconvenience, The SOAP extension is not installed in the server. Please contact the site administrator.');
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

}
