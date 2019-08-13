<?php

class Chronopost_Chronorelais_Model_Carrier_Chronopostsrdv extends Chronopost_Chronorelais_Model_Carrier_AbstractChronorelaisShipping
{

    protected $_code = 'chronopostsrdv';
    protected $_checkContract = true;

    public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
        $result = parent::collectRates($request);
        return $this->updatePriceAndLabel($result);
    }

    /* update shipping method price and label to add date and hours */
    public function updatePriceAndLabel($result) {
        $rates = $result->getAllRates();
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        if(!$quote) {
            return $result;
        }

        $shippingAddress = $quote->getShippingAddress();

        if(!$shippingAddress || !$shippingAddress->getShippingMethod()) { // || !isset($_SESSION['chronopostsrdv_creneaux_info'])
            //return $result;
        }

        // We will rebuild it later
        $result->reset();

        /*$price = $_srdvConfig[$tarifLevel."_price"];
        $price = $_helper->addMargeToQuickcost($price,$this->_code, false);*/

        foreach ($rates as $rate) {

            if($rate->getMethod() == $this->_code) {

                /*if(!$rate->getOriginalPrice()) {
                    $rate->setOriginalPrice($rate->getPrice());
                }*/


                $_srdvConfig = Mage::getStoreConfig('carriers/chronopostsrdv/rdv_config');
                $_srdvConfig = json_decode($_srdvConfig,true);

                $_helper = Mage::helper('chronorelais');

                if(isset($_SESSION['chronopostsrdv_creneaux_info'])) {
                    $chronopostsrdv_creneaux_info = $_SESSION['chronopostsrdv_creneaux_info'];
                    $tarifLevel = $chronopostsrdv_creneaux_info['tariffLevel'];
                    $price = $_srdvConfig[$tarifLevel."_price"] + $rate->getCost();

                    $_dateRdvStart = new DateTime($chronopostsrdv_creneaux_info['deliveryDate']);
                    $_dateRdvStart->setTime($chronopostsrdv_creneaux_info['startHour'],$chronopostsrdv_creneaux_info['startMinutes']);

                    $_dateRdvEnd = new DateTime($chronopostsrdv_creneaux_info['deliveryDate']);
                    $_dateRdvEnd->setTime($chronopostsrdv_creneaux_info['endHour'],$chronopostsrdv_creneaux_info['endMinutes']);

                    $methodTitle = $rate->getMethodTitle();
                    $methodTitle .= ' - '.$_helper->__('Le').' '.$_dateRdvStart->format('d/m/Y');
                    $methodTitle .= ' '.$_helper->__('entre %s et %s',$_dateRdvStart->format('H:i'),$_dateRdvEnd->format('H:i'));
                    $rate->setData('method_title',$methodTitle);

                } else {
                    $minimal_price = '';
                    for($i = 1; $i <= 4; $i++) {
                        if($minimal_price === '' || isset($_srdvConfig["N".$i."_price"]) && $_srdvConfig["N".$i."_price"] < $minimal_price) {
                            $minimal_price = $_srdvConfig["N".$i."_price"];
                        }
                    }
                    $price = $minimal_price + $rate->getCost();
                }

                $rate->setPrice($price);

                $shippingAddress->setShippingDescription($methodTitle);

            }

            $result->append($rate);
        }
        return $result;
    }
}
