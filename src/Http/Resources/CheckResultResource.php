<?php

namespace TecClub\ReleaseKit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use TecClub\ReleaseKit\Data\CheckResult;

/**
 * The §7.3 version check response, unwrapped.
 *
 * @property CheckResult $resource
 */
class CheckResultResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
