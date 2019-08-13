<?php
abstract class Chronopost_Chronorelais_Model_Carrier_AbstractChronorelais extends Varien_Object
{
    protected $_code;
    protected $_rates = null;
    protected $_numBoxes = 1;

    /**
     * Whether this carrier has fixed rates calculation
     *
     * @var bool
     */
    protected $_isFixed = false;

    const HANDLING_TYPE_PERCENT = 'P';
    const HANDLING_TYPE_FIXED = 'F';

    const HANDLING_ACTION_PERPACKAGE = 'P';
    const HANDLING_ACTION_PERORDER = 'O';

    /**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = array();

    public function __construct()
    {
    }
}
?>