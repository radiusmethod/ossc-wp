=== Open Source Software Contributions ===

Contributors: pjaudiomv, radius314
Tags: ossc, open source software contributions, github, pull requests
Requires at least: 6.2
Tested up to: 6.6.1
Stable tag: 1.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays Pull Request links from GitHub for Open Source Software Contributions simply add [ossc] shortcode to your page and configure settings.

== Description ==

Displays Pull Request links from GitHub for Open Source Software Contributions simply add [ossc] shortcode to your page and configure settings.

SHORTCODE
[ossc]

EXAMPLES

<a href="https://radiusmethod.com/oss/">https://radiusmethod.com/oss/</a>

### Third-Party Service Disclosure

This plugin relies on a third-party service, GitHub API, to function properly. The plugin fetches data from GitHub API under the following circumstances:

- When retrieving merged pull requests data to display within the application.

## Service Information

- **Service Used:** GitHub API
- **API Endpoint:** [GitHub API Documentation](https://docs.github.com/en/rest/reference/pulls)
- **Terms of Use:** [GitHub Terms of Service](https://docs.github.com/en/github/site-policy/github-terms-of-service)
- **Privacy Policy:** [GitHub Privacy Statement](https://docs.github.com/en/github/site-policy/github-privacy-statement)

### MORE INFORMATION

<a href="https://github.com/radiusmethod/ossc-wp" target="_blank">https://github.com/radiusmethod/ossc-wp</a>

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the entire Open Source Software Contributions Plugin folder to the /wp-content/plugins/ directory
2. Activate the plugin through the Plugins menu in WordPress
3. Update the following settings in WordPress Dashboard->Settings->OSSC.
   1. Add the GitHub API Token to the Open Source Software Contributions
   2. Add a comma separated string of GitHub repos you want to include contributions of. Ex. `someorg/somerepo,someorg2/somerepo2`.
   3. Add a comma separated string of GitHub users you want to search contributions of within those repos. Ex. `someuser1,someuser2`.
4. Add [ossc] shortcode to your WordPress page/post.

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Changelog ==

= 1.1.2 =

* First WordPress plugin repository release.

= 1.1.1 =

* Added pagination support for Git Repos.

= 1.1.0 =

* Contributions are now stored in database and updated daily with WP Cron.

= 1.0.1 =

* Use just +author over +or+author which generally is only needed when you use multiple logical operators.

= 1.0.0 =

* Initial Release
