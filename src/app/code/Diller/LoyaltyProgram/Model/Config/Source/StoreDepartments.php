<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Model\Config\Source;

use Diller\LoyaltyProgram\Helper\Data;
use Magento\Framework\Data\OptionSourceInterface;

class StoreDepartments implements OptionSourceInterface{

    /**
     * @var Data
     */
    protected Data $loyaltyHelper;

    /**
     * @param Data $loyaltyHelper
     */
    public function __construct(Data $loyaltyHelper) {
        $this->loyaltyHelper = $loyaltyHelper;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array{
        $storeDepartments = $this->loyaltyHelper->getStoreDepartments();
        $result = [];
        if(is_array($storeDepartments)){
            foreach ($storeDepartments as $department){
                $result[] = array(
                    "value" => $department['id'],
                    "label" => $department['name']
                );
            }
        }
        return $result;
    }
}
