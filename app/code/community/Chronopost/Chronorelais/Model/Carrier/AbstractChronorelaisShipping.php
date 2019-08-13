<?php

/**
 * Magento Chronopost Chronorelais Module
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
 * @category   Chronopost
 * @package    Chronopost_Chronorelais
 * @copyright  Copyright (c) 2008-10 Owebia (http://www.owebia.com/)
 * @author     Antoine Lemoine
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

// Pour gérer les cas où il y a eu compilation
if (file_exists(dirname(__FILE__).'/Chronopost_Chronorelais_includes_ChronorelaisShippingHelper.php')) {
	include_once 'Chronopost_Chronorelais_includes_ChronorelaisShippingHelper.php';
}
else {
	include_once Mage::getBaseDir('code').'/community/Chronopost/Chronorelais/includes/ChronorelaisShippingHelper.php';
}

class OCS_Magento_Product implements OCS_Product {
	private $parent_cart_item;
	private $cart_item;
	private $cart_product;
	private $loaded_product;
	private $quantity;

	public function OCS_Magento_Product($cart_item, $parent_cart_item) {
		$this->cart_item = $cart_item;
		$this->cart_product = $cart_item->getProduct();
		$this->parent_cart_item = $parent_cart_item;
		$this->quantity = isset($parent_cart_item) ? $parent_cart_item->getQty() : $cart_item->getQty();
	}

	public function getOption($option_name, $get_by_id=false) {
		$value = null;
		$product = $this->cart_product;
		foreach ($product->getOptions() as $option) {
			if ($option->getTitle()==$option_name) {
				$custom_option = $product->getCustomOption('option_'.$option->getId());
				if ($custom_option) {
					$value = $custom_option->getValue();
					if ($option->getType()=='drop_down' && !$get_by_id) {
						$option_value = $option->getValueById($value);
						if ($option_value) {
							$value = $option_value->getTitle();
						}
					}
				}
				break;
			}
		}
		return $value;
	}

	public function getAttribute($attribute_name, $get_by_id=false) {
		$value = null;
		$product = $this->_getLoadedProduct();
		$attribute = $product->getResource()->getAttribute($attribute_name);
		if ($attribute) {
			$input_type = $attribute->getFrontend()->getInputType();
			if($input_type == 'select') {
				$value = $get_by_id ? $product->getData($attribute_name) : $product->getAttributeText($attribute_name);
			} else {
				$value = $product->getData($attribute_name);
			}
		}
		return $value;
	}

	private function _getLoadedProduct() {
		if (!isset($this->loaded_product)) {
			$this->loaded_product = Mage::getModel('catalog/product')->load($this->cart_product->getId());
		}
		return $this->loaded_product;
	}

	public function getQuantity() {
		return $this->quantity;
	}

	public function getName() {
		return $this->cart_product->getName();
	}

	public function getSku() {
		return $this->cart_product->getSku();
	}

	public function getStockData($key) {
		$stock = $this->cart_product->getStockItem();
		if($key == 'is_in_stock') {
			return (bool)$stock->getIsInStock();
		} elseif($key == 'quantity') {
			$quantity = $stock->getQty();
			return $stock->getIsQtyDecimal() ? (float)$quantity : (int)$quantity;
		}
		return null;
	}
}

abstract class Chronopost_Chronorelais_Model_Carrier_AbstractChronorelaisShipping
	extends Mage_Shipping_Model_Carrier_Abstract
{
	protected $_translate_inline;
	protected $_result;
	protected $_config;
	protected $_countries;
	protected $_helper;
	protected $_messages;

	protected $_checkContract = false;
	protected $_checkRelaiWs = false;

	/**
	 * Collect rates for this shipping method based on information in $request
	 *
	 * @param Mage_Shipping_Model_Rate_Request $data
	 * @return Mage_Shipping_Model_Rate_Result
	 */
	public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
		// skip if not enabled
		if (!$this->__getConfigData('active')) {
			return false;
		}

        $helper = Mage::helper('chronorelais');

		$process = array(
			'request' => $request,
			'result' => Mage::getModel('shipping/rate_result'),
			'cart.items' => array(),
			'products' => array(),
			'data' => array(
				'module.version' => null,
				'carrier.code' => $this->_code,
				'cart.price_excluding_tax' => $request->_data['package_value_with_discount'],
				'cart.price_including_tax' => null,
				'cart.weight' => $request->_data['package_weight'],
				'cart.weight.unit' => $helper->getConfigWeightUnit(),
				'cart.quantity' => $request->_data['package_qty'],
				'destination.country.code' => $request->_data['dest_country_id'],
				'destination.country.name' => null,
				'destination.region.code' => $request->_data['dest_region_code'],
				'destination.postcode' => $request->_data['dest_postcode'],
				'origin.country.code' => $request->_data['country_id'],
				'origin.country.name' => null,
				'origin.region.code' => $request->_data['region_id'],
				'origin.postcode' => $request->_data['postcode'],
				'customer.group.id' => null,
				'customer.group.code' => null,
				'free_shipping' => $request->getFreeShipping(),
				'store.id' => $request->_data['store_id'],
				'store.code' => null,
				'store.name' => null,
				'store.address' => null,
				'store.phone' => null,
				'date.timestamp' => null,
				'date.year' => null,
				'date.month' => null,
				'date.day' => null,
				'date.hour' => null,
				'date.minute' => null,
				'date.second' => null,
			),
			'stop_to_first_match' => null,
			'config' => null,
		);

		$items = $request->getAllItems();
		for ($i=0, $n=count($items); $i<$n; $i++) {
			$item = $items[$i];
			if ($item->getProduct() instanceof Mage_Catalog_Model_Product) {
				$process['cart.items'][$item->getId()] = $item;
			}
		}

		$this->_process($process);

		return $process['result'];
	}


	public function getAllowedMethods() {
		$config = $this->_getConfig();
		$allowed_methods = array();
		if (count($config)>0) {
			foreach ($config as $row) {
				$allowed_methods[$row['*code']] = isset($row['label']) ? $row['label']['value'] : 'No label';
			}
		}
		return $allowed_methods;
	}

	public function isTrackingAvailable() {
		return true;
	}

	public function getTrackingInfo($tracking_number) {
		$tracking_url = Mage::helper('chronorelais')->getConfigurationTrackingViewUrl();
		$parts = explode(':',$tracking_number);
		if (count($parts)>=2) {
			$tracking_number = $parts[1];

			$config = $this->_getConfig();

			if (isset($config[$parts[0]]['tracking_url'])) {
				$row = $config[$parts[0]];
				$tmp_tracking_url = $this->_helper->getRowProperty($row,'tracking_url');
				if (isset($tmp_tracking_url)) {
					$tracking_url = $tmp_tracking_url;
				}
			}
		}

		$tracking_status = Mage::getModel('shipping/tracking_result_status')
			->setCarrier($this->_code)
			->setCarrierTitle($this->__getConfigData('title'))
			->setTracking($tracking_number)
			->addData(
				array(
					'status'=>'<a target="_blank" href="'.str_replace('{tracking_number}',$tracking_number,$tracking_url).'">'.__('track the package').'</a>'
				)
			);
		$tracking_result = Mage::getModel('shipping/tracking_result')
			->append($tracking_status);

		if ($trackings = $tracking_result->getAllTrackings()) {
			return $trackings[0];
		}
		return false;
	}


	/***************************************************************************************************************************/

	protected function _process(&$process) {
		$store = Mage::app()->getStore($process['data']['store.id']);
		$mage_config = Mage::getConfig();
		$timestamp = time();
		$customer_group_id = Mage::getSingleton('customer/session')->getCustomerGroupId();
                $helper = Mage::helper('chronorelais');
		// Pour les commandes depuis Adminhtml
		if ($customer_group_id==0) {
			$customer_group_id2 = Mage::getSingleton('adminhtml/session_quote')->getQuote()->getCustomerGroupId();
			if (isset($customer_group_id2)) {
				$customer_group_id = $customer_group_id2;
			}
		}

		$customer_group_code = Mage::getSingleton('customer/group')->load($customer_group_id)->getData('customer_group_code');
		$process['data'] = array_merge($process['data'],array(
			'customer.group.id' => $customer_group_id,
			'customer.group.code' => $customer_group_code,
			'destination.country.name' => $this->__getCountryName($process['data']['destination.country.code']),
			'origin.country.name' => $this->__getCountryName($process['data']['origin.country.code']),
			'cart.weight.unit' => $helper->getConfigWeightUnit(),
			'store.code' => $store->getCode(),
			'store.name' => $store->getConfig('general/store_information/name'),
			'store.address' => $store->getConfig('general/store_information/address'),
			'store.phone' => $store->getConfig('general/store_information/phone'),
			'date.timestamp' => $timestamp,
			'date.year' => (int)date('Y',$timestamp),
			'date.month' => (int)date('m',$timestamp),
			'date.day' => (int)date('d',$timestamp),
			'date.hour' => (int)date('H',$timestamp),
			'date.minute' => (int)date('i',$timestamp),
			'date.second' => (int)date('s',$timestamp),
			'module.version' => (string)$mage_config->getNode('modules/Chronopost_Chronorelais/version'),
		));

        $weight_limit = $this->__getConfigData('weight_limit'); /* weight_limit in kg */
        $productWeightOverLimit = false;

		foreach ($process['cart.items'] as $item) {
			if ($item->getProduct()->getTypeId()!='configurable') {
				$parent_item_id = $item->getParentItemId();
                $itemWeight = $item->getWeight();
                if($helper->getConfigWeightUnit() == 'g')
                {
                    $itemWeight = $itemWeight / 1000; // conversion g => kg
                }
                if($itemWeight > $weight_limit) {
                    $productWeightOverLimit = true;
                }
				$process['products'][] = new OCS_Magento_Product($item, isset($process['cart.items'][$parent_item_id]) ? $process['cart.items'][$parent_item_id] : null);
			}
		}

		if (!$process['data']['free_shipping']) {
			foreach ($process['cart.items'] as $item) {
				if ($item->getProduct() instanceof Mage_Catalog_Model_Product) {
					if ($item->getFreeShipping()) {
						$process['data']['free_shipping'] = true;
					} else {
						$process['data']['free_shipping'] = false;
						break;
					}
				}
			}
		}

		$process['data']['cart.price_including_tax'] = $this->__getCartTaxAmount($process)+$process['data']['cart.price_excluding_tax'];
		$process['stop_to_first_match'] = $this->__getConfigData('stop_to_first_match');
		$process['config'] = $this->_getConfig();
		$compression = $this->__getConfigData('auto_compression');
		if ($compression=='compress') {
			Mage::getConfig()->saveConfig('carriers/'.$this->_code.'/config',$this->_helper->formatConfig(true));
		} else if ($compression=='uncompress') {
			Mage::getConfig()->saveConfig('carriers/'.$this->_code.'/config',$this->_helper->formatConfig(false));
		}

		$this->_helper->debug = (int)(isset($_GET['debug']) ? $_GET['debug'] : $this->__getConfigData('debug'));
		$http_request = Mage::app()->getFrontController()->getRequest();
		$this->_helper->debug = $this->_helper->debug && $http_request->getRouteName()=='checkout' && $http_request->getControllerName()=='cart';
		if ($this->_helper->debug) {
			$this->_helper->setDebugHeader($process);
		}

		$value_found = false;
		$process_continue = true;

		//Set error messages if not any matching
		$freeShippingEnable = $this->__getConfigData('free_shipping_enable');
		$freeShippingSubtotal = $this->__getConfigData('free_shipping_subtotal');
		$applicationFee 	= $this->__getConfigData('application_fee');
		$handlingFee 		= $this->__getConfigData('handling_fee');

        /* On autorise chronopost > 30 Kg si tous les produits sont <= 30 Kg */
        if($productWeightOverLimit) {
			$value_found = false;
			$process_continue = false;
		}

		$process_continue = $this->validateMethod();

		if($process_continue) {
			foreach ($process['config'] as $row) {
				$result = $this->_helper->processRow($process,$row);
				$this->_addMessages($this->_helper->getMessages());
				if ($result && $result->success) {
					$value_found = true;
					$fees = $result->result;
					if($applicationFee) {
						$fees += $applicationFee;
					}
					if($handlingFee) {
						$fees += $handlingFee;
					}
					if($freeShippingEnable && ($freeShippingSubtotal<=$process['data']['cart.price_excluding_tax'])) {
						$fees = 0;
					}
					$this->__appendMethod($process,$row,$fees);
					if ($process['stop_to_first_match']) {
						break;
					}
				}
			}
		}

		$this->_helper->printDebug();
	}

	/* Additional conditions to show shipping method, each shipping method model might have their own validateMethod function */
	protected function validateMethod() {
		$helperWS = Mage::helper('chronorelais/webservice');

		/* Chronorelais => test Si WS fonctionne */
		if($this->_checkRelaiWs) {
			$shippingAddress = Mage::getSingleton('adminhtml/session_quote')->getQuote()->getShippingAddress();
	        $webservice = $helperWS->getPointsRelaisByCp($shippingAddress->getPostcode());
	        if($webservice === false) {
	            return false;
	        }
		}

        if($this->_checkContract) {
            $isAllowed = $helperWS->getMethodIsAllowed($this->_code);
            if($isAllowed === false) {
                return false;
            }
        }
        return true;
	}

	protected function _getConfig() {
		if (!isset($this->_config)) {
			$this->_helper = new ChronorelaisShippingHelper($this->__getConfigData('config'));
			$this->_config = $this->_helper->getConfig();
			$this->_addMessages($this->_helper->getMessages());
		}
		return $this->_config;
	}

	protected function _getMethodText($process, $row, $property) {
		if (!isset($row[$property])) {
			return '';
		}

		$output = '';

		return $output . ' '.$this->_helper->evalInput($process,$row,$property,str_replace(
			array('{cart.weight}','{cart.price_including_tax}','{cart.price_excluding_tax}'),
			array(
				$process['data']['cart.weight'].$process['data']['cart.weight.unit'],
				$this->__formatPrice($process['data']['cart.price_including_tax']),
				$this->__formatPrice($process['data']['cart.price_excluding_tax'])
			),
			$this->_helper->getRowProperty($row,$property)
		));
	}

	protected function _addMessages($messages) {
		if (!is_array($messages)) {
			$messages = array($messages);
		}
		if (!is_array($this->_messages)) {
			$this->_messages = $messages;
		} else {
			$this->_messages = array_merge($this->_messages,$messages);
		}
	}

	protected function _appendErrors(&$process, $messages) {
		if (is_array($messages)) {
			foreach ($messages as $message) {
				$this->__appendError($process,$this->__($message));
			}
		}
	}

	/***************************************************************************************************************************/

	protected function __getConfigData($key) {
		return $this->getConfigData($key);
	}

	protected function __appendMethod(&$process, $row, $fees) {
		$method = Mage::getModel('shipping/rate_result_method')
			->setCarrier($this->_code)
			->setCarrierTitle($this->__getConfigData('title'))
			->setMethod($row['*code'])
			->setMethodTitle($this->_getMethodText($process,$row,'label'))
			->setMethodDescription($this->_getMethodText($process,$row,'description'))
			->setMethodLogo($this->__getConfigData('logo_url'))
			->setPrice($fees)
			->setCost($fees)
		;
		$process['result']->append($method);
	}

	protected function __appendError(&$process, $message) {
		if (isset($process['result'])) {
			$error = Mage::getModel('shipping/rate_result_error')
				->setCarrier($this->_code)
				->setCarrierTitle($this->__getConfigData('title'))
				->setErrorMessage($message)
			;
			$process['result']->append($error);
		}
	}

	protected function __formatPrice($price) {
		if (!isset($this->_core_helper)) {
			$this->_core_helper = Mage::helper('core');
		}
		return $this->_core_helper->currency($price);
	}

	protected function __($message) {
		$args = func_get_args();
		$message = array_shift($args);
		if ($message instanceof OCS_Message) {
			$args = $message->args;
			$message = $message->message;
		}

		$output = Mage::helper('shipping')->__($message);
		if (count($args)==0) {
			return $output;
		}

		if (!isset($this->_translate_inline)) {
			$this->_translate_inline = Mage::getSingleton('core/translate')->getTranslateInline();
		}
		if ($this->_translate_inline) {
			$parts = explode('}}{{',$output);
			$parts[0] = vsprintf($parts[0],$args);
			return implode('}}{{',$parts);
		} else {
			return vsprintf($output,$args);
		}
	}

	protected function __getCountryName($country_code) {
		return Mage::getModel('directory/country')->load($country_code)->getName();
	}

	protected function __getCartTaxAmount($process) {
		$tax_amount = 0;
		foreach ($process['cart.items'] as $item) {
			$tax_amount += $item->getData('tax_amount');
		}
		return $tax_amount;
	}
}

?>