<?php

namespace TecClub\ReleaseKit\Enums;

enum ReleaseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Yanked = 'yanked';

    /**
     * Allowed transitions: draft → published, published → yanked, yanked → published.
     */
    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::Draft => $status === self::Published,
            self::Published => $status === self::Yanked,
            self::Yanked => $status === self::Published,
        };
    }
}
