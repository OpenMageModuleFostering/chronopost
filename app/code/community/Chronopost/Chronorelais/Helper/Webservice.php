<?php

class Chronopost_Chronorelais_Helper_Webservice extends Mage_Core_Helper_Abstract {

    var $methodsAllowed = false;

    public function getPointsRelaisByCp($cp) {

        try {
            $client = new SoapClient("http://wsshipping.chronopost.fr/soap.point.relais/services/ServiceRechercheBt?wsdl",array('trace'=> 0,'connection_timeout'=>10));
            return $client->__call("rechercheBtParCodeproduitEtCodepostalEtDate",array(0,$cp,0));
        }  catch (Exception $e) {
            return $this->getPointsRelaisByPudo('',$cp);
        }
    }

    /* get point relais by address */
    public function getPointRelaisByAddress($shippingMethodCode = 'chronorelais') {

        if(!$shippingMethodCode) {
            return false;
        }
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        $address = $quote->getShippingAddress();
        $helperData = Mage::helper('chronorelais');

        try {

            $pointRelaisWs = 'https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl';
            $pointRelaisWsMethod = Mage::getStoreConfig('carriers/'.$shippingMethodCode.'/point_relai_ws_method');
            $pointRelaisProductCode = $helperData->getChronoProductCode($address->getCountryId(),$shippingMethodCode);
            $pointRelaisService = 'T';
            $addAddressToWs = Mage::getStoreConfig('carriers/'.$shippingMethodCode.'/add_address_to_ws');
            $maxPointChronopost = Mage::getStoreConfig('carriers/'.$shippingMethodCode.'/max_point_chronopost');

            $maxDistanceSearch = Mage::getStoreConfig('carriers/'.$shippingMethodCode.'/max_distance_search');

            $client = new SoapClient($pointRelaisWs, array('trace' => 0, 'connection_timeout' => 10));

            /* si dom => on ne met pas le code ISO mais un code spécifique, sinon le relai dom ne fonctionne pas */
            $countryDomCode = $this->getCountryDomCode();
            $countryId = $address->getCountryId();

            if(isset($countryDomCode[$countryId])) {
                $countryId = $countryDomCode[$countryId];
            }

            $params = array(
                'accountNumber' => $helperData->getConfigurationAccountNumber(),
                'password' => $helperData->getConfigurationAccountPass(),
                'zipCode' => $this->getFilledValue($address->getPostcode()),
                'city' => $this->getFilledValue($address->getCity()),
                'countryCode' => $this->getFilledValue($countryId),
                'type' => 'P',
                'productCode' => $pointRelaisProductCode,
                'service' => $pointRelaisService,
                'weight' => 2000,
                'shippingDate' => date('d/m/Y'),
                'maxPointChronopost' => $maxPointChronopost,
                'maxDistanceSearch' => $maxDistanceSearch,
                'holidayTolerant' => 1
            );
            if($addAddressToWs) {
                $params['address'] = $this->getFilledValue($address->getStreet(1));
            }
            $webservbt = $client->$pointRelaisWsMethod($params);

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
                if(count($webservbt->return->listePointRelais) == 1) {
                    $listePr = array($listePr);
                }
                $return = array();
                foreach($listePr as $pr)
                {
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
                            case '1' :
                                $newPr->horairesOuvertureLundi = $horaire->horairesAsString;
                                break;
                            case '2' :
                                $newPr->horairesOuvertureMardi = $horaire->horairesAsString;
                                break;
                            case '3' :
                                $newPr->horairesOuvertureMercredi = $horaire->horairesAsString;
                                break;
                            case '4' :
                                $newPr->horairesOuvertureJeudi = $horaire->horairesAsString;
                                break;
                            case '5' :
                                $newPr->horairesOuvertureVendredi = $horaire->horairesAsString;
                                break;
                            case '6' :
                                $newPr->horairesOuvertureSamedi = $horaire->horairesAsString;
                                break;
                            case '7' :
                                $newPr->horairesOuvertureDimanche = $horaire->horairesAsString;
                                break;
                            default : break;
                        }
                    }
                    if(empty($newPr->horairesOuvertureLundi)) {
                        $newPr->horairesOuvertureLundi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureMardi)) {
                        $newPr->horairesOuvertureMardi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureMercredi)) {
                        $newPr->horairesOuvertureMercredi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureJeudi)) {
                        $newPr->horairesOuvertureJeudi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureVendredi)) {
                        $newPr->horairesOuvertureVendredi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureSamedi)) {
                        $newPr->horairesOuvertureSamedi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureDimanche)) {
                        $newPr->horairesOuvertureDimanche = "00:00-00:00 00:00-00:00";
                    }

                    $return[] = $newPr;
                }
                return $return;
            }
        }  catch (Exception $e) {
            return $this->getPointsRelaisByPudo($address);
        }
    }

    protected function getCountryDomCode() {
        return array(
            'RE' => 'REU',
            'MQ' => 'MTQ',
            'GP' => 'GLP',
            'MX' => 'MYT',
            'GF' => 'GUF'
        );
    }

    public function getDetailRelaisPoint($btcode) {
        try {
            $helperData = Mage::helper('chronorelais');
            $params = array(
                'accountNumber' => $helperData->getConfigurationAccountNumber(),
                'password' => $helperData->getConfigurationAccountPass(),
                'identifiant' => $btcode
            );

            $client = new SoapClient("https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS");
            $webservbt = $client->__call("rechercheDetailPointChronopost",$params);

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
                $pr = $webservbt['PUDO_ITEMS']['PUDO_ITEM'];
                if($pr && $pr['@attributes']['active'] == 'true') {
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
                                    if(!empty($newPr->horairesOuvertureLundi)) {
                                        $newPr->horairesOuvertureLundi .= ' ';
                                    }
                                    $newPr->horairesOuvertureLundi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                    break;
                                case '2' :
                                    if(!empty($newPr->horairesOuvertureMardi)) {
                                        $newPr->horairesOuvertureMardi .= ' ';
                                    }
                                    $newPr->horairesOuvertureMardi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                    break;
                                case '3' :
                                    if(!empty($newPr->horairesOuvertureMercredi)) {
                                        $newPr->horairesOuvertureMercredi .= ' ';
                                    }
                                    $newPr->horairesOuvertureMercredi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                    break;
                                case '4' :
                                    if(!empty($newPr->horairesOuvertureJeudi)) {
                                        $newPr->horairesOuvertureJeudi .= ' ';
                                    }
                                    $newPr->horairesOuvertureJeudi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                    break;
                                case '5' :
                                    if(!empty($newPr->horairesOuvertureVendredi)) {
                                        $newPr->horairesOuvertureVendredi .= ' ';
                                    }
                                    $newPr->horairesOuvertureVendredi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                    break;
                                case '6' :
                                    if(!empty($newPr->horairesOuvertureSamedi)) {
                                        $newPr->horairesOuvertureSamedi .= ' ';
                                    }
                                    $newPr->horairesOuvertureSamedi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                    break;
                                case '7' :
                                    if(!empty($newPr->horairesOuvertureDimanche)) {
                                        $newPr->horairesOuvertureDimanche .= ' ';
                                    }
                                    $newPr->horairesOuvertureDimanche .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                    break;
                                default :
                                    break;
                            }
                        }
                    }
                    if(empty($newPr->horairesOuvertureLundi)) {
                        $newPr->horairesOuvertureLundi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureMardi)) {
                        $newPr->horairesOuvertureMardi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureMercredi)) {
                        $newPr->horairesOuvertureMercredi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureJeudi)) {
                        $newPr->horairesOuvertureJeudi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureVendredi)) {
                        $newPr->horairesOuvertureVendredi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureSamedi)) {
                        $newPr->horairesOuvertureSamedi = "00:00-00:00 00:00-00:00";
                    }
                    if(empty($newPr->horairesOuvertureDimanche)) {
                        $newPr->horairesOuvertureDimanche = "00:00-00:00 00:00-00:00";
                    }

                    return $newPr;
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
                                            if(!empty($newPr->horairesOuvertureLundi)) {
                                                $newPr->horairesOuvertureLundi .= ' ';
                                            }
                                            $newPr->horairesOuvertureLundi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '2' :
                                            if(!empty($newPr->horairesOuvertureMardi)) {
                                                $newPr->horairesOuvertureMardi .= ' ';
                                            }
                                            $newPr->horairesOuvertureMardi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '3' :
                                            if(!empty($newPr->horairesOuvertureMercredi)) {
                                                $newPr->horairesOuvertureMercredi .= ' ';
                                            }
                                            $newPr->horairesOuvertureMercredi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '4' :
                                            if(!empty($newPr->horairesOuvertureJeudi)) {
                                                $newPr->horairesOuvertureJeudi .= ' ';
                                            }
                                            $newPr->horairesOuvertureJeudi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '5' :
                                            if(!empty($newPr->horairesOuvertureVendredi)) {
                                                $newPr->horairesOuvertureVendredi .= ' ';
                                            }
                                            $newPr->horairesOuvertureVendredi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '6' :
                                            if(!empty($newPr->horairesOuvertureSamedi)) {
                                                $newPr->horairesOuvertureSamedi .= ' ';
                                            }
                                            $newPr->horairesOuvertureSamedi .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                        case '7' :
                                            if(!empty($newPr->horairesOuvertureDimanche)) {
                                                $newPr->horairesOuvertureDimanche .= ' ';
                                            }
                                            $newPr->horairesOuvertureDimanche .= $horaire['START_TM'].'-'.$horaire['END_TM'];
                                            break;
                                    }
                                }
                            }
                            if(empty($newPr->horairesOuvertureLundi)) {
                                $newPr->horairesOuvertureLundi = "00:00-00:00 00:00-00:00";
                            }
                            if(empty($newPr->horairesOuvertureMardi)) {
                                $newPr->horairesOuvertureMardi = "00:00-00:00 00:00-00:00";
                            }
                            if(empty($newPr->horairesOuvertureMercredi)) {
                                $newPr->horairesOuvertureMercredi = "00:00-00:00 00:00-00:00";
                            }
                            if(empty($newPr->horairesOuvertureJeudi)) {
                                $newPr->horairesOuvertureJeudi = "00:00-00:00 00:00-00:00";
                            }
                            if(empty($newPr->horairesOuvertureVendredi)) {
                                $newPr->horairesOuvertureVendredi = "00:00-00:00 00:00-00:00";
                            }
                            if(empty($newPr->horairesOuvertureSamedi)) {
                                $newPr->horairesOuvertureSamedi = "00:00-00:00 00:00-00:00";
                            }
                            if(empty($newPr->horairesOuvertureDimanche)) {
                                $newPr->horairesOuvertureDimanche = "00:00-00:00 00:00-00:00";
                            }

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
    public function getMethodIsAllowed($code,$quote = '') {
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
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
                    'arrCity' => $address->getCity() ? $this->getFilledValue($address->getCity()) : '-',
                    'type' => 'M',
                    'weight' => 1
                );
                $webservbt = $client->calculateProducts($params);
                if($webservbt->return->errorCode == 0 && $webservbt->return->productList)
                {
                    if(is_array($webservbt->return->productList)) {
                      foreach($webservbt->return->productList as $product) {
                          $this->methodsAllowed[] = $product->productCode;
                      }
                    } else { /* cas ou il y a un seul résultat */
                      $product = $webservbt->return->productList;
                      $this->methodsAllowed[] = $product->productCode;
                    }
                }
            }
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

    public function cancelSkybill($skybillNumber = '') {
        if($skybillNumber) {
            try {

                $client = new SoapClient("https://www.chronopost.fr/tracking-cxf/TrackingServiceWS?wsdl",array('trace'=> 0,'connection_timeout'=>10));

                $helperData = Mage::helper('chronorelais');
                $params = array(
                    'accountNumber' => $helperData->getConfigurationAccountNumber(),
                    'password' => $helperData->getConfigurationAccountPass(),
                    'skybillNumber' => $skybillNumber,
                    'language' => Mage::app()->getLocale()->getLocaleCode()
                );

                return $client->cancelSkybill($params);
            }  catch (Exception $e) {
                return false;
            }
        }
        return false;
    }

    /* Livraison sur rendez-vous */
    public function getSearchDeliverySlot($_srdvConfig = '') {
        $quote = Mage::getSingleton('checkout/cart')->getQuote();

        $_shippingAddress = $quote->getShippingAddress();
        $recipient_address = $_shippingAddress->getStreet();
        if (!isset($recipient_address[1])) {
            $recipient_address[1] = '';
        }

        $_helper = Mage::helper('chronorelais');
        try {

            $soapHeaders = array();
            $namespace = 'http://cxf.soap.ws.creneau.chronopost.fr/';
            $soapHeaders[] = new SoapHeader($namespace, 'password', $_helper->getConfigurationAccountPass());
            $soapHeaders[] = new SoapHeader($namespace, 'accountNumber', $_helper->getConfigurationAccountNumber());

            $client = new SoapClient("https://www.chronopost.fr/rdv-cxf/services/CreneauServiceWS?wsdl", array('trace' => 1, 'connection_timeout' => 10));
            $client->__setSoapHeaders($soapHeaders);

            $_srdvConfig = json_decode($_srdvConfig,true);

            /* definition date de debut */
            $dateBegin = date('Y-m-d H:i:s');
            if(isset($_srdvConfig['dateRemiseColis_nbJour']) && $_srdvConfig['dateRemiseColis_nbJour'] > 0) {
                $dateBegin =  date('Y-m-d', strtotime('+'.(int)$_srdvConfig['dateRemiseColis_nbJour'].' day'));
            } elseif(isset($_srdvConfig['dateRemiseColis_jour']) && isset($_srdvConfig['dateRemiseColis_heures'])) {
                $jour_text = date('l', strtotime("Sunday +".$_srdvConfig['dateRemiseColis_jour']." days"));
                $dateBegin = date('Y-m-d', strtotime('next '.$jour_text)).' '.$_srdvConfig['dateRemiseColis_heures'].':'.$_srdvConfig['dateRemiseColis_minutes'].':00';
            }
            $dateBegin = date('Y-m-d',strtotime($dateBegin)).'T'.date('H:i:s',strtotime($dateBegin));

            $params = array(

                'callerTool' => 'RDVWS',
                'productType' => 'RDV',

                'shipperAdress1' => $_helper->getConfigurationShipperInfo('address1'),
                'shipperAdress2' => $_helper->getConfigurationShipperInfo('address2'),
                'shipperZipCode' => $_helper->getConfigurationShipperInfo('zipcode'),
                'shipperCity' => $_helper->getConfigurationShipperInfo('city'),
                'shipperCountry' => $_helper->getConfigurationShipperInfo('country'),

                'recipientAdress1' => substr($this->getFilledValue($recipient_address[0]), 0, 38),
                'recipientAdress2' => substr($this->getFilledValue($recipient_address[1]), 0, 38),
                'recipientZipCode' => $this->getFilledValue($_shippingAddress->getPostcode()),
                'recipientCity' => $this->getFilledValue($_shippingAddress->getCity()),
                'recipientCountry' => $this->getFilledValue($_shippingAddress->getCountryId()),

                'weight' => 1,
                'dateBegin' => $dateBegin,
                'shipperDeliverySlotClosed' => '',
                'currency' => 'EUR',
                'isDeliveryDate' => 0,
                'slotType' => 'J'
            );


            for($i = 1; $i <= 4; $i++) {

                /* tarif des niveaux tarifaires */
                if(isset($_srdvConfig['N'.$i.'_price'])) {
                    $params['rateN'.$i] = $_srdvConfig['N'.$i.'_price'];
                }

                /* niveaux tarifaires fermés  */
                if(isset($_srdvConfig['N'.$i.'_status']) && $_srdvConfig['N'.$i.'_status'] == 0) {
                    if(!isset($params['rateLevelsNotShow'])) {
                        $params['rateLevelsNotShow'] = array();
                    }
                    $params['rateLevelsNotShow'][]= 'N'.$i;
                }
            }

            /* creneaux à fermer */
            if(isset($_srdvConfig['creneaux'])) {
                foreach($_srdvConfig['creneaux'] as $_creneau) {

                    $jour_debut_text = date('l', Mage::getModel('core/date')->timestamp(strtotime("Sunday +".$_creneau['creneaux_debut_jour']." days")));
                    $jour_fin_text = date('l', Mage::getModel('core/date')->timestamp(strtotime("Sunday +".$_creneau['creneaux_fin_jour']." days")));

                    $dateDebut = '';
                    $dateFin = '';

                    /* creation de creneaux aux bons formats, pour 6 semaines consécutives */
                    for($indiceWeek = 0; $indiceWeek < 6; $indiceWeek++) {

                        if(empty($dateDebut)) {
                            $dateDebut = date('Y-m-d', Mage::getModel('core/date')->timestamp(strtotime('next '.$jour_debut_text))).' '.(int)$_creneau['creneaux_debut_heures'].':'.(int)$_creneau['creneaux_debut_minutes'].':00';
                            $dateFin = date('Y-m-d', Mage::getModel('core/date')->timestamp(strtotime('next '.$jour_fin_text))).' '.(int)$_creneau['creneaux_fin_heures'].':'.(int)$_creneau['creneaux_fin_minutes'].':00';
                            if(date('N') >= $_creneau['creneaux_debut_jour']) {
                                $dateDebut = date('Y-m-d', Mage::getModel('core/date')->timestamp(strtotime(date('Y-m-d',strtotime($dateDebut)).' -7 day'))).' '.(int)$_creneau['creneaux_debut_heures'].':'.(int)$_creneau['creneaux_debut_minutes'].':00';
                            }
                            if(date('N') >= $_creneau['creneaux_fin_jour']) {
                                $dateFin = date('Y-m-d', Mage::getModel('core/date')->timestamp(strtotime(date('Y-m-d',strtotime($dateFin)).' -7 day'))).' '.(int)$_creneau['creneaux_fin_heures'].':'.(int)$_creneau['creneaux_fin_minutes'].':00';
                            }

                        } else {
                            $dateDebut = date('Y-m-d', Mage::getModel('core/date')->timestamp(strtotime($jour_debut_text.' next week '.date('Y-m-d',Mage::getModel('core/date')->timestamp(strtotime($dateDebut)))))).' '.(int)$_creneau['creneaux_debut_heures'].':'.(int)$_creneau['creneaux_debut_minutes'].':00';
                            $dateFin = date('Y-m-d', Mage::getModel('core/date')->timestamp(strtotime($jour_fin_text.' next week '.date('Y-m-d',Mage::getModel('core/date')->timestamp(strtotime($dateFin)))))).' '.(int)$_creneau['creneaux_fin_heures'].':'.(int)$_creneau['creneaux_fin_minutes'].':00';
                        }

                        $dateDebutStr = date('Y-m-d',Mage::getModel('core/date')->timestamp(strtotime($dateDebut))).'T'.date('H:i:s',Mage::getModel('core/date')->timestamp(strtotime($dateDebut)));
                        $dateFinStr = date('Y-m-d',Mage::getModel('core/date')->timestamp(strtotime($dateFin))).'T'.date('H:i:s',Mage::getModel('core/date')->timestamp(strtotime($dateFin)));

                        if(!isset($params['shipperDeliverySlotClosed'])) {
                            $params['shipperDeliverySlotClosed'] = array();
                        }
                        $params['shipperDeliverySlotClosed'][] = $dateDebutStr."/".$dateFinStr;
                    }
                }
            }

            $webservbt = $client->searchDeliverySlot($params);
            if($webservbt->return->code == 0) {
                return $webservbt;
            }
            return false;
        }catch(Exception $e) {
            return false;
        }
    }

    public function confirmDeliverySlot($rdvInfo = '') {
        $_helper = Mage::helper('chronorelais');
        try {

            $soapHeaders = array();
            $namespace = 'http://cxf.soap.ws.creneau.chronopost.fr/';
            $soapHeaders[] = new SoapHeader($namespace, 'password', $_helper->getConfigurationAccountPass());
            $soapHeaders[] = new SoapHeader($namespace, 'accountNumber', $_helper->getConfigurationAccountNumber());

            $client = new SoapClient("https://www.chronopost.fr/rdv-cxf/services/CreneauServiceWS?wsdl", array('trace' => 1, 'connection_timeout' => 10));
            $client->__setSoapHeaders($soapHeaders);

            $params = array(
                'callerTool' => 'RDVWS',
                'productType' => 'RDV',

                'codeSlot' => $rdvInfo['deliverySlotCode'],
                'meshCode' => $rdvInfo['meshCode'],
                'transactionID' => $rdvInfo['transactionID'],
                'rank' => $rdvInfo['rank'],
                'position' => $rdvInfo['rank'],
                'dateSelected' => $rdvInfo['deliveryDate']
            );

            return $client->confirmDeliverySlot($params);
        }catch(Exception $e) {
            return false;
        }
    }
}
