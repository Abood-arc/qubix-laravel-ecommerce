# fleet.digital-labs.ai — the read-only fleet dashboard (Task 4.4 piece 2).
# Applied as <checkout>/docker/caddy/clients/fleet.caddy on the VPS.
#
# Exactly `GET /webhook/fleet-dashboard` is proxied to n8n; everything else on this
# hostname is a 404 that never reaches n8n — in particular /webhook/fleet-onboard,
# /webhook-test/*, /rest/* and the editor. The match is an `expression` on the raw path
# (Caddy's `path` matcher is case-insensitive and merges `//`, so it is not used).
#
# Deliberately NO Caddy basic_auth on this host. The dashboard webhook already
# enforces Basic Auth inside n8n (credential `fleet-dashboard-basic-auth`), and
# Caddy's basic_auth reads and forwards the very same Authorization header. A
# browser sends one credential, so a second layer with a different password
# could never pass both checks, and one with the same password adds nothing.
# n8n's check is the one that travels with the workflow export.
#
# The `>` prefix on the CSP replaces the `sandbox` policy n8n sets on HTML webhook
# responses; a plain `header` would add a second policy beside it.
fleet.digital-labs.ai {
	encode gzip zstd
	header X-Robots-Tag noindex

	@dashboard {
		method GET
		expression {http.request.uri.path} == "/webhook/fleet-dashboard"
	}
	handle @dashboard {
		header >Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'"
		reverse_proxy n8n:5678
	}

	handle {
		respond "Not found" 404
	}
}
