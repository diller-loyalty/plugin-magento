FROM php:8.1-apache as builder

LABEL maintainer = "Diller Dev Team <devtechsupport@diller.no>"

RUN apt-get update && apt-get install --no-install-recommends --assume-yes --quiet unzip wget iputils-ping net-tools

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql

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
