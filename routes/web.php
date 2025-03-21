<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnkiImportController;


Route::get('/', function () {
    return view('welcome');
});

Route::post('/generate-csv', [AnkiImportController::class, 'generateCsv']);
