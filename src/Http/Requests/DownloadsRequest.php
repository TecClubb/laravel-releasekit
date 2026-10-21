<?php

namespace TecClub\ReleaseKit\Http\Requests;

use TecClub\ReleaseKit\Http\Requests\Concerns\HasReleaseKitRules;

class DownloadsRequest extends ApiRequest
{
    use HasReleaseKitRules;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => $this->platformRules(required: false),
            'channel' => $this->channelRules(),
            'architecture' => $this->architectureRules(),
        ];
    }
}
