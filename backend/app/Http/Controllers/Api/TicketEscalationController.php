<?php

namespace App\Http\Controllers\Api;

use App\Actions\EscalateTicketAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\EscalateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;

class TicketEscalationController extends Controller
{
    public function store(
        EscalateTicketRequest $request,
        Ticket $ticket,
        EscalateTicketAction $action,
    ): TicketResource {
        return TicketResource::make(
            $action->execute($ticket->id, $request->channels()),
        );
    }
}
