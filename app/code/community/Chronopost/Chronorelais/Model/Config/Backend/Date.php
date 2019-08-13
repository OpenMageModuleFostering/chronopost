<?php
/**
 * System config file field backend model
 *
 * @category   Adexos
 * @package    Adexos_Productcustomizer
 * @author     Adexos
 */
class Chronopost_Chronorelais_Model_Config_Backend_Date extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        $range = $this->getValue();
        $this->setValue($range[0].':'.$range[1].':'.$range[2]);
        return $this;
    }
}
