<?php

namespace TecClub\ReleaseKit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use TecClub\ReleaseKit\Data\ReleaseSummary;
use TecClub\ReleaseKit\Models\Release;

/**
 * One entry of the §7.5 release history. Never exposes status, metadata
 * or the yank reason.
 *
 * @property Release $resource
 */
class ReleaseResource extends JsonResource
{
    /**
     * @return array<string, string|null>
     */
    public function toArray(Request $request): array
    {
        return ReleaseSummary::fromModel($this->resource)->toArray();
    }
}
