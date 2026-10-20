<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Models\Link;

class DeleteLink
{
    use ValidatesInput;

    public function handle(Link $link): void
    {
        $this->assertContentsEditable($link->release);

        $link->delete();
    }
}
