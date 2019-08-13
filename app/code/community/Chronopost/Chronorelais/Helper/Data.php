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

    const CHRONORELAISEUROPE = '49'; // for Chronorelais Europe
    const CHRONORELAISEUROPE_STR = 'PRU'; // for Chronorelais Europe

    const CHRONORELAISDOM = '4P'; // for Chronorelais DOM
    const CHRONORELAISDOM_STR = 'PRDOM'; // for Chronorelais DOM

    const CHRONOPOST_SMD = '4I'; // for Chronopost SAMEDAY
    const CHRONOPOST_SMD_STR = 'SMD'; // for Chronopost SAMEDAY

    const CHRONOPOST_SRDV = '2O'; // for Chronopost Sur Rendez-vous 'O' majuscule et non 0
    const CHRONOPOST_SRDV_STR = 'SRDV'; // for Chronopost Sur Rendez-vous

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

        $starday = explode(":",$this->getConfigData("chronorelais/saturday/startday"));
        $endday = explode(":",$this->getConfigData("chronorelais/saturday/endday"));

        $saturdayDays = array();
        $saturdayDays['startday'] = (count($starday) == 3 && isset($starday[0])) ? $starday[0] : $this->SaturdayShippingDays['startday'];
        $saturdayDays['starttime'] = (count($starday) == 3 && isset($starday[1])) ? $starday[1].':'.$starday[2].':00' : $this->SaturdayShippingDays['starttime'];
        $saturdayDays['endday'] = (count($endday) == 3 && isset($endday[0])) ? $endday[0] : $this->SaturdayShippingDays['endday'];
        $saturdayDays['endtime'] = (count($endday) == 3 && isset($endday[1])) ? $endday[1].':'.$endday[2].':00' : $this->SaturdayShippingDays['endtime'];

        return $saturdayDays;
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
        /*if (  ($shipping_days['startday'] == $current_day && $current_timestamp >= $start_timestamp) || ($shipping_days['endday'] == $current_day && $current_timestamp <= $end_timestamp)  ) {
            $sending_day = true;
        }*/
        if (  $current_timestamp >= $start_timestamp && $current_timestamp <= $end_timestamp  ) {
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
                $productcode = static::CHRONORELAIS;
                break;
            case 'chronopost' :
                $productcode = static::CHRONO_POST;
                break;
            case 'chronoexpress' :
                $productcode = static::CHRONO_EXPRESS;
                break;
            case 'chronopostc10' :
                $productcode = static::CHRONOPOST_C10;
                break;
            case 'chronopostc18' :
                $productcode = static::CHRONOPOST_C18;
                break;
            case 'chronopostcclassic' :
                $productcode = static::CHRONOPOST_CClassic;
                break;
            case 'chronorelaiseurope' :
                $productcode = static::CHRONORELAISEUROPE;
                break;
            case 'chronorelaisdom' :
                $productcode = static::CHRONORELAISDOM;
                break;
            case 'chronopostsameday' :
                $productcode = static::CHRONOPOST_SMD;
                break;
            case 'chronopostsrdv' :
                $productcode = static::CHRONOPOST_SRDV;
                break;
            default :
                $productcode = static::CHRONO_POST;
                break;
        }
        return $productcode;
    }

    public function getChronoProductCodeToShipment($code='') {
        $productcode = '';
        $code = strtolower($code);

        switch($code) {
            case 'chronorelais' :
                $productcode = static::CHRONORELAIS;
                break;
            case 'chronopost' :
                if($this->getConfigOptionBAL()) {
                    $productcode = static::CHRONO_POST_BAL;
                }
                else {
                    $productcode = static::CHRONO_POST;
                }
                break;
            case 'chronoexpress' :
                $productcode = static::CHRONO_EXPRESS;
                break;
            case 'chronopostc10' :
                $productcode = static::CHRONOPOST_C10;
                break;
            case 'chronopostc18' :
                if($this->getConfigOptionBAL()) {
                    $productcode = static::CHRONOPOST_C18_BAL;
                }
                else {
                    $productcode = static::CHRONOPOST_C18;
                }
                break;
            case 'chronopostcclassic' :
                $productcode = static::CHRONOPOST_CClassic;
                break;
            case 'chronorelaiseurope' :
                $productcode = static::CHRONORELAISEUROPE;
                break;
            case 'chronorelaisdom' :
                $productcode = static::CHRONORELAISDOM;
                break;
            case 'chronopostsameday' :
                $productcode = static::CHRONOPOST_SMD;
                break;
            case 'chronopostsrdv' :
                $productcode = static::CHRONOPOST_SRDV;
                break;
            default :
                $productcode = static::CHRONO_POST;
                break;
        }
        return $productcode;
    }

    public function getChronoProductCodeString($code='') {
        $productcode = '';
        $code = strtolower($code);

        switch($code) {
            case 'chronorelais' :
                $productcode = static::CHRONORELAIS_PR;
                break;
            case 'chronopost' :
                $productcode = static::CHRONOPOST_13H;
                break;
            case 'chronoexpress' :
                $productcode = static::CHRONOEXPRESS_EI;
                break;
            case 'chronopostc10' :
                $productcode = static::CHRONOPOST_C10_STR;
                break;
            case 'chronopostc18' :
                $productcode = static::CHRONOPOST_C18_STR;
                break;
            case 'chronopostcclassic' :
                $productcode = static::CHRONOPOST_CClassic_STR;
                break;
            case 'chronorelaiseurope' :
                $productcode = static::CHRONORELAISEUROPE_STR;
                break;
            case 'chronorelaisdom' :
                $productcode = static::CHRONORELAISDOM_STR;
                break;
            case 'chronopostsameday' :
                $productcode = static::CHRONOPOST_SMD_STR;
                break;
            case 'chronopostsrdv' :
                $productcode = static::CHRONOPOST_SRDV_STR;
                break;
            default :
                $productcode = static::CHRONOPOST_13H;
                break;
        }
        return $productcode;
    }

    public function getChronoProductCodeStringWithBAL($code='') {
        $productcode = '';
        $code = strtolower($code);

        switch($code) {
            case 'chronorelais' :
                $productcode = static::CHRONORELAIS_PR;
                break;
            case 'chronopost' :
                if($this->getConfigOptionBAL()) {
                    $productcode = static::CHRONOPOST_13H_BAL;
                }
                else {
                    $productcode = static::CHRONOPOST_13H;
                }
                break;
            case 'chronoexpress' :
                $productcode = static::CHRONOEXPRESS_EI;
                break;
            case 'chronopostc10' :
                $productcode = static::CHRONOPOST_C10_STR;
                break;
            case 'chronopostc18' :
                if($this->getConfigOptionBAL()) {
                    $productcode = static::CHRONOPOST_C18_BAL_STR;
                }
                else {
                    $productcode = static::CHRONOPOST_C18_STR;
                }
                break;
            case 'chronopostcclassic' :
                $productcode = static::CHRONOPOST_CClassic_STR;
                break;
            case 'chronorelaiseurope' :
                $productcode = static::CHRONORELAISEUROPE_STR;
                break;
            case 'chronorelaisdom' :
                $productcode = static::CHRONORELAISDOM_STR;
                break;
            case 'chronopostsrdv' :
                $productcode = static::CHRONOPOST_SRDV_STR;
                break;
            default :
                $productcode = static::CHRONOPOST_13H;
                break;
        }
        return $productcode;
    }

    public function getConfigurationTrackingUrl() {
        return static::CHRONOPOST_TRACKING_URL;
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
        if ($field && $this->getConfigData('chronorelais/shipperinformation/' . $field)) {
            $fieldValue = $this->getConfigData('chronorelais/shipperinformation/' . $field);
        }
        return $fieldValue;
    }

    /**
     * Chronopost Customer Information
     */
    public function getConfigurationCustomerInfo($field) {
        $fieldValue = '';
        if ($field && $this->getConfigData('chronorelais/customerinformation/' . $field)) {
            $fieldValue = $this->getConfigData('chronorelais/customerinformation/' . $field);
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

    /*
    * Return true if we can show google map
    */
    public function canShowGoogleMap() {
        return $this->getConfigData('carriers/chronorelais/show_google_map');
    }

    /* return track number field name */
    public function getTrackNumberFieldName() {
        if (version_compare(Mage::getVersion(), '1.5.1.0', '>')) {
            return "track_number";
        } else {
            return "number";
        }

    }

    public function addMargeToQuickcost($quickcost_val,$carrierCode = '', $firstPassage = true) {
        if($carrierCode) {

            $quickcostMarge = Mage::getStoreConfig('carriers/' . $carrierCode . '/quickcost_marge');
            $quickcostMargeType = Mage::getStoreConfig('carriers/' . $carrierCode . '/quickcost_marge_type');

            if($quickcostMarge) {
                if($quickcostMargeType == 'amount') {
                    $quickcost_val += $quickcostMarge;
                } elseif($quickcostMargeType == 'prcent') {
                    $quickcost_val += $quickcost_val * $quickcostMarge / 100;
                }
            }
        }

        return $quickcost_val;
    }

}
