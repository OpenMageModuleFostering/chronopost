<?php

class Chronopost_Chronorelais_Helper_Data extends Mage_Core_Helper_Abstract {
    //Choronorelais settings for productCode
    const CHRONO_POST = '01'; // for France
    const CHRONO_POST_BAL = '58'; // For france avec option BAL
    const CHRONO_EXPRESS = '17'; // for Interantional
    const CHRONORELAIS = '86'; // for Chronorelais
    const CHRONOPOST_C10 = '02'; // for Chronopost C10
    const CHRONOPOST_C18 = '16'; // for Chronopost C18
    const CHRONOPOST_C18_BAL = '2M'; // for Chronopost C18 avec option BAL
    const CHRONOPOST_CClassic = '44'; // for Chronopost CClassic
    const CHRONOPOST_13H = '13H'; // for France
    const CHRONOPOST_13H_BAL = '13H BAL'; // for France BAL
    const CHRONOPOST_C10_STR = '10H'; // for Chronopost C10
    const CHRONOPOST_C18_STR = '18H'; // for Chronopost C18
    const CHRONOPOST_C18_BAL_STR = '18H BAL'; // for Chronopost C18 BAL
    const CHRONOPOST_CClassic_STR = 'CClassic'; // for Chronopost CClassic
    const CHRONOEXPRESS_EI = 'EI'; // for Interantional
    const CHRONORELAIS_PR = 'PR'; // for Chronorelais
    const CHRONOPOST_DEFAULT_COUNTRY = 'FR';
    const CHRONOPOST_TRACKING_URL = 'http://wsshipping.chronopost.fr/shipping/services/getReservedSkybill?reservationNumber={trackingNumber}';
    // if you are in a period outside thursday 18:00 to friday 15:00, there is no shipping on saturday
    var $SaturdayShippingDays = array(
        'startday' => 'thursday',
        'endday' => 'friday',
        'starttime' => '18:00:00',
        'endtime' => '15:00:00'
    );

    public function getConfigData($path) {
        return Mage::getStoreConfig($path, Mage::app()->getStore());
    }

    public function getSaturdayShippingDays() {
        return $this->SaturdayShippingDays;
    }

    public function getCurrentTimeByZone($timezone="Europe/Paris", $format="l H:i") {
        $d = new DateTime("now", new DateTimeZone($timezone));
        return $d->format($format);
    }

    public function isSendingDay() {
        $shipping_days = $this->getSaturdayShippingDays();
        $current_day = strtolower($this->getCurrentTimeByZone("Europe/Paris", "l"));
        $current_date = $this->getCurrentTimeByZone("Europe/Paris", "Y-m-d H:i:s");
        $current_datetime = explode(' ', $current_date);

        //get timestamps
        $start_timestamp = strtotime($current_datetime[0] . " " . $shipping_days['starttime']);
        $end_timestamp = strtotime($current_datetime[0] . " " . $shipping_days['endtime']);
        $current_timestamp = strtotime($current_date);

        $sending_day = false;
        if ($shipping_days['startday'] == $current_day && $current_timestamp >= $start_timestamp) {
            $sending_day = true;
        } elseif ($shipping_days['endday'] == $current_day && $current_timestamp <= $end_timestamp) {
            $sending_day = true;
        }
        return $sending_day;
    }

    /**
     * General Shipping configuration
     */
    public function getConfigurationAccountNumber() {
        return $this->getConfigData('chronorelais/shipping/account_number');
    }

    public function getConfigurationSubAccountNumber() {
        return $this->getConfigData('chronorelais/shipping/sub_account_number');
    }

    public function getConfigurationAccountPass() {
        return $this->getConfigData('chronorelais/shipping/account_pass');
    }

    public function getConfigurationTrackingViewUrl() {
        return $this->getConfigData('chronorelais/shipping/tracking_view_url');
    }

    public function getConfigurationGoogleMapAPIKey() {
        return $this->getConfigData('chronorelais/shipping/google_map_api');
    }

    public function getChronoProductCode($country, $code='') {
        $productcode = '';
        $code = strtolower($code);

        switch($code) {
            case 'chronorelais' : 
                $productcode = self::CHRONORELAIS;
                break;
            case 'chronopost' : 
                $productcode = self::CHRONO_POST; 
                break;
            case 'chronoexpress' : 
                $productcode = self::CHRONO_EXPRESS;
                break;
            case 'chronopostc10' : 
                $productcode = self::CHRONOPOST_C10;
                break;
            case 'chronopostc18' : 
                $productcode = self::CHRONOPOST_C18; 
                break;
            case 'chronopostcclassic' : 
                $productcode = self::CHRONOPOST_CClassic;
                break;
        }
        return $productcode;
    }

    public function getChronoProductCodeToShipment($code='') {
        $productcode = '';
        $code = strtolower($code);

        switch($code) {
            case 'chronorelais' :
                $productcode = self::CHRONORELAIS;
                break;
            case 'chronopost' :
                if($this->getConfigOptionBAL()) {
                    $productcode = self::CHRONO_POST_BAL;
                }
                else {
                    $productcode = self::CHRONO_POST;
                }
                break;
            case 'chronoexpress' :
                $productcode = self::CHRONO_EXPRESS;
                break;
            case 'chronopostc10' :
                $productcode = self::CHRONOPOST_C10;
                break;
            case 'chronopostc18' :
                if($this->getConfigOptionBAL()) {
                    $productcode = self::CHRONOPOST_C18_BAL;
                }
                else {
                    $productcode = self::CHRONOPOST_C18;
                }
                break;
            case 'chronopostcclassic' :
                $productcode = self::CHRONOPOST_CClassic;
                break;
        }
        return $productcode;
    }

    public function getChronoProductCodeString($code='') {
        $productcode = '';
        $code = strtolower($code);

        switch($code) {
            case 'chronorelais' : $productcode = self::CHRONORELAIS_PR; break;
            case 'chronopost' : $productcode = self::CHRONOPOST_13H; break;
            case 'chronoexpress' : $productcode = self::CHRONOEXPRESS_EI; break;
            case 'chronopostc10' : $productcode = self::CHRONOPOST_C10_STR; break;
            case 'chronopostc18' : $productcode = self::CHRONOPOST_C18_STR; break;
            case 'chronopostcclassic' : $productcode = self::CHRONOPOST_CClassic_STR; break;
        }
        return $productcode;
    }

    public function getChronoProductCodeStringWithBAL($code='') {
        $productcode = '';
        $code = strtolower($code);

        switch($code) {
            case 'chronorelais' : $productcode = self::CHRONORELAIS_PR; break;
            case 'chronopost' :
                if($this->getConfigOptionBAL()) {
                    $productcode = self::CHRONOPOST_13H_BAL;
                }
                else {
                    $productcode = self::CHRONOPOST_13H;
                }
                break;
            case 'chronoexpress' : $productcode = self::CHRONOEXPRESS_EI; break;
            case 'chronopostc10' : $productcode = self::CHRONOPOST_C10_STR; break;
            case 'chronopostc18' :
                if($this->getConfigOptionBAL()) {
                    $productcode = self::CHRONOPOST_C18_BAL_STR;
                }
                else {
                    $productcode = self::CHRONOPOST_C18_STR;
                }
                break;
            case 'chronopostcclassic' : $productcode = self::CHRONOPOST_CClassic_STR; break;
        }
        return $productcode;
    }

    public function getConfigurationTrackingUrl() {
        return self::CHRONOPOST_TRACKING_URL;
    }

    /**
     * Export configuration
     */
    public function getConfigurationFileExtension($export_type='css') {
        return $this->getConfigData('chronorelais/export_' . $export_type . '/file_extension');
    }

    public function getConfigurationFileCharset($export_type='css') {
        return $this->getConfigData('chronorelais/export_' . $export_type . '/file_charset');
    }

    public function getConfigurationEndOfLineCharacter($export_type='css') {
        return $this->getConfigData('chronorelais/export_' . $export_type . '/endofline_character');
    }

    public function getConfigurationFieldDelimiter($export_type='css') {
        return $this->getConfigData('chronorelais/export_' . $export_type . '/field_delimiter');
    }

    public function getConfigurationFieldSeparator($export_type='css') {
        return $this->getConfigData('chronorelais/export_' . $export_type . '/field_separator');
    }

    /**
     * Import configuration
     */
    public function getConfigurationSendEmail() {
        return $this->getConfigData('chronorelais/import/send_email');
    }

    public function getConfigurationIncludeComment() {
        return $this->getConfigData('chronorelais/import/include_comment');
    }

    public function getConfigurationDefaultTrackingTitle() {
        return $this->getConfigData('chronorelais/import/default_tracking_title');
    }

    public function getConfigurationShippingComment() {
        return $this->getConfigData('chronorelais/import/shipping_comment');
    }

    /**
     * Shipper Information
     */
    public function getConfigurationShipperInfo($field) {
        $fieldValue = '';
        if ($field) {
            if ($this->getConfigData('chronorelais/shipperinformation/' . $field)) {
                $fieldValue = $this->getConfigData('chronorelais/shipperinformation/' . $field);
            }
        }
        return $fieldValue;
    }

    /**
     * Chronopost Customer Information
     */
    public function getConfigurationCustomerInfo($field) {
        $fieldValue = '';
        if ($field) {
            if ($this->getConfigData('chronorelais/customerinformation/' . $field)) {
                $fieldValue = $this->getConfigData('chronorelais/customerinformation/' . $field);
            }
        }
        return $fieldValue;
    }

    /**
     * Import configuration
     */
    public function getConfigurationSkybillParam() {
        return $this->getConfigData('chronorelais/skybillparam/mode');
    }

    /*
     * Weight unit
     */
    public function getConfigWeightUnit() {
        return $this->getConfigData('chronorelais/weightunit/unit');
    }

    /*
     * Option BAL
     */
    public function getConfigOptionBAL() {
        return $this->getConfigData('chronorelais/optionbal/enabled');
    }

    public function hasOptionBAL($order) {
        $shippingMethod = explode('_',$order->getShippingMethod());
        $shippingMethod = $shippingMethod[1];
        $shippingMethodAllowBAL = array('chronopost','chronopostc18');
        if(in_array(strtolower($shippingMethod), $shippingMethodAllowBAL) && $this->getConfigOptionBAL()) {
            return true;
        }
        return false;
    }

    /*
     * Assurance Ad Valorem
     */
    public function assuranceAdValoremEnabled() {
        return $this->getConfigData('chronorelais/assurance/enabled');
    }
    public function assuranceAdValoremAmount() {
        return $this->getConfigData('chronorelais/assurance/amount');
    }
    public function getMaxAdValoremAmount() {
        return 20000;
    }

    /* Get Livraison le Samedi status by orderid */

    public function getLivraisonSamediStatus($order_id) {
        $_connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $_table = Mage::getSingleton('core/resource')->getTableName('sales_chronopost_order_export_status');
        $select = $_connection->select()
                ->from($_table, 'livraison_le_samedi')
                ->where('order_id=?', $order_id);
        $status = $_connection->fetchOne($select);
        return $status;
    }


    /*
     * Return true si la méthode de livraison fait partie du contrat du marchant
     */
    public function shippingMethodEnabled($shippingMethod) {
        return true;
    }

}
