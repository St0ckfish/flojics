<?php

use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketEscalationController;
use Illuminate\Support\Facades\Route;

Route::get('tickets/{ticket}', [TicketController::class, 'show']);
Route::post('tickets/{ticket}/escalate', [TicketEscalationController::class, 'store']);
