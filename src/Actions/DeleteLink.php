<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Support\Concerns\ValidatesInput;

class DeleteLink
{
    use ValidatesInput;

    public function handle(Link $link): void
    {
        $this->assertContentsEditable($link->release);

        $link->delete();
    }
}
