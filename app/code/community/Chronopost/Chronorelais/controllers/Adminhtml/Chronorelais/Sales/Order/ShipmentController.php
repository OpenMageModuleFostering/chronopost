<?php

require_once 'Mage/Adminhtml/controllers/Sales/Order/ShipmentController.php';

class Chronopost_Chronorelais_Adminhtml_Chronorelais_Sales_Order_ShipmentController extends Mage_Adminhtml_Sales_Order_ShipmentController {

    /**
     * Save shipment
     * We can save only new shipment. Existing shipments are not editable
     */
    public function getFilledValue($value) {
        if ($value) {
            return $this->removeaccents(trim($value));
        } else {
            return '';
        }
    }

    public function checkMobileNumber($value) {
        if ($reqvalue = trim($value)) {
            $_number = substr($reqvalue, 0, 2);
            $fixed_array = array('01', '02', '03', '04', '05', '06', '06');
            if (in_array($_number, $fixed_array)) {
                return $reqvalue;
            } else {
                return '';
            }
        }
    }

    protected function saveAndCreateEtiquette($shipment,$data) {
        $shipment->register();

        //Si l'expedition est réalisé par Mondial Relay, on créé le tracking automatiquement.

        $_order = $shipment->getOrder();
        $_shippingMethod = explode("_", $_order->getShippingMethod());

        $_shippingAddress = $shipment->getShippingAddress();
        $_billingAddress = $shipment->getBillingAddress();
        $_helper = Mage::helper('chronorelais');

        $shippingMethodAllow = array('chronorelais','chronopost','chronoexpress','chronopostc10','chronopostc18','chronopostcclassic','chronorelaiseurope','chronorelaisdom','chronopostsrdv','chronopostsameday');
        if (in_array($_shippingMethod[0],$shippingMethodAllow)) {

            $esdParams = $header = $shipper = $customer = $recipient = $ref = $skybill = $skybillParams = $password = array();

            //header parameters
            $header = array(
                'idEmit' => 'MAG',
                'accountNumber' => $_helper->getConfigurationAccountNumber(),
                'subAccount' => $_helper->getConfigurationSubAccountNumber()
            );

            //shipper parameters
            $shipperMobilePhone = $this->checkMobileNumber($_helper->getConfigurationShipperInfo('mobilephone'));
            $shipper = array(
                'shipperAdress1' => $_helper->getConfigurationShipperInfo('address1'),
                'shipperAdress2' => $_helper->getConfigurationShipperInfo('address2'),
                'shipperCity' => $_helper->getConfigurationShipperInfo('city'),
                'shipperCivility' => $_helper->getConfigurationShipperInfo('civility'),
                'shipperContactName' => $_helper->getConfigurationShipperInfo('contactname'),
                'shipperCountry' => $_helper->getConfigurationShipperInfo('country'),
                'shipperEmail' => $_helper->getConfigurationShipperInfo('email'),
                'shipperMobilePhone' => $shipperMobilePhone,
                'shipperName' => $_helper->getConfigurationShipperInfo('name'),
                'shipperName2' => $_helper->getConfigurationShipperInfo('name2'),
                'shipperPhone' => $_helper->getConfigurationShipperInfo('phone'),
                'shipperPreAlert' => '',
                'shipperZipCode' => $_helper->getConfigurationShipperInfo('zipcode')
            );

            //customer parameters
            $customerMobilePhone = $this->checkMobileNumber($_helper->getConfigurationCustomerInfo('mobilephone'));
            $customer = array(
                'customerAdress1' => $_helper->getConfigurationCustomerInfo('address1'),
                'customerAdress2' => $_helper->getConfigurationCustomerInfo('address2'),
                'customerCity' => $_helper->getConfigurationCustomerInfo('city'),
                'customerCivility' => $_helper->getConfigurationCustomerInfo('civility'),
                'customerContactName' => $_helper->getConfigurationCustomerInfo('contactname'),
                'customerCountry' => $_helper->getConfigurationCustomerInfo('country'),
                'customerEmail' => $_helper->getConfigurationCustomerInfo('email'),
                'customerMobilePhone' => $customerMobilePhone,
                'customerName' => $_helper->getConfigurationCustomerInfo('name'),
                'customerName2' => $_helper->getConfigurationCustomerInfo('name2'),
                'customerPhone' => $_helper->getConfigurationCustomerInfo('phone'),
                'customerPreAlert' => '',
                'customerZipCode' => $_helper->getConfigurationCustomerInfo('zipcode')
            );

            //recipient parameters
            $recipient_address = $_shippingAddress->getStreet();
            if (!isset($recipient_address[1])) {
                $recipient_address[1] = '';
            }
            $customer_email = ($_shippingAddress->getEmail()) ? $_shippingAddress->getEmail() : ($_billingAddress->getEmail() ? $_billingAddress->getEmail() : $_order->getCustomerEmail());
            $recipientMobilePhone = $this->checkMobileNumber($_shippingAddress->getTelephone());
            $recipientName = $this->getFilledValue($_shippingAddress->getCompany()); //RelayPoint Name if chronorelais or Companyname if chronopost and
            $recipientName2 = $this->getFilledValue($_shippingAddress->getFirstname() . ' ' . $_shippingAddress->getLastname());

            //remove any alphabets in phone number
            $recipientPhone = trim(preg_replace("/[^0-9\.\-]/", " ", $_shippingAddress->getTelephone()));

            $recipient = array(
                'recipientAdress1' => substr($this->getFilledValue($recipient_address[0]), 0, 38),
                'recipientAdress2' => substr($this->getFilledValue($recipient_address[1]), 0, 38),
                'recipientCity' => $this->getFilledValue($_shippingAddress->getCity()),
                'recipientContactName' => $recipientName2,
                'recipientCountry' => $this->getFilledValue($_shippingAddress->getCountryId()),
                'recipientEmail' => $customer_email,
                'recipientMobilePhone' => $recipientMobilePhone,
                'recipientName' => $recipientName,
                'recipientName2' => $recipientName2,
                'recipientPhone' => $recipientPhone,
                'recipientPreAlert' => '',
                'recipientZipCode' => $this->getFilledValue($_shippingAddress->getPostcode()),
            );

            //ref parameters
            $recipientRef = $this->getFilledValue($_shippingAddress->getWRelayPointCode());
            if (!$recipientRef) {
                $recipientRef = $_order->getCustomerId();
            }
            $shipperRef = $_order->getRealOrderId();

            $ref = array(
                'recipientRef' => $recipientRef,
                'shipperRef' => $shipperRef
            );

            //skybill parameters
            /* Livraison Samedi (Delivery Saturday) field */
            $SaturdayShipping = 0; //default value for the saturday shipping
            if ($_shippingMethod[0] == "chronopost" || $_shippingMethod[0] == "chronorelais" || $_shippingMethod[0] == "chronorelaisdom") {
                if (!$_deliver_on_saturday = Mage::helper('chronorelais')->getLivraisonSamediStatus($_order->getEntityId())) {
                    $_deliver_on_saturday = Mage::helper('chronorelais')->getConfigData('carriers/' . $_shippingMethod[0] . '/deliver_on_saturday');
                } else {
                    if ($_deliver_on_saturday == 'Yes') {
                        $_deliver_on_saturday = 1;
                    } else {
                        $_deliver_on_saturday = 0;
                    }
                }
                $is_sending_day = Mage::helper('chronorelais')->isSendingDay();

                if($_shippingMethod[0] == "chronorelaisdom") {
                    if ($_deliver_on_saturday && $is_sending_day) {
                        $SaturdayShipping = 369;
                    } else {
                        $SaturdayShipping = 368;
                    }
                } else {
                    if ($_deliver_on_saturday && $is_sending_day) {
                        $SaturdayShipping = 6;
                    } elseif (!$_deliver_on_saturday && $is_sending_day) {
                        $SaturdayShipping = 1;
                    }
                }

            }

            $weight = 0;
            foreach ($shipment->getItemsCollection() as $item) {
                $weight += $item->weight * $item->qty;
            }
            if ($_helper->getConfigWeightUnit() == 'g') {
                $weight = $weight / 1000; /* conversion g => kg */
            }

            /* si chronorelaiseurope : service : 337 si poids < 3kg ou 338 si > 3kg */
            if($_shippingMethod[0] == "chronorelaiseurope") {
                $weight <= 3 ? $SaturdayShipping = '337' : $SaturdayShipping = '338';
            }


            $weight = 0; /* On met le poids à 0 car les colis sont pesé sur place */

            $skybill = array(
                'codCurrency' => 'EUR',
                'codValue' => '',
                'content1' => '',
                'content2' => '',
                'content3' => '',
                'content4' => '',
                'content5' => '',
                'customsCurrency' => 'EUR',
                'customsValue' => '',
                'evtCode' => 'DC',
                'insuredCurrency' => 'EUR',
                'insuredValue' => '',
                'objectType' => 'MAR',
                'productCode' => $_helper->getChronoProductCodeToShipment($_shippingMethod[0]),
                'service' => $SaturdayShipping,
                'shipDate' => date('c'),
                'shipHour' => date('H'),
                'weight' => $weight,
                'weightUnit' => 'KGM'
            );

            $skybillParams = array(
                'mode' => $_helper->getConfigurationSkybillParam()
            );

            $expeditionArray = array(
                'headerValue' => $header,
                'shipperValue' => $shipper,
                'customerValue' => $customer,
                'recipientValue' => $recipient,
                'refValue' => $ref,
                'skybillValue' => $skybill,
                'skybillParamsValue' => $skybillParams,
                'password' => $_helper->getConfigurationAccountPass()
            );

            /* si chronopostsrdv : ajout parametres supplementaires */
            if($_shippingMethod[0] == "chronopostsrdv") {

                $chronopostsrdv_creneaux_info = $_shippingAddress->getData('chronopostsrdv_creneaux_info');
                $chronopostsrdv_creneaux_info = json_decode($chronopostsrdv_creneaux_info,true);

                $_dateRdvStart = new DateTime($chronopostsrdv_creneaux_info['deliveryDate']);
                $_dateRdvStart->setTime($chronopostsrdv_creneaux_info['startHour'],$chronopostsrdv_creneaux_info['startMinutes']);

                $_dateRdvEnd = new DateTime($chronopostsrdv_creneaux_info['deliveryDate']);
                $_dateRdvEnd->setTime($chronopostsrdv_creneaux_info['endHour'],$chronopostsrdv_creneaux_info['endMinutes']);


                $scheduledValue = array(
                    'appointmentValue' => array(
                        'timeSlotStartDate' => $_dateRdvStart->format("Y-m-d")."T".$_dateRdvStart->format("H:i:s"),
                        'timeSlotEndDate' => $_dateRdvEnd->format("Y-m-d")."T".$_dateRdvEnd->format("H:i:s"),
                        'timeSlotTariffLevel' => $chronopostsrdv_creneaux_info['tariffLevel']
                    )
                );
                $expeditionArray['scheduledValue'] = $scheduledValue;

                /* modification productCode et service car dynamique pour ce mode de livraison */

                $expeditionArray['skybillValue']['productCode'] = $chronopostsrdv_creneaux_info['productCode'];
                $expeditionArray['skybillValue']['service'] = $chronopostsrdv_creneaux_info['serviceCode'];

            }

            $tracking_order = '';

            $client = new SoapClient("https://www.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl", array('trace' => true));
            try {
                $expedition = $client->shippingV3($expeditionArray);

                if (!$expedition->return->errorCode && $expedition->return->skybillNumber) {
                    $track = Mage::getModel('sales/order_shipment_track')
                            ->setNumber($expedition->return->skybillNumber)
                            ->setChronoReservationNumber(base64_encode($expedition->return->skybill))
                            ->setCarrier(ucwords($_shippingMethod[0]))
                            ->setCarrierCode($_shippingMethod[0])
                            ->setTitle(ucwords($_shippingMethod[0]))
                            ->setPopup(1);
                    $shipment->addTrack($track);

                    $tracking_number = $expedition->return->skybillNumber;

                    $tracking_url = str_replace('{tracking_number}', $tracking_number, Mage::helper('chronorelais')->getConfigurationTrackingViewUrl());
                    $tracking_title = $this->__('Track Your Order');
                    $tracking_order = '<p><a title="' . $tracking_title . '" href="' . $tracking_url . '"><b>' . $tracking_title . '</b></a></p>';

                } else {
                    $this->_getSession()->addError($_helper->__($expedition->return->errorMessage));
                    $this->_redirect('*/*/new', array('order_id' => $this->getRequest()->getParam('order_id')));
                    return;
                }
            } catch (SoapFault $fault) {
                $this->_getSession()->addError($_helper->__($fault->faultstring));
                $this->_redirect('*/*/new', array('order_id' => $this->getRequest()->getParam('order_id')));
                return;
            }
        }

        $comment = '';
        if (!empty($data['comment_text'])) {
            $shipment->addComment($data['comment_text'], isset($data['comment_customer_notify']));
            $comment = $data['comment_text'];
        }

        if (!empty($data['send_email'])) {
            $shipment->setEmailSent(true);
        }

        $this->_saveShipment($shipment);
        $shipment->sendEmail(!empty($data['send_email']), $tracking_order . $comment);
        $this->_getSession()->addSuccess($this->__('Shipment was successfully created.'));
        $this->_redirect('adminhtml/sales_order/view', array('order_id' => $shipment->getOrderId()));
        return;
    }

    /**
     * Initialize shipment model instance
     *
     * @return Mage_Sales_Model_Order_Shipment|bool
     */
    protected function _initShipment()
    {
        $this->_title($this->__('Sales'))->_title($this->__('Shipments'));
        $helper = Mage::helper('chronorelais');
        $shipment = false;
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $orderId = $this->getRequest()->getParam('order_id');
        if ($shipmentId) {
            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
        } elseif ($orderId) {
            $order      = Mage::getModel('sales/order')->load($orderId);

            /**
             * Check order existing
             */
            if (!$order->getId()) {
                $this->_getSession()->addError($this->__('The order no longer exists.'));
                return false;
            }
            /**
             * Check shipment is available to create separate from invoice
             */
            if ($order->getForcedDoShipmentWithInvoice()) {
                $this->_getSession()->addError($this->__('Cannot do shipment for the order separately from invoice.'));
                return false;
            }
            /**
             * Check shipment create availability
             */
            if (!$order->canShip()) {
                $this->_getSession()->addError($this->__('Cannot do shipment for the order.'));
                return false;
            }
            $savedQtys = $this->_getItemQtys();

            /* If shipping method is Chronopost => check if shipping weight isn't over limit */
            $chronopostMethods = array('chronopost_chronopost','chronoexpress_chronoexpress','chronorelais_chronorelais','chronopostc10_chronopostC10','chronopostc18_chronopostC18','chronopostcclassic_chronopostCClassic','chronopostsrdv_chronopostsrdv','chronopostsameday_chronopostsameday');
            $shippingMethod = $order->getShippingMethod();
            if(in_array($shippingMethod, $chronopostMethods)) {
                $weightShipping = 0;
                $shippingMethod = explode("_", $shippingMethod);
                $shippingMethod = $shippingMethod[0];
                $weight_limit = Mage::getStoreConfig('carriers/'.$shippingMethod.'/weight_limit');
                foreach($savedQtys as $iditem => $qty) {
                    $item = $order->getItemById($iditem);
                    $weightShipping += $item->getWeight()*$qty;
                }
                if($helper->getConfigWeightUnit() == 'g')
                {
                    $weightShipping = $weightShipping / 1000; // conversion g => kg
                }
                if($weightShipping > $weight_limit) {

                    /* Create one shipment by product ordered */
                    foreach($savedQtys as $iditem => $qty) {
                        $item = $order->getItemById($iditem);
                        $weightShipping += $item->getWeight()*$qty;
                        for($i = 1; $i <= $qty; $i++) {
                            $shipment[] = Mage::getModel('sales/service_order', $order)->prepareShipment(array($item->getId() => '1'));

                        }
                    }
                }
            }


            if(!$shipment)
            {
                $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($savedQtys);

                $tracks = $this->getRequest()->getPost('tracking');
                if ($tracks) {
                    foreach ($tracks as $data) {
                        if (empty($data['number'])) {
                            Mage::throwException($this->__('Tracking number cannot be empty.'));
                        }
                        $track = Mage::getModel('sales/order_shipment_track')
                            ->addData($data);
                        $shipment->addTrack($track);
                    }
                }
            }
        }

        Mage::register('current_shipment', $shipment);
        return $shipment;
    }

    public function saveAction() {
        $data = $this->getRequest()->getPost('shipment');
        $orderId = $this->getRequest()->getParam('order_id');

        try {

            if ($shipment = $this->_initShipment()) {
                if(is_array($shipment))
                {
                    foreach($shipment as $ship) {
                        $this->saveAndCreateEtiquette($ship,$data);
                    }
                }
                else
                {
                    $this->saveAndCreateEtiquette($shipment,$data);
                }
            } else {
                $this->_redirect('*/*/new', array('order_id' => $this->getRequest()->getParam('order_id')));
                return;
            }

        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Can not save shipment: ' . $e->getMessage()));
        }
        $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
    }

    public function removeaccents($string) {
        $stringToReturn = str_replace(
                array('�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '/', '\xa8'), array('a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', ' ', 'e'), $string);
        // Remove all remaining other unknown characters
        $stringToReturn = preg_replace('/[^a-zA-Z0-9\-]/', ' ', $stringToReturn);
        $stringToReturn = preg_replace('/^[\-]+/', '', $stringToReturn);
        $stringToReturn = preg_replace('/[\-]+$/', '', $stringToReturn);
        $stringToReturn = preg_replace('/[\-]{2,}/', ' ', $stringToReturn);
        return $stringToReturn;
    }

}
