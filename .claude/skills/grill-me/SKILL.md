---
name: grill-me
description: Use before starting any non-trivial coding task — a new feature, a refactor, a bug fix with unclear scope, or any instruction that leaves room for interpretation. Runs a short clarifying interview before writing any code.
---

# Grill Me

Before doing any work on an instruction, clarify it first. Do not start
writing code, running commands, or making changes until this process is
complete and the developer has confirmed alignment.

## Rules

- **Never silently assume.** Anywhere the instruction is ambiguous,
  underspecified, or has more than one reasonable interpretation, ask.
- **Don't just ask open questions.** For each point of ambiguity, give a
  short list of sensible options and **state your recommendation** with a
  one-line reason — the developer should be able to just say "go with
  your suggestion" rather than having to design the answer themselves.
- **Leave room for more detail.** After presenting the questions/options,
  explicitly invite the developer to add context, correct an assumption,
  or explain further — don't treat the first answer as necessarily final.
- **Iterate, don't loop forever.** Ask what's still unclear after each
  round of answers. Stop once there's nothing left that would change how
  you'd implement it — don't manufacture extra questions for their own
  sake once genuine ambiguity is resolved.

## Process

1. Read the instruction. Identify every point where a reasonable
   implementation could go more than one way (scope, edge cases, data
   shape, UX behaviour, what "done" means, error handling, naming,
   architecture choices that aren't dictated by existing patterns).
2. For each point, present it as: the question, 2–4 concrete options, and
   which one you'd pick and why.
3. Ask if there's anything else the developer wants to add, correct, or
   detail further before work starts.
4. Once the developer responds, check whether new ambiguity was
   introduced or anything is still unresolved. If so, repeat step 2–3 for
   just the remaining points — don't re-ask what's already settled.
5. Once aligned, summarise the agreed plan in a few lines and confirm
   before starting the actual work.

## What this is not

This isn't a stalling tactic or a way to avoid making calls — where the
codebase, the brief, or prior conversation already answers something,
don't re-ask it. This is specifically for the parts a developer would
otherwise have to catch in review because they weren't decided up front.
