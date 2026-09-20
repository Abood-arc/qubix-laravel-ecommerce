#!/bin/bash
# Restore pristine target state: seeded projects + volumes, fresh scenarios, empty logs, no cloned dirs.
set -e
T=/opt/fleet-target
rm -rf /opt/qubix-* "$T/state" "$T/log" "$T/scenario"
mkdir -p "$T/state" "$T/log" "$T/scenario"
printf 'qubix\nqubix-sa\nqubix-taken\n' > "$T/state/projects"
# Volume names as docker really creates them: <compose project>_<declared name>
# (see docker-compose.sa.yml: volume `qubix-sa-mysql` in project `qubix-sa`).
# `qubix-stray_qubix-stray-mysql` has NO compose project: a client whose
# containers were removed but whose data volume still exists (finding I2).
cat > "$T/state/volumes" <<'EOF'
qubix_qubix-mysql
qubix_qubix-redis
qubix-sa_qubix-sa-mysql
qubix-sa_qubix-sa-redis
qubix-taken_qubix-taken-mysql
qubix-taken_qubix-taken-redis
qubix-stray_qubix-stray-mysql
EOF
cp -a "$T/scenario-src/." "$T/scenario/"
: > "$T/log/docker.log"; : > "$T/log/git.log"; : > "$T/log/provision.log"
chown -R deploy:deploy "$T/state" "$T/log" "$T/scenario"
