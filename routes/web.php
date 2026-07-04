<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'dashboard.home')->name('home');

Route::livewire('/events/{event}', 'dashboard.overview')->name('event.overview');
Route::livewire('/events/{event}/standings', 'dashboard.standings')->name('event.standings');
Route::livewire('/events/{event}/sections', 'dashboard.sections')->name('event.sections');
Route::livewire('/events/{event}/riders', 'dashboard.riders')->name('event.riders');
