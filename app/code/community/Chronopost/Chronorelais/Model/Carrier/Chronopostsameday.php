<?php

class Chronopost_Chronorelais_Model_Carrier_Chronopostsameday extends Chronopost_Chronorelais_Model_Carrier_AbstractChronorelaisShipping
{

    protected $_code = 'chronopostsameday';
    protected $_checkContract = true;

    protected function validateMethod()
    {
        $validate = parent::validateMethod();

        // Check if we should auto disable the module (it's past hour)
        $deliveryTimeLimitConf = Mage::getStoreConfig('carriers/' . $this->getCarrierCode() . '/delivery_time_limit');
        // Safe fallback
        if (!$deliveryTimeLimitConf) {
            $deliveryTimeLimitConf = '15:00';
        }
        $deliveryTimeLimit = new DateTime(date('Y-m-d') . ' ' . $deliveryTimeLimitConf . ':00');
        $currentTime = new DateTime('NOW');

        if ($validate === true) {
            if (Mage::getModel('core/date')->timestamp($currentTime->getTimestamp())
                <= $deliveryTimeLimit->getTimestamp()) {
                $validate = true;
            } else {
                $validate = false;
            }
        }

        return $validate;
    }
}
