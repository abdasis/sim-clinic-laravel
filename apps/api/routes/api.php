<?php

use App\Http\Controllers\DemoDataTableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/demo/data', [DemoDataTableController::class, 'index']);
