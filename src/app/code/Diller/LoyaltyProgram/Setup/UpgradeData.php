<?php

namespace Diller\LoyaltyProgram\Setup;

use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetup;
use Magento\Customer\Model\Customer;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface{

    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory, Config $eavConfig)
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig       = $eavConfig;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            \Magento\Customer\Model\Customer::ENTITY,
            'diller_member_id',
            [
                'type'         => 'varchar',
                'label'        => 'Diller Member ID',
                'input'        => 'text',
                'required'     => false,
                'visible'      => false,
                'user_defined' => false,
                'position'     => 999,
                'system'       => 0,
            ]
        );
        $dillerMemberId = $this->eavConfig->getAttribute(Customer::ENTITY, 'diller_member_id');
        $dillerMemberId->save();
    }
}
