<?php
class Chronopost_Chronorelais_Block_Export_Orders_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('chronorelais_export_order_grid');
        $this->setUseAjax(false);
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Retrieve collection class
     *
     * @return string
     */
    protected function _getCollectionClass()
    {
        return 'sales/order_grid_collection';
    }

    protected function _prepareCollection()
    {
		$_chronopost_deliver_saturday = Mage::helper('chronorelais')->getConfigData('carriers/chronopost/deliver_on_saturday');
		$_chronorelais_deliver_saturday = Mage::helper('chronorelais')->getConfigData('carriers/chronorelais/deliver_on_saturday');
		if($_chronopost_deliver_saturday==1) {
            $_chronopost_deliver_saturday = 'Yes';
        } else {
            $_chronopost_deliver_saturday = 'No';
        }
		if($_chronorelais_deliver_saturday==1) {
            $_chronorelais_deliver_saturday = 'Yes';
        } else {
            $_chronorelais_deliver_saturday = 'No';
        }

		$collection = Mage::getResourceModel($this->_getCollectionClass());
        $collection->join('order', 'main_table.entity_id = order.entity_id', 'shipping_description');
        $collection->join('order_payment', 'main_table.entity_id = order_payment.parent_id', 'method');
		$collection->getSelect()->joinLeft(array('oes' => Mage::getSingleton('core/resource')->getTableName('sales_chronopost_order_export_status')), 'main_table.entity_id = oes.order_id', array(new Zend_Db_Expr("if(isNull(oes.livraison_le_samedi), CASE LOWER(SUBSTRING_INDEX(order.shipping_method,'_','1')) WHEN 'chronopost' THEN '$_chronopost_deliver_saturday' WHEN 'chronorelais' THEN '$_chronorelais_deliver_saturday' WHEN 'chronoexpress' THEN '--' ELSE 'No' END, oes.livraison_le_samedi) as livraison_le_samedi")));
		$collection->getSelect()->where('order.shipping_method LIKE "chronorelais%" OR order.shipping_method LIKE "chronopost%" OR order.shipping_method LIKE "chronoexpress%"');

		$this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _addColumnFilterToCollection($column)
    {
        if ($this->getCollection()) {
            $field = ( $column->getFilterIndex() ) ? $column->getFilterIndex() : $column->getIndex();
            if ($column->getFilterConditionCallback()) {
                call_user_func($column->getFilterConditionCallback(), $this->getCollection(), $column);
            } else {
                $cond = $column->getFilter()->getCondition();
                if ($field && isset($cond)) {
					// Le champ status est ambigu, il faut donc spécifier sa table lors de l'ajout pour filtrage
                    $this->getCollection()->addFieldToFilter($field == "status" ? "main_table.status" : $field, $cond);
                }
            }
        }
        return $this;
    }

    protected function _prepareColumns()
    {

        $this->addColumn('real_order_id', array(
            'header'=> Mage::helper('sales')->__('Order #'),
            'width' => '80px',
            'type'  => 'text',
            'index' => 'increment_id',
            'filter'    => false,
        ));

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', array(
                'header'    => Mage::helper('sales')->__('Purchased From (Store)'),
                'index'     => 'store_id',
                'type'      => 'store',
                'store_view'=> true,
                'display_deleted' => true,
	            'filter'    => false,
            ));
        }

        $this->addColumn('created_at', array(
            'header' => Mage::helper('sales')->__('Purchased On'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '100px',
            'filter'    => false,
        ));

        $this->addColumn('billing_name', array(
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
            'filter'    => false,
        ));

        $this->addColumn('shipping_name', array(
            'header' => Mage::helper('sales')->__('Ship to Name'),
            'index' => 'shipping_name',
            'filter'    => false,
        ));

        $this->addColumn('base_grand_total', array(
            'header' => Mage::helper('sales')->__('G.T. (Base)'),
            'index' => 'base_grand_total',
            'type'  => 'currency',
            'currency' => 'base_currency_code',
            'filter'    => false,
        ));

        $this->addColumn('grand_total', array(
            'header' => Mage::helper('sales')->__('G.T. (Purchased)'),
            'index' => 'grand_total',
            'type'  => 'currency',
            'currency' => 'order_currency_code',
            'filter'    => false,
        ));

		$this->addColumn('shipping_description', array(
            'header'=> Mage::helper('sales')->__('Shipping Method'),
            'width' => '80px',
            'type'  => 'text',
            'index' => 'shipping_description',
            'filter'    => false,
			'truncate'      => 30,
			'escape'        => true,
        ));

		$this->addColumn('payment', array(
			'header' => Mage::helper('sales')->__('Mode de Paiement'),
			'index' => 'method',
			'width' 	=> '100px',
			'type'      => 'text',
            'filter'    => false,
		));

		if(Mage::helper('chronorelais')->isSendingDay()) {
			$this->addColumn('livraison_le_samedi', array(
				'header' 	=> Mage::helper('sales')->__('Livraison le Samedi'),
				'index'	 	=> 'livraison_le_samedi',
				'width'     => '100px',
				'class'		=> 'a-center',
				'filter'    => false,
			));
		}

        $this->addColumn('status', array(
            'header' => Mage::helper('sales')->__('Status'),
            'index' => 'status',
            'type'  => 'options',
            'width' => '70px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
        ));

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            $this->addColumn('action',
                array(
                    'header'    => Mage::helper('sales')->__('Action'),
                    'width'     => '50px',
                    'type'      => 'action',
                    'getter'     => 'getId',
                    'actions'   => array(
                        array(
                            'caption' => Mage::helper('sales')->__('View'),
                            'url'     => array('base'=>'adminhtml/sales_order/view'),
                            'field'   => 'order_id'
                        )
                    ),
                    'filter'    => false,
                    'sortable'  => false,
                    'index'     => 'stores',
                    'is_system' => true,
            ));
        }

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction111()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('order_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        $this->getMassactionBlock()->addItem('export_css', array(
             'label'=> Mage::helper('chronorelais')->__('Export CSS'),
             'url'  => $this->getUrl('*/*/exportcss'),
        ));
        $this->getMassactionBlock()->addItem('export_cso', array(
             'label'=> Mage::helper('chronorelais')->__('Export CSO'),
             'url'  => $this->getUrl('*/*/exportcso'),
        ));

        return $this;
    }

    protected function _prepareMassaction()
	{
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('order_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

		if(Mage::helper('chronorelais')->isSendingDay()) {
			$shipping = array(
					'Yes' => Mage::helper('chronorelais')->__('Yes'),
					'No'  => Mage::helper('chronorelais')->__('No'));
			$this->getMassactionBlock()->addItem('shipping', array(
					'label'    => Mage::helper('chronorelais')->__('Livraison le Samedi'),
					'url'      => $this->getUrl('*/*/massLivraisonSamediStatus', array('_current'=>true)),
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

        $export = array(
                'css' => Mage::helper('chronorelais')->__('CSS Format'),
                'cso' => Mage::helper('chronorelais')->__('CSO Format'));
        array_unshift($export, array('label'=>'', 'value'=>''));
        $this->getMassactionBlock()->addItem('export', array(
                'label'=> Mage::helper('chronorelais')->__('Export'),
                'url'  => $this->getUrl('*/*/massExport', array('_current'=>true)),
                'additional' => array(
                        'visibility' => array(
                                'name' => 'format',
                                'type' => 'select',
                                'class' => 'required-entry',
                                'label' => Mage::helper('chronorelais')->__('Format'),
                                'values' => $export
                        )
                )
        ));
        return $this;
    }

    public function getRowUrl($row)
    {
        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            return $this->getUrl('adminhtml/sales_order/view', array('order_id' => $row->getId()));
        }
        return false;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/*', array('_current'=>true));
    }

    public function getAdditionalJavaScript()
    {
      echo "
var element = document.getElementById('chronorelais_export_order_grid_massaction-select');
element.selectedIndex = 1;
if ('fireEvent' in element)
    element.fireEvent('onchange');
else
{
    var evt = document.createEvent('HTMLEvents');
    evt.initEvent('change', false, true);
    element.dispatchEvent(evt);
}
";
    }

}
