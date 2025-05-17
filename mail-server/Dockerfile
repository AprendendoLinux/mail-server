FROM ghcr.io/docker-mailserver/docker-mailserver:latest
LABEL maintainer="Henrique Fagundes <henrique@henrique.tec.br>"

RUN apt-get update && \
	apt dist-upgrade -y && \
	apt-get install -y \
	postfix-mysql \
	dovecot-mysql \
	mariadb-client && \
	apt clean && apt clean all && \
	rm -rf /var/lib/apt/lists/*

COPY user-patches.sh /tmp/docker-mailserver/
COPY dovecot.sh /usr/local/bin/setup.d/dovecot.sh
