<?php
// Public / decoy
$router->get('/',           'HomeController',  'index');
$router->get('/robots.txt', 'HomeController',  'robots');

// Auth
$router->get('/login',  'AuthController', 'showLogin');
$router->post('/login', 'AuthController', 'login');
$router->post('/logout','AuthController', 'logout');

// Dashboard
$router->get('/dashboard', 'DashboardController', 'index');

// Listings
$router->get('/listings',          'ListingController', 'index');
$router->get('/listings/{id}',     'ListingController', 'show');
$router->post('/listings/{id}/status', 'ListingController', 'updateStatus');
$router->post('/listings/{id}/generate', 'ListingController', 'generate');
$router->post('/listings/{id}/apply',    'ListingController', 'markApplied');

// Tracks
$router->get('/tracks',         'TrackController', 'index');
$router->get('/tracks/create',  'TrackController', 'create');
$router->post('/tracks/create', 'TrackController', 'store');
$router->get('/tracks/{id}/edit',  'TrackController', 'edit');
$router->post('/tracks/{id}/edit', 'TrackController', 'update');
$router->post('/tracks/{id}/delete','TrackController','delete');

// Blacklist
$router->get('/blacklist',         'BlacklistController', 'index');
$router->post('/blacklist/add',    'BlacklistController', 'add');
$router->post('/blacklist/{id}/delete', 'BlacklistController', 'delete');

// Scraper runs
$router->get('/runs',         'ScraperController', 'index');
$router->post('/runs/start',  'ScraperController', 'start');
$router->get('/runs/{id}',    'ScraperController', 'show');

// Applications
$router->get('/applications',                     'ApplicationController', 'index');
$router->get('/applications/{slug}',              'ApplicationController', 'show');
$router->get('/applications/{slug}/download/{file}', 'ApplicationController', 'download');

// Submit a job URL from phone
$router->get('/submit',  'SubmitController', 'form');
$router->post('/submit', 'SubmitController', 'store');

// Settings
$router->get('/settings',  'SettingsController', 'index');
$router->post('/settings', 'SettingsController', 'update');
