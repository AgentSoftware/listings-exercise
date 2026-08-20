# Technical Exercise — Saved-Search Alerts

Thanks for taking the time. This is a small, realistic piece of engineering: you're adding a feature to the existing Street Listings app (see [`README.md`](README.md) to get it running). We care far more about how you think and the decisions you make than about how much you produce. Please read the whole brief before you start.

## Ground rules

- **Time-box it to 3–4 hours.** We are not looking for a finished product, and we don't want you losing a weekend to it. If you run out of time, stop and write down what you'd do next — that's useful signal, not a gap.
- **Use AI tooling.** We work AI-natively (Claude Code and the like) and expect you to. We're interested in how you direct and review it, not whether you can type it all by hand. Everything you submit is yours, so review what the tools produce.
- **Work with the codebase, not against it.** Follow the patterns already there. Reading an unfamiliar codebase well enough to fit into it is part of the exercise. If you think an existing pattern is wrong, say so rather than silently working around it.
- **Don't gold-plate.** A clean, well-tested vertical slice beats a broad, shallow one.

## Background

Buyers using Street Listings want to hear about new properties without checking the site every day. We want to let them **save a search and be alerted when a new listing matches it.**

A couple of notes from the team that prompted this:

> "Buyers keep asking to be told when something new comes up in their area and budget, instead of refreshing the page." — Product

> "Whatever we do, please don't spam people. We've had complaints about too many emails from other tools." — Support

## The task

Let a user save a search and be alerted when a new listing matches it.

1. A user can **create, view and delete saved searches**. A saved search is a set of criteria — for example: maximum price, minimum bedrooms, property type, and region.
2. When a **new listing becomes live** that matches one of a user's saved searches, an **alert** is generated for that user.
3. A user can **see their alerts**.

That's the core. A few deliberate notes:

- You do **not** need to send real emails or run a queue worker. A persisted alert record, or notifications on the log/database driver, is fine. If your design implies a queue or scheduled job, describe it — you don't have to operate it.
- The small product edges are yours to decide — e.g. what exactly counts as a "match". Make a sensible call and say why.
- Remember auth is stubbed (see the README) — build against `auth()->user()`.

## Optional — only if you have time

You don't need to build these. If you have time, pick up whichever interests you; **if not, a sentence in your notes on how you'd approach it is just as good.** They're here because they're the kind of decision this feature really raises:

- **Backfill.** When a user saves a search, should they be alerted about listings that are *already* live and match — or only ones that go live from now on?
- **Duplicate alerts.** A new listing matches two of the user's saved searches. One alert, or two?
- **Criteria.** We've suggested a few criteria; support the ones you think matter. Is a price *range* better than a max? Would you add anything, or leave anything out?

## What to hand back

- **A fork of this repo** with your work on it. Fork it, commit on a branch, and open a pull request **against your own fork** rather than against this repository — that keeps your solution to yourself, and still gives us a diff to read. Send us the link.
- A short **`NOTES.md`** (half a page is plenty): the key decisions and trade-offs you made, what you deliberately left out, what you'd do with more time, and — honestly — where your solution wouldn't hold up yet (for example, as the data grows). We read this closely; a lot of the signal is here.

## What happens next

If this goes well, the follow-up is a working session where **we extend this feature together** — you don't need to prepare anything, we'll pick it up from your submission. Build it the way you'd want to pick it up yourself in a month.

## What we're looking for

In rough order: sound design decisions with trade-offs you can defend; code we'd be happy to maintain; tests that assert the behaviour that matters; and clear reasoning about what you built and why. Not: feature quantity, visual polish, or a perfect solution.

If anything's unclear, make a reasonable assumption, note it, and carry on. Good luck.
