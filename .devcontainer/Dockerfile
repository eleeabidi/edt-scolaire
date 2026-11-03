FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

# Extensions de l'application : MySQL (pdo_mysql), OpenSpout (zip), DomPDF (gd), Laravel (intl, bcmath, pcntl)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
# (la source Yarn de l'image a une clé expirée qui bloque apt : on la retire, Yarn ne sert pas)
RUN rm -f /etc/apt/sources.list.d/yarn.list \
    && install-php-extensions pdo_mysql zip gd intl bcmath pcntl
