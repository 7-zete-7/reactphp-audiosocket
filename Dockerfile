# syntax=docker/dockerfile:1


# Infection PHAR image
FROM alpine:3.24 AS infection

WORKDIR /

# hadolint ignore=DL3018
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked <<-EOF
	apk add \
		gnupg \
	;
EOF

ARG INFECTION_GPG=C6D76C329EBADE2FB9C458CFC5095986493B4AA0

RUN gpg --recv-keys "${INFECTION_GPG}"

ARG INFECTION_VERSION=0.34.2
ARG INFECTION_HASH=sha256:ae83bc5151579817c3a6217eb3deceb86a169a399139264534218b723320da3d
ARG INFECTION_ASC_HASH=sha256:2928a4b28eda75f166c8db4ae8d90abcdb61b244264ac3d066e7fc2910e61ad4

ADD --checksum="${INFECTION_HASH}" --chmod=0755 "https://github.com/infection/infection/releases/download/${INFECTION_VERSION}/infection.phar" /
ADD --checksum="${INFECTION_ASC_HASH}" "https://github.com/infection/infection/releases/download/${INFECTION_VERSION}/infection.phar.asc" /

RUN gpg --with-fingerprint --verify 'infection.phar.asc' 'infection.phar'


# Devcontainer image
FROM php:8.4-cli

WORKDIR /workspace

COPY --link --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --link --from=ghcr.io/php/pie:bin /pie /usr/local/bin/
COPY --link --from=composer/composer:latest-bin /composer /usr/local/bin/
COPY --link --from=infection /infection.phar /usr/local/bin/infection

RUN mv "${PHP_INI_DIR}/php.ini-development" "${PHP_INI_DIR}/php.ini"

ENV COMPOSER_HOME=/composer

RUN mkdir --parents "${COMPOSER_HOME}"

# hadolint ignore=DL3008
RUN --mount=type=cache,target=/var/lib/apt,sharing=locked \
	--mount=type=cache,target=/var/cache/apt,sharing=locked \
	<<-EOF
	set -eux

	apt-get update
	apt-get install --no-install-recommends --yes \
		git \
		unzip \
	;
EOF

ENV IPE_KEEP_SYSPKG_CACHE=1

RUN --mount=type=cache,target=/var/lib/apt,sharing=locked \
	--mount=type=cache,target=/var/cache/apt,sharing=locked \
	--mount=type=cache,target=/tmp/pear,sharing=locked \
	<<-EOF
	set -eux

	install-php-extensions \
		xdebug \
	;
EOF

ARG NONROOT_UID=1000
ARG NONROOT_GID=1000
ARG NONROOT_USER=nonroot
ARG NONROOT_GROUP=nonroot

RUN <<-EOF
	set -eux

	groupadd \
		--gid "${NONROOT_GID}" \
		"${NONROOT_GROUP}"

	useradd \
		--create-home \
		--shell /bin/bash \
		--uid "${NONROOT_UID}" \
		--gid "${NONROOT_GID}" \
		"${NONROOT_USER}"

	chown --recursive "${NONROOT_USER}:${NONROOT_GROUP}" \
		"$(pwd)" \
		"${COMPOSER_HOME}" \
	;
EOF

USER ${NONROOT_UID}:${NONROOT_GID}

COPY --link --chown="${NONROOT_UID}:${NONROOT_GID}" <<composer.json /composer/
{
    "require": {
        "ext-xdebug": "^3.5",
        "brianium/paratest": "^7.24",
        "friendsofphp/php-cs-fixer": "^3.95",
        "phpstan/phpstan": "^2.2",
        "phpstan/phpstan-phpunit": "^2.0",
        "phpstan/phpstan-strict-rules": "^2.0",
        "phpunit/phpunit": "^13.3",
        "rector/rector": "^2.6"
    }
}
composer.json

RUN --mount=type=cache,target=/composer/cache,uid=${NONROOT_UID},gid=${NONROOT_GID},sharing=locked <<-EOF
	composer global install --no-interaction
EOF

ENV PATH="/composer/vendor/bin:${PATH}"
