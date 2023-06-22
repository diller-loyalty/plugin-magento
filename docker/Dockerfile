FROM php:8.1-apache as builder

LABEL maintainer = "Diller Dev Team <devtechsupport@diller.no>"

RUN apt-get update && apt-get install --no-install-recommends --assume-yes --quiet nano unzip wget iputils-ping net-tools

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
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# expect build-time variables. They can be passed via --build-arg or args: under build:
ARG A_XDEBUG_PORT=9003
ARG A_XDEBUG_IDEKEY=DOCKER
ARG A_APIKEY=12334

# use the value to set the ENV var default
ENV XDEBUG_PORT=$A_XDEBUG_PORT
ENV XDEBUG_IDEKEY=$A_XDEBUG_IDEKEY
ENV APIKEY=$A_APIKEY

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

RUN mkdir /tmp/xdebug \
    && chmod 777 /tmp/xdebug


RUN composer config --global --auth gitlab-token.gitlab.com glpat--x-Xas-m4kagTKEaW35x