<?php

class Chronopost_Chronorelais_Adminhtml_Chronorelais_ExportController extends Mage_Adminhtml_Controller_Action {

    /**
     * Constructor
     */
    protected function _construct() {
        $this->setUsedModuleName('Chronopost_Chronorelais');
    }

    protected function _isAllowed(){
        return true;
    }

    /**
     * Main action : show orders list
     */
    public function indexAction() {
        $this->loadLayout()
                ->_setActiveMenu('sales/chronorelais/export')
                ->_addContent($this->getLayout()->createBlock('chronorelais/export_orders'))
                ->renderLayout();
    }

    public function getValue($value) {
        return ($value != '' ? $value : '');
    }

    public function removeSpclChars($text) {
        return preg_replace("/[^0-9a-zA-Z]/", "", $text);
    }

    public function massLivraisonSamediStatusAction() {
        if ($this->getRequest()->getPost('status')) {
            $this->saveLivraisonSamediStatusAction();
        }
    }

    public function massExportAction() {
        if ($this->getRequest()->getPost('format') == 'css') {
            $this->exportcssAction();
        } elseif ($this->getRequest()->getPost('format') == 'cso') {
            $this->exportcsoAction();
        }
    }

    /**
     * Export CSS Action
     * Generates a CSV file to download (CSS format)
     */
    public function exportcssAction() {
        /* get the orders */
        $orderIds = $this->getRequest()->getPost('order_ids');

        /**
         * Get configuration
         */
        $separator = Mage::helper('chronorelais')->getConfigurationFieldSeparator('css');
        $delimiter = Mage::helper('chronorelais')->getConfigurationFieldDelimiter('css');

        if ($delimiter == 'simple_quote') {
            $delimiter = "'";
        } else if ($delimiter == 'double_quotes') {
            $delimiter = '"';
        } else {
            $delimiter = '';
        }
        $lineBreak = Mage::helper('chronorelais')->getConfigurationEndOfLineCharacter('css');
        if ($lineBreak == 'lf') {
            $lineBreak = "\n";
        } else if ($lineBreak == 'cr') {
            $lineBreak = "\r";
        } else if ($lineBreak == 'crlf') {
            $lineBreak = "\r\n";
        }
        $fileExtension = Mage::helper('chronorelais')->getConfigurationFileExtension('css');
        $fileCharset = Mage::helper('chronorelais')->getConfigurationFileCharset('css');

        /* set the filename */
        $filename = 'orders_exportcss_' . Mage::getSingleton('core/date')->date('Ymd_His') . $fileExtension;

        /* initialize the content variable */
        $content = '';
        $helper = Mage::helper('chronorelais');
        $weightUnit = $helper->getConfigWeightUnit();
        if (!empty($orderIds)) {
            foreach ($orderIds as $orderId) {

                /* get the order */
                $order = Mage::getModel('sales/order')->load($orderId);
                $address = $order->getShippingAddress();
                $billingAddress = $order->getBillingAddress();

                $_shippingMethod = explode('_', $order->getShippingMethod());

                /* customer id */
                $content = $this->_addFieldToCsv($content, $delimiter, ($order->getCustomerId() ? $order->getCustomerId() : $address->getLastname()));
                $content .= $separator;
                /* Nom du point relais OU société si livraison à domicile */
                $content = $this->_addFieldToCsv($content, $delimiter, $address->getCompany());
                $content .= $separator;
                /* customer name */
                $content = $this->_addFieldToCsv($content, $delimiter, ($address->getFirstname() ? $address->getFirstname() : $billingAddress->getFirstname()));
                $content .= $separator;
                $content = $this->_addFieldToCsv($content, $delimiter, ($address->getLastname() ? $address->getLastname() : $billingAddress->getLastname()));
                $content .= $separator;
                /* street address */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getStreet(1)));
                $content .= $separator;
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getStreet(2)));
                $content .= $separator;

                /* digicode (vide)*/
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* postal code */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getPostcode()));
                $content .= $separator;
                /* city */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getCity()));
                $content .= $separator;
                /* country code */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getCountry()));
                $content .= $separator;
                /* telephone */
                $telephone = trim(ereg_replace("[^0-9.-]", " ", $address->getTelephone()));
                $telephone = (strlen($telephone) >= 10 ? $telephone : '');
                $content = $this->_addFieldToCsv($content, $delimiter, $telephone);
                $content .= $separator;
                /* email */
                $customer_email = ($address->getEmail()) ? $address->getEmail() : ($billingAddress->getEmail() ? $billingAddress->getEmail() : $order->getCustomerEmail());
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($customer_email));
                $content .= $separator;
                /* real order id */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($order->getRealOrderId()));
                $content .= $separator;

                /* code barre client (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* productCode */
                $productCode = Mage::helper('chronorelais')->getChronoProductCodeString($_shippingMethod[0]);
                $content = $this->_addFieldToCsv($content, $delimiter, $productCode);
                $content .= $separator;

                /* compte (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* sous compte (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* empty fields */
                $content = $this->_addFieldToCsv($content, $delimiter, 0);
                $content .= $separator;
                $content = $this->_addFieldToCsv($content, $delimiter, 0);
                $content .= $separator;

                /* document / marchandise (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* description contenu (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* Livraison Samedi */
                $SaturdayShipping = 'L'; //default value for the saturday shipping
                if ($_shippingMethod[0] == "chronopost" || $_shippingMethod[0] == "chronorelais") {
                    if (!$_deliver_on_saturday = Mage::helper('chronorelais')->getLivraisonSamediStatus($orderId)) {
                        $_deliver_on_saturday = Mage::helper('chronorelais')->getConfigData('carriers/' . $_shippingMethod[0] . '/deliver_on_saturday');
                    } else {
                        if ($_deliver_on_saturday == 'Yes') {
                            $_deliver_on_saturday = 1;
                        } else {
                            $_deliver_on_saturday = 0;
                        }
                    }
                    $is_sending_day = Mage::helper('chronorelais')->isSendingDay();
                    if ($_deliver_on_saturday && $is_sending_day == true) {
                        $SaturdayShipping = 'S';
                    } elseif (!$_deliver_on_saturday && $is_sending_day == true) {
                        $SaturdayShipping = 'L';
                    }
                }
                $content = $this->_addFieldToCsv($content, $delimiter, $SaturdayShipping);
                $content .= $separator;

                /* chronorelay point */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getWRelayPointCode()));
                $content .= $separator;

                /* total weight (in kg) */
                $order_weight = number_format($order->getWeight(), 2, '.', '');
                if($weightUnit == 'g') {
                    $order_weight = $order_weight / 1000;
                }
                $content = $this->_addFieldToCsv($content, $delimiter, $order_weight);
                $content .= $separator;


                /* largeur (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* longueur (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* hauteur (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* avertir destinataire (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* nb colis (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* date envoi */
                $content = $this->_addFieldToCsv($content, $delimiter, date('d/m/Y'));
                $content .= $separator;

                /* a intégrer (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* avertir expéditeur (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* DLC (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* champ specifique rdv */
                $chronopostsrdv_creneaux_info = $address->getData('chronopostsrdv_creneaux_info');
                if($chronopostsrdv_creneaux_info) {
                    $chronopostsrdv_creneaux_info = json_decode($chronopostsrdv_creneaux_info,true);
                    $_dateRdvStart = new DateTime($chronopostsrdv_creneaux_info['deliveryDate']);
                    $_dateRdvStart->setTime($chronopostsrdv_creneaux_info['startHour'],$chronopostsrdv_creneaux_info['startMinutes']);

                    $_dateRdvEnd = new DateTime($chronopostsrdv_creneaux_info['deliveryDate']);
                    $_dateRdvEnd->setTime($chronopostsrdv_creneaux_info['endHour'],$chronopostsrdv_creneaux_info['endMinutes']);

                    /* date debut rdv */
                    //$content = $this->_addFieldToCsv($content, $delimiter, $_dateRdvStart->format("Y-m-d")."T".$_dateRdvStart->format("H:i:s"));
                    $content = $this->_addFieldToCsv($content, $delimiter, $_dateRdvStart->format("dmyHi"));
                    $content .= $separator;

                    /* date fin rdv */
                    //$content = $this->_addFieldToCsv($content, $delimiter, $_dateRdvEnd->format("Y-m-d")."T".$_dateRdvEnd->format("H:i:s"));
                    $content = $this->_addFieldToCsv($content, $delimiter, $_dateRdvEnd->format("dmyHi"));
                    $content .= $separator;

                    /* Niveau tarifaire */
                    $content = $this->_addFieldToCsv($content, $delimiter, $chronopostsrdv_creneaux_info['tariffLevel']);
                    $content .= $separator;

                    /* code service */
                    $content = $this->_addFieldToCsv($content, $delimiter, $chronopostsrdv_creneaux_info['serviceCode']);
                    $content .= $separator;

                } else {
                    $content = $this->_addFieldToCsv($content, $delimiter, '');
                    $content .= $separator;

                    $content = $this->_addFieldToCsv($content, $delimiter, '');
                    $content .= $separator;

                    $content = $this->_addFieldToCsv($content, $delimiter, '');
                    $content .= $separator;

                    /* code service */
                    $content = $this->_addFieldToCsv($content, $delimiter, '');
                    $content .= $separator;
                }



                $content .= $lineBreak;

            }

            /* decode the content, depending on the charset */
            if ($fileCharset == 'ISO-8859-1') {
                $content = utf8_decode($content);
            }

            /* pick file mime type, depending on the extension */
            switch ($fileExtension) {
                case '.csv':
                    $fileMimeType = 'application/csv';
                    break;
                case '.chr':
                    $fileMimeType = 'application/chr';
                    break;
                default:
                    $fileMimeType = 'text/plain';
                    break;
            }
            /* download the file */
            return $this->_prepareDownloadResponse($filename, $content, $fileMimeType . '; charset="' . $fileCharset . '"');
        } else {
            $this->_getSession()->addError($this->__('No Order has been selected'));
        }
    }

    /**
     * Export CSO Action
     * Generates a CSV file to download (CSO format)
     */
    public function exportcsoAction() {
        /* get the orders */
        $orderIds = $this->getRequest()->getPost('order_ids');

        /**
         * Get configuration
         */
        $separator = Mage::helper('chronorelais')->getConfigurationFieldSeparator('cso');
        $delimiter = Mage::helper('chronorelais')->getConfigurationFieldDelimiter('cso');
        if ($delimiter == 'simple_quote') {
            $delimiter = "'";
        } else if ($delimiter == 'double_quotes') {
            $delimiter = '"';
        } else {
            $delimiter = '';
        }
        $lineBreak = Mage::helper('chronorelais')->getConfigurationEndOfLineCharacter('cso');
        if ($lineBreak == 'lf') {
            $lineBreak = "\n";
        } else if ($lineBreak == 'cr') {
            $lineBreak = "\r";
        } else if ($lineBreak == 'crlf') {
            $lineBreak = "\r\n";
        }
        $fileExtension = Mage::helper('chronorelais')->getConfigurationFileExtension('cso');
        $fileCharset = Mage::helper('chronorelais')->getConfigurationFileCharset('cso');

        /* set the filename */
        $filename = 'orders_exportcso_' . Mage::getSingleton('core/date')->date('Ymd_His') . $fileExtension;

        /* initialize the content variable */
        $content = '';
        $helper = Mage::helper('chronorelais');
        $weightUnit = $helper->getConfigWeightUnit();
        if (!empty($orderIds)) {
            foreach ($orderIds as $orderId) {

                /* get the order */
                $order = Mage::getModel('sales/order')->load($orderId);
                $address = $order->getShippingAddress();
                $billingAddress = $order->getBillingAddress();

                $_shippingMethod = explode('_', $order->getShippingMethod());

                /* customer id */
                $content = $this->_addFieldToCsv($content, $delimiter, ($order->getCustomerId() ? $order->getCustomerId() : $address->getLastname()));
                $content .= $separator;
                /* Nom du point relais OU société si livraison à domicile */
                $content = $this->_addFieldToCsv($content, $delimiter, $address->getCompany());
                $content .= $separator;
                /* customer name */
                $content = $this->_addFieldToCsv($content, $delimiter, ($address->getFirstname() ? $address->getFirstname() : $billingAddress->getFirstname()));
                $content .= $separator;
                $content = $this->_addFieldToCsv($content, $delimiter, ($address->getLastname() ? $address->getLastname() : $billingAddress->getLastname()));
                $content .= $separator;
                /* street address */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getStreet(1)));
                $content .= $separator;
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getStreet(2)));
                $content .= $separator;

                /* digicode (vide)*/
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* postal code */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getPostcode()));
                $content .= $separator;
                /* city */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getCity()));
                $content .= $separator;
                /* country code */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getCountry()));
                $content .= $separator;
                /* telephone */
                $telephone = trim(ereg_replace("[^0-9.-]", " ", $address->getTelephone()));
                $telephone = (strlen($telephone) >= 10 ? $telephone : '');
                $content = $this->_addFieldToCsv($content, $delimiter, $telephone);
                $content .= $separator;
                /* email */
                $customer_email = ($address->getEmail()) ? $address->getEmail() : ($billingAddress->getEmail() ? $billingAddress->getEmail() : $order->getCustomerEmail());
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($customer_email));
                $content .= $separator;
                /* real order id */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($order->getRealOrderId()));
                $content .= $separator;

                /* code barre client (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* productCode */
                $productCode = Mage::helper('chronorelais')->getChronoProductCodeString($_shippingMethod[0]);
                $content = $this->_addFieldToCsv($content, $delimiter, $productCode);
                $content .= $separator;

                /* compte (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* sous compte (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* empty fields */
                $content = $this->_addFieldToCsv($content, $delimiter, 0);
                $content .= $separator;
                $content = $this->_addFieldToCsv($content, $delimiter, 0);
                $content .= $separator;

                /* document / marchandise (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* description contenu (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* Livraison Samedi */
                $SaturdayShipping = 'L'; //default value for the saturday shipping
                if ($_shippingMethod[0] == "chronopost" || $_shippingMethod[0] == "chronorelais") {
                    if (!$_deliver_on_saturday = Mage::helper('chronorelais')->getLivraisonSamediStatus($orderId)) {
                        $_deliver_on_saturday = Mage::helper('chronorelais')->getConfigData('carriers/' . $_shippingMethod[0] . '/deliver_on_saturday');
                    } else {
                        if ($_deliver_on_saturday == 'Yes') {
                            $_deliver_on_saturday = 1;
                        } else {
                            $_deliver_on_saturday = 0;
                        }
                    }
                    $is_sending_day = Mage::helper('chronorelais')->isSendingDay();
                    if ($_deliver_on_saturday && $is_sending_day == true) {
                        $SaturdayShipping = 'S';
                    } elseif (!$_deliver_on_saturday && $is_sending_day == true) {
                        $SaturdayShipping = 'L';
                    }
                }
                $content = $this->_addFieldToCsv($content, $delimiter, $SaturdayShipping);
                $content .= $separator;

                /* chronorelay point */
                $content = $this->_addFieldToCsv($content, $delimiter, $this->getValue($address->getWRelayPointCode()));
                $content .= $separator;

                /* total weight (in kg) */
                $order_weight = number_format($order->getWeight(), 2, '.', '');
                if($weightUnit == 'g') {
                    $order_weight = $order_weight / 1000;
                }
                $content = $this->_addFieldToCsv($content, $delimiter, $order_weight);
                $content .= $separator;


                /* largeur (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* longueur (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* hauteur (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* avertir destinataire (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* nb colis (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* date envoi */
                $content = $this->_addFieldToCsv($content, $delimiter, date('d/m/Y'));
                $content .= $separator;

                /* a intégrer (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* avertir expéditeur (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* DLC (vide) */
                $content = $this->_addFieldToCsv($content, $delimiter, '');
                $content .= $separator;

                /* champ specifique rdv */
                $chronopostsrdv_creneaux_info = $address->getData('chronopostsrdv_creneaux_info');
                if($chronopostsrdv_creneaux_info) {
                    $chronopostsrdv_creneaux_info = json_decode($chronopostsrdv_creneaux_info,true);
                    $_dateRdvStart = new DateTime($chronopostsrdv_creneaux_info['deliveryDate']);
                    $_dateRdvStart->setTime($chronopostsrdv_creneaux_info['startHour'],$chronopostsrdv_creneaux_info['startMinutes']);

                    $_dateRdvEnd = new DateTime($chronopostsrdv_creneaux_info['deliveryDate']);
                    $_dateRdvEnd->setTime($chronopostsrdv_creneaux_info['endHour'],$chronopostsrdv_creneaux_info['endMinutes']);

                    /* date debut rdv */
                    //$content = $this->_addFieldToCsv($content, $delimiter, $_dateRdvStart->format("Y-m-d")."T".$_dateRdvStart->format("H:i:s"));
                    $content = $this->_addFieldToCsv($content, $delimiter, $_dateRdvStart->format("dmyHi"));
                    $content .= $separator;

                    /* date fin rdv */
                    //$content = $this->_addFieldToCsv($content, $delimiter, $_dateRdvEnd->format("Y-m-d")."T".$_dateRdvEnd->format("H:i:s"));
                    $content = $this->_addFieldToCsv($content, $delimiter, $_dateRdvEnd->format("dmyHi"));
                    $content .= $separator;

                    /* Niveau tarifaire */
                    $content = $this->_addFieldToCsv($content, $delimiter, $chronopostsrdv_creneaux_info['tariffLevel']);
                    $content .= $separator;

                    /* code service */
                    $content = $this->_addFieldToCsv($content, $delimiter, $chronopostsrdv_creneaux_info['serviceCode']);
                    $content .= $separator;

                } else {
                    $content = $this->_addFieldToCsv($content, $delimiter, '');
                    $content .= $separator;

                    $content = $this->_addFieldToCsv($content, $delimiter, '');
                    $content .= $separator;

                    $content = $this->_addFieldToCsv($content, $delimiter, '');
                    $content .= $separator;

                    /* code service */
                    $content = $this->_addFieldToCsv($content, $delimiter, '');
                    $content .= $separator;
                }



                $content .= $lineBreak;

            }

            /* decode the content, depending on the charset */
            if ($fileCharset == 'ISO-8859-1') {
                $content = utf8_decode($content);
            }

            /* pick file mime type, depending on the extension */
            switch ($fileExtension) {
                case '.csv':
                    $fileMimeType = 'application/csv';
                    break;
                case '.chr':
                    $fileMimeType = 'application/chr';
                    break;
                default:
                    $fileMimeType = 'text/plain';
                    break;
            }
            /* download the file */
            return $this->_prepareDownloadResponse($filename, $content, $fileMimeType . '; charset="' . $fileCharset . '"');
        } else {
            $this->_getSession()->addError($this->__('No Order has been selected'));
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

    /**
     * Add a new field to the csv file
     * @param csvContent : the current csv content
     * @param fieldDelimiter : the delimiter character
     * @param fieldContent : the content to add
     * @return : the concatenation of current content and content to add
     */
    private function _addFieldToCsv($csvContent, $fieldDelimiter, $fieldContent) {
        return $csvContent . $fieldDelimiter . $fieldContent . $fieldDelimiter;
    }

}