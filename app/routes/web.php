<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/{path?}', fn () => Inertia::render('Navigator'))
    // Reserve the real service prefixes without accidentally excluding the
    // human-readable /api-docs application page.
    ->where('path', '^(?!api(?:/|$)|graphql(?:/|$)|up$).*$');
