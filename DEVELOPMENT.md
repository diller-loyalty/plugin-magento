# Development

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
      # Mount Module Code to Container
      - ./src:/var/www/html/app/code/Diller/LoyaltyProgram
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