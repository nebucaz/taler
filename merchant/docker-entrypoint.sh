#!/bin/bash
set -x

# FIXME wait for DB to start
sleep 10

# intialize database
taler-merchant-dbinit

taler-merchant-httpd

