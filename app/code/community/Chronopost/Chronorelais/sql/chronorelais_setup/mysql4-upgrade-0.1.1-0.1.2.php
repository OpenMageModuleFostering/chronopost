<?php 
$installer = $this;
$installer->startSetup();

$this->run("
	ALTER TABLE {$this->getTable('sales_flat_order_address')} ADD `w_relay_point_code` varchar(255);
");
$this->endSetup();
