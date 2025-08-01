=== Wicket - Memberships plugin for WordPress ===
Contributors: 
Tags: wicket
Requires at least: 6.0
Tested up to: 6.3
Requires PHP: 8.0
Stable tag: 6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This official Wicket plugin includes memberships functionality for integrating the Wicket member data into a WordPress installation.

= Features =
* Connect to Wicket API
* Developer tools and helper functions

= Links =
* [Website](https://wicket.io/)
* [API Documentation](https://wicketapi.docs.apiary.io/)
* [Support](https://support.wicket.io/)

== Installation ==

This plugin is not available in the WordPress.org plugin repository. It is distributed to Wicket clients for implementation by a developer who will add the plugin according to the project code process.


== Changelog ==

= 1.0.101 =
* Date June 10 2025
* Hide early renew callout when autorenew

= 1.0.100 =
* Date June 2 2025
* Bugfix: Prorated logic helper methods added

= 1.0.99 =
* Date May 22 2025
* Bugfix: Timezone issues when date select picker is used

= 1.0.98 =
* Date May 22 2025
* Bugfix: "Create Renewal Order" modal improvements

= 1.0.97 =
* Date May 12 2025
* Bugfix: Settings page form submission

= 1.0.95 =
* Date May 12 2025
* Added: Display email address on organization/individual member pages

= 1.0.94 =
* Date May 07 2025
* Added: Grant Owner Assignment on Org Membership

= 1.0.93 =
* Date Apr 23 2025
* Added: Grace period transitions
* Bugfix: status changemeta updates

= 1.0.91 =
* Date Mar 10 2025
* Added: Auto-expire memberships
* Bugfix: Update mship on org name changed

= 1.0.89 =
* Date Mar 3 2025
  Bugfix: Manual renewal order subscription id
* Added: Log membership status change

= 1.0.87 =
* Date Feb 27 2025
* Added: Membership owner link on edit membership screen

= 1.0.86 =
* Date Feb 21 2025
* Bugfix: Grace period status settings

= 1.0.85 =
* Date Feb 14 2025
* Bugfix: Changing membership dates on edit screen is selecting the previous day / wrong date

= 1.0.84 =
* Date Feb 12 2025
* Added ability to create renewal order for the memberships

= 1.0.83 =
* Date Jan 31 2025
* Grace period updates

= 1.0.82 =
* Date Jan 30 2025
* HPOS compatibility

= 1.0.81 =
* Date Jan 28 2025
* Clear next payment date

= 1.0.80 =
* Date Jan 22 2025
* Optimize and update sync tooling

= 1.0.79 =
* Date Jan 21 2025
* Add WC Logging for plugin errors

= 1.0.78 =
* Date Jan 6 2025
* Bugfix

= 1.0.76 =
* Date Jan 5 2025
* Current-Tier and Inherited-Tier added as Membership Page Edit options
* Bugfix when reloading Membership Page Tier Selection after update

= 1.0.75 =
* Date Jan 2 2025
* Bugfix

= 1.0.71 =
* Date Dec 12 2024
* Do not exclude the "variable products" from the Tier product list if some of them are already in use by other Tiers

= 1.0.70 =
* Date Dec 9 2024
* Bugfixes

= 1.0.69 =
* Date Nov 29 2024
* Bugfixes

= 1.0.68 =
* Date Nov 29 2024
* Bugfixes

= 1.0.67 =
* Date Nov 28 2024
* Bugfixes

= 1.0.66 =
* Date Nov 26 2024
* Bugfixes

= 1.0.65 =
* Date Nov 25 2024
* Set next-tier page on memberships

= 1.0.61 =
* Date Nov 19 2024
* Change org membership owner
* Person search look-ahead

= 1.0.60 =
* Date Nov 18 2024
* Update tier UUID per env
* Modified mship owner data lookup
* Update mship_id on subscription
* CPT API security update
* REST API Error responses

= 1.0.55 =
* Date Nov 10 2024
* Disable renewal callouts
* Update subscription start-date fix
* Search memberships debug

= 1.0.53 =
* Date Oct 29 2024
* Fix missing tier post
* Sync Membership to Tier
* Sync Membership to Config

= 1.0.51 =
* Date Oct 22 2024
* Add Membership data checkout errors
* User created in import
* Fix update dates early renewal at

= 1.0.50 =
* Date Oct 22 2024
* BETA Subscription based renewals
* Subscription date change error note

= 1.0.49 =
* Date Oct 21 2024
* Prevent mship date change when cancelled
* Prevent mship update after status change

= 1.0.48 =
* Date Oct 18 2024
* Date offsets when missing timezone
* External ID fix on Pending Approval
* New callouts w/ existing membership

= 1.0.45 =
* Date Oct 11 2024
* Get and set org data

= 1.0.44 =
* Date Oct 02 2024
* Set org meta on subscription box

= 1.0.40 =
* Date Sep 25 2024
* debug settings

= 1.0.39 =
* Date Sep 24 2024
* hide renewal callout, acc debug setting

= 1.0.38 =
* Date Sep 24 2024
* now includes vendor 

= 1.0.37 =
* Date Sep 24 2024
* Fix renewal crashed on do_action hook

= 1.0.36 =
* Date Sep 23 2024
* Composer update

= 1.0.35 =
* Date Sep 23 2024
* Subscription renewal order bugs fixed

= 1.0.34 =
* Date Sep 19 2024
* Prevent frontend app crashing when product in a Tier has draft status

= 1.0.33 =
* Date Sep 18 2024
* Cleanup composer packages

= 1.0.32 =
* Date Sep 14 2024
* Renewal flow and minor updates

= 1.0.31 =
* Date Sep 14 2024
* Self renew will return subscription in callout

= 1.0.30 =
* Date Sep 12 2024
* Add settings page and debug options

= 1.0.29 =
* Date Sep 6 2024
* Update transform post data to json 

= 1.0.28 =
* Date Sep 5 2024
* Fix Missing Assigned Order Error

= 1.0.27 =
* Date Sep 4 2024
* Added Membership Created Action

= 1.0.26 =
* Date: Sep 4 2024
* Automatewoo Triggers

= 1.0.25 =
* Date: Aug 27 2024
* Bug fixes

= 1.0.22 =
* Date: Aug 23 2024
* Simplify cart meta structure
* Import improvements

= 1.0.20 =
* Launch Date: July 23 2024

= 0.0.1 =
*Release Date March 1st 2024*

* Development - initial plugin setup

[View the full changelog](https://www.wicket.io/wordpress/changelog/)