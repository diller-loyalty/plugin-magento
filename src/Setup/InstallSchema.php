<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Diller\LoyaltyProgram\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * InstallSchema mock class.
 */
class InstallSchema implements InstallSchemaInterface{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context){
        $installer = $setup;
        $installer->startSetup();

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'diller_consent',
            [
                'type' => 'boolean',
                'nullable' => false,
                'comment' => 'Diller loyalty consent',
                'default' => 0
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'diller_consent',
            [
                'type' => 'boolean',
                'nullable' => false,
                'comment' => 'Diller loyalty consent',
                'default' => 0
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'diller_consent',
            [
                'type' => 'boolean',
                'nullable' => false,
                'comment' => 'Diller loyalty consent',
                'default' => 0
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'diller_order_history_consent',
            [
                'type' => 'boolean',
                'nullable' => false,
                'comment' => 'Diller loyalty order history consent',
                'default' => 0
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'diller_order_history_consent',
            [
                'type' => 'boolean',
                'nullable' => false,
                'comment' => 'Diller loyalty order history consent',
                'default' => 0
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'diller_order_history_consent',
            [
                'type' => 'boolean',
                'nullable' => false,
                'comment' => 'Diller loyalty order history consent',
                'default' => 0
            ]
        );

        $setup->endSetup();
    }
}
