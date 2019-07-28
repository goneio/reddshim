FROM gone/php:nginx-php7.3 AS base

#FROM base AS watchman-build
#RUN apt-get -qq update && \
#    apt-get -yq install \
#        git \
#        autoconf automake \
#        build-essential \
#        python-dev \
#        libtool \
#        libssl-dev \
#        pkg-config
#
#RUN  git clone https://github.com/facebook/watchman.git && \
#    cd watchman/ && \
#    git checkout v4.9.0 && \
#    ./autogen.sh && \
#    ./configure && \
#    make && \
#    cat Makefile && \
#    make install
#
#RUN watchman --version && \
#    which watchman

FROM base AS reddshim
#COPY --from=watchman-build /usr/local/bin/watchman /usr/local/bin/
#RUN watchman --version
RUN apt-get -qq update && \
    apt-get -yq install --no-install-recommends \
        redis-tools \
        && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY reddshim.runit /etc/service/reddshim/run

RUN sed -i 's|disable_functions|#disabled_functions|g' /etc/php/7.3/cli/php.ini && \
    sed -i 's|cat /etc/php/.*/fpm/conf.d/env.conf||g' /etc/service/php-fpm/run

HEALTHCHECK NONE