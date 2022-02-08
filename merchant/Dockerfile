#Download base image ubuntu 20.04
FROM ubuntu:20.04

# LABEL about the custom image
LABEL maintainer="admin@sysadminjournal.com"
LABEL version="0.1"
LABEL description="This is custom Docker Image for Taler Merchant" 

# Disable Prompt During Packages Installation
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    gnupg2 \
    curl 

# https://www.linuxuprising.com/2021/01/apt-key-is-deprecated-how-to-add.html
RUN echo "deb [arch=amd64 signed-by=/usr/share/keyrings/taler-systems-keyring.gpg] https://deb.taler.net/apt/ubuntu focal-fossa main" >> /etc/apt/sources.list.d/taler.list  && \
    curl https://taler.net/taler-systems.gpg.key | gpg --dearmor > /usr/share/keyrings/taler-systems-keyring.gpg
	 
RUN apt-get update && \ 
    apt-get install -y --no-install-recommends \
    taler-merchant
