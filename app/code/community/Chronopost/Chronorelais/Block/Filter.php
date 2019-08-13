<?php
class Chronopost_Chronorelais_Block_Filter extends Mage_Core_Block_Template
{
	public function getRelaisPoints(){

		$zipcode = $this->getRequest()->getParam ( 'zipcode' );

		if( $zipcode && $zipcode!="" ){
			$result = Mage::getModel('shipping/rate_result');
            $helper = Mage::helper('chronorelais/webservice');
            return $helper->getPointsRelaisByCp($zipcode);
		}
		return false;
	}

	public function getmethodeCode(){

		$zipcode = $this->getRequest()->getParam ( 'methodecode' );

		if($zipcode){
			$result = Mage::getModel('shipping/rate_result');
			ini_set("soap.wsdl_cache_enabled", "0");
            $helper = Mage::helper('chronorelais/webservice');
            return $helper->getPointsRelaisByCp($zipcode);
		}
		return false;
	}
}
?>