<?php
class Chronopost_Chronorelais_Model_Config_Source_Minute
{
    public function toOptionArray()
    {
        $minute = array();
        for($i = 0; $i <= 59; $i++) {
            $minute_str = str_pad($i, 2, '0',STR_PAD_LEFT);
            $minute[] = array('value' => $minute_str, 'label' => $minute_str);
        }
        return $minute;
    }
}
