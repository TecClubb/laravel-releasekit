<?php

namespace TecClub\ReleaseKit\Http\Requests;

use TecClub\ReleaseKit\Http\Requests\Concerns\HasReleaseKitRules;

class ReleasesRequest extends ApiRequest
{
    use HasReleaseKitRules;

    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => $this->platformRules(required: false),
            'channel' => $this->channelRules(),
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PER_PAGE);
    }
}
