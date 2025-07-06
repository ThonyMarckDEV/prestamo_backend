<?php

use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/reset-password/{idUsuario}/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset.form');
Route::post('/reset-password/{idUsuario}/{token}', [PasswordResetController::class, 'reset'])->name('password.reset.submit');
