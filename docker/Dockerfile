# só pra rodar cobertura e mutação: a máquina de dev não tem pcov (contexto: docker/)
FROM php:8.4-cli-bookworm
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && pecl install pcov && docker-php-ext-enable pcov \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ARG UID=1000
ARG GID=1000
# -o aceita GID/UID q já existem na imagem base (ex. 20 no macOS)
RUN groupadd -o -g ${GID} app && useradd -o -m -u ${UID} -g ${GID} app
USER app
WORKDIR /app
