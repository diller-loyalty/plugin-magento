#!/bin/bash
echo "Start Module Installation"
echo "-------------------------"
bin/magento module:enable Diller_LoyaltyProgram
composer require diller-loyalty/php-sdk:v0.1.12
composer require giggsey/libphonenumber-for-php
bin/magento setup:upgrade
bin/magento setup:di:compile
chmod 777 -R var/ pub/
echo "---------------------"
echo "Installation Complete"