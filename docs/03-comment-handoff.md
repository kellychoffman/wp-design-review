# Comment and Notes handoff

A civilian comment should become something an editor can act on **without asking the civilian what they meant**.

## The journey of one comment

1. **Visitor taps a spot** on the page. The tap snaps to the nearest block (or a style region, like "headings" or "page background" when they tap empty space).
2. **They type one line**, and optionally pick a mood: *Love it*, *Feels off*, *Can't read it*. Name is asked once, no account.
3. **The system quietly attaches context** the visitor never has to think about: which page, which viewport (phone, tablet, desktop), whether compare-to-live was on, and a small screenshot crop of what they were looking at.
4. **It lands as a Note** on that block, in the editor, with a "From review link" tag and the visitor's name. The editing team stays in one place.
5. **The editor replies or resolves.** Resolving posts back to the visitor's link as "Addressed", so the client sees progress without a meeting.

## What the editor sees

> **Dana (client) · via review link · phone · 2 min ago**
> "Hero feels too tight on phone"
> [screenshot crop] · Feels off · Compare was off
> `Reply` `Resolve` `Jump to block`

## Style-region comments

Some feedback isn't about a block: "the whole site feels too gray." These attach to a *style region* (colors, type, spacing) and appear in the Styles panel with the same card. That way a Global Styles pass can be checked off against real feedback.

## Edge cases and decisions

| Situation | Behavior |
|---|---|
| Block was deleted after the comment | Note stays, marked "block removed", still visible on the page's notes list |
| Visitor comments on a page that then goes live | Notes remain; the link flips to "This redesign is live" |
| Very long or multiple comments | Each is its own Note; no threads on the visitor side in v1 |
| Abuse or spam | Comment rate limits per link; owner can turn commenting off without revoking |
| Visitor wants to see others' comments | Off by default. They see only their own and replies to them |

## Soft approve

A single button in the bar: **Looks good to me**. It records a name and time, notifies the owner, and shows as a badge on the review in the editor. It gates nothing. The owner can still publish without it, and can still ask "did anyone approve?" before doing so.
