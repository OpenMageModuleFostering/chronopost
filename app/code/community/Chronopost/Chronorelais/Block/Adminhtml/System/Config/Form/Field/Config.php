<?php

class Chronopost_Chronorelais_Block_Adminhtml_System_Config_Form_Field_Config extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	private static $JS_INCLUDED = false;

	private function label($input) {
		return str_replace(array("\r\n","\r","\n","'"),array("\\n","\\n","\\n","\\'"),$this->__($input));
	}

	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
	{
		$output = '';
		if (!self::$JS_INCLUDED) {
			$include_path = Mage::getBaseUrl('js').'chronopost/chronorelais';
			$output = "<script type=\"text/javascript\" src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.4.3/jquery.min.js\"></script>\n"
				."<script type=\"text/javascript\" src=\"".$include_path."/ocseditor.js?t=".time()."\"></script>\n"
				."<script type=\"text/javascript\">\n"
				."//<![CDATA[\n"
				."jQuery.noConflict();\n"
				."var ocseditor = new OCSEditor({\n"
				."ajax_url: '".$this->getUrl('adminhtml/chronorelais_ajax')."?isAjax=true',\n"
				."form_key: FORM_KEY,\n"
				."menu_item_dissociate_label: '".$this->label('Dissociate')."',\n"
				."menu_item_remove_label: '".$this->label('Remove')."',\n"
				."menu_item_edit_label: '".$this->label('Edit')."',\n"
				."prompt_new_value_label: '".$this->label('Enter the new value:')."',\n"
				."default_row_label: '".$this->label('[No label]')."',\n"
				."loading_label: '".$this->label('Loading...')."'\n"
				."});\n"
				."//]]>\n"
				."</script>\n"
				."<link type=\"text/css\" href=\"".$include_path."/ocseditor.css?t=".time()."\" rel=\"stylesheet\" media=\"all\"/>\n"
			;
			self::$JS_INCLUDED = true;
		}

		return $output
			.'<div style="margin-bottom:1px;"><button type="button" class="scalable" onclick="ocseditor.open(this);"><span>'.$this->__("Open editor").'</span></button></div>'
			.$element->getElementHtml().'<br/>'
		;
	}
}
