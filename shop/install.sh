#!/bin/bash
set -eux

# Core install: installs database, admin user, set title
# Run `wp core install` to create database tables.

docker-compose run shop-cli wp core install \
	--url=shop \
       	--path="/var/www/html" \
	--title="Shop Accepting Taler" \
       	--admin_user=shop_admin \
       	--admin_password=shop_admin \
       	--admin_email=info@example.com

# update
docker-compose run shop-cli wp core update

# delete unused plugins
docker-compose run shop-cli wp plugin delete hello akismet

# install woocommerce plugin
docker-compose run shop-cli wp plugin install woocommerce --activate

# install gnu-taler-payment-for-woocommerce plugin
docker-compose run shop-cli wp plugin install gnu-taler-payment-for-woocommerce --activate

# config shop

# create products

