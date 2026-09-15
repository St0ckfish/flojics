<?php

namespace App\Http\Requests;

use App\Enums\EscalationChannelKey;
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
            'channels.*' => ['required', 'string', Rule::enum(EscalationChannelKey::class)],
        ];
    }

    /**
     * @return list<string>
     */
    public function channels(): array
    {
        /** @var list<string>|null $channels */
        $channels = $this->validated('channels');

        return $channels ?? EscalationChannelKey::values();
    }
}
