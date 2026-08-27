=== Social2WP ===
Contributors: social2wp
Tags: instagram, sync, social media, posts, automation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion plugin for Social2WP — automatically formats and publishes your synced Instagram posts your way.

== Description ==

**This plugin requires an active Social2WP account at [social2wp.com](https://social2wp.com). Without one, the plugin installs successfully but will not receive any data.**

[Social2WP](https://social2wp.com) is a service that monitors your Instagram account and automatically syncs new posts to your WordPress site. This companion plugin handles the WordPress side — it receives the post data from Social2WP, downloads your images into the Media Library, formats the content into blocks, and creates the post.

**Settings:**

* **Gallery format** — native WordPress gallery, or masonry layout if you have Simply Gallery Block installed
* **Post status** — keep posts as drafts for review, or publish them automatically
* **Default category** — assign every synced post to a category automatically
* **Post author** — choose which WordPress user appears as the author

**How it works:**

1. Install and activate this plugin on your WordPress site
2. Go to **Settings → Social2WP** and click **Connect to Social2WP**
3. Create a Social2WP account and start your free trial
4. Connect your Instagram account through Facebook
5. Social2WP handles the rest — your Instagram posts arrive here automatically

Images are downloaded from Instagram and stored in your WordPress Media Library. The first image in each post is set as the featured image. Each post links back to the original Instagram post.

**Privacy:**

This plugin communicates with the Social2WP service (social2wp.com) in two ways: it receives post data sent by Social2WP when syncing, and it checks your account status with social2wp.com when an admin views the plugin settings page (sending only the API key generated during setup). No user-facing data is independently collected or transmitted by this plugin. For details on how Social2WP handles your Instagram data, see the [Social2WP Privacy Policy](https://social2wp.com/privacy) and [Terms of Use](https://social2wp.com/terms).

== Installation ==

1. Upload the `social2wp` folder to `/wp-content/plugins/`
2. Activate through the Plugins screen in WordPress
3. Go to **Settings → Social2WP** and click **Connect to Social2WP**
4. Follow the prompts to create your account, start your free trial, and connect Instagram

== Frequently Asked Questions ==

= Do I need a Social2WP account? =

Yes. This plugin is a companion to the Social2WP service at social2wp.com and does nothing on its own. Click **Connect to Social2WP** on the plugin settings page to create your account and connect your Instagram — the whole process takes just a few minutes.

= What gallery formats are supported? =

Native WordPress Gallery is available on all sites with no additional plugins required. Masonry layout requires the [Simply Gallery Block](https://wordpress.org/plugins/simply-gallery-block/) plugin to be installed and active. The masonry option only appears in settings when Simply Gallery Block is detected.

= How do I connect my WordPress site to Social2WP? =

Go to Settings → Social2WP in your WordPress admin and click **Connect to Social2WP**. This opens social2wp.com where you can create an account, start your subscription, and connect your Instagram — your WordPress site is linked automatically at the end of that process.

= What data does this plugin store? =

Each synced post stores the original Instagram post ID and permalink as post meta (`_social2wp_post_id` and `_social2wp_permalink`). This is used to prevent duplicate posts. Images are saved to your WordPress Media Library like any other uploaded file.

= Does this plugin send any data externally? =

Minimally. When an admin views the plugin settings page, the plugin sends a status request to social2wp.com (using the API key generated during setup) to check whether your account is active. No WordPress user data is included in that request. The plugin never initiates outbound requests outside of this status check and the initial connect flow.

== Screenshots ==

1. Settings page showing gallery format, post status, category, and author options

== Changelog ==

= 1.2.1 =
* Added: caption font size setting — choose Small, Medium, Large, Extra Large, or Theme default

= 1.2.0 =
* Added: hashtags and @mentions in synced captions are now linked to their Instagram pages automatically

= 1.1.7 =
* Fixed: paragraph line breaks generated as XHTML <br /> instead of HTML5 <br>, causing Gutenberg to highlight affected text in the draft editor

= 1.1.6 =
* Fixed: masonry gallery block serialized with inner HTML instead of self-closing format, causing "Block contains unexpected or invalid content" on every synced post

= 1.1.5 =
* Fixed: reconnecting after a plugin-side disconnect failed because the API key was not regenerated before starting the connect flow

= 1.1.4 =
* Security: removed edit_posts capability fallback from API key authentication — API key is now the only accepted credential
* Security: capped media arrays to 20 items in create_post() to prevent unbounded attachment uploads
* Fixed: temp file cleanup failure now logged instead of silently suppressed

= 1.1.3 =
* Fixed separator color — replaced hex color picker with theme palette swatch picker; colors now use CSS classes instead of inline styles, which are stripped by some themes (including Astra)

= 1.1.2 =
* Added featured image setting — choose whether the first image is set as the featured image on each synced post (defaults to off)
* Fixed separator block rendering — removed redundant color property and added proper newlines to block markup

= 1.1.1 =
* Updated setup instructions to reflect new onboarding order — connect Instagram before starting trial
* Updated privacy section to accurately describe outbound status check

= 1.1.0 =
* Added one-click Connect to Social2WP flow — no manual API key or endpoint setup needed
* Added how-to-connect instructions directly on the settings page
* Added divider style setting — choose from any separator styles registered by your theme or plugins
* Added divider color setting — pick a custom color for the divider between image and caption
* API key moved to a collapsed "Advanced" section since it is now managed automatically

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.1 =
Minor readme and instructions update. No code changes.

= 1.1.0 =
Adds one-click connect flow and new divider style and color options. Existing connections are not affected.

= 1.0.0 =
Initial release.
