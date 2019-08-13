<?php

class Chronopost_Chronorelais_Model_Observer
{
    public function checkSoapExists() {
        if (!extension_loaded('soap') && (Mage::helper('chronorelais')->getConfigData('carriers/chronopost/active') || Mage::helper('chronorelais')->getConfigData('carriers/chronorelais/active') || Mage::helper('chronorelais')->getConfigData('carriers/chronoexpress/active'))) {
            Mage::getSingleton('checkout/session')->addError(Mage::helper('chronorelais')->__('The SOAP extension is not installed in the server. Please contact the site administrator. Sorry for inconvenience.'));
            Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/cart"));
            return;
        }
    }

    public function saveBillingBefore() {
        $request = Mage::app()->getRequest();
        $data = $request->getPost('billing', array());
        $customerAddressId = $request->getPost('billing_address_id', false);

        if (isset($data['use_for_shipping']) && $data['use_for_shipping'] == 1) {

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
    }

    public function saveShippingBefore() {
        $request = Mage::app()->getRequest();
        $data = $request->getPost('shipping', array());
        $customerAddressId = $request->getPost('shipping_address_id', false);

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

    public function saveShippingMethodBefore() {
        $request = Mage::app()->getRequest();
        $onepage = Mage::getSingleton('checkout/type_onepage');
        //WEC chronorelais

        if ($_SESSION["customer_shipping_address_reference"]["available"]) {
            $data = $_SESSION["customer_shipping_address_reference"]["data"];
            $customerAddressId = $_SESSION["customer_shipping_address_reference"]["customerAddressId"];
            $_SESSION["customer_shipping_address_reference"]["available"] = false;

            $result = $onepage->saveShipping($data, $customerAddressId);
            if(!Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links')) {
                Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
            }
        }

        $method = $request->getParam('shipping_method');
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        $address = $quote->getShippingAddress();

        $shippingMethodSelected = $request->getParam('shipping_method');

        $methodTitle = "";

        /* Chronorelais */
        if(strpos($shippingMethodSelected,'chronorelais') !== false) {
            $relaisId = $request->getParam('shipping_method_chronorelais');
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

                    $_SESSION['chronopost_relais_id'] = $relaisId;

                    $_SESSION["customer_shipping_address_reference"]["available"] = true;

                    if (isset($relais->localite)) {
                        $methodTitle = ' - ' . $relais->nomEnseigne . ' - ' . trim($relais->adresse1 . " " . $relais->adresse2 . " " . $relais->adresse3) . ' - ' . $relais->codePostal . ' - ' . $relais->localite;
                    }
                }
            } else {
                if(!Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links')) {
                    $result['error'] = true;
                    $result['message'] = Mage::helper('chronorelais')->__('Veuillez sélectionner votre point relais');
                    echo Mage::helper('core')->jsonEncode($result);
                    exit;
                }
            }
        } else {
            unset($_SESSION['chronopost_relais_id']);
        }

        /* Chronopostsrdv */
        if(strpos($shippingMethodSelected,'chronopostsrdv') !== false) {
            $rdvInfo = $request->getParam('chronopostsrdv_creneaux_info');

            if ($rdvInfo != "") {
                $helper = Mage::helper('chronorelais/webservice');

                /* vérification creneau ok */
                $rdvInfo = json_decode($rdvInfo,true);

                $confirm = $helper->confirmDeliverySlot($rdvInfo);
                if($confirm->return->code == 0) {

                    $rdvInfo['productCode'] = $confirm->return->productService->productCode;
                    $rdvInfo['serviceCode'] = $confirm->return->productService->serviceCode;

                    $_SESSION['chronopostsrdv_creneaux_info'] = $rdvInfo;

                    /* Important : permet de mettre à jour libellé method + prix */
                    $address->setChronopostsrdvCreneauxInfo(json_encode($rdvInfo))
                        ->save()
                        ->setCollectShippingRates(true);

                } else {
                    $result['error'] = true;
                    $result['message'] = Mage::helper('chronorelais')->__($confirm->return->message);
                    echo Mage::helper('core')->jsonEncode($result);
                    exit;
                }

            } else {
                if(!Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links')) {
                    $result['error'] = true;
                    $result['message'] = Mage::helper('chronorelais')->__('Veuillez sélectionner votre horaire de rendez-vous');
                    echo Mage::helper('core')->jsonEncode($result);
                    exit;
                }
            }
        } else {
            unset($_SESSION['chronopostsrdv_creneaux_info']);
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
    }

    public function updateShippingAddress($observer) {

        $address = $observer->getEvent()->getAddress();
        $shippingMethodSelected = $address->getShippingMethod();

        $onepage = Mage::getSingleton('checkout/type_onepage');
        //WEC chronorelais

        if(strpos($shippingMethodSelected,'chronorelais') !== false) {
            $relaisId = $_SESSION['chronopost_relais_id'];
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
            } else {
                /*$result['error'] = true;
                $result['message'] = Mage::helper('chronorelais')->__('Veuillez sélectionner votre point relais');
                echo Mage::helper('core')->jsonEncode($result);
                exit;*/
            }
        }

        /* Chronopostsrdv */
        if(strpos($shippingMethodSelected,'chronopostsrdv') !== false) {
            $rdvInfo = $_SESSION['chronopostsrdv_creneaux_info'];

            if ($rdvInfo != "") {
                $helper = Mage::helper('chronorelais/webservice');

                /* vérification creneau ok */
                $confirm = $helper->confirmDeliverySlot($rdvInfo);

                if($confirm->return->code == 0) {

                    $rdvInfo['productCode'] = $confirm->return->productService->productCode;
                    $rdvInfo['serviceCode'] = $confirm->return->productService->serviceCode;

                    /* Important : permet de mettre à jour libellé method + prix */
                    $address->setChronopostsrdvCreneauxInfo(json_encode($rdvInfo))
                        ->setCollectShippingRates(true)->collectShippingRates()->save();

                } else {
                    $result['error'] = true;
                    $result['message'] = Mage::helper('chronorelais')->__($confirm->return->message);
                    echo Mage::helper('core')->jsonEncode($result);
                    exit;
                }

            } else {
                if(!Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links')) {
                    $result['error'] = true;
                    $result['message'] = Mage::helper('chronorelais')->__('Veuillez sélectionner votre horaire de rendez-vous');
                    echo Mage::helper('core')->jsonEncode($result);
                    exit;
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
    }

    public function checkRelayPoint($observer) {

        if(Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links')) {
            $_quote = Mage::helper('checkout')->getQuote();
            $address = $_quote->getShippingAddress();

            $shippingMethodSelected = $address->getShippingMethod();

            if(strpos($shippingMethodSelected,'chronorelais') !== false) {
                $relaisId = $_SESSION['chronopost_relais_id'];
                if ($relaisId == "") {
                    Mage::throwException(Mage::helper('checkout')->__('Veuillez sélectionner votre point relais'));
                }
            }
        }

        unset($_SESSION['chronopostsrdv_creneaux_info']);
    }


}