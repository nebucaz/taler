FROM wordpress:latest

ENV WOOCOMMERCE_VERSION 6.2.0

RUN apt-get update \
    && apt-get install -y --no-install-recommends \ 
       unzip \
       wget \
       git

# woocommerce
RUN wget https://downloads.wordpress.org/plugin/woocommerce.$WOOCOMMERCE_VERSION.zip -O /tmp/woocommerce.zip \
    && cd /usr/src/wordpress/wp-content/plugins \
    && unzip /tmp/woocommerce.zip \
    && rm /tmp/woocommerce.zip 

# taler plugin
RUN mkdir -p /usr/src/wordpress/wp-content/plugins && \
    cd /usr/src/wordpress/wp-content/plugins && \
    git clone https://git.taler.net/gnu-taler-payment-for-woocommerce.git

# wp-cli
RUN cd /usr/bin && \
    wget https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -O wp-cli.phar


#    && rm -rf /var/lib/apt/lists/* 
# https://git.taler.net/gnu-taler-payment-for-woocommerce.git
