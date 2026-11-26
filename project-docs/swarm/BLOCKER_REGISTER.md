# Blocker & Configuration Register — Purple Festival Gujarat 2027

| ID | Category | Description | Affected Scope | Autonomous Development Treatment (§21–§22) |
|---|---|---|---|---|
| **BLK-01** | `CONFIGURATION BLOCKED` | Live Resend API key, verified sending domain (`D-22`), SPF/DKIM/DMARC DNS & Svix webhook secret not provisioned | Live external email delivery | Use `MAIL_DRIVER=file` / `simulate` locally; mark live Resend `CONFIGURATION BLOCKED`; proceed with Phases 5–18 |
| **BLK-02** | `CONFIGURATION BLOCKED` | Live Bluehost staging/production SSH/cPanel credentials & explicit production deploy authorization not provided | Live Bluehost deploy & cPanel cron activation | Build clean release packager, migration plan & checklist; do NOT deploy to live Bluehost |
| **BLK-03** | `OWNER POLICY` (`D-05`–`D-27`) | Organizer business policy sign-offs (fees, multi-category cap, Gujarat SLI filter, CRR optionality, attendance scope, partner logo consent, domain) | Final production configuration flags | Implement safe configurable defaults per §22 (free registration test path, 1 active per category, non-blocking Gujarat SLI tag, optional CRR, daily attendance, draft unpublished partners/sessions) and consolidate into `FINAL_LAUNCH_CONFIGURATION_AND_OWNER_DECISIONS.md` |
