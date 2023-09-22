## Magento 2 Module

To be able to test the module with the docker-compose attached, a docker volume must be created with magento files.

More details and instructions will be added soon

Some helpful dev commands

**// TO INSTALL MAGENTO**
```
bin/magento setup:install --base-url="http://magento.test/" --db-host="mysql" --db-name="magento" --db-user="diller" --db-password="diller123" --admin-firstname="Diller" --admin-lastname="Admin" --admin-email="tony@diller.no" --admin-user="diller" --admin-password="diller123" --use-rewrites="1" --backend-frontname="admin" --db-prefix=mage_
```

**// CHECK MODULES STATUS**
```
bin/magento module:status
```

**// TO ENABLE/DISABLE SPECIFIC MODULE(S)**
```
bin/magento module:enable/disable {Magento_Elasticsearch,Magento_Elasticsearch6,Magento_Elasticsearch7}
```

**// AFTER CHANGING ANY MODULE STATUS**
```
bin/magento setup:upgrade
bin/magento setup:di:compile
chmod 777 -R var/ pub/
```

**// CLEAN CACHE**
```
bin/magento cache:clean
bin/magento cache:flush
```

**// (IF YOU HAVE ISSUES WITH ELASTICSEARCH)**
```
composer config repositories.swissup composer https://docs.swissuplabs.com/packages/
composer require swissup/module-search-mysql-legacy --prefer-source --ignore-platform-reqs
bin/magento module:enable Swissup_SearchMysqlLegacy Swissup_Core
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento indexer:reindex catalogsearch_fulltext
```