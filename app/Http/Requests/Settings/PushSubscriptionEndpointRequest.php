<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Lookup/removal by endpoint. Only shape is checked here: an endpoint is never
 * fetched by the server in these flows.
 */
class PushSubscriptionEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
        ];
    }

    public function endpoint(): string
    {
        return (string) $this->validated('endpoint');
    }
}
