# Trust and privacy sheet

A review link is a door into a site that isn't finished. The rule: **nothing about it should ever surprise the owner or the visitor.**

## What a link is

- One link per review. Unguessable, signed, and tied to one scope (a page, the whole site, or a named look).
- Always has an expiry. Default 7 days, maximum 30. There is no "never expires" in v1.
- Optional password, set by the owner, shared separately.
- Can be revoked instantly by anyone who can edit the page.

## The owner's controls

| Control | Options | Default |
|---|---|---|
| Expiry | 24 hours, 3 days, 7 days, 14 days, 30 days | 7 days |
| Password | Off, or set one | Off |
| Visitor can | View only, Comment, Comment and approve | Comment |
| Show unpublished copy | Off, On | **Off** |
| Revoke | One button, effective immediately | n/a |

## What is always visible to the visitor

- That this is a **review, not the live site**.
- **When it expires**, in plain language ("Expires Friday").
- **Who shared it** (the site or agency name).
- If unpublished copy is included, a clear line saying so.

None of these can be hidden by agency branding.

## What never appears on a review link

- **Unpublished copy**, unless the owner opted in for that link.
- Private or password-protected pages the visitor couldn't otherwise see.
- Admin bar, edit links, or any hint of the editor.
- Other people's comments from unrelated reviews.
- Author emails, usernames or internal Notes.
- Anything from other pending reviews on the same site.

## Making sure it doesn't spread

- The page tells search engines to stay away and is never cached for anyone else.
- The link does not appear in sitemaps, feeds, or previews shared to social networks.
- Comments carry only the name a visitor chooses to type. No account is created.
- Expired and revoked links show a friendly page and reveal nothing about the content.

## What happens at each end state

| State | Visitor sees | Owner sees |
|---|---|---|
| Active | The redesign, with the review bar | Views, comments, expiry countdown |
| Expired | "This review has ended. Ask {owner} for a new link." | Option to extend or reissue |
| Revoked | Same as expired, no detail on why | Who revoked and when |
| Published | "This redesign is live." and a button to the real site | Link auto-retires after 7 days |

## Owner-side audit

Every link keeps a short log: created by, created when, scope, options, first opened, last opened, revoked or expired. Enough to answer "who could see what, and when?" without turning into analytics.
