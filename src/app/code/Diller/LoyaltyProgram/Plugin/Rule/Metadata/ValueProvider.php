<?php

namespace Diller\LoyaltyProgram\Plugin\Rule\Metadata;

class ValueProvider {
    public function afterGetMetadataValues(\Magento\SalesRule\Model\Rule\Metadata\ValueProvider $subject, $result) {
        $applyOptions = [
            'label' => __('Diller Loyalty'),
            'value' => [
                [
                    'label' => 'Stamp card discount',
                    'value' => 'loyalty_stamp_card',
                ]
            ],
        ];
        array_push($result['actions']['children']['simple_action']['arguments']['data']['config']['options'], $applyOptions);
        return $result;
    }
}