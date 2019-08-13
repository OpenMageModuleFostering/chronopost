<?php

require_once 'Mage/Adminhtml/controllers/Sales/Order/ShipmentController.php';

class Chronopost_Chronorelais_Adminhtml_Chronorelais_Sales_ImpressionController extends Mage_Adminhtml_Sales_Order_ShipmentController {

    protected $_trackingNumbers = '';

    /**
     * Additional initialization
     *
     */
    protected function _construct() {
        $this->setUsedModuleName('Chronopost_Chronorelais');
    }

    /**
     * Shipping grid
     */
    public function indexAction() {
        if (!extension_loaded('soap')) {
            $this->_getSession()->addError($this->__('The SOAP extension is not installed in the server. Please contact the site administrator. Sorry for inconvenience.'));
            return $this->_redirectReferer();
        }
        $cmdTestGs = Mage::helper('chronorelais')->getConfigData('chronorelais/shipping/gs_path')." -v";
        if(shell_exec($cmdTestGs) === null) {
            $this->_getSession()->addNotice($this->__('Please install %s on your server to print mass','<a href="http://www.ghostscript.com/download/" target="_blank">Ghostscript</a>'));
        }
        $this->loadLayout()
                ->_setActiveMenu('sales/chronorelais')
                ->_addContent($this->getLayout()->createBlock('chronorelais/sales_impression'))
                ->renderLayout();
    }

    /**
     * Save shipment and order in one transaction
     * @param Mage_Sales_Model_Order_Shipment $shipment
     */
    protected function _saveShipment($shipment) {
        $shipment->getOrder()->setIsInProcess(true);
        $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($shipment)
                ->addObject($shipment->getOrder())
                ->save();

        return $this;
    }

    /**
     * Declare headers and content file in response for file download
     *
     * @param string $fileName
     * @param string|array $content set to null to avoid starting output, $contentLength should be set explicitly in
     *                              that case
     * @param string $contentType
     * @param int $contentLength    explicit content length, if strlen($content) isn't applicable
     * @return Mage_Core_Controller_Varien_Action
     */
    protected function _prepareDownloadResponse($fileName,$content,$contentType = 'application/octet-stream',$contentLength = null)
    {
        $session = Mage::getSingleton('admin/session');
        if ($session->isFirstPageAfterLogin()) {
            $this->_redirect($session->getUser()->getStartupPageUrl());
            return $this;
        }

        $isFile = false;
        $file   = null;
        if (is_array($content)) {
            if (!isset($content['type']) || !isset($content['value'])) {
                return $this;
            }
            if ($content['type'] == 'filename') {
                $isFile         = true;
                $file           = $content['value'];
                $contentLength  = filesize($file);
            }
        }

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', $contentType, true)
            ->setHeader('Content-Length', is_null($contentLength) ? strlen($content) : $contentLength, true)
            ->setHeader('Content-Disposition', 'attachment; filename="'.$fileName.'"', true)
            ->setHeader('Last-Modified', date('r'), true);

        if (!is_null($content)) {
            if ($isFile) {
                $this->getResponse()->clearBody();
                $this->getResponse()->sendHeaders();

                $ioAdapter = new Varien_Io_File();
                $ioAdapter->open(array('path' => $ioAdapter->dirname($file)));
                $ioAdapter->streamOpen($file, 'r');
                while ($buffer = $ioAdapter->streamRead()) {
                    print $buffer;
                }
                $ioAdapter->streamClose();
                if (!empty($content['rm'])) {
                    $ioAdapter->rm($file);
                }

                exit(0);
            } else {
                $this->getResponse()->setBody($content);
            }
        }
        return $this;
    }

    protected function _processDownloadMass($pdf_contents) {

        $paths = array();
        $this->createMediaChronopostFolder();
        $indiceFile = 0;
        foreach ($pdf_contents as $pdf_content) {
            $fileName = 'tmp-etiquette-'.date('H-i-s-'.$indiceFile);
            /* save pdf file */
            $path = Mage::getBaseDir('media').'/chronopost/' . $fileName . '.pdf';
            file_put_contents($path, $pdf_content);
            $paths[] = $path;
            $indiceFile++;
        }

        /* creation d'un pdf unique */
        $pdfMergeFileName = "merged-".date('YmdHis').".pdf";
        $pathMerge = Mage::getBaseDir('media')."/chronopost/".$pdfMergeFileName;
        $cmd = Mage::helper('chronorelais')->getConfigData('chronorelais/shipping/gs_path').' -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$pathMerge.'" -dBATCH '. implode(' ', $paths);
        $res_shell = shell_exec($cmd);

        /* suppression des pdf temp */
        foreach ($paths as $path) {
            if(is_file($path)) {
                unlink($path);
            }
        }

        if ($res_shell === null) {
            return $this->_redirectReferer();
        }
        else {
            $this->_prepareDownloadResponse($pdfMergeFileName,array(
                'type' => 'filename',
                'value' => $pathMerge
            ));
            unlink($pathMerge);
        }
    }

    protected function getTrackingNumber($shipmentId) {
        $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);

        //On récupère le numéro de tracking
        $tracks = $shipment->getTracksCollection();
        foreach ($tracks as $track) {
            if ($track->getParentId() == $shipmentId) {
                $this->_trackingNumbers .= $track->getnumber();
            }
        }

        return $this->_trackingNumbers;
    }

    protected function getFilledValue($value) {
        if ($value) {
            return $this->removeaccents(trim($value));
        } else {
            return '';
        }
    }

    protected function checkMobileNumber($value) {
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

    protected function getExpeditionParams($shipment, $_shippingMethod) {
        $_order = $shipment->getOrder();
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

            return $expeditionArray;
        }
    }

    protected function getEtiquetteUrl($shipmentId) {
        //On récupère les infos d'expédition
        $_helper = Mage::helper('chronorelais');

        $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
        if ($_shipTracks = $shipment->getAllTracks()) {
            foreach ($_shipTracks as $_shipTrack) {
                if ($_shipTrack->getNumber() && $_shipTrack->getChronoReservationNumber()) {
                    $skybill = base64_decode($_shipTrack->getChronoReservationNumber());
                    break;
                }
            }
            if ($skybill) {
                return $skybill;
            }
        }

        $_order = $shipment->getOrder();
        $_shippingMethod = explode("_", $_order->getShippingMethod());

        $expeditionArray = $this->getExpeditionParams($shipment, $_shippingMethod);
        $tracking_number = '';
        if ($expeditionArray) {
            $client = new SoapClient("https://www.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl", array('trace' => true));
            try {
                $webservbt = $client->shippingV3($expeditionArray);
                if (!$webservbt->return->errorCode && $webservbt->return->skybill) {
                    $tracking_number = $webservbt->return->skybillNumber;
                    // Add tracking number for the shipment if not already exists.
                    if (!$this->_trackingNumbers && $webservbt->return->skybillNumber) {
                        $track = Mage::getModel('sales/order_shipment_track')
                                ->setNumber($webservbt->return->skybillNumber)
                                ->setChronoReservationNumber(base64_encode($webservbt->return->skybill))
                                ->setCarrier(ucwords($_shippingMethod[0]))
                                ->setCarrierCode($_shippingMethod[0])
                                ->setTitle(ucwords($_shippingMethod[0]))
                                ->setPopup(1);
                        $shipment->addTrack($track);

                        $tracking_url = str_replace('{tracking_number}', $tracking_number, Mage::helper('chronorelais')->getConfigurationTrackingViewUrl());
                        $tracking_title = $this->__('Track Your Order');
                        $tracking_order = '<p><a title="' . $tracking_title . '" href="' . $tracking_url . '"><b>' . $tracking_title . '</b></a></p>';

                        $comment = '';
                        $shipment->setEmailSent(true);
                        $this->_saveShipment($shipment);
                        $shipment->sendEmail(1, $tracking_order . $comment);
                    }
                    return $webservbt->return->skybill; // pdf base64
                } else {
                    $this->_getSession()->addError($_helper->__($webservbt->return->errorMessage));
                }
            } catch (SoapFault $fault) {
                $this->_getSession()->addError($_helper->__($fault->faultstring));
            }
        }
    }

    public function getShipmentByOrderId($orderId) {
        $_shipment = Mage::getResourceModel('sales/order_shipment_grid_collection')
                ->addAttributeToFilter('order_id', $orderId)
                ->getAllIds();
        return $_shipment;
    }

    public function getShipmentByIncrementId($incrementId) {
        $_shipment = Mage::getResourceModel('sales/order_shipment_grid_collection')
                ->addAttributeToFilter('increment_id', $incrementId)
                ->getAllIds();
        return $_shipment;
    }

    public function initShipment($orderId,$savedQtys = '') {
        $order = Mage::getModel('sales/order')->load($orderId);

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
        if(empty($savedQtys)) {
            $savedQtys = $this->_getItemQtys();
        }
        $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($savedQtys);
        if(Mage::registry('current_shipment')) {
            Mage::unregister ('current_shipment');
        }
        Mage::register('current_shipment', $shipment);
        return $shipment;
    }

    public function createNewShipment($orderId,$savedQtys = '') {
        $_helper = Mage::helper('chronorelais');
        $skybill = '';
        try {
            if ($shipment = $this->initShipment($orderId,$savedQtys)) {
                $shipment->register();

                $_order = $shipment->getOrder();
                $_shippingMethod = explode("_", $_order->getShippingMethod());

                $expeditionArray = $this->getExpeditionParams($shipment, $_shippingMethod);
                $tracking_number = '';
                if ($expeditionArray) {

                    $client = new SoapClient("https://www.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl", array('trace' => true));
                    try {
                        $expedition = $client->shippingV3($expeditionArray);
                        if (!$expedition->return->errorCode && $expedition->return->skybillNumber) {
                            $tracking_number = $expedition->return->skybillNumber;
                            $track = Mage::getModel('sales/order_shipment_track')
                                    ->setNumber($expedition->return->skybillNumber)
                                    ->setChronoReservationNumber(base64_encode($expedition->return->skybill))
                                    ->setCarrier(ucwords($_shippingMethod[0]))
                                    ->setCarrierCode($_shippingMethod[0])
                                    ->setTitle(ucwords($_shippingMethod[0]))
                                    ->setPopup(1);
                            $shipment->addTrack($track);
                            $skybill = $expedition->return->skybill;
                        } else {
                            $this->_getSession()->addError($_helper->__($expedition->return->errorMessage));
                            return;
                        }
                    } catch (SoapFault $fault) {
                        $this->_getSession()->addError($_helper->__($fault->faultstring));
                        return;
                    }
                }

                $tracking_url = str_replace('{tracking_number}', $tracking_number, Mage::helper('chronorelais')->getConfigurationTrackingViewUrl());
                $tracking_title = $this->__('Track Your Order');
                $tracking_order = '<p><a title="' . $tracking_title . '" href="' . $tracking_url . '"><b>' . $tracking_title . '</b></a></p>';

                $comment = '';
                $shipment->setEmailSent(true);
                $this->_saveShipment($shipment);
                $shipment->sendEmail(1, $tracking_order . $comment);
                $this->_getSession()->addSuccess($this->__('Shipment was successfully created.'));
                return $skybill;
            } else {
                $this->_forward('noRoute');
                return;
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            return;
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Can not save shipment: ' . $e->getMessage()));
            return;
        }
    }

    public function printMassAction() {
        $orderIds = $this->getRequest()->getParam('order_ids');
        $skybillArr = array();
        $helper = Mage::helper('chronorelais');
        foreach ($orderIds as $orderId) {
            if ($_shipments = $this->getShipmentByOrderId($orderId)) {
                if (count($_shipments) == 1) {
                    $shipmentId = $_shipments[0];
                    $skybill = $this->getEtiquetteUrl($shipmentId);
                    if ($skybill) {
                        $skybillArr[] = $skybill;
                    }
                } else {
                    foreach ($_shipments as $_shipment) {
                        $skybill = $this->getEtiquetteUrl($_shipment);
                        if ($skybill) {
                            $skybillArr[] = $skybill;
                        }
                    }
                }
            } else {

                $order = Mage::getModel('sales/order')->load($orderId);

                /* If shipping method is Chronopost => check if shipping weight isn't over limit */
                $chronopostMethods = array('chronorelais','chronopost','chronoexpress','chronopostc10','chronopostc18','chronopostcclassic','chronorelaiseurope','chronorelaisdom','chronopostsrdv','chronopostsameday');
                $shippingMethod = $order->getShippingMethod();
                $shippingMethod = explode("_", $shippingMethod);
                $shippingMethod = $shippingMethod[0];
                if(in_array($shippingMethod, $chronopostMethods)) {
                    $weightShipping = 0;
                    $weight_limit = Mage::getStoreConfig('carriers/'.$shippingMethod.'/weight_limit');
                    foreach ($order->getItemsCollection() as $item) {
                        $weightShipping += $item->getWeight()*$item->getQtyOrdered();
                    }
                    if($helper->getConfigWeightUnit() == 'g')
                    {
                        $weightShipping = $weightShipping / 1000; // conversion g => kg
                    }
                    if($weightShipping > $weight_limit) {
                        /* multi shipping. 1 shipment by product */
                        foreach ($order->getItemsCollection() as $item) {
                            $qty = $item->getQtyOrdered();
                            for($i = 1; $i <= $qty; $i++) {
                                $skybill = $this->createNewShipment($orderId,array($item->getId() => '1'));
                                if ($skybill)
                                    $skybillArr[] = $skybill;
                            }
                        }
                    }
                    else {
                        $skybill = $this->createNewShipment($orderId);
                        if ($skybill) {
                            $skybillArr[] = $skybill;
                        }
                    }
                }
                else {
                    $skybill = $this->createNewShipment($orderId);
                    if ($skybill) {
                        $skybillArr[] = $skybill;
                    }
                }
            }
        }
        if (count($skybillArr)) {
            $this->_processDownloadMass($skybillArr);
        }
        else {
            return $this->_redirectReferer();
        }
    }

    public function printAction() {
        // Appel via order_id
        $orderId = $this->getRequest()->getParam('order_id');
        $helper = Mage::helper('chronorelais');
        if ($orderId) {
            if ($_shipments = $this->getShipmentByOrderId($orderId)) {
                if (count($_shipments) == 1) {
                    $shipmentId = $_shipments[0];
                    $skybillArr = $this->getEtiquetteUrl($shipmentId);
                } else {
                    $track = "Cette commande contient plusieurs expéditions, cliquez sur chaque lien pour obtenir les étiquettes :<br>";
                    /*foreach ($_shipments as $_shipment) {
                        $url = str_replace('{trackingNumber}', $this->getEtiquetteUrl($_shipment), $helper->getConfigurationTrackingUrl());
                        $track .= '<a target="_blank" href="' . $url . '">' . $url . '</a><br />';
                    }*/
                    echo $track;
                    return;
                }
            } else {
                $order = Mage::getModel('sales/order')->load($orderId);

                /* If shipping method is Chronopost => check if shipping weight isn't over limit */
                $chronopostMethods = array('chronorelais','chronopost','chronoexpress','chronopostc10','chronopostc18','chronopostcclassic','chronorelaiseurope','chronorelaisdom','chronopostsrdv','chronopostsameday');
                $shippingMethod = $order->getShippingMethod();
                $shippingMethod = explode("_", $shippingMethod);
                $shippingMethod = $shippingMethod[0];
                if(in_array($shippingMethod, $chronopostMethods)) {
                    $weightShipping = 0;
                    $shippingMethod = explode("_", $shippingMethod);
                    $shippingMethod = $shippingMethod[0];
                    $weight_limit = Mage::getStoreConfig('carriers/'.$shippingMethod.'/weight_limit');
                    foreach ($order->getItemsCollection() as $item) {
                        $weightShipping += $item->getWeight()*$item->getQtyOrdered();
                    }
                    if($helper->getConfigWeightUnit() == 'g')
                    {
                        $weightShipping = $weightShipping / 1000; // conversion g => kg
                    }
                    if($weightShipping > $weight_limit) {
                        /* multi shipping. 1 shipment by product */
                        $skybillArr = array();
                        foreach ($order->getItemsCollection() as $item) {
                            $qty = $item->getQtyOrdered();
                            for($i = 1; $i <= $qty; $i++) {
                                $skybillArr[] = $this->createNewShipment($orderId,array($item->getId() => '1'));
                            }
                        }
                    }
                    else {
                        $skybillArr = $this->createNewShipment($orderId);
                    }
                }
                else {
                    $skybillArr = $this->createNewShipment($orderId);
                }
            }
        } else {
            $shipmentId = $this->getRequest()->getParam('shipment_id');
            if ($shipmentId) {
                $skybillArr = $this->getEtiquetteUrl($shipmentId);
            } else {
                $shipmentIncrementId = $this->getRequest()->getParam('shipment_increment_id');
                $shipmentId = $this->getShipmentByIncrementId($shipmentIncrementId);
                $skybillArr = $this->getEtiquetteUrl($shipmentId[0]);
            }
        }


        if ($skybillArr) {
            try {
                if(is_array($skybillArr)) {
                    $this->_processDownloadMass($skybillArr);
                }
                else {
                    $this->_prepareDownloadResponse('Etiquette_chronopost.pdf',  $skybillArr);

                }
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($helper->__('Désolé, une erreur est survenu lors de la récupération de l\'étiquettes. Merci de contacter Chronopost ou de réessayer plus tard'));
            }
        }
        else {
            return $this->_redirectReferer();
        }
    }

    public function massLivraisonSamediStatusAction() {
        if ($this->getRequest()->getPost('status')) {
            $this->saveLivraisonSamediStatusAction();
        }
    }

    /* Save the Livraison le Samedi status to orders */

    public function saveLivraisonSamediStatusAction() {
        /* get the orders */
        $orderIds = $this->getRequest()->getPost('order_ids');
        $status = $this->getRequest()->getPost('status');
        $_connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $_table = Mage::getSingleton('core/resource')->getTableName('sales_chronopost_order_export_status');
        $exceptions = array();

        foreach ($orderIds as $orderId) {
            $order_details = Mage::getModel('sales/order')->load($orderId);
            $shipping_method = '';
            $livraison_le_samedi = $status;
            if ($shipping_method = $order_details->getShippingMethod()) {
                $shipping_method = explode('_', $shipping_method);
                if ($shipping_method[0] == 'chronoexpress') {
                    $livraison_le_samedi = '--';
                }
            }
            $condition = array(
                $_connection->quoteInto('order_id = ?', $orderId),
            );
            $_connection->delete($_table, $condition);

            $dataLine = array(
                'order_id' => $orderId,
                'livraison_le_samedi' => $livraison_le_samedi
            );
            try {
                $_connection->insert($_table, $dataLine);
            } catch (Exception $e) {
                $exceptions[] = Mage::helper('chronorelais')->__('Order assigning error: ' . $e->getMessage());
            }
        }
        if ($exceptions) {
            $this->_getSession()->addError($exceptions);
        } else {
            $this->_getSession()->addSuccess($this->__('Livraison le Samedi statut a &eacute;t&eacute; ajout&eacute;'));
        }
        $this->_redirect('*/*/index');
    }

    /* Remove accents characters */

    public function removeaccents($string) {
        $stringToReturn = str_replace(
                array('à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', '/', '\xa8'), array('a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', ' ', 'e'), $string);
        // Remove all remaining other unknown characters
        $stringToReturn = preg_replace('/[^a-zA-Z0-9\-]/', ' ', $stringToReturn);
        $stringToReturn = preg_replace('/^[\-]+/', '', $stringToReturn);
        $stringToReturn = preg_replace('/[\-]+$/', '', $stringToReturn);
        $stringToReturn = preg_replace('/[\-]{2,}/', ' ', $stringToReturn);
        return $stringToReturn;
    }

    /*
     * *******************************************************************
     * ******************** ETIQUETTE DE RETOUR **************************
     * *******************************************************************
     */

    public function printEtiquetteRetourAction() {
        $shipmentIncrementId = $this->getRequest()->getParam('shipment_increment_id');
        $shipmentId = $this->getShipmentByIncrementId($shipmentIncrementId);
        $shipmentId = $shipmentId[0];
        $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
        $_order = $shipment->getOrder();
        $_shippingAddress = $shipment->getShippingAddress();
        $_billingAddress = $shipment->getBillingAddress();
        $skillbill = $this->getEtiquetteRetourUrl($shipment); /* skillbill pdf encode base64*/

        if ($skillbill) {
            try {
                $path = $this->savePdfWithContent($skillbill, $shipmentId);

                $message_email = 'Bonjour,
                                <br />Vous allez bientôt effectuer un envoi Chronopost. La personne qui vous a adressé ce mail a déjà préparé la lettre de transport que vous utiliserez. Après impression, apposez la lettre de transport dans une pochette plastique adhésive et collez la sur votre envoi. Attention le code à barres doit être bien apparent.
                                <br />Cordialement,';

                $customer_email = ($_shippingAddress->getEmail()) ? $_shippingAddress->getEmail() : ($_billingAddress->getEmail() ? $_billingAddress->getEmail() : $_order->getCustomerEmail());

                $mail = new Zend_Mail('utf-8');
                $mail->setBodyHtml($message_email);
                $mail->setFrom(Mage::getStoreConfig('contacts/email/recipient_email'));
                $mail->setSubject($_order->getStoreName(1) . ' : Etiquette de retour chronopost');
                $mail->createAttachment(file_get_contents($path), Zend_Mime::TYPE_OCTETSTREAM, Zend_Mime::DISPOSITION_ATTACHMENT, Zend_Mime::ENCODING_BASE64, 'etiquette_retour.pdf');

                $mail->addTo($customer_email);
                $mail->send();

                $mail->clearRecipients();
                $mail->addTo(Mage::getStoreConfig('contacts/email/recipient_email'));
                $mail->send();

                $this->_getSession()->addSuccess(Mage::helper('chronorelais')->__('L\'etiquette de retour à bien été envoyée au client.'));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError(Mage::helper('chronorelais')->__('Désolé, une erreure est survenu lors de la récupération de l\'étiquettes. Merci de contacter Chronopost ou de réessayer plus tard'));
            }
        }
        return $this->_redirectReferer();
    }

    protected function getEtiquetteRetourParams($shipment, $_shippingMethod) {
        $_order = $shipment->getOrder();
        $_shippingAddress = $shipment->getShippingAddress();
        $_billingAddress = $shipment->getBillingAddress();
        $_helper = Mage::helper('chronorelais');

        if ($_shippingAddress->getCountryId() != 'FR'
            && strpos($_shippingMethod[0], 'chronorelaiseurope') === false
            && strpos($_shippingMethod[0], 'chronorelaisdom') === false
            && strpos($_shippingMethod[0], 'chronopostsrdv') === false
        ) {
            $this->_getSession()->addError($_helper->__('Les retours sont disponibles uniquement pour la France'));
            return;
        }
        $shippingMethodAllow = array('chronorelaiseurope','chronorelais','chronopost','chronopostc10','chronopostc18','chronopostsrdv');
        if (!in_array($_shippingMethod[0], $shippingMethodAllow)) {
            $this->_getSession()->addError($_helper->__('Les retours ne sont pas disponibles pour le mode de livraison ' . $_shippingMethod[0]));
            return;
        }

        if (in_array($_shippingMethod[0], $shippingMethodAllow)) {
            $esdParams = $header = $shipper = $customer = $recipient = $ref = $skybill = $skybillParams = $password = array();

            //header parameters
            $header = array(
                'idEmit' => 'MAG',
                'accountNumber' => $_helper->getConfigurationAccountNumber(),
                'subAccount' => $_helper->getConfigurationSubAccountNumber()
            );

            //shipper parameters
            $shipperMobilePhone = $this->checkMobileNumber($_helper->getConfigurationShipperInfo('mobilephone'));
            $recipient = array(
                'recipientAdress1' => $_helper->getConfigurationShipperInfo('address1'),
                'recipientAdress2' => $_helper->getConfigurationShipperInfo('address2'),
                'recipientCity' => $_helper->getConfigurationShipperInfo('city'),
                'recipientCivility' => $_helper->getConfigurationShipperInfo('civility'),
                'recipientContactName' => $_helper->getConfigurationShipperInfo('contactname'),
                'recipientCountry' => $_helper->getConfigurationShipperInfo('country'),
                'recipientEmail' => $_helper->getConfigurationShipperInfo('email'),
                'recipientMobilePhone' => $shipperMobilePhone,
                'recipientName' => $_helper->getConfigurationShipperInfo('name'),
                'recipientName2' => $_helper->getConfigurationShipperInfo('name2'),
                'recipientPhone' => $_helper->getConfigurationShipperInfo('phone'),
                'recipientPreAlert' => '',
                'recipientZipCode' => $_helper->getConfigurationShipperInfo('zipcode')
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
            $_recipientAddress = $_shippingAddress;
            if(strpos($_shippingMethod[0],'chronorelais') !== false) {
                // Nicolas, le 27/11/2014 : si Chronorelais, on doit utiliser l'adresse de facturation, non de livraison (qui est celle du relais)
                $_recipientAddress = $_billingAddress;
            }
            $recipient_address = $_recipientAddress->getStreet();

            // Champs forcément basés sur l'adresse de livraison
            $customer_email = ($_shippingAddress->getEmail()) ? $_shippingAddress->getEmail() : ($_billingAddress->getEmail() ? $_billingAddress->getEmail() : $_order->getCustomerEmail());
            $recipientMobilePhone = $this->checkMobileNumber($_shippingAddress->getTelephone());
            $recipientName = $this->getFilledValue($_recipientAddress->getCompany()); //RelayPoint Name if chronorelais or Companyname if chronopost and
            $recipientName2 = $this->getFilledValue($_shippingAddress->getFirstname() . ' ' . $_shippingAddress->getLastname());
            //remove any alphabets in phone number

            $recipientPhone = trim(preg_replace("/[^0-9\.\-]/", " ", $_shippingAddress->getTelephone()));
            if (!isset($recipient_address[1])) {
                $recipient_address[1] = '';
            }

            $shipper = array(
                'shipperAdress1' => substr($this->getFilledValue($recipient_address[0]), 0, 38),
                'shipperAdress2' => $recipient_address[1] ? substr($this->getFilledValue($recipient_address[1]), 0, 38) : '',
                'shipperCity' => $this->getFilledValue($_recipientAddress->getCity()),
                'shipperCivility' => 'M',
                'shipperContactName' => $recipientName2,
                'shipperCountry' => $this->getFilledValue($_recipientAddress->getCountryId()),
                'shipperEmail' => $customer_email,
                'shipperMobilePhone' => $recipientMobilePhone,
                'shipperName' => $recipientName,
                'shipperName2' => $recipientName2,
                'shipperPhone' => $recipientPhone,
                'shipperPreAlert' => '',
                'shipperZipCode' => $this->getFilledValue($_recipientAddress->getPostcode()),
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
            if ($_shippingMethod[0] == "chronopost" || $_shippingMethod[0] == "chronorelais") {
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
                if ($_deliver_on_saturday && $is_sending_day) {
                    $SaturdayShipping = 6;
                } elseif (!$_deliver_on_saturday && $is_sending_day) {
                    $SaturdayShipping = 1;
                }
            }

            $weight = 0;
            foreach ($shipment->getItemsCollection() as $item) {
                $weight += $item->weight * $item->qty;
            }
            if ($_helper->getConfigWeightUnit() == 'g') {
                $weight = $weight / 1000; /* conversion g => kg */
            }

            $productCode = Chronopost_Chronorelais_Helper_Data::CHRONO_POST;
            if($_shippingMethod[0] == 'chronorelaiseurope') {
                $productCode = '3T';
                //$weight <= 3 ? $SaturdayShipping = '337' : $SaturdayShipping = '338';
                $SaturdayShipping = '332';
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
                'productCode' => $productCode,
                'service' => $SaturdayShipping,
                'shipDate' => date('c'),
                'shipHour' => date('H'),
                'weight' => $weight,
                'weightUnit' => 'KGM'
            );

            $mode = $_helper->getConfigurationSkybillParam();
            if($_shippingMethod[0] == 'chronorelaiseurope') {
                $mode = 'PPR';
            }
            $skybillParams = array(
                'mode' => $mode
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
            return $expeditionArray;
        }
    }

    protected function getEtiquetteRetourUrl($shipment) {
        //On récupère les infos d'expédition
        $_helper = Mage::helper('chronorelais');

        $_order = $shipment->getOrder();
        $_shippingMethod = explode("_", $_order->getShippingMethod());

        $expeditionArray = $this->getEtiquetteRetourParams($shipment, $_shippingMethod);
        if ($expeditionArray) {
            $client = new SoapClient("https://www.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl", array('trace' => true));
            try {
                $webservbt = $client->shippingV3($expeditionArray);

                if (!$webservbt->return->errorCode && $webservbt->return->skybill) {
                    return $webservbt->return->skybill;
                } else {
                    $this->_getSession()->addError($_helper->__($webservbt->return->errorMessage));
                }
            } catch (SoapFault $fault) {
                $this->_getSession()->addError($_helper->__($fault->faultstring));
            }
        }
    }

    protected function savePdf($url, $shipmentId) {
        $this->createMediaChronopostFolder();
        $path = 'media/chronopost/etiquetteRetour-' . $shipmentId . '.pdf';
        file_put_contents($path, file_get_contents($url));
        return $path;
    }

    protected function savePdfWithContent($content_base64, $shipmentId) {
        $this->createMediaChronopostFolder();
        $path = 'media/chronopost/etiquetteRetour-' . $shipmentId . '.pdf';
        file_put_contents($path, $content_base64);
        return $path;
    }

    /* create folder media/chronopost if not exist */
    protected function createMediaChronopostFolder() {
        $path = 'media/chronopost';
        if(!is_dir($path)) {
            mkdir($path,0777);
        }
    }

    protected function getShipmentObjectByOrderId($orderId) {
        $_shipment = Mage::getResourceModel('sales/order_shipment_collection')
                ->addAttributeToFilter('order_id', $orderId);
        return $_shipment;
    }

    public function cancelMassAction() {
        $orderIds = $this->getRequest()->getParam('order_ids');

        if($orderIds) {
            $nbEtiquettesDelete = 0;
            foreach($orderIds as $orderId) {
                $shipments = $this->getShipmentObjectByOrderId($orderId);
                if($shipments) {
                    foreach($shipments as $shipment) {

                        $tracks = $shipment->getTracksCollection();
                        foreach ($tracks as $track) {

                            /* numero chrono si getChronoReservationNumber non null */
                            if($track->getChronoReservationNumber()) {

                                /* appel WS pour annuler LT */
                                $webservbt = Mage::helper('chronorelais/webservice')->cancelSkybill($track->getNumber());
                                if($webservbt) {
                                    /* suppression du numéro de tracking */
                                    if($webservbt->return->errorCode == 0) {
                                        $nbEtiquettesDelete++;
                                        $track->delete();
                                    } else {
                                        switch($webservbt->return->errorCode) {
                                            case "1" :
                                                $errorMessage = $this->__("Une erreur système est survenue");
                                                break;
                                            case "2" :
                                                $errorMessage = $this->__("le colis n'appartient pas au contrat passé en paramètre ou n'a pas encore été enregistré dans le système de tracking Chronopost");
                                                break;
                                            case "3" :
                                                $errorMessage = $this->__("Le colis ne peut être pas annulé car il a été pris en charge par Chronopost");
                                                break;
                                            default :
                                                $errorMessage = '';
                                                break;
                                        }
                                        $this->_getSession()->addError($this->__("Erreur lors de la suppression de l'étiquettes %s : %s.",$track->getNumber(),$errorMessage));
                                    }
                                } else {
                                    $this->_getSession()->addError($this->__('Désolé, une erreur est survenu lors de la suppression de l\'étiquette %s. Merci de contacter Chronopost ou de réessayer plus tard',$track->getNumber()));
                                }
                            }
                        }
                    }
                }
            }
            if($nbEtiquettesDelete > 0) {
                if($nbEtiquettesDelete > 1) {
                    $this->_getSession()->addSuccess($this->__('%s étiquettes de transport ont bien été annulées.',$nbEtiquettesDelete));
                } else {
                    $this->_getSession()->addSuccess($this->__('%s étiquette de transport a bien été annulée.',$nbEtiquettesDelete));
                }

            }
        } else {
            $this->_getSession()->addError($this->__("Veuillez sélectionner au moins une lettre de transport."));
        }
        $this->_redirect('*/*/index');
    }

}
