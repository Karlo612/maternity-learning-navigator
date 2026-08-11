<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/{path?}', fn () => Inertia::render('Navigator'))
    ->where('path', '^(?!api|graphql|up).*$');
