# WP Design Review

**Share the look, not the editor.**

Send a link. They see your real site wearing the redesign. They leave feedback. You publish when it's done.

WP Design Review makes a signed, expiring link to a page (or the whole site) with pending redesign styles applied. Clients open it on their phone with no login, see a thin review bar instead of anything from wp-admin, and leave comments that land as **Notes** on the exact block, in the editor your team already uses.

## What you get

- **Share review panel** in the block editor sidebar. Pick a look, an expiry (1 to 30 days), what visitors can do (view, comment, comment and approve), and an optional password. Copy the link. Revoke any time.
- **A pending look, not a mock.** A "look" is one of your theme's style variations. It is applied only while rendering the review link. Nothing is saved, and normal visitors never see it.
- **Whole-site reviews** from the Design Reviews screen: the entire site under a look, every page browsable.
- **Compare to live** in one tap, keeping your scroll position.
- **Thin, branded review bar** that shows what they're looking at and when it expires. Set your logo, name and color; the expiry and status wording stay fixed.
- **Tap to comment.** Visitors tap a block, write a line, pick a mood, and send. It becomes a Note on that block (or a reply if the block already has a thread). Comments not tied to a block are logged on the Design Reviews screen.
- **Closing the loop.** Mark a comment addressed (or resolve the Note in the editor) and the reviewer sees "Addressed" next to their comment.
- **Soft approve.** A "Looks good" button that notifies you. It gates nothing.
- **Publish and expire.** Publishing a shared page flips its links to "This redesign is live." "Publish this look" on the Design Reviews screen saves the look as the site's styles and does the same. Links retire a week later.

## Trust and privacy

- Links are unguessable tokens, always expire (default 7 days, max 30), and can be revoked instantly.
- Review pages are never cached and tell search engines to stay away. No admin bar, edit links or canonical URLs.
- **Draft copy never appears unless you share a page that isn't published**, and then the review bar says "Includes unpublished copy". A link can only ever show the one page it was made for.
- Password-protected links rate-limit guesses. Comments are rate-limited per link.
- Expired and revoked links show a friendly page that reveals nothing about the content.
- Visitors don't get accounts. A name typed once is stored in their own browser.

## Requirements

- WordPress 6.9+ (Notes) and PHP 8.0+
- A block theme with style variations to use looks (Twenty Twenty-Five works well). Without one you can still share a page, just not a look.

## Try it

Playground (no setup, needs Node):

```bash
cd ~/wp-design-review && npx @wp-playground/cli@latest server --blueprint=playground/blueprint.json --mount=$PWD:/wordpress/wp-content/plugins/wp-design-review
```

Or copy the folder into `wp-content/plugins/` and activate it. Then open any page in the editor, find **Share review** in the Page sidebar, and send yourself the link.

## Things to know

- Review tokens are stored in the database (so you can copy a link again later). Anyone with database access could see them, but they could see your drafts too.
- Page caching plugins and CDNs must honor `DONOTCACHEPAGE` / `Cache-Control: no-store` for review URLs. Most do.
- "Publish this look" replaces the site's current global styles with the look. WordPress keeps style revisions.
- Comments become Notes by adding a note reference to the block in post content, the same way the editor does. Each comment that starts a new thread therefore adds a revision to the page.

## Out of scope for v1

Collaborative editing, realtime presence, AI actors, custom CSS pasting, visitor accounts, hard approval gates, side-by-side compare, viewport switching, multisite.

## Design background

The original brief and design exploration live in [docs/](docs/01-one-pager.md) and [design/index.html](design/index.html).
