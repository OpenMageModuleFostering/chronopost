<?php

class Chronopost_Chronorelais_Block_Sales_Shipment_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
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
            'column_css_class' => 'shipment_ids'
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

        if (Mage::helper('chronorelais')->isSendingDay()) {
            $this->addColumn('livraison_le_samedi', array(
                'header' => Mage::helper('sales')->__('Livraison le Samedi'),
                'index' => 'livraison_le_samedi',
                'width' => '100px',
                'class' => 'a-center',
                'filter' => false,
            ));
        }

        $this->addColumn('etiquette_expedition', array(
            'header' => Mage::helper('chronorelais')->__('Impression étiquette expédition'),
            'type' => 'action',
            'getter' => 'getId',
            'column_css_class' => 'etiquette_expedition',
            'actions' => array(
                array(
                    'caption' => Mage::helper('chronorelais')->__('Expédition'),
                    'url' => array(
                        'base' => '*/*/print'
                    ),
                    'field' => 'order_id'
                )
            ),
            'filter' => false,
            'sortable' => false,
            'index' => 'stores'
        ));

        $this->addColumn('etiquette_retour', array(
            'header' => Mage::helper('chronorelais')->__('Impression étiquette retour'),
            'type' => 'action',
            'getter' => 'getId',
            'column_css_class' => 'etiquette_retour',
            'actions' => array(
                array(
                    'caption' => Mage::helper('chronorelais')->__('Retour SAV'),
                    'url' => array(
                        'base' => '*/*/printEtiquetteRetour'
                    ),
                    'field' => 'order_id'
                )
            ),
            'filter' => false,
            'renderer'  => 'Chronopost_Chronorelais_Block_Sales_Shipment_Grid_Renderer_Retoursav',
            'sortable' => false,
            'index' => 'stores'
        ));

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

        $this->getMassactionBlock()->addItem('cancel', array(
            'label' => Mage::helper('chronorelais')->__('Annuler les étiquettes'),
            'url' => $this->getUrl('*/*/cancelMass', array('_current' => true)),
        ));

        $cmd = Mage::helper('chronorelais')->getConfigData('chronorelais/shipping/gs_path')." -v";
        $res_shell = shell_exec($cmd);
        if($res_shell !== null) {
            $this->getMassactionBlock()->addItem('print', array(
                'label' => Mage::helper('chronorelais')->__('Imprimer les étiquettes'),
                'url' => $this->getUrl('*/*/printMass', array('_current' => true)),
            ));
        }

        if (Mage::helper('chronorelais')->isSendingDay()) {

            $shipping = array(
                'Yes' => Mage::helper('chronorelais')->__('Yes'),
                'No' => Mage::helper('chronorelais')->__('No'));
            $this->getMassactionBlock()->addItem('shipping', array(
                'label' => Mage::helper('chronorelais')->__('Livraison le Samedi'),
                'url' => $this->getUrl('*/*/massLivraisonSamediStatus', array('_current' => true)),
                'additional' => array(
                    'visibility' => array(
                        'name' => 'status',
                        'type' => 'select',
                        'class' => 'required-entry',
                        'style' => 'width:80px',
                        'label' => Mage::helper('chronorelais')->__('Status'),
                        'values' => $shipping
                    )
                )
            ));
        }
        return $this;
    }

    public function getGridUrl() {
        return $this->getUrl('*/*/*', array('_current' => true));
    }

    public function getAdditionalJavaScript() {
        echo "
            var tds = $$('#sales_order_grid_table tr td.shipment_ids');
        tds.each(function(item) {
        var chaine = item.innerHTML.replace(/^\s+/g,'').replace(/\s+$/g,'');
        var lienExpedition = $(item).next('.etiquette_expedition').down('a');
        var lienRetour = $(item).next('.etiquette_retour').down('a');
        if (chaine.split(', ').length > 1) {
          var contentExpedition = '';
          var contentRetour = '';
          var numbers = chaine.split(', ');
          for (var i=0; i<numbers.length; i++) {
            contentExpedition += '<a href=\"' + lienExpedition.href.replace(/\/order_id\/\d+\//, '/shipment_increment_id/' + numbers[i] + '/') + '\">Exp&eacute;dition ' + numbers[i] + '</a><br />';
            contentRetour += '<a href=\"' + lienRetour.href.replace(/\/order_id\/\d+\//, '/shipment_increment_id/' + numbers[i] + '/') + '\" >Retour SAV ' + numbers[i] + '</a><br />';
          }
          lienExpedition.up().innerHTML = contentExpedition;
          if(typeof lienRetour !== 'undefined')
          {
            lienRetour.up().innerHTML = contentRetour;
          }
        }
        else if(chaine.length > 2) {
            lienExpedition.up().innerHTML = '<a href=\"' + lienExpedition.href.replace(/\/order_id\/\d+\//, '/shipment_increment_id/' + chaine + '/') + '\">Exp&eacute;dition</a><br />';
            if(typeof lienRetour !== 'undefined')
            {
                lienRetour.up().innerHTML = '<a href=\"' + lienRetour.href.replace(/\/order_id\/\d+\//, '/shipment_increment_id/' + chaine + '/') + '\" >Retour SAV</a><br />';
            }
        }
        else {
            if(typeof lienRetour !== 'undefined')
            {
                lienRetour.up().innerHTML = '';
            }
        }
      });";
    }

}
