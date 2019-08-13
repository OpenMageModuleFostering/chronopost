<?php

class Chronopost_Chronorelais_Block_Sales_Bordereau_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
        $this->setTemplate('chronorelais/bordereau/grid.phtml'); /* Pour ajouter la phrase d'impression des bordereaux enxemplaires */
        $this->setId('sales_order_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
    }

    /**
     * Retrieve collection class
     *
     * @return string
     */
    protected function _getCollectionClass() {
        return 'sales/order_grid_collection';
    }

    protected function _prepareCollection() {
        $_chronopost_deliver_saturday = Mage::helper('chronorelais')->getConfigData('carriers/chronopost/deliver_on_saturday');
        $_chronorelais_deliver_saturday = Mage::helper('chronorelais')->getConfigData('carriers/chronorelais/deliver_on_saturday');
        if ($_chronopost_deliver_saturday == 1) {
            $_chronopost_deliver_saturday = 'Yes';
        } else {
            $_chronopost_deliver_saturday = 'No';
        }
        if ($_chronorelais_deliver_saturday == 1) {
            $_chronorelais_deliver_saturday = 'Yes';
        } else {
            $_chronorelais_deliver_saturday = 'No';
        }

        $trackNumberFieldName = Mage::helper('chronorelais')->getTrackNumberFieldName();

        $collection = Mage::getResourceModel($this->_getCollectionClass());
        $collection->getSelect()->joinLeft(array('og' => $collection->getTable('sales/order')), 'main_table.entity_id = og.entity_id', array('CASE LOWER(SUBSTRING_INDEX(og.shipping_method,"_","1")) WHEN "chronoexpress" THEN "Chrono Express" WHEN "chronorelais" THEN "Chrono Relais" ELSE CONCAT(UCASE(SUBSTRING(SUBSTRING_INDEX(og.shipping_method,"_","1"),1,1)),LOWER(SUBSTRING(SUBSTRING_INDEX(og.shipping_method,"_","1"), 2))) END as chrono_shipping_method', 'og.total_qty_ordered'));
        $collection->getSelect()->joinLeft(array('osg' => $collection->getTable('sales/shipment_grid')), 'main_table.entity_id = osg.order_id', array(new Zend_Db_Expr('if(isNull(osg.increment_id) , "--" , GROUP_CONCAT(DISTINCT osg.increment_id SEPARATOR \', \')) as shipment_increment_id', 'GROUP_CONCAT(DISTINCT osg.created_at SEPARATOR \', \') as shipment_created_at')));
        $collection->getSelect()->joinLeft(array('ost' => $collection->getTable('sales/shipment_track')), 'main_table.entity_id = ost.order_id', array(new Zend_Db_Expr('if(isNull(ost.'.$trackNumberFieldName.') , "--" , GROUP_CONCAT(DISTINCT ost.'.$trackNumberFieldName.' SEPARATOR \', \')) as track_number', 'if(isNull(ost.title) , "--" , GROUP_CONCAT(DISTINCT ost.title SEPARATOR \', \')) as title')));
        $collection->getSelect()->joinLeft(array('oes' => Mage::getSingleton('core/resource')->getTableName('sales_chronopost_order_export_status')), 'main_table.entity_id = oes.order_id', array(new Zend_Db_Expr("if(isNull(oes.livraison_le_samedi), CASE LOWER(SUBSTRING_INDEX(og.shipping_method,'_','1')) WHEN 'chronopost' THEN '$_chronopost_deliver_saturday' WHEN 'chronorelais' THEN '$_chronorelais_deliver_saturday' WHEN 'chronoexpress' THEN '--' ELSE 'No' END, oes.livraison_le_samedi) as livraison_le_samedi")));
        $collection->getSelect()->where('og.shipping_method LIKE "chronorelais%" OR og.shipping_method LIKE "chronopost%" OR og.shipping_method LIKE "chronoexpress%"');
        $collection->getSelect()->group('main_table.entity_id');
        $sql = $collection->getSelectSql(true);
        $collection->getSelect()->reset()->from(
                array('e' => new Zend_Db_Expr("({$sql})")), array('e' => "*")
        );

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {

        $this->addColumn('real_order_id', array(
            'header' => Mage::helper('sales')->__('Order #'),
            'width' => '100px',
            'type' => 'text',
            'index' => 'increment_id',
            'filter' => false,
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('sales')->__('Order Date'),
            'index' => 'created_at',
            'type' => 'datetime',
            'filter' => false,
        ));

        $this->addColumn('shipment_increment_id', array(
            'header' => Mage::helper('sales')->__('Shipment #'),
            'index' => 'shipment_increment_id',
            'type' => 'text',
            'width' => '100px',
            'filter' => false,
        ));

        $this->addColumn('shipment_created_at', array(
            'header' => Mage::helper('sales')->__('Date Shipped'),
            'index' => 'shipment_created_at',
            'type' => 'datetime',
            'filter' => false,
        ));

        $this->addColumn('shipping_name', array(
            'header' => Mage::helper('sales')->__('Ship to Name'),
            'index' => 'shipping_name',
            'filter' => false,
        ));

        $this->addColumn('total_qty_ordered', array(
            'header' => Mage::helper('sales')->__('Total Qty'),
            'index' => 'total_qty_ordered',
            'type' => 'number',
            'filter' => false,
        ));

        $this->addColumn('track_number', array(
            'header' => Mage::helper('sales')->__('Tracking'),
            'index' => 'track_number',
            'filter' => false,
        ));

        $this->addColumn('chrono_shipping_method', array(
            'header' => Mage::helper('sales')->__('Mode de transport'),
            'index' => 'chrono_shipping_method',
            'type' => 'text',
            'filter' => false,
        ));

        $this->addColumn('status', array(
            'header' => Mage::helper('sales')->__('Status'),
            'index' => 'status',
            'type'  => 'options',
            'width' => '90px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
        ));

        if (Mage::helper('chronorelais')->isSendingDay()) {
            $this->addColumn('livraison_le_samedi', array(
                'header' => Mage::helper('sales')->__('Livraison le Samedi'),
                'index' => 'livraison_le_samedi',
                'width' => '100px',
                'class' => 'a-center',
                'filter' => false,
            ));
        }

        return parent::_prepareColumns();
    }

    public function getRowUrl($row) {
        if (!Mage::getSingleton('admin/session')->isAllowed('sales/order/shipment')) {
            return false;
        }

        return $this->getUrl('adminhtml/sales_order/view', array(
                    'order_id' => $row->getId(),
                        )
        );
    }

    protected function _prepareMassaction() {

        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('order_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        $this->getMassactionBlock()->addItem('print_bordereau', array(
                'label' => Mage::helper('chronorelais')->__('Imprimer le bordereau'),
                'url' => $this->getUrl('*/*/massPrintBordereau', array('_current' => true)),
                'selected' => true,
            ));

        return $this;
    }

    public function getGridUrl() {
        return $this->getUrl('*/*/*', array('_current' => true));
    }
}