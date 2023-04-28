## Magento 2 Module

To be able to test the module with the docker-compose attached, a docker volume must be created with magento files.

More details and instructions will be added soon

Some helpful dev commands

**// TO INSTALL MAGENTO**
php bin/magento setup:install --base-url="http://magento.test/" --db-host="mysql" --db-name="magento" --db-user="diller" --db-password="diller123" --admin-firstname="Diller" --admin-lastname="Admin" --admin-email="tony@diller.no" --admin-user="diller" --admin-password="diller123" --use-rewrites="1" --backend-frontname="admin" --db-prefix=mage_

**// CHECK MODULES STATUS**
<br>bin/magento module:status

**// TO ENABLE/DISABLE SPECIFIC MODULE(S)**
<br>bin/magento module:enable/disable {Magento_Elasticsearch,Magento_Elasticsearch6,Magento_Elasticsearch7}
<br><br>
**// AFTER CHANGING ANY MODULE STATUS**
<br>bin/magento setup:upgrade && bin/magento setup:static-content:deploy -f

**// AND**
<br>bin/magento setup:di:compile && chmod 777 -R var/ pub/

**// CLEAN CACHE**
<br>bin/magento cache:clean