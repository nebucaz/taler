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
