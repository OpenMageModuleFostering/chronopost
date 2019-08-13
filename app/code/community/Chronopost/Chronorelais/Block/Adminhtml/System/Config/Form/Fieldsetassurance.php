<?php
class Chronopost_Chronorelais_Block_Adminhtml_System_Config_Form_Fieldsetassurance
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

        $html .= '<div class="chronorelais_assurance">';
            $html .= '<div class="chronorelais_assurance_picto">';
                $html .= '<img src="'.Mage::getDesign()->getSkinUrl('chronorelais/picto.gif').'" id="chronorelais_assurance_picto_img"/>';
            $html .= '</div>';
            $html .= '<div class="chronorelais_assurance_infobulle" id="chronorelais_assurance_infobulle" style="display:none;">';
                $html .= '<div class="chronorelais_assurance_infobulle_header">';
                    $html .= '<div class="chronorelais_assurance_infobulle_header_title">POP-UP INFORMATION</div>';
                    $html .= '<div class="chronorelais_assurance_infobulle_header_close" id="chronorelais_assurance_infobulle_header_close"><button class="scalable close" type="button"><span>Close</span></button></div>';
                    $html .= '<div class="clear"></div>';
                $html .= '</div>';
                $html .= '<div class="chronorelais_assurance_infobulle_content">';
                    $html .= 'En activant cette option, pour chaque colis dépassant le montant que vous renseignez, votre envoi sera assuré à hauteur du montant des articles de ce dernier (maximum 20 000€).';
                $html .= '</div>';
            $html .= '</div>';
        $html .= '</div>';

        $html .= '</fieldset>' . $this->_getExtraJs($element, $tooltipsExist);

        if ($element->getIsNested()) {
            $html .= '</div></td></tr>';
        } else {
            $html .= '</div>';
        }
        return $html;
    }
}
