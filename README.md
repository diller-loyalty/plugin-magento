# Magento 2 Module

## Description

** Feature list **

- Membership status (My Account Dashboard)
- Coupons, stamps (My Account Dashboard)
- Subscription form for joining the loyalty program
- Multisite compatible
- Multi-language support (NO, EN, and more to come)
- GDPR compliance

---

##  HOW TO OBTAIN THE CREDENTIALS TO CONNECT MY STORE

Get in contact with our awesome support and customer service team support@diller.no and they'll get you the store PIN code and API key to connect your store.
 
---

## Composer Installation

** Install Diller into Magento 2.4.4 and above **
```
composer require diller-loyalty/magento-module:dev-main
```

** Install Diller into Magento 2.4.3 **
```
composer require diller-loyalty/magento-module:2.4.3.x-dev
```

After adding Diller Module
```
bin/magento module:enable Diller_LoyaltyProgram
bin/magento setup:upgrade
setup:di:compile
```

---

## Local Testing

We use DockMage images to test our module.
Fill the image and composer with the correct values depending with version as mencion above.

docker-compose.yml
```
version: '3.5'
name: 'magento-plugin'
services:
  magento2:
    image: inluxc/dockmage:2.4.6
    restart: unless-stopped
    ports:
      - '80:80'
    volumes:
      # Mount Your Magento Composer Credentials
      - ./auth.json:/var/www/html/auth.json
      # Mount Module Init Module Commands
      - ./custom_module.sh:/var/www/boot_end.sh
```

custom_module.sh
```
#!/bin/bash
echo "Start Module Installation"
composer require diller-loyalty/magento-module:dev-main
bin/magento module:enable Diller_LoyaltyProgram
bin/magento setup:upgrade
bin/magento setup:di:compile
chmod 777 -R var/ pub/
echo "Installation Complete"
```