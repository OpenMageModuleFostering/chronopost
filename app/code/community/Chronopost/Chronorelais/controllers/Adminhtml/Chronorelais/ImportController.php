<?php
class Chronopost_Chronorelais_Adminhtml_Chronorelais_ImportController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Constructor
     */
    protected function _construct()
    {
        $this->setUsedModuleName('Chronopost_Chronorelais');
    }

    /**
     * Main action : show import form
     */
    public function indexAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/chronorelais/import')
            ->_addContent($this->getLayout()->createBlock('chronorelais/import_form'))
            ->renderLayout();
    }

    /**
     * Import Action
     */
    public function importAction()
    {
        if ($this->getRequest()->isPost() && !empty($_FILES['import_chronorelais_file']['tmp_name'])) {
            try {
                $trackingTitle = $_POST['import_chronorelais_tracking_title'];
                $this->_importChronorelaisFile($_FILES['import_chronorelais_file']['tmp_name'], $trackingTitle);
            }
            catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
            catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $this->_getSession()->addError($this->__('Invalid file upload attempt'));
            }
        }
        else {
            $this->_getSession()->addError($this->__('Invalid file upload attempt'));
        }
        $this->_redirect('*/*/index');
    }

    /**
     * Importation logic
     * @param string $fileName
     * @param string $trackingTitle
     */
    protected function _importChronorelaisFile($fileName, $trackingTitle)
    {
        /**
         * File handling
         **/
        ini_set('auto_detect_line_endings', true);
        $csvObject = new Varien_File_Csv();
        $csvData = $csvObject->getData($fileName);

        /**
         * File expected fields
         */
        $expectedCsvFields  = array(
            0   => $this->__('Order Id'),
            1   => $this->__('Tracking Number')
        );

        /**
         * Get configuration
         */
        $sendEmail = Mage::helper('chronorelais')->getConfigurationSendEmail();
        $comment = Mage::helper('chronorelais')->getConfigurationShippingComment();
        $includeComment = Mage::helper('chronorelais')->getConfigurationIncludeComment();

        /**
         * $k is line number
         * $v is line content array
         */
        foreach ($csvData as $k => $v) {

            /**
             * End of file has more than one empty lines
             */
            if (count($v) <= 1 && !strlen($v[0])) {
                continue;
            }

            /**
             * Check that the number of fields is not lower than expected
             */
            if (count($v) < count($expectedCsvFields)) {
                $this->_getSession()->addError($this->__('Line %s format is invalid and has been ignored', $k));
                continue;
            }

            /**
             * Get fields content
             */
            $orderId = $v[0];
            $trackingNumber = $v[1];

            /**
             * Try to load the order
             */
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if (!$order->getId()) {
                $this->_getSession()->addError($this->__('Order %s does not exist', $orderId));
                continue;
            }

            /**
             * Try to create a shipment
             */
            $shipmentId = $this->_createShipment($order, $trackingNumber, $trackingTitle, $sendEmail, $comment, $includeComment);

            if ($shipmentId != 0) {
                $this->_getSession()->addSuccess($this->__('Shipment %s created for order %s, with tracking number %s', $shipmentId, $orderId, $trackingNumber));
            }

        }//foreach

    }

    /**
     * Create new shipment for order
     * Inspired by Mage_Sales_Model_Order_Shipment_Api methods
     *
     * @param Mage_Sales_Model_Order $order (it should exist, no control is done into the method)
     * @param string $trackingNumber
     * @param string $trackingTitle
     * @param booleam $email
     * @param string $comment
     * @param boolean $includeComment
     * @return int : shipment real id if creation was ok, else 0
     */
    public function _createShipment($order, $trackingNumber, $trackingTitle, $email, $comment, $includeComment)
    {
        /**
         * Check shipment creation availability
         */
        if (!$order->canShip()) {
            $this->_getSession()->addError($this->__('Order %s can not be shipped or has already been shipped', $order->getRealOrderId()));
            return 0;
        }

        /**
         * Initialize the Mage_Sales_Model_Order_Shipment object
         */
        $convertor = Mage::getModel('sales/convert_order');
        $shipment = $convertor->toShipment($order);

        /**
         * Add the items to send
         */
        foreach ($order->getAllItems() as $orderItem) {
            if (!$orderItem->getQtyToShip()) {
                continue;
            }
            if ($orderItem->getIsVirtual()) {
                continue;
            }

            $item = $convertor->itemToShipmentItem($orderItem);
            $qty = $orderItem->getQtyToShip();
            $item->setQty($qty);

        	$shipment->addItem($item);
        }//foreach

        $shipment->register();

        /**
         * Tracking number instanciation
         */

		$_shippingMethod = explode("_",$order->getShippingMethod());
		switch($_shippingMethod[0]) {
			case "chronopost":
			case "chronorelais":
			case "chronoexpress":
				$carrier_code = $_shippingMethod[0];
				$popup = 1;
				$tracking_url = str_replace('{tracking_number}', $trackingNumber, Mage::helper('chronorelais')->getConfigurationTrackingViewUrl());
				$tracking_title = $this->__('Track Your Order');
				$tracking_order = '<p><a title="'.$tracking_title.'" href="'.$tracking_url.'"><b>'.$tracking_title.'</b></a></p>';
				break;
			default:
				$carrier_code = 'custom';
				$popup = 0;
				$tracking_order = '';
				break;
		}

        $track = Mage::getModel('sales/order_shipment_track')
                	->setNumber($trackingNumber) //setTrackingNumber ?
                    ->setCarrierCode($carrier_code)
                    ->setTitle($trackingTitle)
					->setPopup($popup);
        $shipment->addTrack($track);

        /**
         * Comment handling
         */
        $shipment->addComment($comment, $email && $includeComment);

        /**
         * Change order status to Processing
         */
        $shipment->getOrder()->setIsInProcess(true);

        /**
         * If e-mail, set as sent (must be done before shipment object saving)
         */
        if ($email) {
            $shipment->setEmailSent(true);
        }

        try {
        	/**
             * Save the created shipment and the updated order
             */
            $shipment->save();
            $shipment->getOrder()->save();

            /**
             * Email sending
             */
            $shipment->sendEmail($email, ($includeComment ? $tracking_order.$comment : $tracking_order));
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($this->__('Shipment creation error for Order %s : %s', $orderId, $e->getMessage()));
            return 0;
        }

        /**
         * Everything was ok : return Shipment real id
         */
        return $shipment->getIncrementId();
    }
}