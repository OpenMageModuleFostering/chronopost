<?php
class Chronopost_Chronorelais_RelaisController extends Mage_Core_Controller_Front_Action {
	
	public function filterAction() {
		$this->loadLayout();     
		$this->renderLayout();
	}
	
	public function detailAction() {
		$this->loadLayout();     
		$this->renderLayout();
	}

	/**
	* Go to the tacking page
	* external function for mail link "Track Your Order" in shipment mail
	*/
    public function trackingAction()
    {
		if($this->getRequest()->isGet()) {
			if($hash = $this->getRequest()->getParam('hash')) {
				$req_values = Mage::helper('shipping')->decodeTrackingHash($hash);
				if($req_values) {
					$order = Mage::getModel('sales/order')->load($req_values['id']);
					$popup_url = Mage::helper('shipping')->getTrackingPopupUrlBySalesModel($order);
					header('location: '.$popup_url); exit();
				}
			}
		}	
	}	
	
}