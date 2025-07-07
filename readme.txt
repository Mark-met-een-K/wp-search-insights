=== Search Insights - Privacy-Friendly Search Analytics ===
Contributors: markwolters
Donate link: https://www.paypal.me/wpsearchinsights
Tags: search analytics, search, statistics, insights, content
Requires at least: 4.8
License: GPLv2
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 2.1

Uncover exactly what visitors search for on your site. Stop guessing what content to create, fix content gaps, and boost engagement.

== Description ==
**Ever wondered what your visitors are really looking for?** The answer is in their searches.

**Search Insights** reveals valuable user intent that most site owners never see. Our intuitive analytics dashboard shows you exactly what your audience wants - helping you create content that meets their needs before they look elsewhere.

🔍 **Know exactly what to create next based on real user demand**

With our powerful, privacy-first search analytics, you'll:

✅ **Uncover hidden content opportunities** your competitors are missing
✅ **See which search terms are trending up** to stay ahead of the curve
✅ **Identify frustrating "no results" searches** that are costing you conversions
✅ **Measure your content effectiveness** with clear success rate metrics
✅ **Understand user journeys** by seeing which pages trigger searches

Unlike Google Analytics or other complex tools, Search Insights gives you **actionable data without the privacy headaches**. All information stays on your server with zero personal data collected - making it 100% GDPR-friendly.

== Key Features of Search Insights ==
* **Intuitive Analytics Dashboard** - access all your search insights from a dedicated admin menu
* **Trend Visualization** - identify rising search topics that represent content opportunities
* **Smart Search Filtering** - easily toggle between successful searches and missed opportunities
* **High-Performance Engine** - handles thousands of searches easily
* **Privacy-First Design** - all data stays on your server with zero external services
* **Search Origin Tracking** - see exactly which pages trigger searches

== Why Thousands of Site Owners Rely on Search Insights ==

= Create Content That Actually Converts =
Stop wasting time creating content nobody wants. Search Insights shows you exactly what your visitors are actively looking for - so you can create high-converting content that meets real demand.

= Discover Untapped Opportunities =
The new "searches without results" filter instantly reveals content gaps your competitors haven't noticed yet - perfect for capturing underserved audiences and search traffic.

= Stay Ahead with Trend Detection =
The exclusive trend visualization shows which topics are gaining momentum, helping you create timely content before everyone else jumps on the bandwagon.

= Eliminate Visitor Frustration =
Nothing drives visitors away faster than failed searches. Search Insights pinpoints exactly where users get stuck, so you can fix broken paths and keep visitors engaged.

= Perfect for Privacy-Conscious Sites =
In today's privacy-focused world, Search Insights gives you deep analytics with none of the compliance headaches. No external services, no personal data collection.

== Who Gets the Most Value from Search Insights? ==

* **Content Publishers** - Create exactly what your readers want, when they want it
* **WooCommerce Stores** - Discover products customers want but can't find on your site
* **Membership Sites** - Slash support tickets by improving documentation searchability
* **Education Sites** - Optimize course materials based on student confusion points
* **Forums & Communities** - Stay ahead of member interests through search trend analysis

== Key Features That Make Search Insights Essential ==
* **Complete Search Intelligence** - Capture every search query including Ajax searches
* **Trend Analysis** - Exclusive visualization of rising search terms
* **Search Success Metrics** - Measure how effectively your content meets visitor needs
* **Failed Search Detection** - Instantly identify content gaps costing you conversions
* **Search Origin Tracking** - See exactly which pages trigger search behavior
* **Lightning-Fast Performance** - Optimized for sites with thousands of daily searches
* **Easy CSV Export** - Download your insights for team sharing or deeper analysis
* **Zero Privacy Concerns** - All data stays on your server, making GDPR compliance effortless
* **Instant Value** - Clean, intuitive dashboard works right out of the box

= Search Insights in your language? =
Translations can be added very easily [here](https://translate.wordpress.org/projects/wp-plugins/wp-search-insights).

= Love Search Insights? =
If Search Insights has helped you improve your site and create better content, please take a moment to leave a [⭐⭐⭐⭐⭐ review](https://wordpress.org/plugins/wp-search-insights/#reviews)!

== Frequently asked questions ==
= Will this plugin slow down my site? =
* No! Search insights is lightweight and only activates when searches occur. It has no impact on your site's general performance.
= Does this work with custom search forms? =
* Yes! Search insights works with the default WordPress search and popular search plugins including Ajax-powered searches.
= What about privacy and GDPR? =
* Search insights is privacy-first: all data stays on your server, no personal information is collected, and no data is sent to external services.
= Where can I find documentation? =
* Visit the Search insights knowledge base at [https://wpsi.io](https://wpsi.io/docs/) for comprehensive documentation.
= Where can I access the search results and plugin settings? =
* You can find the plugin's dashboard and settings under the Tools -> Search insights menu in your WordPress admin area.
= How does Search insights work? =
* WordPress search forms are essential for improving user experience. By recording visitor searches, Search insights provides valuable data on what your visitors are looking for, which pages they are searching from, and which posts are most relevant to their queries. Analyzing this data helps you optimize your website to better serve your visitors' needs.
= Can I leave a feature request? =
* Absolutely! We are continuously working on improving Search insights and developing premium add-ons to enhance the search data analysis capabilities. Please share your feature requests with us.
= Is it possible to remove search results? =
* Yes. In the plugin settings, you can clear your entire search database. Please note that this action cannot be reversed. Individual search queries can be deleted from the dashboard using the 'delete' option.

== Installation ==
Two ways to install Search insights:

1. From your WordPress dashboard:
   * Go to Plugins > Add New
   * Search for 'Search insights'
   * Click 'Install Now' and then 'Activate'
   * Your search tracking will start automatically!

2. Manual installation:
   * Download Search insights from wordpress.org
   * Go to Plugins > Add New > Upload Plugin
   * Upload the plugin file
   * Activate the plugin

That's it! Click the link in the activation notice to see your search insights dashboard.

== Changelog ==
= 2.1. =
* Improvement: greatly improved filtering of spammy search terms

= 2.0
* New: Rebranded from "WP Search Insights" to "Search Insights" with dedicated admin menu
* New: Added trend data visualization to popular searches
* New: Filter to view searches with or without results in popular searches block
* New: Visual loading indicators for large datasets
* New: Added database indexes for improved query performance
* New: Completely redesigned export experience
* New: AJAX-based settings saving with visual feedback
* New: Custom lightweight modal system (replacing ThickBox)
* Improved: Date ranges like "Today" or "Last 7 days" now properly update to reflect the current date
* Improved: Optimized queries for large datasets with better performance
* Improved: Enhanced search tracking for more accurate analytics
* Improved: Tooltips are now always visible when needed
* Improved: Increased settings block height to prevent unnecessary scrolling
* Improved: Shortened pagination in the All Searches table for large datasets
* Improved: Standardized timestamp storage to UTC
* Improved: Various styling consistency enhancements across all screens
* Dev: Added wpsi_batch_size filter to control AJAX batch size
* Dev: Added wpsi_datatable_columns filter to customize table columns
* Dev: Added wpsi_table_row_data filter to modify table row contents
* Dev: Added wpsi_dashboard_widget_cache_time filter (props @azinfiro)
* Updated: DataTables library with proper CSS prefixing
* Updated: Shepherd.js tour guide library
* Updated: Muuri.js grid layout library
* Updated: Help tips and guided tour content
* Fixed: 'Clear data on uninstall' option now properly removes all plugin data
* Fixed: All issues flagged by the WordPress Plugin Checker
* Removed: Tether library dependency
* Removed: Redundant moment.js (now using WP core version)
* Tested: Compatible with WordPress 6.8

= 1.4.0 =
* Fixed a textdomain notification introduced in WordPress 6.7

= 1.3.9 =
* Fixed an issue with overlapping blocks on settings page which caused the save button to disappear
* Added scrollbar to settings block on overflow
* Fixed dashboard capability check for non-administrator users
* Fixed BBPress integration

= 1.3.8 =
* Added ignore button to all searches table
* Added option to clear database tables after certain time period
* Added all time option to search overview
* Fix: search terms containing multiple words are now correctly filtered
* Fix: filter is now case-insensitive

= 1.3.7 =
* Updated domain in readme/files

= 1.3.6 =
* Fix: random number one next to delete button

= 1.3.5 =
* Name change

= 1.3.4 =
* Mobile pagination
* PHP docs update

= 1.3.3 =
* WP 5.5 compatibility

= 1.3.2 =
* Improvement: added support for Toolset search
* Improvement: added option to provide your own search parameter

= 1.3.1 =
* Fix: remove frequency numbers from overview

= 1.3.0 =
* Changed to date-range picker
* Load more searches in data range in the background
* CSV export directly from server, allowing complete database download
* State saving of order and page
* Improved mobile and ipad design
* Improved front-end search tracking
* Ajax tracking made optional, default enabled when any one of the known ajax search plugins is active

= 1.2.1 =
* Improved review notice dismissal

= 1.2 =
* Changed lay-out

= 1.1 =
* Added text filter area to exclude terms from results
* Added option to select which user roles can view the dashboard
* Updated tour structure and added native WP buttons
* Added suggested privacy statement text
* Updating some strings
* Fix: when same string is search, the time is now updated as well

= 1.0.0 =
* Initial release

== Upgrade notice ==
* Please backup before upgrading.

== Screenshots ==
1. Search insights Dashboard
2. Search Insights Settings Page
