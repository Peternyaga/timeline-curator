# Fieldwork Reach external tester guide

Fieldwork Reach is available through the Vumbua Labs Git marketplace. It connects Codex to the hosted Reach service at `https://reach.vumbualabs.com/mcp`; the plugin package contains no database credentials or lead data. This Git marketplace is separate from the public ChatGPT and Codex plugin directory.

## Before installation

- Install Codex Desktop or the Codex CLI.
- Ask the Reach workspace owner to invite you to `https://reach.vumbualabs.com/`. Complete your account setup and sign in there.
- Use your own Reach account when Codex opens the authorization page. Review the requested read, lead-write, and internal follow-up-write permissions.

## Install

In Codex Desktop, open **Settings → Plugins → Marketplaces**, add `Peternyaga/timeline-curator`, then install **Fieldwork Reach** from **Vumbua Labs**. If you prefer the CLI:

```text
codex plugin marketplace add Peternyaga/timeline-curator
codex plugin add sales-workspace-hosted@vumbua-labs
```

Start a new Codex task after installation. If account linking did not start during installation, run `codex mcp login fieldwork`. Sign in and approve the permissions in the browser. A browser error on the temporary `127.0.0.1` return page does not establish success or failure; verify that Codex reports a completed login and can read your Reach profile.

The plugin can read leads and follow-ups, create and update leads and factual notes, and manage **internal** follow-up tasks. It cannot place calls or send messages or calendar invitations.

## Optional background worker on Windows

In a new Codex task, ask: “Set up the Fieldwork Reach background worker for this workspace.” The setup skill explains each permission before registration. After approval, a current-user Windows task checks Reach at sign-in and every five minutes. An empty check makes one small readiness request and does not start an agent. A pending item can start a Codex run that uses model tokens and records its work in Reach's **Agent activity**. The user can request a reversal there.

The Windows worker is optional and is not installed merely by adding the plugin. It currently requires a writable workspace directory and a signed-in Windows account. The setup stores a narrow polling credential in an ignored `.fieldwork-reach` directory inside that workspace. The credential cannot read lead details or write to Reach. The worker can be removed with the plugin's removal script or its access can be revoked in Reach under **Connected apps**.

## Updating

```text
codex plugin marketplace upgrade vumbua-labs
codex plugin add sales-workspace-hosted@vumbua-labs
```

Start a new Codex task after updating so it loads the refreshed tools and setup skill.
