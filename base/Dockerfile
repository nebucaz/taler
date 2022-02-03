FROM ubuntu:focal

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    apt-utils \
    gnupg2 \
    curl

RUN curl https://taler.net/taler-systems.gpg.key | gpg --dearmor > /usr/share/taler-systems-keyring.gpg && \
    echo "deb [arch=amd64 signed-by=/usr/share/taler-systems-keyring.gpg] https://deb.taler.net/apt/ubuntu focal-fossa main" >> /etc/apt/sources.list.d/taler.list && \
    apt-get update

