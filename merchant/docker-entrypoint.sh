#!/bin/bash
set -x

# FIXME wait for DB to start
sleep 5 

# intialize database
taler-merchant-dbinit

# FIXME: user supervisor
taler-merchant-httpd &

sleep 5

# initialize default instance
curl -i -X POST http://merchant:8888/management/instances \
-H 'Content-Type: application/json'  \
-H "Authorization: Bearer ${TALER_MERCHANT_TOKEN}" \
-d '{"payto_uris" : [ "payto://x-taler-bank/taler.macmini/Shop?receiver-name=Shop" ], "id" :"default","name": "example.com",  "address": { "country" : "Switzerland" },  "auth": { "method" : "external"} ,  "jurisdiction": { "country" : "Switzerland" },  "default_max_wire_fee": "CHF:1",  "default_wire_fee_amortization": 100,  "default_max_deposit_fee": "CHF:1",  "default_wire_transfer_delay": { "d_ms" : 1209600000 },"default_pay_delay": { "d_ms" : 1209600000 }}'

wait -n
exit $?
