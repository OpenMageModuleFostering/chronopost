<?php
class Chronopost_Chronorelais_Block_Detail extends Mage_Core_Block_Template
{

	public function _prepareLayout()
    {
 		return parent::_prepareLayout(); 
    }

	public function getRelaisPoint(){
	
		
		$btcode = $this->getRequest()->getParam ( 'btcode' );

		if($btcode){
			$result = Mage::getModel('shipping/rate_result');        
                        ini_set("soap.wsdl_cache_enabled", "0");
                        $helper = Mage::helper('chronorelais/webservice');
                        $webservbt = $helper->getDetailRelaisPoint($btcode);

			return $webservbt;
		}
		else{
			return false;
		}
	}
	
}
?>