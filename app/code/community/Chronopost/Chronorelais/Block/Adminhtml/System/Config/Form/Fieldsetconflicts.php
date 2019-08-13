<?php
class Chronopost_Chronorelais_Block_Adminhtml_System_Config_Form_Fieldsetconflicts
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    /**
     * Return footer html for fieldset
     * Add extra tooltip comments to elements
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getFooterHtml($element)
    {
        $tooltipsExist = false;
        $html = '</tbody></table>';

        $html .= '<div class="chronorelais_conflicts">';
        $html .= '<button onclick="javascript:checkConflicts(); return false;" class="scalable" type="button" id="chronorelais_conflicts">';
        $html .= '    <span>Lancer la vérification</span>';
        $html .= '</button>';
        $html .= '<div id="chronorelais_conflicts_result"></div>';
        $html .= '</div>';

        $html .= '</fieldset>' . $this->_getExtraJs($element, $tooltipsExist);

        $html .= '<script type="text/javascript">' . "\r\n";
        $html .= '//<![CDATA[' . "\r\n";
        $html .= '    function checkConflicts() {' . "\r\n";
        $html .= '        new Ajax.Request(\'' . Mage::getSingleton('adminhtml/url')->getUrl('adminhtml/chronorelais_ajax/checkConflicts') . '\', {' . "\r\n";
        $html .= '            onSuccess: function(data) {' . "\r\n";
        $html .= '                var response = data.responseText;' . "\r\n";
        $html .= '                $(\'chronorelais_conflicts_result\').update(response).show();' . "\r\n";
        $html .= '            }' . "\r\n";
        $html .= '        });' . "\r\n";
        $html .= '    }' . "\r\n";
        $html .= '//]]>' . "\r\n";
        $html .= '</script>' . "\r\n";

        if ($element->getIsNested()) {
            $html .= '</div></td></tr>';
        } else {
            $html .= '</div>';
        }
        return $html;
    }
}
