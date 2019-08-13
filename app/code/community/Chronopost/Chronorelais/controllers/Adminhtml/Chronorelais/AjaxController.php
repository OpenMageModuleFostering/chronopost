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
class Chronopost_Chronorelais_Adminhtml_Chronorelais_AjaxController extends Chronopost_Chronorelais_Controller_Abstract {

    private function getPropertyHelper($row_id, $property_key, $property) {
        $cleaned_property = $this->cleanKey($property_key);
        $prefix = "r-" . $row_id . "-p-" . $cleaned_property;
        $value = isset($property['original_value']) ? $property['original_value'] : (isset($property['value']) ? $property['value'] : '');

        switch ($property_key) {
            case 'enabled':
                $enabled = $value !== false;
                return "<p><select id=\"" . $prefix . "\" class=\"field\">"
                        . "<option value=\"0\"" . ($enabled ? '' : ' selected="selected"') . ">" . $this->__('Disabled') . "</option>"
                        . "<option value=\"1\"" . ($enabled ? ' selected="selected"' : '') . ">" . $this->__('Enabled') . "</option>"
                        . "</select><p>";
                break;
            case 'label':
            case 'description':
                return "<textarea id=\"" . $prefix . "\" class=\"field\">" . $value . "</textarea>"
                        . "<fieldset class=\"buttons-set\"><legend>" . $this->__('Insert') . "</legend>"
                        . "<p>"
                        . $this->button('Destination country', "ocseditor.insertAtCaret(this,'{destination.country.name}');")
                        . $this->button('Cart weight', "ocseditor.insertAtCaret(this,'{cart.weight}');")
                        . $this->button('Cart quantity', "ocseditor.insertAtCaret(this,'{cart.quantity}');")
                        . $this->button('Price including tax', "ocseditor.insertAtCaret(this,'{cart.price_including_tax}');")
                        . $this->button('Price excluding tax', "ocseditor.insertAtCaret(this,'{cart.price_excluding_tax}');")
                        . "</p>"
                        . "</fieldset>";
                break;
            case 'fees':
                return "<textarea id=\"" . $prefix . "\" class=\"field\">" . $value . "</textarea>"
                        . "<fieldset class=\"buttons-set\"><legend>" . $this->__('Insert') . "</legend>"
                        . "<p><span class=\"buttons-set-label\">" . $this->__('Cart') . "</span>"
                        . $this->button('Weight', "ocseditor.insertAtCaret(this,'{cart.weight}');")
                        . $this->button('Products quantity', "ocseditor.insertAtCaret(this,'{cart.quantity}');")
                        . $this->button('Price including tax', "ocseditor.insertAtCaret(this,'{cart.price_including_tax}');")
                        . $this->button('Price excluding tax', "ocseditor.insertAtCaret(this,'{cart.price_excluding_tax}');")
                        . "</p>"
                        . "<p><span class=\"buttons-set-label\">" . $this->__('Selection') . "</span>"
                        . $this->button('Weight', "ocseditor.insertAtCaret(this,'{selection.weight}');")
                        . $this->button('Products quantity', "ocseditor.insertAtCaret(this,'{selection.quantity}');")
                        . "</p>"
                        . "<p><span class=\"buttons-set-label\">" . $this->__('Product') . "</span>"
                        . $this->button('Weight', "ocseditor.insertAtCaret(this,'{product.weight}');")
                        . $this->button('Quantity', "ocseditor.insertAtCaret(this,'{product.quantity}');")
                        . "</p>"
                        . "</fieldset>";
                break;
            case 'conditions':
                return "<textarea id=\"" . $prefix . "\" class=\"field\">" . $value . "</textarea>"
                        . "<fieldset class=\"buttons-set\"><legend>" . $this->__('Insert') . "</legend>"
                        . "<p><span class=\"buttons-set-label\">" . $this->__('Cart') . "</span>"
                        . $this->button('Weight', "ocseditor.insertAtCaret(this,'{cart.weight}');")
                        . $this->button('Products quantity', "ocseditor.insertAtCaret(this,'{cart.quantity}');")
                        . $this->button('Price including tax', "ocseditor.insertAtCaret(this,'{cart.price_including_tax}');")
                        . $this->button('Price excluding tax', "ocseditor.insertAtCaret(this,'{cart.price_excluding_tax}');")
                        . "</p>"
                        . "</fieldset>";
                break;
            case 'customer_groups':
                return "<textarea id=\"" . $prefix . "\" class=\"field\">" . $value . "</textarea>"
                        . "<fieldset class=\"buttons-set\"><legend>" . $this->__('Insert') . "</legend>"
                        . "<p>"
                        . $this->button('Not logged in', "ocseditor.insertAtCaret(this,'NOT LOGGED IN');")
                        . "</p>"
                        . "</fieldset>";
                break;
            case 'tracking_url':
                return "<textarea id=\"" . $prefix . "\" class=\"field\">" . $value . "</textarea>"
                        . "<fieldset class=\"buttons-set\"><legend>" . $this->__('Insert') . "</legend>"
                        . "<p>"
                        . $this->button('Tracking number', "ocseditor.insertAtCaret(this,'{tracking_number}');")
                        . "</p>"
                        . "</fieldset>";
                break;
            case 'destination':
            case 'origin':
                $parsed_value = $this->parseAddressFilter($value);
                $excluding = $parsed_value['excluding'];
                return "<div class=\"address-filters-property\"><p>"
                        . "<input type=\"radio\" class=\"excluding\" id=\"" . $prefix . "-exluding-0\" name=\"" . $prefix . "-exluding\""
                        . " value=\"0\"" . (!$excluding ? " checked=\"checked\"" : '') . " onclick=\"ocseditor.updateCountries(this);\"/>"
                        . "<label for=\"" . $prefix . "-exluding-0\"> " . $this->__('Limit to') . "</label> &nbsp; "
                        . "<input type=\"radio\" class=\"excluding\" id=\"" . $prefix . "-exluding-1\" name=\"" . $prefix . "-exluding\""
                        . " value=\"1\"" . ($excluding ? " checked=\"checked\"" : '') . " onclick=\"ocseditor.updateCountries(this);\"/>"
                        . "<label for=\"" . $prefix . "-exluding-1\"> " . $this->__('Exclude') . "</label></p>"
                        . "<textarea id=\"" . $prefix . "\" class=\"field\">" . $value . "</textarea>"
                        . "<fieldset class=\"buttons-set\"><legend>" . $this->__('Display') . "</legend>"
                        . "<p>"
                        . $this->button('Display original input', "ocseditor.displayCountry('original-value',this,false);")
                        . $this->button('Display corrected names', "ocseditor.displayCountry('full-value',this,false);")
                        . $this->button('Display codes', "ocseditor.displayCountry('compact-value',this,true);")
                        . "</p>"
                        . "</fieldset>"
                        . "<fieldset class=\"buttons-set\"><legend>" . $this->__('Preview') . "</legend>"
                        . "<div class=\"address-filter-list\">" . $this->getAddressFilters($parsed_value) . "</div>"
                        . "</fieldset>"
                        . "</div>"
                ;
                break;
            case '*comment' :
                $lines = explode("\n", trim($value));
                for ($i = 0; $i < count($lines); $i++) {
                    $lines[$i] = preg_replace('/^# ?/', '', $lines[$i]);
                }
                $value = implode("\n", $lines);
                return "<textarea id=\"" . $prefix . "\" class=\"field\">" . $value . "</textarea>";
            default :
                return "<textarea id=\"" . $prefix . "\" class=\"field\">" . $value . "</textarea>";
        }
    }

    private function getAddressFilters($data) {
        $address_filters = array();
        foreach ($data['countries'] as $country) {
            $address_filters[] = new AddressFilter($country);
        }
        return implode('', $address_filters);
    }

    private function parseAddressFilter($address_filter) {
        $output = array(
            'excluding' => false,
            'countries' => array(),
            'original' => $address_filter,
        );

        $address_filter = str_replace(array("\r\n", "\r", "\n"), array(',', ',', ','), $address_filter);

        if (preg_match('# *\* *- *\((.*)\) *#s', $address_filter, $result)) {
            $address_filter = $result[1];
            $output['excluding'] = true;
        }

        $tmp_address_filter_array = explode(',', trim($address_filter));

        $concat = false;
        $concatened = '';
        $address_filter_array = array();
        $i = 0;

        foreach ($tmp_address_filter_array as $address_filter) {
            if ($concat) {
                $concatened .= ',' . $address_filter;
            } else {
                if ($i < count($tmp_address_filter_array) - 1 && preg_match('#\(#', $address_filter)) {
                    $concat = true;
                    $concatened .= $address_filter;
                } else {
                    $address_filter_array[] = $address_filter;
                }
            }
            if (preg_match('#\)#', $address_filter)) {
                $address_filter_array[] = $concatened;
                $concatened = '';
                $concat = false;
            }
            $i++;
        }

        foreach ($address_filter_array as $address_filter) {
            $address_filter = trim($address_filter);
            if (trim($address_filter) != '') {
                if (preg_match('# *([^,(]+) *(-)? *(?:\( *(-)? *(.*)\))? *#s', $address_filter, $result)) {
                    $country_code = $result[1];

                    $region_codes = array();
                    if(isset($result[4])) {
                        $region_codes = explode(',', $result[4]);
                        for ($i = count($region_codes); --$i >= 0;) {
                            $code = trim($region_codes[$i]);
                            $region_codes[$i] = $code;
                        }
                    }

                    $excluding_region = (isset($result[2]) && $result[2] == '-') || (isset($result[3]) && $result[3] == '-');
                    $output['countries'][] = array(
                        'excluding' => $excluding_region,
                        'country_code' => $country_code,
                        'region_codes' => implode(',', $region_codes),
                        'original' => $address_filter,
                    );
                } else {
                    $output['countries'][] = array(
                        'excluding' => null,
                        'country_code' => $address_filter,
                        'region_codes' => null,
                        'original' => $address_filter,
                    );
                }
            }
        }
        return $output;
    }

    private function getRowUI($row, $selected) {
        $row['_ID_']['value'] = isset($row['_ID_']['value']) ? $row['_ID_']['value'] : uniqid('c');
        $row_id = $row['_ID_']['value'];

        if (isset($row['lines'])) {
            $output = "<div id=\"r-" . $row_id . "-container\" class=\"row-container has-error ignored-lines" . ($selected ? ' selected' : '') . "\">"
                    . "<div class=\"row-header\" onclick=\"ocseditor.selectRow('" . $row_id . "');\">"
                    . "<div class=\"row-actions\">"
                    . $this->button('Apply changes', "ocseditor.applyChanges();")
                    . $this->button('Delete', "ocseditor.removeRow(this);", 'delete')
                    . "</div>"
                    . "<div class=\"row-title\">" . $this->__('Ignored lines') . "</div></div>"
                    . "<div class=\"properties-container\"><textarea class=\"field\">" . $row['lines'] . "</textarea></div></div>";
            return $output;
        }

        if (!isset($row['label'])) {
            $row['label']['value'] = $this->__('New shipping method');
        }

        $properties = array(
            'enabled' => 'Enabled',
            'code' => 'Code',
            'label' => 'Label',
            'description' => 'Description',
            'destination' => 'Destination',
            'origin' => 'Origin',
            'conditions' => 'Conditions',
            'fees' => 'Fees',
            'customer_groups' => 'Customer groups',
            'tracking_url' => 'Tracking url',
            '*comment' => 'Comment',
        );

        $label = $row['label']['value'];
        $output = "<div id=\"r-" . $row_id . "-container\" class=\"row-container" . ($selected ? ' selected' : '') . "\">"
                . "<div class=\"row-header\" onclick=\"ocseditor.selectRow('" . $row_id . "');\">"
                . "<div class=\"row-actions\">" . $this->button('Delete', "ocseditor.removeRow(this);", 'delete') . "</div><div class=\"row-title\">" . $label . "</div></div>"
                . "<div class=\"properties-container\">";
        $list = "<ul class=\"properties-list\">";
        $j = 0;
        foreach ($properties as $property_key => $label) {
            $cleaned_property = $this->cleanKey($property_key);
            $value = isset($row[$property_key]) ? trim($row[$property_key]['value']) : '';
            $property = isset($row[$property_key]) ? $row[$property_key] : '';

            $list .= "<li id=\"r-" . $row_id . "-p-" . $cleaned_property . "-item\" class=\"property-item" . ($j == 0 ? ' selected' : '')
                    . (empty($value) ? ' empty' : '')
                    . "\" onclick=\"ocseditor.selectProperty('" . $row_id . "','" . $cleaned_property . "');\">" . $this->__($label) . "</li>";
            $output .= "<div id=\"r-" . $row_id . "-p-" . $cleaned_property . "-container\" class=\"property-container"
                    . ($j == 0 ? ' selected' : '') . "\" property-name=\"" . $property_key . "\">"
                    . "<div class=\"buttons-set\" style=\"text-align:right;\">" . $this->button('Help', "ocseditor.help('property." . $property_key . "');", 'help') . "</div>"
                    . $this->getPropertyHelper($row_id, $property_key, $property) . "</div>";
            $j++;
        }
        foreach ($row as $property_key => $property) {
            if (!isset($properties[$property_key]) && substr($property_key, 0, 1) != '*') {
                $label = $property_key;
                $cleaned_property = $this->cleanKey($property_key);
                $value = isset($row[$property_key]) ? trim($row[$property_key]['value']) : '';
                $list .= "<li id=\"r-" . $row_id . "-p-" . $cleaned_property . "-item\" class=\"property-item" . ($j == 0 ? ' selected' : '')
                        . (empty($value) ? ' empty' : '') . ($cleaned_property == '_ID_' ? ' hide' : '')
                        . "\" onclick=\"ocseditor.selectProperty('" . $row_id . "','" . $cleaned_property . "');\">" . $this->__($label) . "</li>";
                $output .= "<div id=\"r-" . $row_id . "-p-" . $cleaned_property . "-container\" class=\"property-container"
                        . ($j == 0 ? ' selected' : '') . "\" property-name=\"" . $property_key . "\">"
                        . "<div class=\"buttons-set\" style=\"text-align:right;\">" . $this->button('Help', "ocseditor.help('property." . $property_key . "');", 'help') . "</div>"
                        . $this->getPropertyHelper($row_id, $property_key, $property) . "</div>";
                $j++;
            }
        }
        $output .= $list . "</div></div>";
        return $output;
    }

    private function getConfigErrors($config) {
        $script = "ocseditor.resetErrors();";
        foreach ($config as $row) {
            if (isset($row['*messages'])) {
                $error = '';
                foreach ($row['*messages'] as $message) {
                    $error .= "<p>" . $this->__($message) . "</p>";
                }
                if ($error != '') {
                    $script .= "ocseditor.setError('" . $row['_ID_']['value'] . "','','" . $this->jsEscape($error) . "');";
                }
            }
            foreach ($row as $property_key => $property) {
                if (isset($property['messages'])) {
                    $error = '';
                    foreach ($property['messages'] as $message) {
                        $error .= "<p>" . $this->__($message) . "</p>";
                    }
                    if ($error != '') {
                        $script .= "ocseditor.setError('" . $row['_ID_']['value'] . "','" . $property_key . "','"
                                . $this->jsEscape($error
                                        . ($property['value'] != $property['original_value'] ?
                                                "<p>"
                                                . $this->button('Correct', "ocseditor.correct('" . $row['_ID_']['value'] . "','" . $property_key . "','" . $this->jsEscape($property['value']) . "');")
                                                . "</p>" : '')
                                ) . "');";
                    }
                }
            }
        }
        return $script;
    }

    private function loadConfig($input) {
        include_once $this->getIncludingPath('ChronorelaisShippingHelper.php');

        $helper = new ChronorelaisShippingHelper($input);
        $helper->checkConfig();
        $config = $helper->getConfig();

        $output = "<div class=\"buttons-set\">"
                . $this->button('Add a shipping method', "ocseditor.addRow();", 'add')
                . "</div><div class=\"config-container\">";
        $i = 0;
        foreach ($config as &$row) {
            $output .= $this->getRowUI($row, $i == 0);
            $i++;
        }
        $output .= "</div><script type=\"text/javascript\">" . $this->getConfigErrors($config) . "</script>";
        return $output;
    }

    public function indexAction() {
        header('Content-Type: text/html; charset=UTF-8');

        include_once $this->getIncludingPath('countries.inc.php');

        switch ($_POST['what']) {
            case 'open':
                $output = ""
                        // Help page
                        . $this->page('help', "Chronorelais Shipping extension help", '')
                        // Main page
                        . $this->pageHeader("Chronorelais Shipping configuration editor", $this->button('Save', "ocseditor.save();", 'save')
                                . $this->button('Export', "ocseditor.saveToFile();", '')
                                . $this->button('Load', "ocseditor.showConfigLoader();", '')
                                . $this->button('Close', "ocseditor.close();", 'cancel')
                        )
                        . "<div id=\"ocs-editor-config-loader\">"
                        . "<textarea></textarea>"
                        . "<div class=\"buttons-set\">"
                        . $this->button('Load', "ocseditor.loadConfig();", '')
                        . $this->button('Cancel', "ocseditor.hideConfigLoader();", 'cancel')
                        . "</div>"
                        . "</div>"
                        . "<div id=\"ocs-editor-config-container\">" . $this->loadConfig($_POST['input']) . "</div>";
                echo $output;
                exit;
            case 'help':
                echo $this->__('{ocseditor.help.' . $_POST['input'] . '}');
                exit;
            case 'add-row':
                echo $this->getRowUI(array(), true);
                exit;
            case 'load-config':
                echo $this->loadConfig($_POST['config']);
                exit;
            case 'check-config':
                include_once $this->getIncludingPath('ChronorelaisShippingHelper.php');

                $helper = new ChronorelaisShippingHelper(urldecode($_POST['config']));
                $helper->checkConfig();
                print_r($helper->getConfig(), $out);
                $script = $this->getConfigErrors($helper->getConfig());
                break;
            case 'save-to-file':
                include_once $this->getIncludingPath('ChronorelaisShippingHelper.php');

                $helper = new ChronorelaisShippingHelper(urldecode($_POST['config']));
                $formatted_config = $helper->formatConfig(false);
                $this->forceDownload('chronorelais-shipping-config.txt', $formatted_config);
                exit;
            case 'get-address-filters':
                $result = $this->parseAddressFilter($_POST['input']);
                echo $this->getAddressFilters($result);
                exit;
            default:
                break;
        }

        echo "<script type=\"text/javascript\">" . $script . "</script>";
        exit;
    }

    public function checkloginAction() {
        $params = $this->getRequest()->getParams();
        $account_number = $params['account_number'];
        $account_pass = $params['account_pass'];
        $helper = Mage::helper('chronorelais');

        $WSParams = array(
                    'accountNumber' => $account_number,
                    'password' => $account_pass,
                    'depCountryCode' => $helper->getConfigurationShipperInfo('country'),
                    'depZipCode' => $helper->getConfigurationShipperInfo('zipcode'),
                    'arrCountryCode' => $helper->getConfigurationShipperInfo('country'),
                    'arrZipCode' => $helper->getConfigurationShipperInfo('zipcode'),
                    'arrCity' => $helper->getConfigurationShipperInfo('city'),
                    'type' => 'M',
                    'weight' => 1
                );

        $helperWS = Mage::helper('chronorelais/webservice');
        $webservbt = (array)$helperWS->checkLogin($WSParams);

        echo json_encode($webservbt);
    }

    public function checkConflictsAction() {
        // Vérification
        echo '<pre style="background: #000; color: #FFF; padding: 5px 10px; margin-top: 5px; border: 1px solid #0F0; font-family: Courier, Sans Serif; white-space: normal; text-indent: -20px; padding-left: 30px; line-height: 15px;">' . Mage::helper('chronorelais/conflicts')->checkForConflicts() . '</pre>';
    }
}
