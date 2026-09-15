<?php

use App\Exceptions\ConfigureApiExceptions;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('route model binding 404s map to a compact ticket message', function () {
    $previous = (new ModelNotFoundException)->setModel(Ticket::class, [9999]);
    $exception = new NotFoundHttpException('No query results for model', $previous);

    expect(ConfigureApiExceptions::notFoundMessage($exception))->toBe('Ticket [9999] was not found.');
});

test('plain http 404s stay generic', function () {
    expect(ConfigureApiExceptions::notFoundMessage(new NotFoundHttpException('Not Found')))
        ->toBe('Not found.');
});
