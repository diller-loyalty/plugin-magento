#!/bin/bash

# Install our Custom Module
echo "Start Module Instalation"
composer install Diller_LoyaltyProgram
bin/magento module:enable Diller_LoyaltyProgram
bin/magento setup:upgrade
bin/magento setup:di:compile
chmod 777 -R var/ pub/
echo "Instalation Complete"