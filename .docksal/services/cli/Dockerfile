# Note how we use cli:2 here, which refers the latest available 2.x version
# So that we wouldn't need to update this every time new version of Docksal cli releases
FROM docksal/cli:php7.3

USER root

RUN apt-get update && apt-get install -y graphviz && apt-get clean

RUN curl "https://github.com/tideways/php-xhprof-extension/archive/v5.0-beta3.tar.gz" -fsL -o ./a.tar.gz \
    && tar xf ./a.tar.gz \
    && ls -la \
    && cd ./php-xhprof-extension-5.0-beta3 \
    && ls -la \
    && apt-get update -qq \
    && apt-get install -y build-essential checkinstall autoconf \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && cd .. \
    && rm -rf ./a.tar.gz ./php-xhprof-extension-5.0-beta3 \
    && docker-php-ext-enable tideways_xhprof
