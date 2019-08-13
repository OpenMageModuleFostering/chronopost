<?php
class Chronopost_Chronorelais_SrdvController extends Mage_Core_Controller_Front_Action {

	/**
     * Get payment method step html
     *
     * @return string
     */
    protected function _getChronopostSrdvHtml() {
        return $this->getLayout()->getBlock('root')->toHtml();
    }

	/**
     * Get planning
     */
    public function getPlanningAction() {
        $result = array();
        $shippingMethod = $this->getRequest()->getPost('shipping_method');

        if (extension_loaded('soap')) {

            $shippingMethodCode = explode("_", $shippingMethod);
            $shippingMethodCode = $shippingMethodCode[0];

            $this->loadLayout('checkout_onepage_shippingchronopostsrdv');
            $result['goto_section'] = 'shipping-method';
            $result['update_section'] = array(
                'name' => 'shipping-method-'.$shippingMethod,
                'html' => $this->_getChronopostSrdvHtml()
            );
            $result['planning'] = true;
        } else {
            $result['error'] = true;
            $result['message'] = $this->__('Sorry for inconvenience, The SOAP extension is not installed in the server. Please contact the site administrator.');
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}