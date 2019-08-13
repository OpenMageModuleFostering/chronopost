<?php
class Chronopost_Chronorelais_Block_Import_Form extends Mage_Adminhtml_Block_Widget
{

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('chronorelais/import/form.phtml');
    }

}
