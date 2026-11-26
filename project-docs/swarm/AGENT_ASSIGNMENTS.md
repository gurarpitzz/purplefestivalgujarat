# Multi-Agent Swarm Assignments & Model Routing

**Orchestration Platform:** Native Antigravity Multi-Agent Swarm (`swarm` tool + shared message board + isolated `workspace:"share"` git worktrees)  
**Model Diversity Policy (§13 Agent M):** Implementation spans Gemini (`gemini-4-neon-high`), Claude (`claude-sonnet-5-5-high`), and GPT (`gpt-oss-120b-medium`), while Independent Security & Architecture Review uses a distinct frontier model family (`claude-opus-5-5-high`).

| Logical Role (§13) | Swarm Worker ID | Model Assigned | Workspace | Responsibility |
|---|---|---|---|---|
| Master Orchestrator | `coordinator` | Gemini 4 Neon (XHigh) | Primary (`swarm/phases-5-18`) | Task graph, contracts, branch integration, regression gate, milestone acceptance |
| Agent B — Database & Data Integrity | `db-architect` | Claude Sonnet 5.5 (High) | `share` | Centralized versioned migrations `007`–`010`, rollbacks, seeder parity |
| Agent K — Accessibility | `a11y-core` | Gemini 4 Neon (High) | `share` | WCAG 2.2 AA core CSS/JS fixes (`FND-20`, `FND-46`), form & portal a11y audit |
| Agent E — Participant Auth | `auth-eng` | Claude Sonnet 5.5 (High) | `share` | Phase 5 signup, email verification, participant/staff login, recovery, profile, legacy claim |
| Agent F — Registration & Lifecycle | `reg-eng` | Gemini 4 Neon (High) | `share` | Phases 6–7 three-category hub, Attendee/Volunteer/SLI forms, shared a11y, state machine |
| Agent I — Registration Desk | `rgst-eng` | Gemini 4 Neon (High) | `share` | Phase 9 manual `/rgst` desk, paginated search, atomic checkbox attendance, corrections |
| Agent H/J — Admin & Festival Ops | `admin-eng` | Claude Sonnet 5.5 (High) | `share` | Phases 8, 11, 12 permission profiles, complete admin portal, venues, programme, duty assignments |
| Agent G — Participant Frontend | `portal-eng` | Gemini 4 Neon (High) | `share` | Phase 10 authenticated Attendee/Volunteer/SLI dashboards & in-app notifications |
| Agent Public/Comms | `public-eng` | Gemini 4 Neon (High) | `share` | Phases 13–14 public navigation, CMS consent_verified, legal accuracy, final trigger wiring |
| Agent M — Independent Reviewer | `reviewer` | Claude Opus 5.5 (High) | `inherit` | Independent second-model security, RBAC, migration, attendance & test-assertion audit |
| Agent L — QA & E2E Verification | `qa-eng` | Gemini 4 Neon (High) | `share` | Phase 17 PHP/MariaDB + Apache + Playwright E2E + concurrency + WCAG + visual regression |
| Agent N/O — DevOps & Governance | `release-docs` | GPT-OSS 120B (Medium) | `share` | Phase 16 classification, Phase 18 clean release packager, handover guides & final reports |
