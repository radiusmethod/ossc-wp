FROM wordpress:6.4.1-php8.2-apache

RUN apt-get update && \
	apt-get install -y  --no-install-recommends ssl-cert && \
	rm -r /var/lib/apt/lists/* && \
	a2enmod ssl rewrite expires && \
	a2ensite default-ssl

ENV PHP_INI_PATH /usr/local/etc/php/php.ini
ENV PHP_XDEBUG_ENABLED: 1

RUN pecl install xdebug-3.2.2 && docker-php-ext-enable xdebug \
    && echo "zend_extension=$(find /usr/lib/php/ -name xdebug.so)" >> ${PHP_INI_PATH} \
    && echo "xdebug.mode=coverage,debug" >> ${PHP_INI_PATH} \
    && echo "xdebug.client_port=9003" >> ${PHP_INI_PATH} \
    && echo "xdebug.client_host=host.docker.internal" >> ${PHP_INI_PATH} \
    && echo "xdebug.start_with_request=yes" >> ${PHP_INI_PATH} \
    && echo "xdebug.log=/tmp/xdebug.log" >> ${PHP_INI_PATH} \
    && echo "xdebug.idekey=IDE_DEBUG" >> ${PHP_INI_PATH}


EXPOSE 80
EXPOSE 443
