<?php
class Chronopost_Chronorelais_Block_Adminhtml_Notification extends Mage_Core_Block_Template
{
    const XML_SEVERITY_ICONS_URL_PATH  = 'system/adminnotification/severity_icons_url';
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate('chronorelais/notification.phtml');
        }
        return $this;
    }

    public function getSeverityIconsUrl()
    {
        return (Mage::app()->getFrontController()->getRequest()->isSecure() ? 'https://' : 'http://')
                . sprintf(Mage::getStoreConfig(self::XML_SEVERITY_ICONS_URL_PATH), Mage::getVersion(),
                    'SEVERITY_NOTICE');
    }

    public function canShow()
    {
        if (!Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {
            return false;
        }

        $_helper = Mage::helper('chronorelais');
        $account_number = $_helper->getConfigurationAccountNumber();
        $password = $_helper->getConfigurationAccountPass();
        $origin_postcode = $_helper->getConfigurationShipperInfo('zipcode');

        $WSParams = array(
            'accountNumber' => $account_number,
            'password' => $password,
            'depCountryCode' => $_helper->getConfigurationShipperInfo('country'),
            'depZipCode' => $origin_postcode,
            'arrCountryCode' => $_helper->getConfigurationShipperInfo('country'),
            'arrZipCode' => $origin_postcode,
            'arrCity' => $_helper->getConfigurationShipperInfo('city'),
            'type' => 'M',
            'weight' => 1
        );

        $helperWS = Mage::helper('chronorelais/webservice');
        $webservbt = $helperWS->checkLogin($WSParams);

        if(!$webservbt) return true;
        
        $webservbt = (array)$webservbt;
        if($webservbt['errorCode'] != 0) { return true; }

        return false;
    }

}
