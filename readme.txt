=== GIF to WebM ===
Contributors: Finland93
Tags: gif, webm, video, performance, shortcode
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Output GIFs as lightweight autoplaying WebM videos with an automatic GIF fallback, via a simple shortcode.

== Description ==

Store a GIF + WebM pair in the admin and drop a shortcode anywhere to display the small WebM video, falling back to the GIF automatically if WebM playback fails.

== Changelog ==

= 2.0.0 =
* Fixed: generated shortcode is no longer empty (1.0 saved [gif-video id='']).
* Fixed: the WebM->GIF fallback now actually works and only loads where needed.
* Fixed: delete and the add/edit form are nonce-protected with capability checks.
* Added: edit existing entries, optional link/dimensions, lazy loading, escaping, clean uninstall.

= 1.0 =
* Initial release.
