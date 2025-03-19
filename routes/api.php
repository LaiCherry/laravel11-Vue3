<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
// use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProductController;
   
// Route::controller(RegisterController::class)->group(function(){
//     Route::post('register', 'register');
//     Route::post('login', 'login');
// });

Route::controller(AuthController::class)->group(function(){
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('logout', 'logout');
    Route::post('verify-turnstile', 'verifyTurnstile');
    // Route::get('user1', 'usercheck');
    // Route::get('user', 'userget');
})->middleware('auth');

Route::get('/user', function (Request $request) {
    // return "111";
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/user1', function (Request $request) {
    // return "111";
    // dd(Auth::user());
    return Auth::user();
});
         
Route::middleware('auth:sanctum')->group( function () {
    Route::resource('products', ProductController::class);
});
