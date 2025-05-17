FROM certbot/dns-cloudflare:latest
LABEL maintainer="Henrique Fagundes <henrique@henrique.tec.br>"

RUN apk update && \
	apk upgrade && \
	rm -rf /var/cache/apk/*	

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
