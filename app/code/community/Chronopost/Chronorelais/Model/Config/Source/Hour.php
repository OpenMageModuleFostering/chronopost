<?php
class Chronopost_Chronorelais_Model_Config_Source_Hour
{
    public function toOptionArray()
    {
        $hour = array();
        for($i = 0; $i <= 23; $i++) {
            $hour_str = str_pad($i, 2, '0',STR_PAD_LEFT);
            $hour[] = array('value' => $hour_str, 'label' => $hour_str);
        }
        return $hour;
    }
}
