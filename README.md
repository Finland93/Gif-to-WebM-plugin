# GIF to WebM — WordPress Plugin

Store GIF + WebM pairs and output them with a simple shortcode as a lightweight, autoplaying **WebM video** that automatically **falls back to the GIF** if WebM can't play. WebM is typically a fraction of a GIF's size, so this speeds up pages while keeping the animation everywhere.

## How to use

1. Convert your GIF to WebM (e.g. with the free [ezgif.com/gif-to-webm](https://ezgif.com/gif-to-webm)).
2. Upload **both** the GIF and the WebM to your Media Library.
3. Go to **GIF to WEBM** in the admin menu, paste both URLs (plus optional size and link), and save.
4. Copy the generated shortcode — e.g. `[gif-video id='12']` — into any post or page.

## Styling

| Element | CSS class |
| --- | --- |
| Container `<div>` | `.bannerVideo` |
| Video / image | `.bannerGif` |

```css
.bannerVideo { display: flex; justify-content: center; }
.bannerGif   { max-width: 100%; height: auto; }
```

## What changed in 2.0.0

The 1.0 version had three real bugs; all are fixed:

- **Saved shortcode was always empty** — `$shortcode_id` was used to build the shortcode string *before* `wp_insert_post()` had returned it, so every entry stored `[gif-video id='']`. The ID is now generated first and the correct shortcode is saved.
- **The fallback never worked** — the footer script looked for element IDs the shortcode never rendered, and it ran on every page. It's replaced by a properly scoped script that's only loaded where a shortcode appears and that actually swaps to the GIF when the WebM fails (handling both `<source>` and codec errors).
- **Delete was a CSRF risk** — entries were removed via an unprotected `GET` link. Deletes (and the add/edit form) are now nonce-protected with capability checks.

Also added: an **Edit** action (1.0 was add/delete only), optional links/dimensions, `loading="lazy"` + `playsinline`, output escaping throughout, and a clean uninstall.

## License

GPLv2 or later — see [LICENSE](LICENSE).

**Author:** [Finland93](https://github.com/Finland93)
