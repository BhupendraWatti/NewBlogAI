<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard', ['activeView' => 'dashboard']);
});

Route::get('/customers', function () {
    return view('dashboard', ['activeView' => 'customers']);
});

Route::get('/fleet', function () {
    return view('dashboard', ['activeView' => 'fleet']);
});

Route::get('/sites', function () {
    return view('dashboard', ['activeView' => 'sites']);
});

Route::get('/prompts', function () {
    return view('dashboard', ['activeView' => 'prompts']);
});

Route::get('/topics', function () {
    return view('dashboard', ['activeView' => 'topics']);
});

Route::get('/pipeline', function () {
    return view('dashboard', ['activeView' => 'pipeline']);
});

Route::get('/scheduler', function () {
    return view('dashboard', ['activeView' => 'scheduler']);
});

Route::get('/providers', function () {
    return view('dashboard', ['activeView' => 'providers']);
});

Route::get('/media', function () {
    return view('dashboard', ['activeView' => 'media']);
});
