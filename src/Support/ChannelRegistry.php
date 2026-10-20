<?php

namespace TecClub\ReleaseKit\Support;

use Illuminate\Contracts\Config\Repository;
use TecClub\ReleaseKit\Enums\ChannelAccess;

/**
 * Read access to the channels defined in config('releasekit.channels').
 *
 * Unknown channels, or channels with an unrecognised "access" value,
 * are treated as protected so a config mistake never opens a channel.
 */
class ChannelRegistry
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return (array) $this->config->get('releasekit.channels', []);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $channel): bool
    {
        return array_key_exists($channel, $this->all());
    }

    public function default(): string
    {
        return (string) $this->config->get('releasekit.default_channel', 'stable');
    }

    public function label(string $channel): string
    {
        return (string) ($this->all()[$channel]['label'] ?? $channel);
    }

    public function access(string $channel): ChannelAccess
    {
        $access = $this->all()[$channel]['access'] ?? null;

        return (is_string($access) ? ChannelAccess::tryFrom($access) : null) ?? ChannelAccess::Protected;
    }

    public function isPublic(string $channel): bool
    {
        return $this->access($channel) === ChannelAccess::Public;
    }

    public function isProtected(string $channel): bool
    {
        return $this->access($channel) === ChannelAccess::Protected;
    }

    /**
     * The channel's own disk override, if any.
     */
    public function disk(string $channel): ?string
    {
        $disk = $this->all()[$channel]['disk'] ?? null;

        return is_string($disk) && $disk !== '' ? $disk : null;
    }
}
