<?php

require_once 'Mage/Adminhtml/controllers/Sales/Order/ShipmentController.php';

class Chronopost_Chronorelais_Sales_ImpressionController extends Mage_Adminhtml_Sales_Order_ShipmentController {

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

  protected function _processDownload($resource, $resourceType) {
    $helper = Mage::helper('downloadable/download');
    /* @var $helper Mage_Downloadable_Helper_Download */

    $helper->setResource($resource, $resourceType);

    $fileName = $helper->getFilename();
    $contentType = $helper->getContentType();

    $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', $contentType, true);

    if ($fileSize = $helper->getFilesize()) {
      $this->getResponse()
              ->setHeader('Content-Length', $fileSize);
    }

    if ($contentDisposition = $helper->getContentDisposition()) {
      $this->getResponse()
              ->setHeader('Content-Disposition', $contentDisposition . '; filename=' . $fileName);
    }

    $this->getResponse()
            ->clearBody();
    $this->getResponse()
            ->sendHeaders();

    $helper->output();
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

    if ($_shippingMethod[0] == 'chronorelais' || $_shippingMethod[0] == 'chronopost' || $_shippingMethod[0] == 'chronoexpress') {
      $esdParams = $header = $shipper = $customer = $recipient = $ref = $skybill = $skybillParams = $password = array();

      //esdParams parameters
      $esdParams = array(
          'height' => '',
          'width' => '',
          'length' => ''
      );

      //header parameters
      $header = array(
          'idEmit' => 'CHRFR',
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
      $recipientPhone = trim(ereg_replace("[^0-9.-]", " ", $_shippingAddress->getTelephone()));

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
          'productCode' => $_helper->getChronoProductCode($_shippingAddress->getCountryId(), $_shippingMethod[0]),
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

  public function initShipment($orderId) {
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
    $savedQtys = $this->_getItemQtys();
    $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($savedQtys);

    Mage::register('current_shipment', $shipment);
    return $shipment;
  }

  public function createNewShipment($orderId) {
    $_helper = Mage::helper('chronorelais');
    $reservationNumber = '';
    try {
      if ($shipment = $this->initShipment($orderId)) {
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
    $shipmentsIds = $this->getRequest()->getPost('shipment_ids');
    try {
      $trackingNumber = $this->getEtiquetteUrl($shipmentsIds);
      if ($trackingNumber) {
        $tracking_url = str_replace('{trackingNumber}', $trackingNumber, Mage::helper('chronorelais')->getConfigurationTrackingUrl());
        $this->_processDownload($tracking_url, 'url');
        exit(0);
      }
    } catch (Mage_Core_Exception $e) {
      $this->_getSession()->addError(Mage::helper('chronorelais')->__('Désolé, une erreure est survenu lors de la récupération de l\'étiquetes. Merci de contacter Chronopost ou de réessayer plus tard'));
    }
    return $this->_redirectReferer();
  }

  public function printAction() {
    // Appel via order_id
    $orderId = $this->getRequest()->getParam('order_id');
    if ($orderId) {
      if ($_shipments = $this->getShipmentByOrderId($orderId)) {
        if (count($_shipments) == 1) {
          $shipmentId = $_shipments[0];
          $trackingNumber = $this->getEtiquetteUrl($shipmentId);
        } else {
          $track = "Cette commande contient plusieurs expéditions, cliquez sur chaque lien pour obtenir les étiquettes :<br>";
          foreach ($_shipments as $_shipment) {
            $url = str_replace('{trackingNumber}', $this->getEtiquetteUrl($_shipment), Mage::helper('chronorelais')->getConfigurationTrackingUrl());
            $track .= '<a target="_blank" href="'.$url.'">'.$url.'</a><br />';
          }
          echo $track;
          return;
        }
      } else {
        $trackingNumber = $this->createNewShipment($orderId);
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
        $tracking_url = str_replace('{trackingNumber}', $trackingNumber, Mage::helper('chronorelais')->getConfigurationTrackingUrl());
        $this->_processDownload($tracking_url, 'url');
        exit(0);
      } catch (Mage_Core_Exception $e) {
        $this->_getSession()->addError(Mage::helper('chronorelais')->__('Désolé, une erreure est survenu lors de la récupération de l\'étiquetes. Merci de contacter Chronopost ou de réessayer plus tard'));
      }
    }
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

}