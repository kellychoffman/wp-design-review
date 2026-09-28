# Engineering notes

How the plugin works. Kept separate from the design docs on purpose.

## Structure

- `wp-design-review.php`: bootstrap and autoloader.
- `src/Reviews.php`: reviews as private `dr_review` posts with meta (token, scope, target, look, expiry, password hash, visitor level, state, views, approvals). Status is computed: active, live, expired, ended, revoked. Looks are the theme's style variations, deduplicated by title (the fuller variation wins).
- `src/Frontend.php`: detects `?dr_review=` (then a cookie), gates it (ended page, password page), and decides scope after the main query runs.
- `src/Feedback.php`: visitor comments. Each is logged as a private `dr_feedback` post and, when it points at a block, also becomes a Note.
- `src/Rest.php`: editor routes (capability checked) and visitor routes (authorized by the link token in an `X-WPDR-Token` header).
- `src/Admin.php`: the Design Reviews screen and editor assets.
- `assets/editor.js`: Share review sidebar panel, no build step.
- `assets/review-bar.js`: the review bar in a shadow root so theme CSS cannot touch it.

## Applying a look

A filter on `wp_theme_json_data_user` merges the variation over the site's user styles for that request only. The scope is only known after the main query, so the filter is inert until `resolve_scope` turns it on and clears the theme JSON caches. Those caches live in a non-persistent group, so the pending look cannot leak to other requests.

## Unpublished pages

For a page-scope review of a draft, `posts_pre_query` returns that one post, cloned with a published status, for that link only. Canonical redirects are switched off for that request. Nothing else changes.

## Comments to Notes

The bar tags each rendered block of the shared post with `data-dr-block`, a path of indexes into the parsed block tree (matched by a hash of name, attributes and inner content while inside `the_content`). The server walks that path, inserts a `note` comment, and sets `metadata.noteId` on the block. If the block already has a thread the comment becomes a reply. Saving content temporarily removes the kses filters, since the request has no user. Resolving adds an empty child note with `_wp_note_status = resolved` on the thread root, which is what the editor does.

## Known gaps

- No automated tests yet. Behavior was checked by hand against WordPress 7.0 in Playground: link creation, look applied only to the review request, drafts only via opt-in, password gate, scope, compare, comment to Note (including replies), resolve, revoke, publish and live state.
- Looks come from style variations only. There is no saved "pending Global Styles" concept in core to read from.
- Side-by-side compare, viewport switching and visitor-visible threads are not built.
