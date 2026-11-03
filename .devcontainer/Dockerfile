FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

# Extensions de l'application : MySQL (pdo_mysql), OpenSpout (zip), DomPDF (gd), Laravel (intl, bcmath, pcntl)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql zip gd intl bcmath pcntl
