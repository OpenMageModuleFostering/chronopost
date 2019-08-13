<?php
$installer = $this;
$installer->startSetup();

$this->run("
	ALTER TABLE {$this->getTable('sales_flat_shipment_track')} MODIFY `chrono_reservation_number` longtext;
");
$this->endSetup();
