<?php

namespace TecClub\ReleaseKit\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\PlatformRegistry;
use TecClub\ReleaseKit\Support\VersionComparator;

trait HasReleaseKitRules
{
    /**
     * @return list<mixed>
     */
    protected function platformRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'string', Rule::in(app(PlatformRegistry::class)->keys())];
    }

    /**
     * @return list<mixed>
     */
    protected function channelRules(): array
    {
        return ['nullable', 'string', Rule::in(app(ChannelRegistry::class)->keys())];
    }

    /**
     * @return list<string>
     */
    protected function versionRules(): array
    {
        return ['required', 'string', 'max:50', 'regex:'.VersionComparator::PATTERN];
    }

    /**
     * Validated against the platform's architectures when a known platform is given.
     *
     * @return list<mixed>
     */
    protected function architectureRules(): array
    {
        $platform = $this->input('platform');
        $platforms = app(PlatformRegistry::class);

        if (is_string($platform) && $platforms->has($platform)) {
            return ['nullable', 'string', Rule::in($platforms->architectures($platform))];
        }

        return ['nullable', 'string', 'max:50'];
    }
}
