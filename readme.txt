=== Social2WP ===
Contributors: social2wp
Tags: instagram, sync, social media, posts, automation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
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

1. Create an account at social2wp.com and connect your Instagram
2. Install and activate this plugin on your WordPress site
3. Copy the plugin sync endpoint URL from Settings → Social2WP
4. Paste it into your Social2WP dashboard when connecting this WordPress site
5. Social2WP handles the rest — your Instagram posts arrive here automatically

Images are downloaded from Instagram and stored in your WordPress Media Library. The first image in each post is set as the featured image. Each post links back to the original Instagram post.

**Privacy:**

This plugin receives data sent from the Social2WP service (social2wp.com). It does not independently collect or transmit any user data. For details on how Social2WP handles your Instagram data, see the [Social2WP Privacy Policy](https://social2wp.com/privacy).

== Installation ==

1. Upload the `social2wp` folder to `/wp-content/plugins/`
2. Activate through the Plugins screen in WordPress
3. Go to **Settings → Social2WP**
4. Copy the plugin endpoint URL and enter it in your Social2WP dashboard

== Frequently Asked Questions ==

= Do I need a Social2WP account? =

Yes. This plugin is a companion to the Social2WP service at social2wp.com and does nothing on its own. You must have an active Social2WP account and connect your Instagram through their dashboard before any posts will arrive at this plugin.

= What gallery formats are supported? =

Native WordPress Gallery is available on all sites with no additional plugins required. Masonry layout requires the [Simply Gallery Block](https://wordpress.org/plugins/simply-gallery-block/) plugin to be installed and active. The masonry option only appears in settings when Simply Gallery Block is detected.

= Where do I find the sync endpoint URL? =

Go to Settings → Social2WP in your WordPress admin. The URL is displayed at the top of the page. Copy it and paste it into your Social2WP dashboard when connecting this site.

= What data does this plugin store? =

Each synced post stores the original Instagram post ID and permalink as post meta (`_social2wp_post_id` and `_social2wp_permalink`). This is used to prevent duplicate posts. Images are saved to your WordPress Media Library like any other uploaded file.

= Does this plugin send any data externally? =

No. The plugin only receives data — it never initiates outbound requests on its own.

== Screenshots ==

1. Settings page showing gallery format, post status, category, and author options

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
