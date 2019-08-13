<?php
class Chronopost_Chronorelais_Block_Detail extends Mage_Core_Block_Template
{
	public function getRelaisPoint(){


		$btcode = $this->getRequest()->getParam ( 'btcode' );

		if($btcode){
			$result = Mage::getModel('shipping/rate_result');
            ini_set("soap.wsdl_cache_enabled", "0");
            $helper = Mage::helper('chronorelais/webservice');
            return $helper->getDetailRelaisPoint($btcode);
		}
        return false;
	}
}
?>