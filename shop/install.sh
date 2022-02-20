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

# create category
cat_create_msg = docker-compose run shop-cli wp wc product_cat create --name="Voucher" --user=shop_admin
[[ $cat_create_msg =~ $pat ]] # $pat must be unquoted
#echo "${BASH_REMATCH[0]}"
#echo "${BASH_REMATCH[1]}"

cat_id = $BASH_REMATCH[1]

# create voucher products
docker-compose run shop-cli wp wc product create --name="Netflix" --sku="NTFLX01" --regular_price=20.00 --virtual=1 --categories="{[${cat_id}]}" --user=shop_admin

