<?php

namespace TecClub\ReleaseKit\Http\Requests;

use TecClub\ReleaseKit\Http\Requests\Concerns\HasReleaseKitRules;

class CheckRequest extends ApiRequest
{
    use HasReleaseKitRules;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => $this->platformRules(required: true),
            'version' => $this->versionRules(),
            'channel' => $this->channelRules(),
            'architecture' => $this->architectureRules(),
        ];
    }

    public function version(): string
    {
        return $this->validated('version');
    }
}
