<?php
class Chronopost_Chronorelais_Block_Adminhtml_Notification extends Mage_Core_Block_Template
{
    const XML_SEVERITY_ICONS_URL_PATH  = 'system/adminnotification/severity_icons_url';

    const MODULE_RELEASES_XML_URL = 'http://connect20.magentocommerce.com/community/Chronopost/releases.xml';
    //all community packages => http://connect20.magentocommerce.com/community/packages.xml

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
        return true;
    }

    public function getNotifications()
    {
        $notifications = array();
        /* test if WS is ok */
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

        if(!$webservbt) {
            $notifications[] = 'quickcost_not_available';
        } else {
            $webservbt = (array)$webservbt;
            if(isset($webservbt['errorCode']) && $webservbt['errorCode'] != 0) {
                $notifications[] = 'quickcost_not_available';
            }
        }

        /* test if new version is available */
        $currentVersion = (string)Mage::getConfig()->getModuleConfig("Chronopost_Chronorelais")->version;

        $xml = simplexml_load_file(self::MODULE_RELEASES_XML_URL);
        $nbRelease = count($xml->children());
        $releases = $xml->children();
        $lastRelease = $releases[0];
        if(version_compare($lastRelease->v, $currentVersion, '>')) {
            $notifications[] = 'new_version';
        }

        return $notifications;

    }

}
