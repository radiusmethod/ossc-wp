# Open Source Software Contributions WordPress Plugin

## Description

Displays Pull Request links from GitHub for Open Source Software Contributions simply add [ossc] shortcode to your page and configure settings.

## SHORTCODE
[ossc]

## EXAMPLE
[https://radiusmethod.com/oss/](https://radiusmethod.com/oss/)

## Installation

This section describes how to install the plugin and get it working.

1. Upload the entire Open Source Software Contributions Plugin folder to the /wp-content/plugins/ directory
2. Activate the plugin through the Plugins menu in WordPress
3. Update the following settings in WordPress Dashboard->Settings->OSSC.
   1. Add the GitHub API Token to the Open Source Software Contributions
   2. Add a comma separated string of GitHub repos you want to include contributions of. Ex. `someorg/somerepo,someorg2/somerepo2`.
   3. Add a comma separated string of GitHub users you want to search contributions of within those repos. Ex. `someuser1,someuser2`.
4. Add [ossc] shortcode to your WordPress page/post.
