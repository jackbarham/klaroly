<?php

/*
 * Usernames no account may ever claim. Each becomes a hostname under
 * klaroly.com, so anything that is or could be a DNS label, a service name or
 * an application route lives here. App\Rules\Username reads this list.
 */
return [
    // Application and infrastructure hostnames
    'app', 'api', 'www', 'web', 'cdn', 'static', 'assets', 'media', 'files', 'img', 'images',
    'mail', 'smtp', 'mx', 'imap', 'pop', 'pop3', 'webmail', 'email', 'newsletter',
    'ftp', 'sftp', 'ssh', 'vpn', 'ns', 'ns1', 'ns2', 'ns3', 'dns',
    'autodiscover', 'autoconfig', 'localhost', 'broadcasthost',
    'dev', 'develop', 'development', 'staging', 'stage', 'test', 'testing', 'demo', 'sandbox',
    'preview', 'beta', 'alpha', 'canary', 'internal', 'ops', 'monitor', 'metrics', 'status',

    // Product surfaces and routes
    'admin', 'administrator', 'root', 'system', 'sys', 'support', 'help', 'helpdesk', 'docs',
    'documentation', 'blog', 'news', 'press', 'careers', 'jobs', 'about', 'contact', 'pricing',
    'plans', 'features', 'terms', 'privacy', 'legal', 'security', 'cookies', 'gdpr', 'dpa',
    'login', 'logout', 'signin', 'signout', 'signup', 'register', 'password', 'reset', 'verify',
    'auth', 'oauth', 'sso', 'token', 'tokens', 'session', 'sessions', 'two-factor', 'passkey',
    'account', 'accounts', 'settings', 'profile', 'profiles', 'user', 'users', 'me', 'my',
    'billing', 'bill', 'pay', 'payment', 'payments', 'invoice', 'invoices', 'checkout',
    'subscribe', 'subscription', 'subscriptions', 'stripe', 'apple', 'google', 'webhook', 'webhooks',
    'book', 'booking', 'bookings', 'enquiry', 'enquiries', 'quote', 'quotes', 'agreement',
    'agreements', 'contract', 'contracts', 'sign', 'client', 'clients', 'contact', 'contacts',
    'portal', 'dashboard', 'calendar', 'schedule', 'notes', 'events', 'services', 'templates',
    'mobile', 'm', 'ios', 'android', 'download', 'downloads', 'install', 'update', 'updates',

    // Brand and generic names
    'klaroly', 'klarolyapp', 'klarolyhq', 'sundaysouth', 'official', 'team', 'staff', 'info',
    'hello', 'hi', 'noreply', 'no-reply', 'postmaster', 'hostmaster', 'webmaster', 'abuse',
    'null', 'undefined', 'anonymous', 'guest', 'public', 'private', 'default', 'example',
    'search', 'explore', 'discover', 'directory', 'shop', 'store', 'marketplace', 'partners',
    'affiliate', 'affiliates', 'referral', 'referrals', 'invite', 'invites', 'feedback', 'survey',
];
