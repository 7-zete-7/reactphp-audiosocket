#syntax=docker/dockerfile:1

# Versions
FROM php:8.3-cli AS php_upstream
FROM ghcr.io/mlocati/php-extension-installer AS php_extension_installer_upstream

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target


FROM php_upstream AS app_base

WORKDIR /app

# persistent / runtime deps
# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	file \
	gettext \
	git \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=php_extension_installer_upstream --link /usr/bin/install-php-extensions /usr/local/bin/

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		event \
		intl \
		opcache \
		zip \
	;

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1


FROM app_base AS app_dev

ENV XDEBUG_MODE=off

RUN set -eux; \
	install-php-extensions \
		xdebug \
	;
