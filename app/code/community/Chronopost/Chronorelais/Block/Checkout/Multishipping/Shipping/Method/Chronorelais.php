<?php
/**
* Magento
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@magentocommerce.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade Magento to newer
* versions in the future. If you wish to customize Magento for your
* needs please refer to http://www.magentocommerce.com for more information.
*
* @category    Mage
* @package     Mage_Checkout
* @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
* @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

/**
* One page checkout status
*
* @category   Mage
* @category   Mage
* @package    Mage_Checkout
* @author      Magento Core Team <core@magentocommerce.com>
*/
class Chronopost_Chronorelais_Block_Checkout_Multishipping_Shipping_Method_Chronorelais extends Mage_Checkout_Block_Onepage_Abstract
{
  protected $_chronorelais;

  public function getChronorelais()
  {
    if (empty($this->_chronorelais)) {
      $postcode = Mage::getSingleton('core/session')->getMultiPostcode();
      
      $client = new SoapClient("http://wsshipping.chronopost.fr/soap.point.relais/services/ServiceRechercheBt?wsdl",array('trace'=> 0,'connection_timeout'=>10));
      $webservbt = $client->__call("rechercheBtParCodeproduitEtCodepostalEtDate",array(0,$postcode,0));
      $this->_chronorelais = $webservbt;
    }
    
    return $this->_chronorelais;
  }

}