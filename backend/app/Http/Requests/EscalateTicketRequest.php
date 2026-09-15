<?php

namespace App\Http\Requests;

use App\NotificationChannels\EscalationChannelRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EscalateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['required', 'string', 'distinct', Rule::in($this->allowedChannels())],
        ];
    }

    /**
     * @return list<string>
     */
    public function channels(): array
    {
        /** @var list<string>|null $channels */
        $channels = $this->validated('channels');

        return $channels ?? $this->allowedChannels();
    }

    /**
     * @return list<string>
     */
    private function allowedChannels(): array
    {
        return app(EscalationChannelRegistry::class)->keys();
    }
}
