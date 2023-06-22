<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Model\Config\Source;

use Diller\LoyaltyProgram\Helper\Data as DillerLoyaltyHelper;
use Magento\Framework\Data\OptionSourceInterface;

class StoreDepartments implements OptionSourceInterface{

    protected DillerLoyaltyHelper $loyaltyHelper;

    public function __construct(DillerLoyaltyHelper $loyaltyHelper) {
        $this->loyaltyHelper = $loyaltyHelper;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array{
        $result = [];
        if($this->loyaltyHelper->getModuleStatus()){
            $storeDepartments = $this->loyaltyHelper->getStoreDepartments();
            if(is_array($storeDepartments)){
                foreach ($storeDepartments as $department){
                    $result[] = array(
                        "value" => $department['id'],
                        "label" => $department['name']
                    );
                }
            }
        }
        return $result;
    }
}
