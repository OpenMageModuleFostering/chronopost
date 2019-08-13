<?php
$installer = $this;
$installer->startSetup();

$this->run("
    ALTER TABLE {$this->getTable('sales_flat_quote_address')} ADD `chronopostsrdv_creneaux_info` text;
	ALTER TABLE {$this->getTable('sales_flat_order_address')} ADD `chronopostsrdv_creneaux_info` text;
");
$this->endSetup();
