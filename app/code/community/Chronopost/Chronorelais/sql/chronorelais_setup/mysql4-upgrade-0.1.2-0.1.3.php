<?php 
$installer = $this;
$installer->startSetup();

$this->run("
	ALTER TABLE {$this->getTable('sales_flat_shipment_track')} ADD `chrono_reservation_number` varchar(255);
");
$this->endSetup();
