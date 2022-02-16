FROM taler/base:latest
RUN DEBIAN_FRONTEND=noninteractive \
    apt-get install -y --no-install-recommends \
    nginx \
    taler-exchange \
    gosu \
    supervisor \
    vim

#VOLUME ["/etc"]
#COPY etc/taler /etc/
COPY etc/supervisor/conf.d/* /etc/supervisor/conf.d/
COPY docker-entrypoint.sh /usr/local/bin/

#RUN taler-exchange-httpd taler-exchange-dbinit
#RUN docker-entrypoint.sh
#ENTRYPOINT ["supervisord"]
