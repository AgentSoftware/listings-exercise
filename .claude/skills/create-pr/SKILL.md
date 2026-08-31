---
name: create-pr
description: Use when the user asks to create a pull request, open a PR, or ship their current branch for review. Handles PR creation, Jira linking, Copilot review, and stacking large PRs.
---

# Create Pull Request

Follow these steps in order. Do not skip the size check — it comes before
opening the PR, not after.

## 1. Review the diff and check size

Run `git diff main...HEAD --stat` (or the relevant base branch) to see
what's changed.

If the diff is large or touches multiple unrelated concerns, **stop and
propose a stack** instead of one PR: break the branch into a sequence of
smaller branches/PRs, each depending on the previous one, each reviewable
on its own (e.g. "schema + migration" → "backend logic" → "frontend").
Describe the proposed stack to the user and confirm before restructuring
history. Only proceed to a single PR once the change is a sensible,
reviewable unit.

## 2. Ask for the Jira ticket link

Before creating the PR, ask the user for the Jira ticket link if one
hasn't been provided. Don't guess a ticket number or invent one.

## 3. Write the PR description

Summarise the actual changes (not a commit-by-commit log) — what changed,
why, and anything a reviewer should pay attention to. Include the Jira
link in the description (e.g. under a "Ticket:" line).

## 4. Create and mark ready for review

gh pr create --title "<concise title>" --body "<description>" --base main
gh pr ready <pr-number> # only if it was opened as a draft


Confirm the PR is not left in draft state unless the user asked for that.

## 5. Request a Copilot review

gh pr edit <pr-number> --add-reviewer @copilot


Wait for Copilot's review to post.

## 6. Triage Copilot's suggestions

For each suggestion Copilot makes:

- **If you agree** — apply the fix, commit, and push.
- **If you disagree** — do not apply it. Instead, reply on the PR (or
  summarise to the user) explaining specifically why the suggestion is
  wrong or not worth taking, so the user can make the final call. Never
  silently ignore a suggestion — every one gets either fixed or explained.

## 7. Watch for further activity

After the above, continue monitoring the PR for new commits, new Copilot
review rounds triggered by pushes, or human reviewer comments, and repeat
step 6's triage for anything new that comes in — until the user says
they're done or the PR is merged.
