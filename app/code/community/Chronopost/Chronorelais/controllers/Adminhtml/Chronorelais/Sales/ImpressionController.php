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
    protected function _prepareDownloadResponse(
        $fileName,
        $content,
        $contentType = 'application/octet-stream',
        $contentLength = null)
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

    protected function _processDownloadMass($urls) {

        $helper = Mage::helper('downloadable/download');
        $countUrl = count($urls);
        $indice = 1;
        $filesSize = 0;
        $content = '';

        $paths = array();
        $this->createMediaChronopostFolder();
        foreach ($urls as $url) {
            $helper->setResource($url, 'url');
            $fileName = $helper->getFilename();
            $fileName = explode('reservationNumber=', $fileName);
            $fileName = $fileName[1];
            $filesSize = $helper->getFilesize();
            $contentType = $helper->getContentType();

            /* save pdf file */
            $path = Mage::getBaseDir('media').'/chronopost/' . $fileName . '.pdf';
            file_put_contents($path, file_get_contents($url));
            $paths[] = $path;
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

        $shippingMethodAllow = array('chronorelais','chronopost','chronoexpress','chronopostc10','chronopostc18','chronopostcclassic');
        if (in_array($_shippingMethod[0],$shippingMethodAllow)) {
            $esdParams = $header = $shipper = $customer = $recipient = $ref = $skybill = $skybillParams = $password = array();

            //esdParams parameters
            $esdParams = array(
                'height' => '',
                'width' => '',
                'length' => ''
            );

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

            //$recipientPhone = trim(ereg_replace("[^0-9.-]", " ", $_shippingAddress->getTelephone()));
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
            $send_day = strtolower(date('l'));
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
                'esdParams' => $esdParams,
                'header' => $header,
                'shipper' => $shipper,
                'customer' => $customer,
                'recipient' => $recipient,
                'ref' => $ref,
                'skybill' => $skybill,
                'skybillParams' => $skybillParams,
                'password' => $_helper->getConfigurationAccountPass(),
                'option' => '0'
            );
            //printArray($expeditionArray); exit;
            return $expeditionArray;
        }
    }

    protected function getEtiquetteUrl($shipmentId) {
        //On récupère les infos d'expédition
        $reservationNumber = '';
        $_helper = Mage::helper('chronorelais');

        $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
        if ($_shipTracks = $shipment->getAllTracks()) {
            foreach ($_shipTracks as $_shipTrack) {
                if ($_shipTrack->getNumber() && $_shipTrack->getChronoReservationNumber()) {
                    $reservationNumber = $_shipTrack->getChronoReservationNumber();
                    break;
                }
            }
            if ($reservationNumber) {
                return $reservationNumber;
            }
        }

        $_order = $shipment->getOrder();
        $_shippingMethod = explode("_", $_order->getShippingMethod());

        $expeditionArray = $this->getExpeditionParams($shipment, $_shippingMethod);
        $tracking_number = '';
        if ($expeditionArray) {
            $client = new SoapClient("http://wsshipping.chronopost.fr/shipping/services/services/ServiceEProcurement?wsdl", array('trace' => true));
            try {
                $webservbt = $client->__call("reservationExpeditionV2", $expeditionArray);
                if (!$webservbt->errorCode && $webservbt->reservationNumber) {
                    $tracking_number = $webservbt->skybillNumber;
                    // Add tracking number for the shipment if not already exists.
                    if (!$this->_trackingNumbers && $webservbt->skybillNumber) {
                        $track = Mage::getModel('sales/order_shipment_track')
                                ->setNumber($webservbt->skybillNumber)
                                ->setCarrier(ucwords($_shippingMethod[0]))
                                ->setCarrierCode($_shippingMethod[0])
                                ->setTitle(ucwords($_shippingMethod[0]))
                                ->setChronoReservationNumber($webservbt->reservationNumber)
                                ->setPopup(1);
                        $shipment->addTrack($track);

                        $tracking_url = str_replace('{tracking_number}', $tracking_number, Mage::helper('chronorelais')->getConfigurationTrackingViewUrl());
                        $tracking_title = $this->__('Track Your Order');
                        $tracking_order = '<p><a title="' . $tracking_title . '" href="' . $tracking_url . '"><b>' . $tracking_title . '</b></a></p>';

                        //$shipment->register();
                        $comment = '';
                        $shipment->setEmailSent(true);
                        $this->_saveShipment($shipment);
                        $shipment->sendEmail(1, $tracking_order . $comment);
                    }
                    return $webservbt->reservationNumber;
                } else {
                    $this->_getSession()->addError($_helper->__($webservbt->errorMessage));
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
        /* if (!$order->canShip()) {
          $this->_getSession()->addError($this->__('Cannot do shipment for the order.'));
          return false;
          } */
        if(empty($savedQtys))
            $savedQtys = $this->_getItemQtys();
        $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($savedQtys);
        if(Mage::registry('current_shipment')) Mage::unregister ('current_shipment');
        Mage::register('current_shipment', $shipment);
        return $shipment;
    }

    public function createNewShipment($orderId,$savedQtys = '') {
        $_helper = Mage::helper('chronorelais');
        $reservationNumber = '';
        try {
            if ($shipment = $this->initShipment($orderId,$savedQtys)) {
                $shipment->register();

                $_order = $shipment->getOrder();
                $_shippingMethod = explode("_", $_order->getShippingMethod());

                $expeditionArray = $this->getExpeditionParams($shipment, $_shippingMethod);
                $tracking_number = '';
                if ($expeditionArray) {

                    $client = new SoapClient("http://wsshipping.chronopost.fr/shipping/services/services/ServiceEProcurement?wsdl", array('trace' => true));
                    try {
                        $expedition = $client->__call("reservationExpeditionV2", $expeditionArray);
                        if (!$expedition->errorCode && $expedition->skybillNumber) {
                            $tracking_number = $expedition->skybillNumber;
                            $track = Mage::getModel('sales/order_shipment_track')
                                    ->setNumber($expedition->skybillNumber)
                                    ->setCarrier(ucwords($_shippingMethod[0]))
                                    ->setCarrierCode($_shippingMethod[0])
                                    ->setTitle(ucwords($_shippingMethod[0]))
                                    ->setChronoReservationNumber($expedition->reservationNumber)
                                    ->setPopup(1);
                            $shipment->addTrack($track);
                            $reservationNumber = $expedition->reservationNumber;
                        } else {
                            $this->_getSession()->addError($_helper->__($expedition->errorMessage));
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
                return $reservationNumber;
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
        $trackNumberUrls = array();
        $helper = Mage::helper('chronorelais');
        foreach ($orderIds as $orderId) {
            if ($_shipments = $this->getShipmentByOrderId($orderId)) {
                if (count($_shipments) == 1) {
                    $shipmentId = $_shipments[0];
                    $trackNumber = $this->getEtiquetteUrl($shipmentId);
                    if ($trackNumber)
                        $trackNumberUrls[] = str_replace('{trackingNumber}', $trackNumber, $helper->getConfigurationTrackingUrl());
                } else {
                    foreach ($_shipments as $_shipment) {
                        $trackNumber = $this->getEtiquetteUrl($_shipment);
                        if ($trackNumber)
                            $trackNumberUrls[] = str_replace('{trackingNumber}', $trackNumber, $helper->getConfigurationTrackingUrl());
                    }
                }
            } else {

                $order = Mage::getModel('sales/order')->load($orderId);

                /* If shipping method is Chronopost => check if shipping weight isn't over limit */
                $chronopostMethods = array('chronopost','chronoexpress','chronorelais','chronopostc10','chronopostc18','chronopostcclassic');
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
                        $trackingNumber = array();
                        foreach ($order->getItemsCollection() as $item) {
                            $qty = $item->getQtyOrdered();
                            for($i = 1; $i <= $qty; $i++) {
                                $trackingNumber = $this->createNewShipment($orderId,array($item->getId() => '1'));
                                if ($trackingNumber)
                                    $trackNumberUrls[] = str_replace('{trackingNumber}', $trackingNumber, $helper->getConfigurationTrackingUrl());
                            }
                        }
                    }
                    else {
                        $trackingNumber = $this->createNewShipment($orderId);
                        if ($trackingNumber)
                            $trackNumberUrls[] = str_replace('{trackingNumber}', $trackingNumber, $helper->getConfigurationTrackingUrl());
                    }
                }
                else {
                    $trackingNumber = $this->createNewShipment($orderId);
                    if ($trackingNumber)
                        $trackNumberUrls[] = str_replace('{trackingNumber}', $trackingNumber, $helper->getConfigurationTrackingUrl());
                }
            }
        }
        if (count($trackNumberUrls)) {
            $this->_processDownloadMass($trackNumberUrls);
            //exit(0);
        }
        else
            return $this->_redirectReferer();
    }

    public function printAction() {
        // Appel via order_id
        $orderId = $this->getRequest()->getParam('order_id');
        $helper = Mage::helper('chronorelais');
        if ($orderId) {
            if ($_shipments = $this->getShipmentByOrderId($orderId)) {
                if (count($_shipments) == 1) {
                    $shipmentId = $_shipments[0];
                    $trackingNumber = $this->getEtiquetteUrl($shipmentId);
                } else {
                    $track = "Cette commande contient plusieurs expéditions, cliquez sur chaque lien pour obtenir les étiquettes :<br>";
                    foreach ($_shipments as $_shipment) {
                        $url = str_replace('{trackingNumber}', $this->getEtiquetteUrl($_shipment), $helper->getConfigurationTrackingUrl());
                        $track .= '<a target="_blank" href="' . $url . '">' . $url . '</a><br />';
                    }
                    echo $track;
                    return;
                }
            } else {
                $order = Mage::getModel('sales/order')->load($orderId);

                /* If shipping method is Chronopost => check if shipping weight isn't over limit */
                $chronopostMethods = array('chronopost','chronoexpress','chronorelais','chronopostc10','chronopostc18','chronopostcclassic');
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
                        $trackingNumber = array();
                        foreach ($order->getItemsCollection() as $item) {
                            $qty = $item->getQtyOrdered();
                            for($i = 1; $i <= $qty; $i++) {
                                $trackingNumber[] = $this->createNewShipment($orderId,array($item->getId() => '1'));
                            }
                        }
                    }
                    else {
                        $trackingNumber = $this->createNewShipment($orderId);
                    }
                }
                else {
                    $trackingNumber = $this->createNewShipment($orderId);
                }
            }
        } else {
            $shipmentId = $this->getRequest()->getParam('shipment_id');
            if ($shipmentId) {
                $trackingNumber = $this->getEtiquetteUrl($shipmentId);
            } else {
                $shipmentIncrementId = $this->getRequest()->getParam('shipment_increment_id');
                $shipmentId = $this->getShipmentByIncrementId($shipmentIncrementId);
                $trackingNumber = $this->getEtiquetteUrl($shipmentId[0]);
            }
        }


        if ($trackingNumber) {
            try {
                if(is_array($trackingNumber)) {
                    $trackNumberUrls = array();
                    foreach($trackingNumber as $track) {
                        $trackNumberUrls[] = str_replace('{trackingNumber}', $track, $helper->getConfigurationTrackingUrl());
                    }
                    $this->_processDownloadMass($trackNumberUrls);
                    //exit(0);
                }
                else {
                    $tracking_url = str_replace('{trackingNumber}', $trackingNumber, $helper->getConfigurationTrackingUrl());
                    $this->_prepareDownloadResponse('Etiquette_chronopost.pdf',  file_get_contents($tracking_url));
                    //$this->_processDownload($tracking_url, 'url');
                    //exit(0);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($helper->__('Désolé, une erreur est survenu lors de la récupération de l\'étiquetes. Merci de contacter Chronopost ou de réessayer plus tard'));
            }
        }
        else
            return $this->_redirectReferer();
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
        $trackingNumber = $this->getEtiquetteRetourUrl($shipment);

        if ($trackingNumber) {
            try {
                $tracking_url = str_replace('{trackingNumber}', $trackingNumber, Mage::helper('chronorelais')->getConfigurationTrackingUrl());
                $path = $this->savePdf($tracking_url, $shipmentId);

                /*
                 * TODO adexos : Send mail with pdf to customer
                 */
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
                $this->_getSession()->addError(Mage::helper('chronorelais')->__('Désolé, une erreure est survenu lors de la récupération de l\'étiquetes. Merci de contacter Chronopost ou de réessayer plus tard'));
            }
        }
        return $this->_redirectReferer();
    }

    protected function getEtiquetteRetourParams($shipment, $_shippingMethod) {
        $_order = $shipment->getOrder();
        $_shippingAddress = $shipment->getShippingAddress();
        $_billingAddress = $shipment->getBillingAddress();
        $_helper = Mage::helper('chronorelais');

        if ($_shippingAddress->getCountryId() != 'FR') {
            $this->_getSession()->addError($_helper->__('Les retours sont disponibles uniquement pour la France'));
            return;
        }
        $shippingMethodAllow = array('chronorelais','chronopost','chronopostc10','chronopostc18');
        if (!in_array($_shippingMethod[0], $shippingMethodAllow)) {
            $this->_getSession()->addError($_helper->__('Les retours ne sont pas disponibles pour le mode de livraison ' . $_shippingMethod[0]));
            return;
        }

        if (in_array($_shippingMethod[0], $shippingMethodAllow)) {
            $esdParams = $header = $shipper = $customer = $recipient = $ref = $skybill = $skybillParams = $password = array();

            //esdParams parameters
            $esdParams = array(
                'height' => '',
                'width' => '',
                'length' => ''
            );

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
            if ($_shippingMethod[0] == 'chronorelais') {
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

            //$recipientPhone = trim(ereg_replace("[^0-9.-]", " ", $_shippingAddress->getTelephone()));
            $recipientPhone = trim(preg_replace("/[^0-9\.\-]/", " ", $_shippingAddress->getTelephone()));
            if (!isset($recipient_address[1])) {
                $recipient_address[1] = '';
            }

            $shipper = array(
                'shipperAdress1' => substr($this->getFilledValue($recipient_address[0]), 0, 38),
                'shipperAdress2' => substr($this->getFilledValue($recipient_address[1]), 0, 38),
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
            $send_day = strtolower(date('l'));
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
                'productCode' => Chronopost_Chronorelais_Helper_Data::CHRONO_POST,
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
                'esdParams' => $esdParams,
                'header' => $header,
                'shipper' => $shipper,
                'customer' => $customer,
                'recipient' => $recipient,
                'ref' => $ref,
                'skybill' => $skybill,
                'skybillParams' => $skybillParams,
                'password' => $_helper->getConfigurationAccountPass(),
                'option' => '0'
            );

            return $expeditionArray;
        }
    }

    protected function getEtiquetteRetourUrl($shipment) {
        //On récupère les infos d'expédition
        $reservationNumber = '';
        $_helper = Mage::helper('chronorelais');

        $_order = $shipment->getOrder();
        $_shippingMethod = explode("_", $_order->getShippingMethod());

        $expeditionArray = $this->getEtiquetteRetourParams($shipment, $_shippingMethod);
        $tracking_number = '';
        if ($expeditionArray) {
            $client = new SoapClient("http://wsshipping.chronopost.fr/shipping/services/services/ServiceEProcurement?wsdl", array('trace' => true));
            try {
                $webservbt = $client->__call("reservationExpeditionV2", $expeditionArray);
                if (!$webservbt->errorCode && $webservbt->reservationNumber) {
                    $tracking_number = $webservbt->skybillNumber;
                    return $webservbt->reservationNumber;
                } else {
                    $this->_getSession()->addError($_helper->__($webservbt->errorMessage));
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

    /* create folder media/chronopost if not exist */
    protected function createMediaChronopostFolder() {
        $path = 'media/chronopost';
        if(!is_dir($path)) {
            mkdir($path,0777);
        }
    }

}