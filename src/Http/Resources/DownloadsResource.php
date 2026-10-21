<?php

namespace TecClub\ReleaseKit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use TecClub\ReleaseKit\Data\DownloadsResult;

/**
 * The §7.4 downloads response, unwrapped. An empty platform list is
 * returned as a JSON object, not an array.
 *
 * @property DownloadsResult $resource
 */
class DownloadsResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();

        if ($data['platforms'] === []) {
            $data['platforms'] = new \stdClass;
        }

        return $data;
    }
}
