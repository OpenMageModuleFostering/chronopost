<?php
class Chronopost_Chronorelais_Block_Filter extends Mage_Core_Block_Template
{

	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }

	
	public function getRelaisPoints(){
		
		$zipcode = $this->getRequest()->getParam ( 'zipcode' );

		if( $zipcode && $zipcode!="" ){
			$result = Mage::getModel('shipping/rate_result');        

			try { 
				$client = new SoapClient("http://wsshipping.chronopost.fr/soap.point.relais/services/ServiceRechercheBt?wsdl",array('trace'=> 0,'connection_timeout'=>10));

				$webservbt = $client->__call("rechercheBtParCodeproduitEtCodepostalEtDate",array(0,$zipcode,0));
				
			} catch(SoapFault $fault){

				return false;
			}
			

			return $webservbt;
		}
		else{
			return false;
		}
	}

	public function getmethodeCode(){
		
		$zipcode = $this->getRequest()->getParam ( 'methodecode' );

		if($zipcode){
			$result = Mage::getModel('shipping/rate_result');        
			ini_set("soap.wsdl_cache_enabled", "0");
			$client = new SoapClient("http://wsshipping.chronopost.fr/soap.point.relais/services/ServiceRechercheBt?wsdl");
			$webservbt = $client->__call("rechercheBtParCodeproduitEtCodepostalEtDate",array(0,$zipcode,0));
			return $webservbt;
		}
		else{
			return false;
		}
	}
	
}
?>