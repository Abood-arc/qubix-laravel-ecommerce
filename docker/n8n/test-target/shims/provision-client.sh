#!/bin/bash
# Fake provisioner: mirrors the real wrapper's args/exit codes/output, driven by
# /opt/fleet-target/scenario/<slug> (space-separated exit codes, one consumed per run).
T=/opt/fleet-target
echo "$(date -u +%FT%TZ) provision $*" >> $T/log/provision.log
i=0; slug=; email=; slug_given=0
for a in "$@"; do
  echo "ARG[$i]=$a"; i=$((i+1))
  case $a in
    --slug=*) slug=${a#--slug=}; slug_given=1;;
    --admin-email=*) email=${a#--admin-email=};;
  esac
done

# Pre-validation, mirroring scripts/provision-client.sh (this shim builds a path
# from --slug, so it must reject exactly what the real wrapper rejects, before
# touching any state). Exit 1 = terminal/invalid input, nothing created.
for a in "$@"; do
  if [[ "$a" =~ [[:cntrl:]] ]]; then
    echo "Error: an argument contains a control character (newline/CR/tab/etc.), length ${#a} — invalid input." >&2
    exit 1
  fi
  if [ "$a" = "--slug" ]; then
    echo "Error: --slug requires the --slug=<slug> form — invalid input." >&2
    exit 1
  fi
done
if [ "$slug_given" != 1 ] || [ -z "$slug" ]; then
  echo "Error: --slug=<slug> is required (e.g. --slug=acme)" >&2; exit 1
fi
if [[ ! "$slug" =~ ^[a-z][a-z0-9-]{1,20}$ ]]; then
  echo "Error: invalid --slug (must match ^[a-z][a-z0-9-]{1,20}\$; got ${#slug} chars) — invalid input." >&2
  exit 1
fi

# /opt/fleet-target/scenario/<slug>.marker rewrites the ownership marker this run
# just wrote, standing in for "another run claimed the checkout between our deploy
# and our teardown" (finding I3). cwd is the checkout dir, as the real wrapper's is.
if [ -f "$T/scenario/$slug.marker" ] && [ -d ./.git ]; then
  cat "$T/scenario/$slug.marker" > ./.git/fleet-run-id
fi

f=$T/scenario/$slug; codes=; [ -f "$f" ] && read -r codes < "$f"
set -- $codes; code=${1:-0}; shift; [ -f "$f" ] && echo "$*" > "$f"
proj=qubix-$slug
if [ "$code" = 2 ]; then
  echo "Error: slug '$slug' collides with an existing Docker resource matching '$proj'." >&2
  echo "Choose a different slug." >&2; exit 2
fi
grep -qxF "$proj" $T/state/projects || echo "$proj" >> $T/state/projects
# The real stack's named volumes come up with `docker compose up`, i.e. before
# any later step can fail — so a half-built stack leaves these behind too.
for v in "${proj}_${proj}-mysql" "${proj}_${proj}-redis"; do
  grep -qxF "$v" $T/state/volumes || echo "$v" >> $T/state/volumes
done
if [ "$code" != 0 ]; then echo "Provisioning failed (simulated exit $code)." >&2; exit "$code"; fi
pw=$(LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 20)
echo
echo " Provisioned '$slug' successfully."
echo "  URL:          https://$slug.digital-labs.ai (DNS/Caddy not wired yet — Task 4.4)"
echo "  Compose:      docker-compose.$slug.yml (project $proj)"
echo "  Admin email:  $email"
# /opt/fleet-target/scenario/<slug>.pwfmt switches the wording of the password
# line, to prove the redaction is not coupled to one exact string (finding I1).
fmt=""; [ -f "$T/scenario/$slug.pwfmt" ] && read -r fmt < "$T/scenario/$slug.pwfmt"
case "$fmt" in
  alt)   printf '  Generated admin password = %s\n' "$pw" ;;
  ansi)  printf '  \033[33mAdmin password (generated, shown once): %s\033[0m\n' "$pw" ;;
  none)  echo "  Admin credentials were written to the operator vault." ;;
  *)     echo "  Admin password (generated, shown once): $pw" ;;
esac
