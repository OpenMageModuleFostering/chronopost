<?php

$installer = $this;

$installer->startSetup();

$installer->addAttribute('order', 'w_relay_point_code', array(
    'type'              => 'varchar',
    'input'             => 'text',
    'default'           => 0,
    'label'             => 'Relay point',
    'required'          => 0
    )); 

$installer->endSetup();