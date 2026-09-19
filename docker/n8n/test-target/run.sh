#!/bin/bash
# Usage: KEY_PUB=<public key path> run.sh   (start/refresh, idempotent)
#        run.sh --reset                     (restore state, clear logs)
#        run.sh --down                      (remove container + network)
set -e
cd "$(dirname "$0")"
NAME=fleet-target NET=fleet-test IMG=qubix-fleet-target
case "${1:-}" in
  --down)
    docker rm -f $NAME >/dev/null 2>&1 || true
    # n8n-local keeps running; removing the network detaches it from that network only.
    docker network disconnect $NET n8n-local >/dev/null 2>&1 || true
    docker network rm $NET >/dev/null 2>&1 || true; exit 0;;
  --reset) docker exec $NAME /opt/fleet-target/reset.sh; exit 0;;
esac
: "${KEY_PUB:?set KEY_PUB to the public key path}"
docker build -q -t $IMG .
docker network inspect $NET >/dev/null 2>&1 || docker network create $NET >/dev/null
if [ -z "$(docker ps -q -f name=^$NAME$)" ]; then
  docker rm -f $NAME >/dev/null 2>&1 || true
  docker run -d --name $NAME --hostname $NAME --network $NET --network-alias $NAME \
    -p 127.0.0.1:2222:22 -v "$(realpath "$KEY_PUB")":/etc/ssh/authorized_keys/deploy:ro $IMG >/dev/null
fi
docker inspect -f '{{json .NetworkSettings.Networks}}' n8n-local | grep -q "\"$NET\"" \
  || docker network connect $NET n8n-local
docker exec $NAME /opt/fleet-target/reset.sh
