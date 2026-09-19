#!/bin/bash
# Restore pristine target state: seeded projects, fresh scenarios, empty logs, no cloned dirs.
set -e
T=/opt/fleet-target
rm -rf /opt/qubix-* "$T/state" "$T/log" "$T/scenario"
mkdir -p "$T/state" "$T/log" "$T/scenario"
printf 'qubix\nqubix-sa\nqubix-taken\n' > "$T/state/projects"
cp -a "$T/scenario-src/." "$T/scenario/"
: > "$T/log/docker.log"; : > "$T/log/git.log"; : > "$T/log/provision.log"
chown -R deploy:deploy "$T/state" "$T/log" "$T/scenario"
