# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in Champlin AI Internal Linker, please **do not** open a public issue.

Instead, email **security@champlinenterprises.com** with:

- A description of the vulnerability
- Steps to reproduce
- The affected version(s)
- Any proof-of-concept code or screenshots

We aim to acknowledge reports within 48 hours and ship a patch within 7 days for high-severity issues.

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 1.0.x   | ✅        |

## Scope

In scope:
- Authentication / authorization bypass
- SQL injection
- XSS, CSRF (REST endpoints, admin pages)
- Privilege escalation
- Insecure handling of OpenAI API keys

Out of scope:
- Issues in WordPress core, third-party themes, or unrelated plugins
- Findings that require an attacker to already have administrator-level access
- Self-XSS in the block editor when an editor user manually crafts malicious HTML
