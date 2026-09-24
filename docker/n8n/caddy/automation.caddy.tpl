# automation.digital-labs.ai — the self-hosted n8n editor (Task 4.4 piece 2).
# Rendered by docker/n8n/caddy/render-block.sh and applied as
# <checkout>/docker/caddy/clients/automation.caddy on the VPS (gitignored there;
# never committed with a real hash). Stock caddy:2-alpine, per-host HTTP-01 cert.
#
# Fail-closed: only EXACTLY `POST /webhook/fleet-onboard` skips Caddy's password;
# every other request — any other method, a trailing slash, //, a sub-path,
# dot-segments, a different case, /webhook-test/*, /rest/*, /api/*, the editor —
# goes through basic_auth. The exemption is an `expression` on the raw path, not
# Caddy's `path` matcher, which is case-insensitive and merges `//`. That endpoint is guarded by n8n's X-Fleet-Token header credential
# instead, capped at 64 KiB, and is not usable from a browser (no CORS preflight).
#
# Basic auth here does not collide with n8n's own login: the editor authenticates
# with a cookie, and the n8n public API with X-N8N-API-KEY, so neither reads the
# Authorization header Caddy consumes.
automation.digital-labs.ai {
	encode gzip zstd
	header X-Robots-Tag noindex

	@onboard {
		method POST
		expression {http.request.uri.path} == "/webhook/fleet-onboard"
	}
	handle @onboard {
		request_body {
			max_size 64KiB
		}
		reverse_proxy n8n:5678
	}

	handle {
		basic_auth {
			{{BASIC_AUTH_USER}} {{BASIC_AUTH_HASH}}
		}
		reverse_proxy n8n:5678
	}
}
