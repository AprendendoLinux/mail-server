FROM roundcube/roundcubemail:latest
LABEL maintainer="Henrique Fagundes <henrique@henrique.tec.br>"

RUN apt-get update && \
	apt dist-upgrade -y && \
	apt clean && apt clean all && \
	rm -rf /var/lib/apt/lists/*

COPY docker-entrypoint.sh /docker-entrypoint.sh
