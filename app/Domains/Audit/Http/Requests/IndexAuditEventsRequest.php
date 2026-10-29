<?php

declare(strict_types=1);

namespace App\Domains\Audit\Http\Requests;

use App\Core\Foundation\Requests\BaseRequest;

final class IndexAuditEventsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'event_key' => ['sometimes', 'string', 'max:120'],
            'subject_type' => ['sometimes', 'string', 'max:160'],
            'subject_id' => ['sometimes', 'string', 'max:64'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
