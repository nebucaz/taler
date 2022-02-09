#Download base image ubuntu 20.04
#FROM ubuntu:20.04
FROM taler/base:latest

# LABEL about the custom image
LABEL maintainer="admin@sysadminjournal.com"
LABEL version="0.1"
LABEL description="This is custom Docker Image for Taler Merchant" 

# Disable Prompt During Packages Installation
#ARG DEBIAN_FRONTEND=noninteractive

#RUN apt-get update -y && \
#    apt-get install -y --no-install-recommends \
#    ca-certificates \
#    gnupg2 \
#    curl \
#    supervisor 

# https://www.linuxuprising.com/2021/01/apt-key-is-deprecated-how-to-add.html
#RUN echo "deb [arch=amd64 signed-by=/usr/share/keyrings/taler-systems-keyring.gpg] https://deb.taler.net/apt/ubuntu focal-fossa main" >> /etc/apt/sources.list.d/taler.list  && \
#    curl https://taler.net/taler-systems.gpg.key | gpg --dearmor > /usr/share/keyrings/taler-systems-keyring.gpg
	 
RUN apt-get update && \ 
    DEBIAN_FRONTEND=noninteractive \
    TZ=Europe/Zurich \
    apt-get install -y --no-install-recommends \
    taler-merchant \
    supervisor
#RUN useradd -U -ms /bin/bash -u 1001 taler-merchant-httpd
USER taler-merchant-httpd
#RUN taler-merchant-dbinit

#RUN taler-merchant-dbinit && \
#     taler-config -s MERCHANT-EXCHANGE-1 -o MASTER_KEY -V ${TALER_MASTER_PUBLIC_KEY}
#     useradd -uG taler-merchant-httpd
#USER taler-merchant-httpd
# CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
