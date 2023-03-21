FROM php:8.1-apache as builder

LABEL maintainer = "Diller Dev Team <devtechsupport@diller.no>"

RUN apt-get update && apt-get install --no-install-recommends --assume-yes --quiet unzip wget iputils-ping net-tools

RUN pecl install xdebug

RUN docker-php-ext-install mysqli pdo pdo_mysql sockets && docker-php-ext-enable pdo_mysql

RUN curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s bcmath gd intl soap xsl zip

# Enable mod_rewrite for images with apache
RUN if command -v a2enmod >/dev/null 2>&1; then \
        a2enmod rewrite headers \
    ;fi

RUN service apache2 restart

# Override max_execution_time = 30 from /usr/local/etc/php/php.ini-production to a higher value.
# This is desired when using trace/debug/profiling and script execution takes longer to complete
RUN echo 'max_execution_time = 0' >> /usr/local/etc/php/conf.d/docker-php-maxexectime.ini;
RUN echo 'memory_limit  = -1' >> /usr/local/etc/php/conf.d/docker-php-memorylimit.ini;

# Timezone env with default
ARG A_TZ
ENV TZ=$A_TZ

RUN echo "SETTING TZ TO: ${TZ}"

# Set timezone
RUN rm /etc/localtime \
    && ln -s /usr/share/zoneinfo/${TZ} /etc/localtime \
    && "date"


# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"

RUN mv composer.phar /usr/local/bin/composer

# XDEBUG
RUN { \
    echo '\n\n;Diller: Modified by Docker RUN command\n'; \
	echo '[xdebug]'; \
    echo 'zend_extension=xdebug.so'; \

    # Enable multiple modes at the same time by comma separating their identifiers as value to xdebug.mode: xdebug.mode=develop,trace.
    echo 'xdebug.mode=debug,profile,trace'; \

    # Adjust for verbosity
    echo 'xdebug.log_level=7'; \
    echo "xdebug.idekey=${XDEBUG_IDEKEY}"; \
    echo 'xdebug.output_dir=/tmp/xdebug/profiling'; \

    echo 'xdebug.client_host=host.docker.internal'; \
    echo "xdebug.client_port=${XDEBUG_PORT}"; \
    echo 'xdebug.log=/tmp/xdebug/xdebug.log'; \

    # Tracing
    # To view trace logs use https://github.com/kuun/xdebug-trace-viewer
    echo 'xdebug.trace_format=1'; \
    echo 'xdebug.auto_trace=false'; \
    echo 'xdebug.trace_enable_trigger=true'; \
    echo 'xdebug.collect_assignments=true'; \
    echo 'xdebug.collect_return=true'; \
    echo 'xdebug.trace_output_name=trace.%p.%t'; \

    # '1' the trace files will be appended to, instead of being overwritten in subsequent requests.
    echo 'xdebug.trace_options=1'; \

    # Enable on demand, by sending a GET/POST parameter or COOKIE variable of the name XDEBUG_SESSION
    # Or via chrome/ff extension: Xdebug helper
    echo 'xdebug.start_with_request=trigger'; \

} > /usr/local/etc/php/conf.d/docker-php-xdebug.ini


# Override max_execution_time = 30 from /usr/local/etc/php/php.ini-production to a higher value.
# This is desired when using trace/debug/profiling and script execution takes longer to complete
RUN echo 'max_execution_time = 0' >> /usr/local/etc/php/conf.d/docker-php-maxexectime.ini;
RUN echo 'memory_limit  = -1' >> /usr/local/etc/php/conf.d/docker-php-memorylimit.ini;