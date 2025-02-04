<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\SendEmailController;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/user', function (Request $request) {
//     return "111";
// })->middleware('auth:sanctum');

Route::get('/user12', function (Request $request) {
    // return "111";
    // dd(Auth()->user());
    return Auth::user();
})->middleware('auth');

Route::get('/register/check', [AuthController::class, 'UserOpen']);

Route::post('/aaa/login', [AuthController::class, 'login']);
Route::get('/aaa/login', [AuthController::class, 'userget']);

Route::get('/user', [AuthController::class, 'userget']);

Route::get('{any}', function () {
    return view('appHome');
})->where('any', '.*');

// Route::get('/user12', function (Request $request) {
//     // return "111";
//     dd(Auth::user());
//     return Auth::user()->id;
// });

Auth::routes([
    'verify' => true
  ]);
// Auth::routes();

// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
