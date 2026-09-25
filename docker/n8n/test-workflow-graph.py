#!/usr/bin/env python3
"""Static topology checks for the committed n8n workflow JSON. No n8n, no Docker, no network.

Why this exists: Task 4.4's Caddy re-chain left a stray `Rec Caddy -> Build Credentials Email`
edge in the repo JSON, forming a cycle that re-sends the admin password. The locally tested
n8n copy did not have that edge, so nothing that ran against it could have caught it. This
checks the file that actually gets imported.

Usage: docker/n8n/test-workflow-graph.py            (exit 0 = all pass, 1 = any failure)
"""
import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
# The ONE intended loop in the onboarding workflow: a transient failure waits, then re-runs Prep Deploy.
ALLOWED_LOOP_BREAKERS = {"client-onboarding.workflow.json": {"Backoff Wait"}}

failures = []


def check(ok, msg):
    print(("PASS " if ok else "FAIL ") + msg)
    if not ok:
        failures.append(msg)


def edges(wf):
    g = {}
    for src, v in wf["connections"].items():
        for out in v.get("main", []):
            for t in out or []:
                g.setdefault(src, []).append(t["node"])
    return g


def find_cycle(g, ignore_from):
    """Return one cycle (as a node list) ignoring outgoing edges of `ignore_from`, else None."""
    WHITE, GREY, BLACK = 0, 1, 2
    colour = {}
    stack = []

    def dfs(n):
        colour[n] = GREY
        stack.append(n)
        for m in ([] if n in ignore_from else g.get(n, [])):
            c = colour.get(m, WHITE)
            if c == GREY:
                return stack[stack.index(m):] + [m]
            if c == WHITE:
                cyc = dfs(m)
                if cyc:
                    return cyc
        stack.pop()
        colour[n] = BLACK
        return None

    for n in list(g):
        if colour.get(n, WHITE) == WHITE:
            cyc = dfs(n)
            if cyc:
                return cyc
    return None


for name in sorted(p.name for p in HERE.glob("*.workflow.json")):
    wf = json.loads((HERE / name).read_text())
    g = edges(wf)
    names = {n["name"] for n in wf["nodes"]}
    dangling = [(s, t) for s, ts in g.items() for t in ts if t not in names] + [s for s in g if s not in names]
    check(not dangling, f"{name}: every connection endpoint is an existing node {dangling or ''}")
    cyc = find_cycle(g, ALLOWED_LOOP_BREAKERS.get(name, set()))
    check(cyc is None, f"{name}: no cycle other than through {sorted(ALLOWED_LOOP_BREAKERS.get(name, set())) or 'nothing'}"
                       + (f" -- found: {' -> '.join(cyc)}" if cyc else ""))

    if name == "client-onboarding.workflow.json":
        incoming = lambda n: sorted(s for s, ts in g.items() if n in ts)
        check(g.get("Rec Caddy", []) == [], f"Rec Caddy is terminal (outgoing: {g.get('Rec Caddy', [])})")
        check(incoming("Build Credentials Email") == ["Mark Active"],
              f"Build Credentials Email is reached only from Mark Active (incoming: {incoming('Build Credentials Email')})")
        check(incoming("Prep Caddy") == ["Rec Delivery"],
              f"Prep Caddy runs only after Rec Delivery, so Caddy can never delay the credentials email (incoming: {incoming('Prep Caddy')})")

sys.exit(1 if failures else 0)
