#!/bin/bash
# Fake provisioner: mirrors the real wrapper's args/exit codes/output, driven by
# /opt/fleet-target/scenario/<slug> (space-separated exit codes, one consumed per run).
T=/opt/fleet-target
echo "$(date -u +%FT%TZ) provision $*" >> $T/log/provision.log
i=0; slug=; email=
for a in "$@"; do
  echo "ARG[$i]=$a"; i=$((i+1))
  case $a in --slug=*) slug=${a#--slug=};; --admin-email=*) email=${a#--admin-email=};; esac
done
f=$T/scenario/$slug; codes=; [ -f "$f" ] && read -r codes < "$f"
set -- $codes; code=${1:-0}; shift; [ -f "$f" ] && echo "$*" > "$f"
proj=qubix-$slug
if [ "$code" = 2 ]; then
  echo "Error: slug '$slug' collides with an existing Docker resource matching '$proj'." >&2
  echo "Choose a different slug." >&2; exit 2
fi
grep -qxF "$proj" $T/state/projects || echo "$proj" >> $T/state/projects
if [ "$code" != 0 ]; then echo "Provisioning failed (simulated exit $code)." >&2; exit "$code"; fi
pw=$(LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 20)
echo
echo " Provisioned '$slug' successfully."
echo "  URL:          https://$slug.digital-labs.ai (DNS/Caddy not wired yet — Task 4.4)"
echo "  Compose:      docker-compose.$slug.yml (project $proj)"
echo "  Admin email:  $email"
echo "  Admin password (generated, shown once): $pw"
