<?php

require_once 'Mage/Adminhtml/controllers/Sales/OrderController.php';

class Chronopost_Chronorelais_Adminhtml_Chronorelais_Sales_BordereauController extends Mage_Adminhtml_Sales_OrderController {

    /**
     * Additional initialization
     *
     */
    protected function _construct() {
        $this->setUsedModuleName('Chronopost_Chronorelais');
    }

    /**
     * Order grid
     */
    public function indexAction() {
        if (!extension_loaded('soap')) {
            $this->_getSession()->addError($this->__('The SOAP extension is not installed in the server. Please contact the site administrator. Sorry for inconvenience.'));
            return $this->_redirectReferer();
        }
        $this->loadLayout()
                ->_setActiveMenu('sales/chronorelais')
                ->_addContent($this->getLayout()->createBlock('chronorelais/sales_bordereau'))
                ->renderLayout();
    }

    public function massPrintBordereauAction() {
        $orderIds = $this->getRequest()->getPost('order_ids');
        try {

            $weightNational = 0;
            $nbNational = 0;
            $weightInternational = 0;
            $nbInternational = 0;

            $helper = Mage::helper('chronorelais');

            /* Shipper */
            $shipper = array(
                'accountNumber' => $helper->getConfigurationAccountNumber(),
                'name' => $helper->getConfigurationShipperInfo('name'),
                'address1' => $helper->getConfigurationShipperInfo('address1'),
                'address2' => $helper->getConfigurationShipperInfo('address2'),
                'city' => $helper->getConfigurationShipperInfo('city'),
                'postcode' => $helper->getConfigurationShipperInfo('zipcode'),
                'country' => $helper->getConfigurationShipperInfo('country'),
                'phone' => $helper->getConfigurationShipperInfo('phone'),
            );

            $detail = '';
            $resume = '';
            foreach($orderIds as $orderId) {
                $order = Mage::getModel('sales/order')->load($orderId);
                $_shippingMethod = explode("_", $order->getShippingMethod());
                $productCode = 'Chrono '.$helper->getChronoProductCodeStringWithBAL($_shippingMethod[0]);
                $shipments = $order->getShipmentsCollection();
                foreach($shipments as $shipment) {

                    /* Tracking Number */
                    $trackNumber = $this->getTrackingNumber($shipment->getId());

                    /* items */
                    $items = $shipment->getAllItems();
                    $weightTotal = 0;

                    $maxAmount = $helper->getMaxAdValoremAmount();
                    $adValoremAmount = $helper->assuranceAdValoremAmount();
                    $adValoremEnabled = $helper->assuranceAdValoremEnabled();
                    $totalAdValorem = 0;

                    foreach($items as $item) {
                        $weightTotal += $item->getWeight() * $item->getQty();
                        $totalAdValorem += $item->getPrice() * $item->getQty();
                    }
                    /* Si montant < au montant minimum ad valorem => pas d'assurance */
                    $totalAdValorem = 0;
                    if($adValoremEnabled)
                    {
                        $totalAdValorem = min($totalAdValorem,$maxAmount);
                        if($totalAdValorem < $adValoremAmount) {
                            $totalAdValorem = 0;
                        }
                    }

                    /* Shipping address */
                    $address = Mage::getModel('sales/order_address')->load($shipment->getShippingAddressId());
                    if($address->getCountryId() == 'FR') {
                        $weightNational += $weightTotal;
                        $nbNational++;
                    }
                    else {
                        $weightInternational += $weightTotal;
                        $nbInternational++;
                    }

                    $detail[] = array(
                        'trackNumber' => $trackNumber,
                        'weight' => $weightTotal,
                        'product_code' => $productCode,
                        'postcode' => $address->getPostcode(),
                        'country' => $address->getCountryId(),
                        'assurance' => $totalAdValorem,
                        'city' => Mage::helper('core/string')->truncate($address->getCity(),17),
                    );
                }
            }
            $resume = array(
                'NATIONAL' => array('unite' => $nbNational, 'poids' => $weightNational),
                'INTERNATIONAL' => array('unite' => $nbInternational, 'poids' => $weightInternational),
                'TOTAL' => array('unite' => ($nbNational+$nbInternational), 'poids' => ($weightNational+$weightInternational)),
            );

            /* Create pdf */
            $fileName = 'bordereau.pdf';
            $content = $this->getPdfFile($shipper,$detail,$resume);
            $this->_prepareDownloadResponse($fileName, $content);

        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError(Mage::helper('chronorelais')->__('Désolé, une erreur est survenu lors de la génération des bordereau. Merci de contacter Chronopost ou de réessayer plus tard'));
        }
    }

    protected function getPdfFile($shipper,$detail,$resume) {
        $pdf = new Zend_Pdf();
        $page = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_A4);

        $helper = Mage::helper('chronorelais');
        $weightUnit = $helper->getConfigWeightUnit();

        $minYPosToChangePage = 60;
        $xPos = 20;
        $yPos = $page->getHeight()-20;
        $lineHeight = 15;

        $font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES);
        $fontBold = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES_BOLD);

        /* DATE */
        $page->setFont($font, 11);
        $page->drawText('date : '.date('d/m/Y'), $page->getWidth()-100, $yPos);
        $yPos -= ($lineHeight);
        $page->setFont($font, 12);

        /* TITRE */
        $page->setFont($fontBold, 12);
        $page->drawText('BORDEREAU RECAPITULATIF', $xPos, $yPos);
        $yPos -= ($lineHeight+20);
        $page->setFont($font, 12);


        /* EMETTEUR */
        $page->setFont($fontBold, 12);
        $page->drawText('EMETTEUR: ', $xPos, $yPos);
        $yPos -= ($lineHeight+5);
        $page->setFont($font, 12);

        $page->drawText('NUMERO DE COMPTE', $xPos, $yPos);
        $page->drawText($shipper['accountNumber'], $xPos+150, $yPos);
        $yPos -= $lineHeight;

        $page->drawText('NOM', $xPos, $yPos);
        $page->drawText($shipper['name'], $xPos+150, $yPos);
        $yPos -= $lineHeight;

        $page->drawText('ADRESSE', $xPos, $yPos);
        $page->drawText($shipper['address1'], $xPos+150, $yPos);
        $yPos -= $lineHeight;

        $page->drawText('ADRESSE (SUITE)', $xPos, $yPos);
        $page->drawText($shipper['address2'], $xPos+150, $yPos);
        $yPos -= $lineHeight;

        $page->drawText('VILLE', $xPos, $yPos);
        $page->drawText($shipper['city'], $xPos+150, $yPos);
        $yPos -= $lineHeight;

        $page->drawText('CODE POSTAL', $xPos, $yPos);
        $page->drawText($shipper['postcode'], $xPos+150, $yPos);
        $yPos -= $lineHeight;

        $page->drawText('PAYS', $xPos, $yPos);
        $page->drawText($shipper['country'], $xPos+150, $yPos);
        $yPos -= $lineHeight;

        $page->drawText('TELEPHONE', $xPos, $yPos);
        $page->drawText($shipper['phone'], $xPos+150, $yPos);
        $yPos -= $lineHeight;

        $page->drawText('POSTE COMPTABLE', $xPos, $yPos);
        $page->drawText(substr($shipper['postcode'],0,2).'999', $xPos+150, $yPos);
        $yPos -= $lineHeight;

        /* DETAIL DES ENVOIS */
        $yPos -= 50;
        $page->setFont($fontBold, 12);
        $page->drawText('DETAIL DES ENVOIS ', $xPos, $yPos);
        $yPos -= ($lineHeight+5);
        $page->setFont($font, 12);

        $page->setFillColor(new Zend_Pdf_Color_Rgb(0.85, 0.85, 0.85));
        $page->setLineColor(new Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawRectangle($xPos, $yPos, 570, $yPos -20);
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
        $yPos -= 15;

        $page->drawText("Numéro de LT", $xPos+5, $yPos,'UTF-8');
        $page->drawText('Poids (kg)', $xPos+110, $yPos);
        $page->drawText('Code produit', $xPos+170, $yPos);
        $page->drawText('Code postal', $xPos+270, $yPos);
        $page->drawText('Pays', $xPos+340, $yPos);
        $page->drawText('Assurance', $xPos+380, $yPos);
        $page->drawText('Ville', $xPos+440, $yPos);
        $yPos -= 5;

        foreach($detail as $line) {

            $page->setFillColor(new Zend_Pdf_Color_Rgb(255, 255, 255));
            $page->setLineColor(new Zend_Pdf_Color_GrayScale(0.5));
            $page->setLineWidth(0.5);
            $page->drawRectangle($xPos, $yPos, 570, $yPos -20);
            $page->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
            $yPos -= 15;

            $lineWeight = $line['weight'];
            if($weightUnit == 'g') {
                $lineWeight = $lineWeight / 1000;
            }

            $page->drawText($line['trackNumber'], $xPos+5, $yPos,'UTF-8');
            $page->drawText($lineWeight, $xPos+110, $yPos);
            $page->drawText($line['product_code'], $xPos+170, $yPos);
            $page->drawText($line['postcode'], $xPos+270, $yPos);
            $page->drawText($line['country'], $xPos+340, $yPos);
            $page->drawText($line['assurance'], $xPos+380, $yPos);
            $page->drawText($line['city'], $xPos+440, $yPos,'UTF-8');
            $yPos -= 5;

            if($yPos <= $minYPosToChangePage) {
                $pdf->pages[] = $page;
                $page = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_A4);
                $yPos = $page->getHeight()-20;
            }
        }

        /* RESUME */
        $yPos -= 50;
        $page->setFont($fontBold, 12);
        $page->drawText('RESUME ', $xPos, $yPos);
        $yPos -= ($lineHeight+5);
        $page->setFont($font, 12);

        $page->setFillColor(new Zend_Pdf_Color_Rgb(0.85, 0.85, 0.85));
        $page->setLineColor(new Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawRectangle($xPos, $yPos, 570, $yPos -20);
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
        $yPos -= 15;

        $page->drawText("DESTINATION", $xPos+5, $yPos,'UTF-8');
        $page->drawText('UNITE', $xPos+170, $yPos);
        $page->drawText('POIDS TOTAL (kg)', $xPos+320, $yPos);
        $yPos -= 5;

        foreach($resume as $destination => $line) {

            $page->setFillColor(new Zend_Pdf_Color_Rgb(255, 255, 255));
            $page->setLineColor(new Zend_Pdf_Color_GrayScale(0.5));
            $page->setLineWidth(0.5);
            $page->drawRectangle($xPos, $yPos, 570, $yPos -20);
            $page->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
            $yPos -= 15;

            $lineWeight = $line['poids'];
            if($weightUnit == 'g') {
                $lineWeight = $lineWeight / 1000;
            }

            $page->drawText($destination, $xPos+5, $yPos,'UTF-8');
            $page->drawText($line['unite'], $xPos+180, $yPos);
            $page->drawText($lineWeight, $xPos+340, $yPos);
            $yPos -= 5;
        }

        if($yPos <= $minYPosToChangePage) {
            $pdf->pages[] = $page;
            $page = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_A4);
            $yPos = $page->getHeight()-20;
        }

        $yPos -= 50;
        $page->setFont($fontBold, 12);
        $page->drawText('Bien pris en charge '.$resume['TOTAL']['unite'].' colis', $xPos, $yPos);

        if($yPos <= $minYPosToChangePage) {
            $pdf->pages[] = $page;
            $page = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_A4);
            $yPos = $page->getHeight()-20;
        }

        /* signatures */
        $yPos -= 60;
        $page->setFont($font, 12);
        $page->drawText('Signature du client', $xPos, $yPos);
        $page->drawText('Signature du Messager Chronopost', 400, $yPos);



        $pdf->pages[] = $page;
        return $pdf->render();
    }


    protected function getTrackingNumber($shipmentId) {
        $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
        $trackNumber = '';
        //On récupère le numéro de tracking
        $tracks = $shipment->getTracksCollection();
        foreach ($tracks as $track) {
            if ($track->getParentId() == $shipmentId) {
                $trackNumber = $track->getnumber();
            }
        }

        return $trackNumber;
    }
}