<?php

return [
    /*
     * Path to the client secret json file. Take a look at the README of this package
     * to learn how to create one. You can also credentials using an array.
     * For OAuth, we will not use this directly, but the package might look for it
     * if OAuth settings are not fully provided or if using a different flow.
     * It's generally safe to leave it pointing to a non-existent path if exclusively using OAuth.
     */
    'service_account_credentials_json' => storage_path('app/google-calendar/service-account-credentials.json'),

    /*
     * The id of the calendar that will be used by default.
     * You can overwrite this on a per-event basis.
     * For a multi-user system (even if just one admin user with their own calendar),
     * it's better to store the user's chosen calendar_id with their OAuth tokens.
     * 'primary' is a special keyword that refers to the primary calendar of the authenticated user.
     */
    'calendar_id' => env('GOOGLE_CALENDAR_ID', 'primary'),

    /**
     * OAuth Configuration
     * Set this configuration to use OAuth2 for user-consented access.
     */
    'oauth' => [
        /*
         * The id of the client as provided by Google.
         * Load this from your .env file.
         */
        'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),

        /*
         * The client secret as provided by Google.
         * Load this from your .env file.
         */
        'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),

        /*
         * The redirect URI as configured in your Google project and .env file.
         * This should be the exact same as you configured in the Google API console.
         * Example: url('/admin/settings/google-calendar/callback')
         * Make sure to create this route in your web.php.
         */
        'redirect_uri' => env('GOOGLE_OAUTH_REDIRECT_URI'),
    ],

    /*
     * When using OAuth2, you need to specify how to store and retrieve the access token.
     * The package needs a class that implements `Spatie\GoogleCalendar\GoogleCalendarAccesToken`.
     */
    'access_token_repository' => \App\Repositories\DatabaseTokenRepository::class, // <-- *** التصحيح هنا ***

    /*
     * If you want to use an impersonated account (service account acting on behalf of a user),
     * you can set the email address of the user to impersonate here.
     * This is not needed for the OAuth flow we are implementing.
     */
    // 'impersonate' => null,

    /**
     * The scopes to be requested. Indicate what kind of access you need.
     * See: https://developers.google.com/identity/protocols/oauth2/scopes#calendar
     * Ensure these scopes are enabled in your Google Cloud project for the OAuth consent screen.
     */
    'scopes' => [
        // Recommended scope for managing events:
        \Google\Service\Calendar::CALENDAR_EVENTS,

        // Use \Google\Service\Calendar::CALENDAR if you need to manage calendars themselves (e.g., create new calendars)
        // \Google\Service\Calendar::CALENDAR,

        // Use \Google\Service\Calendar::CALENDAR_READONLY or \Google\Service\Calendar::CALENDAR_EVENTS_READONLY for read-only access
    ],

    /**
     * Set the access type.
     * Recommended: 'offline' to get a refresh token, allowing long-lived access
     * without repeated user consent.
     */
    'access_type' => 'offline',

    /**
     * Set the approval prompt.
     * Recommended: 'force' to ensure the refresh token is always returned on the first
     * authorization attempt, especially if the user has authorized the app before.
     */
    'approval_prompt' => 'force',

    /*
     * The amount of seconds the Google API calls will wait before timing out.
     */
    'timeout' => 30,

    /*
     * The amount of seconds the Google API calls will wait while trying to connect to a server.
     */
    'connect_timeout' => 10,

    // ... (بقية الإعدادات الاختيارية المتعلقة بالكاش و ETag تبقى كما هي)
];