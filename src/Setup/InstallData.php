<?php

namespace Diller\LoyaltyProgram\Setup;

use Magento\Eav\Model\Config;
use Magento\Customer\Model\Customer;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
class InstallData implements InstallDataInterface{

    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory, Config $eavConfig)
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig       = $eavConfig;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        // member_id field to customer
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            Customer::ENTITY,
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
