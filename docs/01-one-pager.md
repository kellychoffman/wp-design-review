# WP Design Review: share the look, not the editor

*Working title. Narrative one-pager for design leadership and product.*

## The promise

**Share a link. They see the real site looking like the redesign. They leave feedback. You publish when it's done.**

## The problem

Redesign reviews in WordPress happen in the wrong place, twice over.

- **Comps drift.** Teams export PDFs and Figma frames. By the time a stakeholder reacts, the real theme has already disagreed with the picture: line breaks, spacing on phones, how the font actually renders. Feedback gets spent on differences that aren't real.
- **The editor is a scary room.** The alternative is inviting a client into the editing experience with a role that lets them break things. Most clients never want to be there. They want to look at a thing and say "this feels off."

WordPress already has the ingredients: drafts, revisions, Global Styles, Notes. It has never packaged them as one simple thing a non-technical person can open: *this page, with these pending styles.*

## The product in one picture

A designer finishes a pass and chooses **Share review** instead of Publish. They get a link, with an optional password and an expiry. The client taps it on their phone and sees their real site wearing the redesign, under a thin bar that says what they're looking at and when it expires. They tap a spot, say "hero feels too tight on phone," and it lands in the same place the editing team already works. When the designer publishes, the link either retires or says "This redesign is live."

## Why now

- Global Styles and style variations made "a whole look" a first-class, swappable thing. Reviewing one used to mean redoing CSS. Now it means flipping a switch.
- Notes gave the editor a home for block-level feedback. A review link can feed it instead of inventing a second inbox.
- Stakeholders live on their phones and expect Figma-grade sharing. A link that needs a login feels broken.
- Agencies and in-house design orgs are asking for this in different words: "staging is too heavy," "clients keep emailing screenshots."

## Who it's for

Agencies and in-house design orgs reviewing redesigns with non-technical clients. Site owners who get comps that don't match the theme. Anyone already using drafts, revisions, Global Styles or Notes who wants a civilian-facing share surface.

## What success looks like

A non-designer opens one link on their phone, understands they're reviewing a redesign, leaves one useful comment, and never sees the Site Editor.

## Design principles

1. **Civilian-first.** One sentence of understanding: "look at this link."
2. **Real site, not a mock.** No parallel Figma truth needed for feedback rounds.
3. **Thin, branded chrome.** The review bar never feels like wp-admin.
4. **Trust is visible.** Expiry, revoke and a "preview, not live" cue are always on screen. Unpublished copy never leaks silently.
5. **Sits next to what exists.** Drafts, revisions, Global Styles, Notes. No second content model.

## How we resolved the open questions (recommendations)

| Question | Recommendation for v1 | Why |
|---|---|---|
| **Scope** | Three choices in one picker: *This page*, *Whole site with pending look*, *A named look* (e.g. "Summer refresh"). Default to *This page*. | Matches how people talk about reviews. The named look is the one that scales to agencies juggling options A and B. |
| **Visitor power** | View and comment by default. Soft approve is a per-link toggle. | Comments are the point. Approval is a social signal, not a permission, so it stays optional. |
| **Applying pending styles to live content** | The look is applied only while rendering the review link. Nothing changes for normal visitors, and nothing is saved as a side effect. | The real theme renders the real content, so there's no drift, and there's nothing to clean up afterwards. |
| **Draft content leakage** | Off by default. A link shows published copy in the pending look. Showing unpublished copy is an explicit opt-in that changes the bar to say so. | Safest default. The visible label means nobody is surprised. |
| **Compare to live** | One-tap toggle (hold to peek) on phones. Side-by-side is a desktop follow-up. | A toggle keeps the same scroll position, which is what makes the difference legible on a small screen. |
| **Agency branding** | Logo and accent color are customizable. Expiry, "preview" status and revoked or live states are fixed. | Agencies get to look like themselves. Clients keep the trust cues they rely on. |

## The deliverables in this repo

| # | Deliverable | Where |
|---|---|---|
| 1 | Narrative one-pager | this file |
| 2 | Three flow sketches | [design/index.html](../design/index.html), section 2 |
| 3 | Review chrome kit | [design/index.html](../design/index.html), section 3 |
| 4 | Comment and Notes handoff | [design/index.html](../design/index.html), section 4 and [03-comment-handoff.md](03-comment-handoff.md) |
| 5 | Trust and privacy sheet | [02-trust-and-privacy.md](02-trust-and-privacy.md) |
| 6 | Out of scope for v1 | [04-out-of-scope.md](04-out-of-scope.md) |
| Appendix | Engineering notes (later) | [05-engineering-appendix.md](05-engineering-appendix.md) |
