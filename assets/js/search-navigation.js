jQuery(document).ready(function ($) {
    "use strict";

    const MAX_SEARCH_AGE = 5 * 60 * 1000; // 5 minutes in milliseconds

    // Check if we're on a search results page
    if (window.location.href.includes('?s=')) {
        return; // Don't track landing pages on search pages
    }

    // Get search ID directly
    let search_id = null;
    try {
        const search_id_match = document.cookie.match(/wpsi_search_id=([^;]+)/);
        if (search_id_match) {
            search_id = decodeURIComponent(search_id_match[1]);
        }
    } catch (e) {
        // Silently fail
    }

    // Try to get search term and timestamp data
    let searchData = null;
    try {
        const cookieMatch = document.cookie.match(/wpsi_last_search=([^;]+)/);

        if (cookieMatch) {
            searchData = JSON.parse(decodeURIComponent(cookieMatch[1]));
        }  else {
            // Silently fail
        }
    } catch (e) {
        // Silently fail
    }

    if (!searchData) {
        return;
    }

    // If we have a search_id from the cookie, use it (priority)
    if (search_id && (!searchData.search_id || searchData.search_id !== search_id)) {
        searchData.search_id = search_id;
    }

    const currentTime = new Date().getTime();

    // Check if the search session is still valid AND from the same browser session
    if (currentTime - searchData.timestamp > MAX_SEARCH_AGE) {
        // Clear cookies
        document.cookie = "wpsi_last_search=; path=/; max-age=0";
        document.cookie = "wpsi_search_id=; path=/; max-age=0";
        return;
    }

    const currentUrl = window.location.href;
    const currentDomain = window.location.hostname;

    // Check if this is a conversion (clicked search result)
    const isConversion = searchData.results && searchData.results.includes(currentUrl);
    // Check if this is internal navigation (same domain but not in results)
    const isInternalNavigation = !isConversion && currentUrl.includes(currentDomain);

    // Track the landing page with the appropriate status
    $.ajax({
        type: "POST",
        url: wpsi_search_navigation.ajaxurl,
        dataType: 'json',
        data: {
            action: 'wpsi_store_landing_page',
            search_term: searchData.term,
            search_id: searchData.search_id,
            landing_page: currentUrl,
            is_conversion: isConversion ? 1 : 0,
            is_internal: isInternalNavigation ? 1 : 0,
            search_timestamp: searchData.timestamp,
            landing_time: currentTime / 1000,
            token: wpsi_search_navigation.token
        },
        success: function () {
            // Clear cookies on success
            document.cookie = "wpsi_last_search=; path=/; max-age=0";
            document.cookie = "wpsi_search_id=; path=/; max-age=0";
        },
        error: function () {
            // Also clear cookies on error to prevent stale data
            document.cookie = "wpsi_last_search=; path=/; max-age=0";
            document.cookie = "wpsi_search_id=; path=/; max-age=0";
        }
    });
});
