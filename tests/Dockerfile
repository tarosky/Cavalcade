ARG php_version
FROM php:${php_version}-cli

WORKDIR /workspace

RUN apt-get update && apt-get install -y \
  default-mysql-client \
  git \
  libzip-dev \
  unzip \
  wait-for-it

RUN docker-php-ext-install \
  mysqli \
  zip

COPY tests/build /build
RUN /build/install-composer

ARG uid
ARG gid
ARG wp_version
COPY db.php /db.php
RUN /build/install-wp "${uid}" "${gid}" "${wp_version}"
RUN rm -fr /build

ENTRYPOINT ["tests/entrypoint"]
