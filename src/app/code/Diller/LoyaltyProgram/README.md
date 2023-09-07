## Magento 2 Module

### INSTALL DILLER LOYALTY ON YOUR MAGENTO

Run the following commands in your Magento directory

```
composer config --global --auth gitlab-token.gitlab.com YOUR_TOKEN_HERE
composer config repositories.diller composerhttps://gitlab.com/api/v4/group/55737584/-/packages/composer/
composer require diller-loyalty/magento-module --prefer-source
bin/magento module:enable Diller_LoyaltyProgram
bin/magento setup:upgrade
bin/magento setup:di:compile
```
Replace YOUR_TOKEN_HERE with the access token we gave you.<br>
<strong>This token is specific to you, make sure you store it safely!</strong>