# Usar imagem base Alpine
FROM alpine:3.21.3

# Definir mantenedor
LABEL maintainer="Henrique Fagundes <henrique@henrique.tec.br>"

# Definir argumentos para TimeZone
ARG TZ=America/Sao_Paulo

# Criar diretório de trabalho
RUN mkdir -p /var/www/html

# Instalar dependências do sistema, Apache e extensões PHP
RUN apk update && apk upgrade && apk add --no-cache \
    tzdata \
    ca-certificates \
    bash \
    curl \
    apache2 \
    php84 \
    php84-common \
    php84-session \
    php84-apache2 \
    php84-pdo_mysql \
    php84-mysqli \
    php84-mysqlnd \
    php84-zip \
    php84-gd \
    php84-xml \
    php84-curl \
    php84-mbstring \
    php84-ctype \
    php84-dom \
    php84-iconv \
    php84-fileinfo \
    php84-phar \
    php84-opcache \
    mysql-client \
    && cp /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo "${TZ}" > /etc/timezone \
    && rm -rf /var/cache/apk/* \
    && ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log \
    && sed -i 's/\/var\/www\/localhost\/htdocs/\/var\/www\/html/g' /etc/apache2/httpd.conf \
    && rm -rf /var/www/localhost/htdocs

# Habilitar módulo rewrite do Apache
RUN if [ -f /etc/apache2/httpd.conf ]; then \
        sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf; \
    else \
        echo "Warning: httpd.conf not found, checking if rewrite module is already enabled"; \
    fi

# Configurar PHP: TimeZone e outras configurações
RUN echo "date.timezone = ${TZ}" > /etc/php84/conf.d/timezone.ini \
    && echo "memory_limit = 256M" >> /etc/php84/conf.d/custom.ini \
    && echo "upload_max_filesize = 10M" >> /etc/php84/conf.d/custom.ini \
    && echo "post_max_size = 10M" >> /etc/php84/conf.d/custom.ini

# COPIA CONTEUDO
COPY ./mailadmin /var/www/html
COPY entrypoint.sh /

# Ajustar permissões do diretório de trabalho
RUN chown -R apache:apache /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod +x /entrypoint.sh

# Expor porta 80
EXPOSE 80

# Definir diretório de trabalho
WORKDIR /var/www/html

# Comando para iniciar o Apache
ENTRYPOINT ["/entrypoint.sh"]
