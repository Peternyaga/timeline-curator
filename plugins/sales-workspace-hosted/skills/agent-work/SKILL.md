---
name: fieldwork-agent-work
description: Process queued Fieldwork Reach agent work and reversal requests from the local poller, with durable reports.
---

# Process Fieldwork work items

Read pending items with `list_agent_work`; handle at most ten in one run. Read the lead and existing follow-ups before deciding. Never assume a call happened or invent a callback commitment. If no reliable time can be chosen, use `resolve_agent_work` with `needs_review` and a clear reason.

For an ordinary item, use `schedule_agent_followup` with a plain-language summary of the source, decision, and new internal task. The tool records the task and report together. If no change is needed, use `resolve_agent_work` with a factual reason. Do not use generic write tools to process queue items, because they cannot close the queue item and create its report atomically.

For `reverse_work`, use `reverse_agent_work` on that work-item ID. The server will undo only an unchanged, still-pending follow-up created by the original item. If it has changed or been completed, the server records `needs_review`; do not force a reversal through another tool. Do not contact a lead or send an external message.
