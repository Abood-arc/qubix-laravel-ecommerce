#!/bin/bash
# Usage: KEY_PUB=<public key path> run.sh   (start/refresh, idempotent)
#        run.sh --reset                     (restore state, clear logs)
#        run.sh --down                      (remove container + network)
#
# NOTHING here is ever bind-mounted. Docker creates a missing bind-mount source as
# a ROOT-owned path on the host, and this key normally lives in an agent scratchpad
# under /tmp/claude-1000 — a wiped /tmp then left that whole directory owned by
# uid 0, which stops Claude Code from starting. The public key is copied in with
# `docker cp` instead, and a missing key is a hard error rather than something
# Docker silently materialises.
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
if [ ! -f "$KEY_PUB" ]; then
  echo "run.sh: KEY_PUB ('$KEY_PUB') is not an existing regular file." >&2
  echo "        Generate one first: ssh-keygen -t ed25519 -N '' -f <path>" >&2
  exit 1
fi
docker build -q -t $IMG .
docker network inspect $NET >/dev/null 2>&1 || docker network create $NET >/dev/null
# Recreate if absent, stopped, carrying any mount (older builds bind-mounted the
# key), or running an image older than the one just built — otherwise an edited
# shim silently would not reach the container.
recreate=1
if [ -n "$(docker ps -q -f name=^$NAME$)" ] \
  && [ "$(docker inspect -f '{{len .Mounts}}' $NAME 2>/dev/null)" = 0 ] \
  && [ "$(docker inspect -f '{{.Image}}' $NAME 2>/dev/null)" = "$(docker image inspect -f '{{.Id}}' $IMG)" ]; then
  recreate=0
fi
if [ "$recreate" = 1 ]; then
  docker rm -f $NAME >/dev/null 2>&1 || true
  docker run -d --name $NAME --hostname $NAME --network $NET --network-alias $NAME \
    -p 127.0.0.1:2222:22 $IMG >/dev/null
fi
# Only the PUBLIC key ever leaves the host, and it is copied, not mounted.
docker cp "$KEY_PUB" $NAME:/etc/ssh/authorized_keys/deploy
docker exec -u root $NAME sh -c 'chown root:root /etc/ssh/authorized_keys/deploy && chmod 0644 /etc/ssh/authorized_keys/deploy'
# The n8n instance has to exist before it can be attached to the test network. Without
# this check the script died on `docker network connect` with two raw daemon errors
# ("error: no such object" / "Error response from daemon: No such container") and no
# hint about what it actually wanted — verified by reproducing those two lines with a
# non-existent container name.
if [ -z "$(docker ps -aq -f name=^n8n-local$)" ]; then
  echo "run.sh: the n8n container 'n8n-local' does not exist." >&2
  echo "        The target is up, but n8n cannot reach it until that container exists" >&2
  echo "        and is attached to the '$NET' network. Start n8n, then re-run this script." >&2
  exit 1
fi
docker inspect -f '{{json .NetworkSettings.Networks}}' n8n-local | grep -q "\"$NET\"" \
  || docker network connect $NET n8n-local
docker exec $NAME /opt/fleet-target/reset.sh
