# Timeline Curator: Codex setup and tester guide

Timeline Curator turns your interests into a private, evidence-backed feed. You choose the topics and editorial rules. Codex researches the web, verifies sources and suitable media, and publishes the strongest stories to your Timeline.

This guide covers installation with the Codex desktop experience or Codex CLI.

## What you need

- Codex in the ChatGPT desktop app, or the Codex CLI
- A modern web browser
- A free Timeline account
- About 10 minutes for setup and your first test

You do not need an OpenAI API key. Each tester connects Codex directly to their own Timeline account through OAuth.

## 1. Create your Timeline account

1. Open [curator.vumbualabs.com/register](https://curator.vumbualabs.com/register).
2. Create an account and sign in.
3. Open the **Policy** page.
4. Add at least one topic. You can select a suggested topic to prefill its name and coverage brief.
5. Edit the coverage brief so it clearly describes what you want.
6. Add directives if you have special preferences, such as:
   - prioritize primary sources;
   - avoid sensational reporting;
   - include practical recommendations;
   - seek diverse viewpoints;
   - prefer recent developments.
7. Save the topic and directives.

Timeline will not invent a generic feed when there are no active topics.

### Example topic

**Name:** Artificial intelligence

**Coverage brief:** Important AI research, useful products, policy decisions, business developments, and practical applications. Prefer primary research and substantive reporting over speculation or promotional announcements.

## 2. Install the Vumbua Labs marketplace

### Codex desktop

1. Open Codex in the ChatGPT desktop app.
2. Open **Settings → Plugins → Marketplaces**.
3. Select **Add marketplace**.
4. Enter:

   ```text
   Peternyaga/timeline-curator
   ```

5. Open the Vumbua Labs marketplace.
6. Find **Timeline Curator** and select **Install**.
7. Restart the desktop app or open a new Codex task.

If your version exposes the command menu instead, enter `/plugins`, add or open the Vumbua Labs marketplace, and install **Timeline Curator**.

### Codex CLI

Open a terminal and run:

```bash
codex plugin marketplace add Peternyaga/timeline-curator
codex plugin add timeline-curator@vumbua-labs
```

On Windows PowerShell, if `codex` is not recognized, use:

```powershell
& "$env:APPDATA\npm\codex.cmd" plugin marketplace add Peternyaga/timeline-curator
& "$env:APPDATA\npm\codex.cmd" plugin add timeline-curator@vumbua-labs
```

Start a new Codex task after installation. Plugins are loaded when a task starts, so a task that was already open may not see Timeline.

## 3. Connect Codex to Timeline

Installation may start authentication automatically. If it does not, run:

```bash
codex mcp login timeline
```

Windows PowerShell fallback:

```powershell
& "$env:APPDATA\npm\codex.cmd" mcp login timeline
```

Then:

1. Your browser opens the Timeline authorization page.
2. Sign in with your Timeline account.
3. Review the requested access.
4. Select **Approve**.
5. Keep Codex running while the browser returns to its temporary local callback.

Never share access tokens, authorization codes, session cookies, or the complete localhost callback URL.

## 4. Run your first curation cycle

Start a new Codex task and send:

```text
Run my Timeline curation cycle now. Monitor it through completion.
```

You can also mention the plugin explicitly:

```text
@Timeline Curator Run my Timeline curation cycle now.
```

Codex will:

1. Retrieve your active topics, directives, and prior feedback.
2. Formulate and record exact research queries.
3. Begin a curation run.
4. Research several search angles and inspect candidate sources.
5. Verify important claims and distinguish fact, analysis, opinion, and uncertainty.
6. Search for attributable, embeddable story images or videos.
7. Submit the stories that satisfy your policy.
8. Complete the run, including a valid empty result when nothing is strong enough.

A thorough run can take several minutes. Let Codex continue until it reports that the run is complete.

## 5. Review your feed

1. Open [curator.vumbualabs.com/timeline](https://curator.vumbualabs.com/timeline).
2. Review the story summaries, media, reasons they matter, and inspected sources.
3. Use the relevance and depth controls.
4. Select any story-specific feedback tags that describe your reaction.
5. Add a comment when you want to explain a preference.
6. Submit the feedback.

Feedback becomes part of the context for future runs. Useful examples include:

- “More reporting from sources like this.”
- “I already knew this.”
- “This is too introductory.”
- “More local relevance.”
- “Avoid product-launch stories unless independent reviewers have tested the product.”

## 6. Schedule recurring curation

A scheduled task is what keeps Timeline fresh. After the first manual cycle succeeds, create a standalone recurring task that runs the curator without waiting for you to start a chat.

Scheduled-task management is available in ChatGPT on the web and in the ChatGPT desktop app. It is not currently managed from the Codex CLI or IDE extension.

### Recommended schedule

Start with one run every morning. For example:

- **Task name:** Refresh my Timeline
- **Schedule:** Every day at 7:00 AM in your local timezone
- **Destination:** A new standalone run in **Scheduled**

One daily run is usually enough for a personal feed. Timeline currently permits no more than three curation runs per UTC day, so do not create overlapping or excessively frequent schedules.

### Create the scheduled task from chat

In the ChatGPT desktop app or ChatGPT on the web, open a new task where Timeline Curator is available and send:

```text
Create a standalone scheduled task named “Refresh my Timeline”.

Run it every day at 7:00 AM in my local timezone.

On every scheduled run, use $timeline-curator:timeline-curator to run one complete Timeline curation cycle. Retrieve my current context and feedback, formulate and record exact queries, begin the run, research and verify the strongest stories and suitable media, submit accepted stories, and complete the run. Monitor the cycle through completion. If nothing satisfies my policy, complete the run with a valid empty result. Report the accepted and rejected story counts and any action I need to take.
```

Change the time or cadence before sending if necessary. A daily or weekday schedule is a good starting point.

When Codex presents the task details:

1. Confirm that it is a **standalone scheduled task**.
2. Confirm the timezone and next run time.
3. Confirm that **Timeline Curator** is available to the task.
4. Create or approve the task.
5. Open **Scheduled** from the sidebar.
6. Verify that **Refresh my Timeline** is active and that its next run is correct.

Using the explicit `$timeline-curator:timeline-curator` skill name prevents a scheduled run from depending only on automatic plugin selection.

### Verify the first scheduled runs

1. Let the first scheduled run finish.
2. Open **Scheduled** and inspect its result.
3. Confirm that it reports a completed Timeline run rather than only returning research in chat.
4. Open [curator.vumbualabs.com/timeline](https://curator.vumbualabs.com/timeline) and confirm that accepted stories appeared.
5. Review the first three runs and adjust the topic policy, schedule, or prompt if results are too broad, repetitive, or sparse.

Standalone runs are preferred because every cycle should retrieve the latest Timeline policy and feedback. A long-running chat is not required.

Timeline uses a remote service and does not need access to a local source-code folder. If you deliberately attach the scheduled task to a local project, keep the computer powered on, the project available, and the ChatGPT desktop app running at the scheduled time.

### Manage the schedule

Open **Scheduled** to:

- run the task manually;
- inspect recent and failed runs;
- change its time or cadence;
- pause it temporarily;
- resume it;
- delete it.

If you substantially change the task prompt, test the new prompt once in a normal Codex task before relying on it unattended.

## 7. Share a story

1. Select **Share** beside a story title.
2. Timeline creates a private-to-public snapshot containing only the story, media, and sources.
3. Choose your device share sheet or a shortcut such as WhatsApp, X, Facebook, LinkedIn, Telegram, Reddit, Threads, Bluesky, or email.
4. Use **Copy prepared post** if you prefer to paste it elsewhere.
5. Select **Disable public link** if the snapshot should no longer be accessible.

The public snapshot excludes your identity, policy, private feedback, and internal curation data.

## 8. Run another cycle manually

After leaving feedback or changing the policy, start a new Codex task and run:

```text
Run another complete Timeline curation cycle. Apply my latest policy and feedback.
```

Each run reads the current policy before researching.

The scheduled task will normally run future cycles. Use a manual run when you want an immediate refresh after changing topics, directives, or feedback.

## 9. Keep the plugin updated

Run:

```bash
codex plugin marketplace upgrade vumbua-labs
codex plugin add timeline-curator@vumbua-labs
```

Then restart Codex or open a new task.

## Troubleshooting

### Timeline Curator is not visible

- Confirm that the Vumbua Labs marketplace was added.
- Reinstall the plugin.
- Restart Codex or open a new task.

### Authentication did not open or expired

Run:

```bash
codex mcp logout timeline
codex mcp login timeline
```

Keep Codex open throughout the authorization flow.

### The browser reaches localhost but does not return to Codex

Allow the temporary `127.0.0.1` callback through browser or endpoint-security controls, then retry. The callback port may change on each attempt.

### Codex reports that there are no topics

Open the Timeline **Policy** page and create at least one active topic.

### The run completes with no stories

This can be a correct result. It means no candidate cleared your current policy and verification threshold. Broaden an overly narrow coverage brief only if that reflects what you actually want.

### A scheduled run did not happen

- Open **Scheduled** and confirm that the task is active.
- Check its timezone, next-run time, and recent run history.
- Confirm that Timeline Curator is still installed and available to the task.
- If the task uses a local project, keep the computer on and the ChatGPT desktop app running.
- Run the prompt manually to separate a scheduling problem from a Timeline or authentication problem.

### A scheduled run reports an authentication error

Reconnect Timeline:

```bash
codex mcp logout timeline
codex mcp login timeline
```

Then test one manual cycle before waiting for the next scheduled run.

### A tool reports an internal error

Retry once in a new Codex task. If the problem persists, open a [GitHub issue](https://github.com/Peternyaga/timeline-curator/issues) containing:

- operating system and Codex version;
- the step that failed;
- the Timeline tool name, if shown;
- the approximate time;
- the sanitized error message;
- what you expected to happen.

Do not include passwords, access tokens, authorization codes, session cookies, or complete callback URLs.

## Tester checklist

Please test:

- marketplace and plugin installation;
- OAuth login and consent;
- topic and directive presets;
- the first curation cycle;
- creation of the daily scheduled task;
- at least one completed scheduled run;
- source and media quality;
- story-specific feedback;
- live story notifications;
- story sharing and public previews;
- plugin update or reauthentication.

Report problems at [github.com/Peternyaga/timeline-curator/issues](https://github.com/Peternyaga/timeline-curator/issues).

## Useful links

- Timeline: [curator.vumbualabs.com](https://curator.vumbualabs.com)
- Source and issue tracker: [github.com/Peternyaga/timeline-curator](https://github.com/Peternyaga/timeline-curator)
- Codex plugin guidance: [learn.chatgpt.com/docs/plugins](https://learn.chatgpt.com/docs/plugins)
