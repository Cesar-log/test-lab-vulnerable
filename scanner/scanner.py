#!/usr/bin/env python3
"""
scanner.py - web vulnerability scanner (study project).

Built to run against the lab in this repository (../app).
USE ONLY against your own targets or ones you have written authorization to test.

Usage:
    python scanner.py http://127.0.0.1:8099
"""
import sys
import re
import json
from datetime import datetime
import requests


# ---------------------------------------------------------------------------
# Each "check" receives the HTTP session and the base URL and returns a
# finding dictionary. Adding a new check is easy: write another function and
# drop it into the CHECKS list at the bottom.
# ---------------------------------------------------------------------------

def finding(name, vulnerable, severity, detail, evidence=""):
    """Standardizes a check's result."""
    return {
        "name": name,
        "vulnerable": vulnerable,
        "severity": severity,
        "detail": detail,
        "evidence": evidence,
    }


def check_sqli_login(s, base):
    """
    SQL Injection on the login (authentication bypass).

    Sends the classic `admin' -- ` payload in the username field. If the app
    builds the SQL by string concatenation, the quote closes the field and the
    -- comments out the password check -> logs in without valid credentials.

    How we detect it: the success page shows "Bem-vindo" (welcome); the failure
    page shows "invalidos". We also send a deliberately wrong password, so a
    "welcome" response can only have come from the injection.
    """
    name = "SQL Injection - login bypass"
    url = base + "/login.php"
    payload = {"usuario": "admin' -- ", "senha": "deliberately_wrong_password"}

    r = s.post(url, data=payload, timeout=5)

    if "Bem-vindo" in r.text:
        return finding(
            name, True, "Critical",
            "Logged in without a valid password using `admin' -- ` in the username field.",
            f"POST {url}  usuario=admin' --   -> response contains 'Bem-vindo'",
        )
    return finding(name, False, "Critical", "Could not bypass the login with the tested payload.")


def check_xss_reflected(s, base):
    """
    Reflected XSS on the search page (busca.php?q=).

    We send a unique, unmistakable payload and check whether it comes back RAW
    in the HTML (not escaped). The oracle's care here:

      - a random marker (S7K9...) avoids matching something already on the page
        -> prevents false positives;
      - we require the RAW `<script>` tag. If the app escaped it (the correct
        behavior) it would return `&lt;script&gt;` and our `in r.text` test would
        be False, which is the right result for a secure page.
    """
    name = "Reflected XSS - search"
    marker = "S7K9xss"
    payload = f"<script>{marker}</script>"
    url = base + "/busca.php"

    r = s.get(url, params={"q": payload}, timeout=5)

    if payload in r.text:  # the <script> tag came back whole and raw
        return finding(
            name, True, "High",
            "The search term is reflected into the HTML unescaped -> <script> runs in the browser.",
            f"GET {url}?q={payload}  -> payload reflected raw in the response",
        )
    return finding(name, False, "High", "The search seems to escape its output (payload did not come back raw).")


def check_xss_stored(s, base):
    """
    Stored XSS on the message board (mural.php).

    Two-step oracle, to prove PERSISTENCE:
      1) POST: publish a message with a unique-marker payload;
      2) GET (a SEPARATE request): open the board again and check whether the
         payload comes back RAW.

    If the payload shows up on a fresh, later visit, it was STORED on the server
    -> every visitor would be hit. That's the difference from reflected XSS,
    where the payload only existed in the attacker's own request.
    """
    name = "Stored XSS - message board"
    marker = "K3Y9stored"
    payload = f"<script>{marker}</script>"
    url = base + "/mural.php"

    # 1) publish
    s.post(url, data={"autor": "scanner", "texto": payload}, timeout=5)
    # 2) revisit from scratch
    r = s.get(url, timeout=5)

    if payload in r.text:
        return finding(
            name, True, "High",
            "A message containing <script> is stored and served raw to anyone who opens the board.",
            f"POST {url} (texto={payload}) then GET {url} -> payload persisted raw",
        )
    return finding(name, False, "High", "The board seems to escape its output (payload did not persist raw).")


def check_idor(s, base):
    """
    IDOR on the profile page (perfil.php?id=).

    A different kind of oracle: there is no malicious payload and no script
    signal. The input is a legitimate number. What gives the flaw away is the
    PATTERN: with no login, we request sequential ids (1, 2, 3...) and see
    whether each returns a DIFFERENT person's profile. Reading several distinct
    users (without authenticating) = broken access control.

    We extract the "Usuario" field from each response. If >= 2 distinct users
    show up, the enumeration is confirmed.
    """
    name = "IDOR - profile enumeration"
    url = base + "/perfil.php"
    users = {}   # id -> leaked username

    for id_ in range(1, 6):
        r = s.get(url, params={"id": id_}, timeout=5)
        # the page renders: <tr><td>Usuario</td><td>NAME</td></tr>
        m = re.search(r"<td>Usuario</td><td>([^<]+)</td>", r.text)
        if m:
            users[id_] = m.group(1)

    distinct = set(users.values())
    if len(distinct) >= 2:
        listing = ", ".join(f"id={i}:{u}" for i, u in sorted(users.items()))
        return finding(
            name, True, "High",
            "With no login, sequential ids return different users' profiles.",
            f"GET {url}?id=1..5  -> leaked {len(distinct)} users: {listing}",
        )
    return finding(name, False, "High", "Could not enumerate other users' profiles.")


# Checks the scanner runs.
CHECKS = [
    check_sqli_login,
    check_xss_reflected,
    check_xss_stored,
    check_idor,
]


# ---------------------------------------------------------------------------
# Engine + report
# ---------------------------------------------------------------------------

def save_report(base, findings):
    """Writes the result as markdown (to read) and json (for another tool to use)."""
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    vulns = [f for f in findings if f["vulnerable"]]

    # --- markdown ---
    lines = [
        "# Scanner report",
        "",
        f"- **Target:** {base}",
        f"- **Date:** {now}",
        f"- **Summary:** {len(vulns)} vulnerability(ies) across {len(findings)} test(s)",
        "",
        "| Status | Flaw | Severity | Detail |",
        "|--------|------|----------|--------|",
    ]
    for f in findings:
        status = "VULNERABLE" if f["vulnerable"] else "ok"
        lines.append(f"| {status} | {f['name']} | {f['severity']} | {f['detail']} |")
    lines.append("")
    lines.append("## Evidence")
    for f in vulns:
        lines.append(f"- **{f['name']}**: `{f['evidence']}`")
    with open("report.md", "w", encoding="utf-8") as fh:
        fh.write("\n".join(lines))

    # --- json ---
    data = {"target": base, "date": now, "findings": findings}
    with open("report.json", "w", encoding="utf-8") as fh:
        json.dump(data, fh, ensure_ascii=False, indent=2)

    print("[*] Report saved to report.md and report.json")


def run(base):
    base = base.rstrip("/")
    s = requests.Session()
    s.headers.update({"User-Agent": "lab-scanner/0.1"})

    print(f"\n[*] Target: {base}")
    print(f"[*] Running {len(CHECKS)} check(s)...\n")

    findings = []
    for check in CHECKS:
        try:
            result = check(s, base)
        except requests.RequestException as e:
            print(f"[!] connection error in {check.__name__}: {e}")
            continue
        findings.append(result)
        mark = "VULN" if result["vulnerable"] else " ok "
        print(f"[{mark}] {result['name']}")
        if result["vulnerable"]:
            print(f"        -> {result['detail']}")
            if result["evidence"]:
                print(f"        evidence: {result['evidence']}")

    vulns = [f for f in findings if f["vulnerable"]]
    print(f"\n[*] Done. {len(vulns)} vulnerability(ies) found across {len(findings)} test(s).")
    save_report(base, findings)
    print()
    return vulns


def main():
    if len(sys.argv) != 2:
        print("usage: python scanner.py <base_url>")
        print("e.g. : python scanner.py http://127.0.0.1:8099")
        sys.exit(1)
    run(sys.argv[1])


if __name__ == "__main__":
    main()
