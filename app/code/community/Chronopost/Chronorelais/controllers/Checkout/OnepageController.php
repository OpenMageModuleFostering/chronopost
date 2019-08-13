<?php

require_once 'Mage/Checkout/controllers/OnepageController.php';

class Chronopost_Chronorelais_Checkout_OnepageController extends Mage_Checkout_OnepageController {

    protected $_sectionUpdateFunctions = array(
        'payment-method' => '_getPaymentMethodsHtml',
        'shipping-method' => '_getShippingMethodsHtml',
        'review' => '_getReviewHtml',
        'shipping-method-chronorelais' => '_getReviewHtml',
    );

    /**
     * Get payment method step html
     *
     * @return string
     */
    protected function _getChronoRelaisHtml() {
        return $this->getLayout()->getBlock('root')->toHtml();
    }

    /**
     * Checkout page
     */
    public function indexAction() {
        if (!extension_loaded('soap')) {
            if (Mage::helper('chronorelais')->getConfigData('carriers/chronopost/active') || Mage::helper('chronorelais')->getConfigData('carriers/chronorelais/active') || Mage::helper('chronorelais')->getConfigData('carriers/chronoexpress/active')) {
                Mage::getSingleton('checkout/session')->addError($this->__('The SOAP extension is not installed in the server. Please contact the site administrator. Sorry for inconvenience.'));
                $this->_redirect('checkout/cart');
                return;
            }
        }
        parent::indexAction();
    }

    /**
     * save checkout billing address
     */
    public function saveBillingAction() {
        if ($this->_expireAjax()) {
            return;
        }
        if ($this->getRequest()->isPost()) {
//      $postData = $this->getRequest()->getPost('billing', array());
//      $data = $this->_filterPostData($postData);
            $data = $this->getRequest()->getPost('billing', array());
            $customerAddressId = $this->getRequest()->getPost('billing_address_id', false);

            if (isset($data['email'])) {
                $data['email'] = trim($data['email']);
            }
            $result = $this->getOnepage()->saveBilling($data, $customerAddressId);

            if (!isset($result['error'])) {
                /* check quote for virtual */
                if ($this->getOnepage()->getQuote()->isVirtual()) {
                    $result['goto_section'] = 'payment';
                    $result['update_section'] = array(
                        'name' => 'payment-method',
                        'html' => $this->_getPaymentMethodsHtml()
                    );
                } elseif (isset($data['use_for_shipping']) && $data['use_for_shipping'] == 1) {
                    $result['goto_section'] = 'shipping_method';
                    $result['update_section'] = array(
                        'name' => 'shipping-method',
                        'html' => $this->_getShippingMethodsHtml()
                    );

                    $result['allow_sections'] = array('shipping');
                    $result['duplicateBillingInfo'] = 'true';

                    //WEC chronorelais
                    if (isset($_SESSION["customer_shipping_address_reference"])) {
                        unset($_SESSION["customer_shipping_address_reference"]);
                    }

                    if (!array_key_exists("company", $data)) {
                        $data["company"] = "";
                    }

                    $_SESSION["customer_shipping_address_reference"]["data"] = $data;
                    $_SESSION["customer_shipping_address_reference"]["customerAddressId"] = $customerAddressId;
                    $_SESSION["customer_shipping_address_reference"]["available"] = false;
                    //ENDWEC
                } else {
                    $result['goto_section'] = 'shipping';
                }
            }
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }

    /**
     * Shipping address save action
     */
    public function saveShippingAction() {
        if ($this->_expireAjax()) {
            return;
        }
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shipping', array());
            $customerAddressId = $this->getRequest()->getPost('shipping_address_id', false);
            $result = $this->getOnepage()->saveShipping($data, $customerAddressId);

            if (!isset($result['error'])) {
                $result['goto_section'] = 'shipping_method';
                $result['update_section'] = array(
                    'name' => 'shipping-method',
                    'html' => $this->_getShippingMethodsHtml()
                );
            }
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }

        //WEC chronorelais
        if (isset($_SESSION["customer_shipping_address_reference"])) {
            unset($_SESSION["customer_shipping_address_reference"]);
        }
        if (!array_key_exists("company", $data)) {
            $data["company"] = "";
        }
        $_SESSION["customer_shipping_address_reference"]["data"] = $data;
        $_SESSION["customer_shipping_address_reference"]["customerAddressId"] = $customerAddressId;
        $_SESSION["customer_shipping_address_reference"]["available"] = false;

        //ENDWEC
    }

    /**
     * Shipping method save action
     */
    public function saveShippingMethodAction() {
        if ($this->_expireAjax()) {
            return;
        }
        if ($this->getRequest()->isPost()) {

            //WEC chronorelais
            if ($_SESSION["customer_shipping_address_reference"]["available"]) {
                $data = $_SESSION["customer_shipping_address_reference"]["data"];
                $customerAddressId = $_SESSION["customer_shipping_address_reference"]["customerAddressId"];
                $_SESSION["customer_shipping_address_reference"]["available"] = false;

                $result = $this->getOnepage()->saveShipping($data, $customerAddressId);
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
            }

            $method = $this->getRequest()->getParam('shipping_method');
            $quote = $this->getOnepage()->getQuote(); //Mage::getSingleton('checkout/cart')->init()->getQuote();
            $address = $quote->getShippingAddress();

            if (substr($this->getRequest()->getParam('shipping_method'), 0, 12) == "chronorelais") {
                $relaisId = $this->getRequest()->getParam('shipping_method_chronorelais');
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

                        $_SESSION["customer_shipping_address_reference"]["available"] = true;
                    }
                }
            }

            $methodTitle = "";
            if (isset($relais->localite)) {
                $methodTitle = ' - ' . $relais->nomEnseigne . ' - ' . trim($relais->adresse1 . " " . $relais->adresse2 . " " . $relais->adresse3) . ' - ' . $relais->codePostal . ' - ' . $relais->localite;
            }
            if ($method) {
                foreach ($address->getAllShippingRates() as $rate) {
                    if ($rate->getCode() == $method) {
                        $address->setShippingDescription($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle() . $methodTitle);
                        break;
                    }
                }
            }
            //ENDWEC chronorelais

            $data = $this->getRequest()->getPost('shipping_method', '');
            $result = $this->getOnepage()->saveShippingMethod($data);
            /*
              $result will have erro data if shipping method is empty
             */
            if (!$result) {
                Mage::dispatchEvent('checkout_controller_onepage_save_shipping_method', array('request' => $this->getRequest(),
                    'quote' => $this->getOnepage()->getQuote()));
                $this->getOnepage()->getQuote()->collectTotals();
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));

                $result['goto_section'] = 'payment';
                $result['update_section'] = array(
                    'name' => 'payment-method',
                    'html' => $this->_getPaymentMethodsHtml()
                );
            }
            $this->getOnepage()->getQuote()->collectTotals()->save();
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }

    /**
     * Get relais
     */
    public function getRelaisAction() {
        if ($this->_expireAjax()) {
            return;
        }
        $result = array();
        $quote = $this->getOnepage()->getQuote(); //Mage::getSingleton('checkout/cart')->init()->getQuote();
        $address = $quote->getShippingAddress();
        $postcode = $address->getPostcode();

        if (extension_loaded('soap')) {

            $helper = Mage::helper('chronorelais/webservice');
            $webservbt = $helper->getPointRelaisByAddress();

            if ($webservbt) {
                $this->loadLayout('checkout_onepage_shippingchronorelais');
                $result['goto_section'] = 'shipping-method';
                $result['update_section'] = array(
                    'name' => 'shipping-method-chronorelais',
                    'html' => $this->_getChronoRelaisHtml()
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
        if ($this->_expireAjax()) {
            return;
        }
        $result = array();
        $webservbt = array();
        $postcode = $this->getRequest()->getPost('mappostalcode');
        if ($postcode) {
            $quote = $this->getOnepage()->getQuote(); //Mage::getSingleton('checkout/cart')->init()->getQuote();
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
                'name' => 'shipping-method-chronorelais',
                'html' => $this->_getChronoRelaisHtml()
            );
            $result['relaypoints'] = $webservbt;
        } else {
            $result['error'] = true;
            $result['message'] = $this->__('No point relay is associated with this postcode');
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

}