# Engineering appendix (draft, for later)

Kept separate on purpose: the design docs avoid implementation terms. This is the starting sketch for a first build. Nothing here is verified yet.

## Shape of the plugin

- **Reviews** stored as a private custom post type or table: token hash, scope, target post IDs or style variation, expiry, password hash, options, status, created by.
- **Signed URL**: `/?dr_review=<token>`, or a pretty path. Store only a hash of the token. Token comparison is constant-time.
- **Request handling**: on `template_redirect`, if a valid, unexpired, unrevoked token is present, set a request-scoped flag. Everything below applies to that request only.

## Applying the pending look

- Filter the site's Global Styles data for the request (`wp_theme_json_data_user` or the equivalent user-data filter) so the pending styles or the chosen variation replace the saved ones. Nothing is written to the database.
- Scope *page*: only the target post renders; other URLs 404 or redirect to live. Scope *site*: all published URLs render under the pending look, with the token carried in a cookie or a path prefix.
- **Compare to live**: the same request with the filter off. Toggle swaps via a query flag and restores scroll position on the client.

## Draft content

- Default: query as normal, published content only.
- Opt-in: filter `the_content` / post lookup to the latest revision or draft for target posts only. Bar shows the "includes unpublished copy" line.

## Review chrome

- Injected on `wp_footer`/`wp_body_open` in a shadow-DOM container so theme CSS can't break it and it can't leak into the theme.
- No admin bar (`show_admin_bar` false for the request).

## Comments to Notes

- Visitor POST to a REST route validated by token, rate limited.
- Creates a Note (block comment) authored by a synthetic "reviewer" identity, with meta: name, viewport, compare state, screenshot ref.
- Resolve on the editor side writes back a status the review page polls or fetches on load.

## Headers and hygiene

- `X-Robots-Tag: noindex, nofollow`, `Cache-Control: private, no-store`, exclude from sitemaps and feeds, no Open Graph.
- Publish hook: on publish of target content, set status to `live`, schedule retirement.

## Open technical questions

- Which caching layers (page cache, CDN) reliably honor the bypass for tokened requests.
- Whether a style variation preview can be scoped to the request cleanly in current core, or needs a wrapper.
- How Notes handle a non-user author.
