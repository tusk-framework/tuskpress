FROM ghcr.io/tusk-framework/tusk-engine:latest

# Instalar o composer a partir da imagem oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar diretório do projeto
WORKDIR /app

# Copy project files
COPY . .

# Substituir binário do tusk engine pela nossa versão local que corrige os arrays nos headers
COPY tusk-engine-linux /usr/local/bin/tusk
RUN chmod +x /usr/local/bin/tusk

# Instalar extensões adicionais necessárias pelo TuskPress
RUN apk add --no-cache php82-pdo_mysql php82-pdo

# Instalar as dependências do TuskPress via Composer
# (We use --ignore-platform-reqs temporarily in case the Alpine base image doesn't have all extensions configured, and --no-dev for production)
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Expor a porta 8080 que a Engine utiliza
EXPOSE 8080

# Iniciar o Tusk Engine rodando o nosso worker persistente
CMD ["tusk", "start", "worker.php"]
