---
name: yt-audit
description: >-
  Audit a YouTube channel end to end - packaging, consistency, the first
  fifteen seconds, and what to fix first. Use for "audit my channel", "why
  isn't my channel growing", "review my videos", or a pasted channel URL.
---

# yt-audit

An audit that lists twenty problems is a way of avoiding the one that matters. This ends in ONE fix.

For dialogue-free Poster Shorts, inspect the visible action and payoff directly. Use Studio retention only when available. Public views and other platforms’ watch times cannot establish where YouTube viewers left.

## Read the owner’s YouTube Analytics

The project-local [analytics.py](analytics.py) needs Python 3.10+ and runs independently of Poster. Enable **YouTube Data API v3** and **YouTube Analytics API** in the OAuth project. Download a **Desktop app** OAuth client to `storage/app/private/youtube/desktop-client.json` (or pass `--client PATH` before the subcommand). Keep it and the generated refresh token private. See [Google desktop OAuth](https://developers.google.com/identity/protocols/oauth2/native-app).

Run from the repository root on the computer with the user's browser. On the first run the user selects their channel and grants the two read-only scopes. If you know the expected channel ID, pass `auth --channel UC...` to verify it before replacing the local grant.

```sh
tool=.agents/skills/youtube-agent-skill/skills/yt-audit/analytics.py
python3 "$tool" auth
python3 "$tool" report FYkol8VhNqc ouFAcr71mTM --start 2026-11-15 --end 2026-11-19
python3 "$tool" report ouFAcr71mTM --retention
```

Use the actual video IDs and dates for the audit. The default window is the last 28 Pacific calendar days, inclusive. Compare episodes at a similar age. The first report returns views, engaged views, average duration in seconds, average percentage viewed, likes, comments, shares and subscribers gained. The optional second report returns timeline and watch ratios as fractions; multiply timeline position by duration for approximate seconds. An empty row list means YouTube supplied no data for that video and period. Allow for reporting lag. Use first-party Studio for swipe-away rates. [Supported channel reports](https://developers.google.com/youtube/analytics/channel_reports)

## Before you write

1. Read `storage/app/private/youtube/voice.md` if it exists. That is the user's voice profile: how they talk
   on camera, the words they never use, who they are talking to, what they will not claim. If it
   does not exist, ask for **three of their own videos**, read or transcribe them, infer the voice,
   and write the file. A script in the wrong voice is worse than no script, because they have to
   read it out loud.
2. Never invent a number, a result or a source. If a figure would strengthen it and you do not have
   one, ask for it or write the line without it.

## What to look at, in this order

1. **The last ten titles, as a set.** Read them as a list, the way the channel page shows them. Do
   they promise different things? Run them through `../yt-package/title.py`. A channel where every
   title is the same shape has a format problem, not a title problem.
2. **The thumbnails, at feed size.** Shrink them. What survives? If three of them are unreadable at
   that size, that is the fix and nothing else matters yet.
3. **The first fifteen seconds of the three most recent.** Transcribe them and score with
   `../yt-script/hookscore.py`. This is where most channels lose.
4. **Upload rhythm.** Not frequency - CONSISTENCY. Six videos in one week and then nothing for a
   month is worse than one a fortnight forever.
5. **The retention shape**, if they can export it. `$yt-retention`.

## What to hand back

- The single biggest fix, named, with what to do this week.
- Three things that are already working, so they do not break them. Be specific; "your energy is
  good" is not an observation.
- What NOT to do yet, and why.

Never open an audit with praise you do not mean, and never end one with a list of twenty things.

## The gate

Nothing here publishes. This skill writes and you publish. Every output ends in a block the user
copies, and the last line of every run is the question: **ship it, or change it?**
