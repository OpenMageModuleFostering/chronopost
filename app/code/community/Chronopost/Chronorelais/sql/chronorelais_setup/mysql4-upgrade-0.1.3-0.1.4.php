<?php
$installer = $this;
$installer->startSetup();

$installer->run("
	-- DROP TABLE IF EXISTS {$this->getTable('sales_chronopost_order_export_status')};
	CREATE TABLE {$this->getTable('sales_chronopost_order_export_status')} (
	  `order_id` int(10) unsigned NOT NULL,
	  `livraison_le_samedi` varchar(10) NOT NULL DEFAULT 'Yes',
	  UNIQUE KEY `order_id` (`order_id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
");

$installer->endSetup(); 