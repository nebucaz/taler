#!/bin/bash

taler-config -s MERCHANT -o SERVE -V TCP
taler-config -s MERCHANT -o PORT -V 8888
taler-config -s TALER -o CURRENCY -V KUDOS
