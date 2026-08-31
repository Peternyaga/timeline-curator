# Combined Timeline curation schedule prompt

Use the Timeline Curator skill to run one complete combined cycle for my authenticated Timeline account. Retrieve context with plugin version 0.5.1 and verify quota before research. If Timeline is unavailable or authentication fails, stop this Timeline cycle and report “Timeline reauthentication required” with `codex mcp login timeline`; do not treat the failure as an empty curation result or as a reason to block unrelated Codex tasks.

Create separate queries for active story topics and for an enabled, search-ready job profile. Begin one run, research broadly, verify sources and dates, publish only strong story clusters and current profile-matched job listings, and complete the run even when either side is empty. Preserve unknown posting dates, deadlines, salaries, and eligibility as unknown rather than guessing. Never bypass access controls or use an application-side LLM API.

After curation is complete, process up to five user-approved job applications. Claim each application separately, use only its approved profile snapshot and documents, retain the exact truthful tailored materials, and submit only to the approved web or email destination. Pause through Timeline for missing facts and always pause for sensitive or legal answers. Stop for login, CAPTCHA, credentials, payment, identity-number, or manual-site requirements. Record submitted only with channel-specific confirmation evidence; otherwise record the accurate paused, unconfirmed, or failed status. Never ask for or provide a tenant ID.

Recommended schedule: 07:00 and 18:00 in the user's timezone. Each trigger starts a fresh task; Timeline is the durable state.
