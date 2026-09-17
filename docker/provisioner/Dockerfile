# Qubix — provisioning image for `qubix:provision` (Task 4.3).
#
# This is NOT one of the long-running per-client containers (app/queue/
# scheduler) — it exists only for the one-off, root, Docker-outside-of-
# Docker (DooD) invocation that provisions a brand-new client's stack from
# inside a container while still being able to drive `docker`/`docker
# compose` on the host. Adding the Docker CLI + compose plugin to every
# request-serving container (docker/8.3/Dockerfile) would bake host
# Docker-socket tooling into containers that never need it — needless bloat
# and attack surface for the long-running services. Instead this is a thin
# layer built ON TOP OF the client's own already-built app image
# (BASE_IMAGE), never duplicating docker/8.3/Dockerfile's package list.
#
# Pin capture method matches Task 2.0 (see docker/8.3/Dockerfile's own
# header): throwaway `ubuntu:24.04` container, `apt-get update`, then
# `apt-cache policy docker.io docker-compose-v2` for the exact `Candidate:`
# version string. Captured 2026-09-18 on this host:
#   docker.io           -> 29.1.3-0ubuntu3~24.04.2
#   docker-compose-v2   -> 2.40.3+ds1-0ubuntu1~24.04.1
# Both are in Ubuntu 24.04's own `universe` repo — no external Docker apt
# source added, for the same reachability reason docker/8.3/Dockerfile
# avoids the ondrej PPA. Re-pin using the same method/procedure as Task 2.0
# if a build ever fails with an "unable to locate package" error (Ubuntu's
# archive dropped the pinned point release from its pool).
ARG BASE_IMAGE=qubix/app
FROM ${BASE_IMAGE}

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        docker.io=29.1.3-0ubuntu3~24.04.2 \
        docker-compose-v2=2.40.3+ds1-0ubuntu1~24.04.1 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Deliberately no USER directive back to `sail`: this image only ever runs
# as a one-off, ephemeral `docker run --rm` invocation with the host's
# Docker socket bind-mounted in, and socket access is root-equivalent
# regardless of the container's own declared user — there's no meaningful
# security gain to dropping privileges just before exiting this Dockerfile.
# Root-owned writes to the bind-mounted checkout (the generated compose
# file, .env) are cleaned up afterward by scripts/provision-client.sh's own
# unconditional `chown -R "$(id -u):$(id -g)" .` step, run on the host after
# this container exits.
