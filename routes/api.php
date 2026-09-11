<?php

use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('transactions', TransactionController::class)->except('update');

Route::apiResource('receivables', ReceivableController::class)->only(['index', 'show', 'destroy']);
