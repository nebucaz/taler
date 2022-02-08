# taler
Dockerization of GNU Taler

docker run -it --mount type=bind,src="$(pwd)"/etc/taler,target=/etc/taler taler/merchant -e EXCHANGE_MASTER_KEY=123

