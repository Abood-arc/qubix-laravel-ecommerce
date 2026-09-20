# Throwaway SSH target standing in for the VPS (Task 4.5 dry-run). Never used in production.
FROM debian:bookworm-slim
RUN apt-get update \
 && apt-get install -y --no-install-recommends openssh-server procps bash ca-certificates \
 && rm -rf /var/lib/apt/lists/* \
 && mkdir -p /run/sshd /etc/ssh/authorized_keys \
 && useradd -m -s /bin/bash deploy \
 && passwd -l deploy \
 && chown deploy /opt
COPY sshd_config /etc/ssh/sshd_config.d/fleet-target.conf
COPY shims/ /opt/fleet-target/bin/
COPY scenarios/ /opt/fleet-target/scenario-src/
COPY reset.sh /opt/fleet-target/reset.sh
RUN chmod 0755 /opt/fleet-target/bin/* /opt/fleet-target/reset.sh \
 && /opt/fleet-target/reset.sh
# authorized_keys is copied in by run.sh with `docker cp` (never bind-mounted — see
# run.sh's header); only the public key is ever supplied.
EXPOSE 22
CMD ["/usr/sbin/sshd", "-D", "-e"]
