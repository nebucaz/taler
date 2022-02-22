#!/bin/bash
set -eux

# wp-cli: https://developer.wordpress.org/cli/commands/
# woocommerce cli command extensions: https://github.com/woocommerce/woocommerce/wiki/WC-CLI-Commands
# Core install: installs database, admin user, set title
# Run `wp core install` to create database tables.

docker-compose run --rm shop-cli wp core install \
	--url=shop \
       	--path="/var/www/html" \
	--title="Shop Accepting Taler" \
       	--admin_user=shop_admin \
       	--admin_password=shop_admin \
       	--admin_email=info@example.com

# update
docker-compose run --rm shop-cli wp core update

# install theme (for woocommerce)
docker-compose run --rm shop-cli wp theme install storefront --activate

# delete unused plugins
docker-compose run --rm shop-cli wp plugin delete hello akismet

# install woocommerce plugin
docker-compose run --rm shop-cli wp plugin install woocommerce --activate

# install gnu-taler-payment-for-woocommerce plugin
docker-compose run --rm shop-cli wp plugin install gnu-taler-payment-for-woocommerce --activate

# config shop
docker-compose run --rm shop-cli wp option update woocommerce_store_address "Store street 2"
docker-compose run --rm shop-cli wp option update woocommerce_store_city "Bern"
docker-compose run --rm shop-cli wp option update woocommerce_default_country "CH:BE"
docker-compose run --rm shop-cli wp option update woocommerce_store_postcode "3030"
docker-compose run --rm shop-cli wp option update woocommerce_show_marketplace_suggestions 'no'
docker-compose run --rm shop-cli wp option update woocommerce_allow_tracking 'no'
docker-compose run --rm shop-cli wp option update woocommerce_task_list_hidden 'yes'
# docker-compose run shop-cli wp option update woocommerce_task_list_complete 'yes'
docker-compose run --rm shop-cli wp option update woocommerce_task_list_welcome_modal_dismissed 'yes'
docker-compose run --rm shop-cli wp option update --format=json woocommerce_onboarding_profile "{\"is_agree_marketing\":false,\"store_email\":\"info@example.com\",\"industry\":[{\"slug\":\"other\",\"detail\":\"Digital Goods\"}],\"product_types\":[\"downloads\"],\"product_count\":\"0\",\"selling_venues\":\"no\",\"setup_client\":true,\"business_extensions\":[],\"theme\":\"storefront\",\"completed\":true,\"skipped\":true}"

# payment gateway
docker-compose run --rm shop-cli wp wc payment_gateway update gnutaler --enabled=1 --user=shop_admin
docker-compose run --rm shop-cli wp option update woocommerce_gnutaler_settings --format=json '{"enabled":"yes","title":"GNU Taler","description":"Pay with GNU Taler","gnu_taler_backend_url":"http:\/\/merchant\/instances\/shop\/","GNU_Taler_Backend_API_Key":"Sandbox ApiKey","Order_text":"WooTalerShop #%s","GNU_Taler_refund_delay":"14","debug":"no"}'

# create category
cat_create_msg=docker-compose run --rm shop-cli wp wc product_cat create --name="Voucher" --user=shop_admin
[[ $cat_create_msg =~ $pat ]] # $pat must be unquoted

cat_id = $BASH_REMATCH[1]

# create voucher products
docker-compose run --rm shop-cli wp wc product create --name="Netflix" --sku="NTFLX01" --regular_price=20.00 --virtual=1 --categories="{[${cat_id}]}" --user=shop_admin

