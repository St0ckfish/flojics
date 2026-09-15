<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;

class TicketController extends Controller
{
    public function show(Ticket $ticket): TicketResource
    {
        return TicketResource::make($ticket->load('notificationLogs'));
    }
}
