<?php

class Chronopost_Chronorelais_Helper_Webservice extends Mage_Core_Helper_Abstract {

    var $methodsAllowed = false;

    public function getPointsRelaisByCp($cp) {

        try {
            $client = new SoapClient("http://wsshipping.chronopost.fr/soap.point.relais/services/ServiceRechercheBt?wsdl",array('trace'=> 0,'connection_timeout'=>10));
            $webservbt = $client->__call("rechercheBtParCodeproduitEtCodepostalEtDate",array(0,$cp,0));
            return $webservbt;
        }  catch (Exception $e) {
            $webservbt =  $this->getPointsRelaisByPudo('',$cp);
            return $webservbt;
        }
    }

    /* get point relais by address */
    public function getPointRelaisByAddress() {

        $quote = Mage::getSingleton('checkout/cart')->init()->getQuote();
        $address = $quote->getShippingAddress();
        $helperData = Mage::helper('chronorelais');

        try {
            $client = new SoapClient("https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl", array('trace' => 0, 'connection_timeout' => 10));

            $params = array(
                'accountNumber' => $helperData->getConfigurationAccountNumber(),
                'password' => $helperData->getConfigurationAccountPass(),
                'address' => $this->getFilledValue($address->getStreet(1)),
                'zipCode' => $this->getFilledValue($address->getPostcode()),
                'city' => $this->getFilledValue($address->getCity()),
                'countryCode' => $this->getFilledValue($address->getCountryId()),
                'type' => 'P',
                'productCode' => '1',
                'service' => 'T',
                'weight' => 2000,
                'shippingDate' => date('d/m/Y'),
                'maxPointChronopost' => 5,
                'maxDistanceSearch' => 10,
                'holidayTolerant' => 1
            );
            $webservbt = $client->recherchePointChronopost($params);

            /* format $webservbt pour avoir le meme format que lors de l'appel du WS par code postal */
            if($webservbt->return->errorCode == 0)
            {
                /*
                 * Format entrée
                 *
                 * accesPersonneMobiliteReduite
                    actif
                    adresse1
                    adresse2
                    adresse3
                    codePays
                    codePostal
                    coordGeolocalisationLatitude
                    coordGeolocalisationLongitude
                    distanceEnMetre
                    identifiant
                    indiceDeLocalisation
                    listeHoraireOuverture
                    localite
                    nom
                    poidsMaxi
                    typeDePoint
                    urlGoogleMaps
                 *
                 * Format sortie
                 * adresse1
                    adresse2
                    adresse3
                    codePostal
                    dateArriveColis
                    horairesOuvertureDimanche ("10:00-12:30 14:30-19:00")
                    horairesOuvertureJeudi
                    horairesOuvertureLundi
                    horairesOuvertureMardi
                    horairesOuvertureMercredi
                    horairesOuvertureSamedi
                    horairesOuvertureVendredi
                    identifiantChronopostPointA2PAS
                    localite
                    nomEnseigne
                 *
                 *
                 *
                 * 2013-02-19T10:42:40.196Z
                 *
                 */
                $listePr = $webservbt->return->listePointRelais;
                $return = array();
                foreach($listePr as $pr)
                {
                    //$newPr = new object();
                    $newPr = (object)array();
                    $newPr->adresse1 = $pr->adresse1;
                    $newPr->adresse2 = $pr->adresse2;
                    $newPr->adresse3 = $pr->adresse3;
                    $newPr->codePostal = $pr->codePostal;
                    $newPr->identifiantChronopostPointA2PAS = $pr->identifiant;
                    $newPr->localite = $pr->localite;
                    $newPr->nomEnseigne = $pr->nom;
                    $time = new DateTime;
                    $newPr->dateArriveColis = $time->format(DateTime::ATOM);
                    $newPr->horairesOuvertureLundi = $newPr->horairesOuvertureMardi = $newPr->horairesOuvertureMercredi = $newPr->horairesOuvertureJeudi = $newPr->horairesOuvertureVendredi = $newPr->horairesOuvertureSamedi = $newPr->horairesOuvertureDimanche = '';
                    foreach($pr->listeHoraireOuverture as $horaire) {
                        switch($horaire->jour) {
                            case '1' : $newPr->horairesOuvertureLundi = $horaire->horairesAsString; break;
                            case '2' : $newPr->horairesOuvertureMardi = $horaire->horairesAsString; break;
                            case '3' : $newPr->horairesOuvertureMercredi = $horaire->horairesAsString; break;
                            case '4' : $newPr->horairesOuvertureJeudi = $horaire->horairesAsString; break;
                            case '5' : $newPr->horairesOuvertureVendredi = $horaire->horairesAsString; break;
                            case '6' : $newPr->horairesOuvertureSamedi = $horaire->horairesAsString; break;
                            case '7' : $newPr->horairesOuvertureDimanche = $horaire->horairesAsString; break;
                        }
                    }
                    if(empty($newPr->horairesOuvertureLundi)) $newPr->horairesOuvertureLundi = "00:00-00:00 00:00-00:00";
                    if(empty($newPr->horairesOuvertureMardi)) $newPr->horairesOuvertureMardi = "00:00-00:00 00:00-00:00";
                    if(empty($newPr->horairesOuvertureMercredi)) $newPr->horairesOuvertureMercredi = "00:00-00:00 00:00-00:00";
                    if(empty($newPr->horairesOuvertureJeudi)) $newPr->horairesOuvertureJeudi = "00:00-00:00 00:00-00:00";
                    if(empty($newPr->horairesOuvertureVendredi)) $newPr->horairesOuvertureVendredi = "00:00-00:00 00:00-00:00";
                    if(empty($newPr->horairesOuvertureSamedi)) $newPr->horairesOuvertureSamedi = "00:00-00:00 00:00-00:00";
                    if(empty($newPr->horairesOuvertureDimanche)) $newPr->horairesOuvertureDimanche = "00:00-00:00 00:00-00:00";

                    $return[] = $newPr;
                }
                return $return;
            }
        }  catch (Exception $e) {
            return $this->getPointsRelaisByPudo($address);
        }
    }

    public function getDetailRelaisPoint($btcode) {
        try {
            $client = new SoapClient("http://wsshipping.chronopost.fr/soap.point.relais/services/ServiceRechercheBt?wsdl");
            $webservbt = $client->__call("rechercheBtParIdChronopostA2Pas",array($btcode));
            return $webservbt[0];
        }  catch (Exception $e) {
            return $this->getDetailRelaisPointByPudo($btcode);
        }
    }


    /*
     *
     * WS de secours
     */

    public function getDetailRelaisPointByPudo($btcode) {
        $params = array(
            'carrier' => 'CHR',
            'key' => '75f6fe195dc88ceecbc0f8a2f70a8f3a',
            'pudo_id' => $btcode,
        );

        try {
            $client = new SoapClient("http://mypudo.pickup-services.com/mypudo/mypudo.asmx?wsdl", array('trace' => 0, 'connection_timeout' => 10));
            $webservbt = $client->GetPudoDetails($params);
            $webservbt = json_decode(json_encode((object) simplexml_load_string($webservbt->GetPudoDetailsResult->any)), 1);
            if(!isset($webservbt['ERROR'])) {
                $return = array();
                $pr = $webservbt['PUDO_ITEMS']['PUDO_ITEM'];
                if($pr) {
                    if($pr['@attributes']['active'] == 'true')
                    {
                        $newPr = (object)array();
                        $newPr->adresse1 = $pr['ADDRESS1'];
                        $newPr->adresse2 = is_array($pr['ADDRESS2']) ? implode(' ', $pr['ADDRESS2']) : $pr['ADDRESS2'];
                        $newPr->adresse3 = is_array($pr['ADDRESS3']) ? implode(' ', $pr['ADDRESS3']) : $pr['ADDRESS3'];
                        $newPr->codePostal = $pr['ZIPCODE'];
                        $newPr->identifiantChronopostPointA2PAS = $pr['PUDO_ID'];
                        $newPr->localite = $pr['CITY'];
                        $newPr->nomEnseigne = $pr['NAME'];
                        $time = new DateTime;
                        $newPr->dateArriveColis = $time->format(DateTime::ATOM);
                        $newPr->horairesOuvertureLundi = $newPr->horairesOuvertureMardi = $newPr->horairesOuvertureMercredi = $newPr->horairesOuvertureJeudi = $newPr->horairesOuvertureVendredi = $newPr->horairesOuvertureSamedi = $newPr->horairesOuvertureDimanche = '';

                        if(isset($pr['OPENING_HOURS_ITEMS']['OPENING_HOURS_ITEM'])) {
                            $listeHoraires = $pr['OPENING_HOURS_ITEMS']['OPENING_HOURS_ITEM'];
                            foreach($listeHoraires as $horaire) {
                                switch($horaire['DAY_ID']) {
                                    case '1' :
                                        if(!empty($newPr->horairesOuvertureLundi)) $newPr->horairesOuvertureLundi .= ' ';
                                        $newPr->horairesOuvertureLundi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                        break;
                                    case '2' :
                                        if(!empty($newPr->horairesOuvertureMardi)) $newPr->horairesOuvertureMardi .= ' ';
                                        $newPr->horairesOuvertureMardi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                        break;
                                    case '3' :
                                        if(!empty($newPr->horairesOuvertureMercredi)) $newPr->horairesOuvertureMercredi .= ' ';
                                        $newPr->horairesOuvertureMercredi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                        break;
                                    case '4' :
                                        if(!empty($newPr->horairesOuvertureJeudi)) $newPr->horairesOuvertureJeudi .= ' ';
                                        $newPr->horairesOuvertureJeudi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                        break;
                                    case '5' :
                                        if(!empty($newPr->horairesOuvertureVendredi)) $newPr->horairesOuvertureVendredi .= ' ';
                                        $newPr->horairesOuvertureVendredi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                        break;
                                    case '6' :
                                        if(!empty($newPr->horairesOuvertureSamedi)) $newPr->horairesOuvertureSamedi .= ' ';
                                        $newPr->horairesOuvertureSamedi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                        break;
                                    case '7' :
                                        if(!empty($newPr->horairesOuvertureDimanche)) $newPr->horairesOuvertureDimanche .= ' ';
                                        $newPr->horairesOuvertureDimanche .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                        break;
                                }
                            }
                        }
                        if(empty($newPr->horairesOuvertureLundi)) $newPr->horairesOuvertureLundi = "00:00-00:00 00:00-00:00";
                        if(empty($newPr->horairesOuvertureMardi)) $newPr->horairesOuvertureMardi = "00:00-00:00 00:00-00:00";
                        if(empty($newPr->horairesOuvertureMercredi)) $newPr->horairesOuvertureMercredi = "00:00-00:00 00:00-00:00";
                        if(empty($newPr->horairesOuvertureJeudi)) $newPr->horairesOuvertureJeudi = "00:00-00:00 00:00-00:00";
                        if(empty($newPr->horairesOuvertureVendredi)) $newPr->horairesOuvertureVendredi = "00:00-00:00 00:00-00:00";
                        if(empty($newPr->horairesOuvertureSamedi)) $newPr->horairesOuvertureSamedi = "00:00-00:00 00:00-00:00";
                        if(empty($newPr->horairesOuvertureDimanche)) $newPr->horairesOuvertureDimanche = "00:00-00:00 00:00-00:00";

                        return $newPr;
                    }
                }
            }
        }
        catch (Exception $e) {
            return false;
        }
        return false;
    }

    public function getPointsRelaisByPudo($address = '', $cp = '') {

        $params = array(
            'carrier' => 'CHR',
            'key' => '75f6fe195dc88ceecbc0f8a2f70a8f3a',
            'address' => $address ? $this->getFilledValue($address->getStreet(1)) : '',
            'zipCode' => $address ? $this->getFilledValue($address->getPostcode()) : $cp,
            'city' => $address ? $this->getFilledValue($address->getCity()) : 'Lille',
            'countrycode' => $address ? $this->getFilledValue($address->getCountryId()) : '',
            'requestID' => '1',
            'date_from' => date('d/m/Y'),
            'max_pudo_number' => 5,
            'max_distance_search' => 10,
            'weight' => 1,
            'category' => '',
            'holiday_tolerant' => 1,
        );
        try {
            $client = new SoapClient("http://mypudo.pickup-services.com/mypudo/mypudo.asmx?wsdl", array('trace' => 0, 'connection_timeout' => 10));
            $webservbt = $client->GetPudoList($params);
            $webservbt = json_decode(json_encode((object) simplexml_load_string($webservbt->GetPudoListResult->any)), 1);
            if(!isset($webservbt['ERROR'])) {
                $return = array();

                $listePr = $webservbt['PUDO_ITEMS']['PUDO_ITEM'];
                if($listePr) {
                    foreach($listePr as $pr)
                    {
                        if($pr['@attributes']['active'] == 'true')
                        {
                            $newPr = (object)array();
                            $newPr->adresse1 = $pr['ADDRESS1'];
                            $newPr->adresse2 = is_array($pr['ADDRESS2']) ? implode(' ', $pr['ADDRESS2']) : $pr['ADDRESS2'];
                            $newPr->adresse3 = is_array($pr['ADDRESS3']) ? implode(' ', $pr['ADDRESS3']) : $pr['ADDRESS3'];
                            $newPr->codePostal = $pr['ZIPCODE'];
                            $newPr->identifiantChronopostPointA2PAS = $pr['PUDO_ID'];
                            $newPr->localite = $pr['CITY'];
                            $newPr->nomEnseigne = $pr['NAME'];
                            $time = new DateTime;
                            $newPr->dateArriveColis = $time->format(DateTime::ATOM);
                            $newPr->horairesOuvertureLundi = $newPr->horairesOuvertureMardi = $newPr->horairesOuvertureMercredi = $newPr->horairesOuvertureJeudi = $newPr->horairesOuvertureVendredi = $newPr->horairesOuvertureSamedi = $newPr->horairesOuvertureDimanche = '';

                            if(isset($pr['OPENING_HOURS_ITEMS']['OPENING_HOURS_ITEM'])) {
                                $listeHoraires = $pr['OPENING_HOURS_ITEMS']['OPENING_HOURS_ITEM'];
                                foreach($listeHoraires as $horaire) {
                                    switch($horaire['DAY_ID']) {
                                        case '1' :
                                            if(!empty($newPr->horairesOuvertureLundi)) $newPr->horairesOuvertureLundi .= ' ';
                                            $newPr->horairesOuvertureLundi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '2' :
                                            if(!empty($newPr->horairesOuvertureMardi)) $newPr->horairesOuvertureMardi .= ' ';
                                            $newPr->horairesOuvertureMardi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '3' :
                                            if(!empty($newPr->horairesOuvertureMercredi)) $newPr->horairesOuvertureMercredi .= ' ';
                                            $newPr->horairesOuvertureMercredi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '4' :
                                            if(!empty($newPr->horairesOuvertureJeudi)) $newPr->horairesOuvertureJeudi .= ' ';
                                            $newPr->horairesOuvertureJeudi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '5' :
                                            if(!empty($newPr->horairesOuvertureVendredi)) $newPr->horairesOuvertureVendredi .= ' ';
                                            $newPr->horairesOuvertureVendredi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '6' :
                                            if(!empty($newPr->horairesOuvertureSamedi)) $newPr->horairesOuvertureSamedi .= ' ';
                                            $newPr->horairesOuvertureSamedi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '7' :
                                            if(!empty($newPr->horairesOuvertureDimanche)) $newPr->horairesOuvertureDimanche .= ' ';
                                            $newPr->horairesOuvertureDimanche .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                    }
                                }
                            }
                            if(empty($newPr->horairesOuvertureLundi)) $newPr->horairesOuvertureLundi = "00:00-00:00 00:00-00:00";
                            if(empty($newPr->horairesOuvertureMardi)) $newPr->horairesOuvertureMardi = "00:00-00:00 00:00-00:00";
                            if(empty($newPr->horairesOuvertureMercredi)) $newPr->horairesOuvertureMercredi = "00:00-00:00 00:00-00:00";
                            if(empty($newPr->horairesOuvertureJeudi)) $newPr->horairesOuvertureJeudi = "00:00-00:00 00:00-00:00";
                            if(empty($newPr->horairesOuvertureVendredi)) $newPr->horairesOuvertureVendredi = "00:00-00:00 00:00-00:00";
                            if(empty($newPr->horairesOuvertureSamedi)) $newPr->horairesOuvertureSamedi = "00:00-00:00 00:00-00:00";
                            if(empty($newPr->horairesOuvertureDimanche)) $newPr->horairesOuvertureDimanche = "00:00-00:00 00:00-00:00";

                            $return[] = $newPr;
                        }
                    }
                    return $return;
                }
            }
        }
        catch (Exception $e) {
            return false;
        }
        return false;
    }


    public function getQuickcost($quickCost,$quickcost_url = '') {
        if (!$quickcost_url) {
            //$quickcost_url = "http://wsshipping.chronopost.fr/wsQuickcost/services/ServiceQuickCost?wsdl";
            $quickcost_url = "https://www.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl";
        }
        try {
            $client = new SoapClient($quickcost_url);
            $webservbt = $client->quickCost($quickCost);

            return $webservbt->return;
        } catch (Exception $e) {
            return false;
        }
    }

     public function checkLogin($quickCost,$quickcost_url = '') {
        if (!$quickcost_url) {
            $quickcost_url = "https://www.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl";
        }
        try {
            $client = new SoapClient($quickcost_url);
            $webservbt = $client->calculateProducts($quickCost);
            return $webservbt;
        } catch (Exception $e) {
            return false;
        }
    }

    /*
     * Return true si la méthode de livraison fait partie du contrat
     */
    public function getMethodIsAllowed($code,$quote) {
        $quote = Mage::getSingleton('checkout/cart')->init()->getQuote();
        $address = $quote->getShippingAddress();
        $helperData = Mage::helper('chronorelais');
        $code = $helperData->getChronoProductCode('',$code);
        try {
            if($this->methodsAllowed === false) {
                $this->methodsAllowed = array();
                $client = new SoapClient("https://www.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl", array('trace' => 0, 'connection_timeout' => 10));
                $params = array(
                    'accountNumber' => $helperData->getConfigurationAccountNumber(),
                    'password' => $helperData->getConfigurationAccountPass(),
                    'depCountryCode' => $helperData->getConfigurationShipperInfo('country'),
                    'depZipCode' => $helperData->getConfigurationShipperInfo('zipcode'),
                    'arrCountryCode' => $this->getFilledValue($address->getCountryId()),
                    'arrZipCode' => $this->getFilledValue($address->getPostcode()),
                    'arrCity' => $this->getFilledValue($address->getCity()),
                    'type' => 'M',
                    'weight' => 1
                );
                //print_r($params);
                $webservbt = $client->calculateProducts($params);
                //print_r($webservbt);
                if($webservbt->return->errorCode == 0)
                {
                    if($webservbt->return->productList) {
                        foreach($webservbt->return->productList as $product) {
                            //echo $product->productCode.' ---- ';
                            $this->methodsAllowed[] = $product->productCode;
                        }
                    }
                }
            }
            //print_r($this->methodsAllowed);
            if(!empty($this->methodsAllowed) && in_array($code, $this->methodsAllowed)) {
                return true;
            }
            return false;
        }catch(Exception $e) {
            return false;
        }
    }









    public function getFilledValue($value) {
        if ($value) {
            return $this->removeaccents(trim($value));
        } else {
            return '';
        }
    }

    public function removeaccents($string) {
        $stringToReturn = str_replace(
                array('à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', '/', '\xa8'), array('a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', ' ', 'e'), $string);
        // Remove all remaining other unknown characters
        $stringToReturn = preg_replace('/[^a-zA-Z0-9\-]/', ' ', $stringToReturn);
        $stringToReturn = preg_replace('/^[\-]+/', '', $stringToReturn);
        $stringToReturn = preg_replace('/[\-]+$/', '', $stringToReturn);
        $stringToReturn = preg_replace('/[\-]{2,}/', ' ', $stringToReturn);
        return $stringToReturn;
    }
}
