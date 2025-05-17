# Verifica se as variáveis de ambiente estão definidas
if [ -z "$CERTBOT_DOMAINS" ]; then
  echo "Erro: A variável CERTBOT_DOMAINS não está definida."
  exit 1
fi
if [ -z "$CERTBOT_BASE_DOMAIN" ]; then
  echo "Erro: A variável CERTBOT_BASE_DOMAIN não está definida."
  exit 1
fi
if [ -z "$CERTBOT_EMAIL" ]; then
  echo "Erro: A variável CERTBOT_EMAIL não está definida."
  exit 1
fi
if [ -z "$CLOUDFLARE_EMAIL" ]; then
  echo "Erro: A variável CLOUDFLARE_EMAIL não está definida."
  exit 1
fi
if [ -z "$CLOUDFLARE_API_KEY" ]; then
  echo "Erro: A variável CLOUDFLARE_API_KEY não está definida."
  exit 1
fi

# Cria o arquivo cloudflare.ini com as credenciais
CLOUDFLARE_INI="/etc/letsencrypt/cloudflare.ini"
echo "dns_cloudflare_email = $CLOUDFLARE_EMAIL" > "$CLOUDFLARE_INI"
echo "dns_cloudflare_api_key = $CLOUDFLARE_API_KEY" >> "$CLOUDFLARE_INI"

# Ajusta as permissões do arquivo para 600 (somente root pode ler/escrever)
chmod 600 "$CLOUDFLARE_INI"
chown root:root "$CLOUDFLARE_INI"

# Caminho para o certificado
CERT_PATH="/etc/letsencrypt/live/$CERTBOT_BASE_DOMAIN"

# Tenta gerar ou expandir o certificado
echo "Tentando gerar ou expandir o certificado para $CERTBOT_BASE_DOMAIN..."
certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials "$CLOUDFLARE_INI" \
  --dns-cloudflare-propagation-seconds 20 \
  --email "$CERTBOT_EMAIL" \
  --agree-tos \
  --no-eff-email \
  --non-interactive \
  --expand \
  --force-renewal \
  $CERTBOT_DOMAINS

# Verifica se o comando foi bem-sucedido
if [ $? -eq 0 ]; then
  echo "Certificado gerado ou expandido com sucesso."
else
  echo "Erro ao gerar ou expandir o certificado. Verifique o log em /var/log/letsencrypt/letsencrypt.log."
  exit 1
fi

# Configura renovação automática (verifica a cada 12 horas)
echo "Iniciando loop de renovação automática..."
while :; do
  certbot renew --quiet
  sleep 12h
done

