<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rotas de Controle de Usuários (Painel)
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);

// Rotas da Jornada do Herói (RESTful)
Route::post('/journey/start', [JourneyController::class, 'start']);
Route::post('/journey/respond', [JourneyController::class, 'respond']);
Route::get('/journey/history/{userId}', [JourneyController::class, 'history']);
