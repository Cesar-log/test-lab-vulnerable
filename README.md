# 🛡️ Vulnerable Web Lab

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Python](https://img.shields.io/badge/Python-3.13-3776AB?logo=python&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-embedded-003B57?logo=sqlite&logoColor=white)
![OWASP](https://img.shields.io/badge/OWASP-Top%2010-000000?logo=owasp&logoColor=white)
![Status](https://img.shields.io/badge/use-educational-success)

> An **intentionally vulnerable** training environment plus a **custom scanner** that finds and exploits the flaws automatically. A hands-on study project in **offensive web security (web pentesting)**.

This repository has **two sides of the same coin**: a web application with classic flaws planted on purpose (the **target**) and a Python scanner that detects them on its own (the **tool**). The goal is to show not only *how* to exploit each vulnerability, but *why* it happens and **how to fix it** — the defensive side, which is what actually matters.

I'm studying cybersecurity and built this lab as a controlled environment to test attacks, understand the mechanics behind each flaw, and document everything in a reproducible way.

---

## ⚠️ Disclaimer

This application is **insecure by design**. It is meant to run **only on your own machine** (`localhost`), as a study environment.

- **Never** expose this lab to the internet or a shared network.
- Use the scanner **only** against this lab or systems you have **explicit written authorization** to test.
- Testing third-party systems without permission is a crime in most jurisdictions (e.g., in Brazil, Law 12.737/2012).

---

## 🧱 Architecture

```
vulnerable-web-lab/
├── app/         # the TARGET — vulnerable web app (PHP + SQLite)
│   ├── db.php           # creates the SQLite database and seeds data
│   ├── layout.php       # shared header/menu/theme
│   ├── index.php        # menu of the vulnerable areas
│   ├── login.php        # SQL Injection (authentication bypass)
│   ├── busca.php        # SQL Injection (UNION) + reflected XSS
│   ├── mural.php        # stored XSS
│   └── perfil.php       # IDOR (Broken Access Control)
│
├── scanner/     # the TOOL — vulnerability scanner (Python)
│   └── scanner.py       # detects and exploits the lab's flaws, writes a report
│
└── writeup/     # the DOCUMENTATION — how to find, exploit and FIX each flaw
    ├── 01-sql-injection.md
    ├── 02-xss.md
    └── 03-idor.md
```

**Stack:** PHP 8.4 (built-in server `php -S`, no Apache), SQLite (file-based database, no server), Python 3 + `requests`. Lightweight, no heavy dependencies.

> Note: the source files use Portuguese names/comments (`busca` = search, `mural` = message board, `perfil` = profile), kept as written during development.

---

## 🎯 Implemented vulnerabilities

| # | Flaw | Where | OWASP Top 10 | Fix |
|---|------|-------|--------------|-----|
| 1 | **SQL Injection** (login bypass + `UNION` dump) | `login.php`, `busca.php` | A03 — Injection | Prepared statements |
| 2 | **Reflected XSS** | `busca.php` | A03 — Injection | Output encoding (`htmlspecialchars`) |
| 3 | **Stored XSS** | `mural.php` | A03 — Injection | Output encoding + CSP + `HttpOnly` cookie |
| 4 | **IDOR** (Broken Access Control) | `perfil.php` | A01 — Broken Access Control | Server-side authorization check |

Each flaw has a dedicated **writeup** under [`writeup/`](writeup/) with a step-by-step exploitation and the corresponding, tested patch.

> **Common thread:** SQL Injection and XSS are the same disease — *user data turning into code* — only the interpreter changes (the database vs. the browser). IDOR is a different family: an **authorization** flaw, not an injection one. The writeups explore this parallel.

---

## 🚀 Getting started

### 1. Run the lab (the target)

Requirement: **PHP 8+** installed ([php.net/downloads](https://www.php.net/downloads), or the portable Windows build at [windows.php.net](https://windows.php.net/download)). SQLite is already bundled.

```bash
git clone https://github.com/Cesar-log/test-lab-vulnerable.git
cd test-lab-vulnerable

php -S 127.0.0.1:8099 -t app
```

Open **http://127.0.0.1:8099** in your browser. The database (`lab.db`) is created automatically on first run.

> The server runs **only while the command is open** and **only on `localhost`** — it is not a tunnel and is never exposed. Stop it with `Ctrl+C`.

### 2. Run the scanner (the tool)

Requirement: **Python 3** + the `requests` library (`pip install requests`).

```bash
python scanner/scanner.py http://127.0.0.1:8099
```

Example output:

```
[*] Target: http://127.0.0.1:8099
[*] Running 4 check(s)...

[VULN] SQL Injection - login bypass
        -> Logged in without a valid password using `admin' -- ` in the username field.
[VULN] Reflected XSS - search
[VULN] Stored XSS - message board
[VULN] IDOR - profile enumeration
        -> With no login, sequential ids return different users' profiles.
        -> leaked 3 users: id=1:admin, id=2:user, id=3:maria

[*] Done. 4 vulnerability(ies) found across 4 test(s).
[*] Report saved to report.md and report.json
```

When finished, the scanner writes a report to **`report.md`** (human-readable) and **`report.json`** (for consumption by another tool / CI).

---

## 🔎 How the scanner works

Each vulnerability is an independent **check** (`check_sqli_login`, `check_xss_reflected`, ...) in an extensible list. The heart of each check is its **oracle**: the way it *knows* the attack worked, without raising a **false positive**.

| Check | Oracle (success signal) |
|-------|-------------------------|
| SQLi login | Payload `admin' --` **with a deliberately wrong password** → a "welcome" response can only come from the injection |
| Reflected XSS | Unique marker + the `<script>` tag must come back **raw** (not escaped) |
| Stored XSS | **Two-step** oracle: post the payload, then **revisit** to prove it was persisted |
| IDOR | Enumerates `?id=1..5` and detects **different users' profiles** with no authentication |

A useful side effect: against a **fixed** version of the lab, the checks start reporting `ok` on their own — so the scanner also works as a way to **validate patches** (regression testing).

---

## 📚 What this project practices

- Hands-on exploitation of **SQL Injection, XSS (reflected and stored), and IDOR**.
- Mapping flaws to the **OWASP Top 10** and to the **correct fixes** (not band-aids).
- Building a **scanner** from scratch: oracles, false-positive reduction, structured reporting.
- An **attack + defense** mindset: every flaw ships with its tested patch.

---

## 📄 License

Educational use. Feel free to study, adapt, and learn from the code.
