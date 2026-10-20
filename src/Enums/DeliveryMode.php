<?php

namespace TecClub\ReleaseKit\Enums;

enum DeliveryMode: string
{
    case Stream = 'stream';
    case Signed = 'signed';
    case Public = 'public';
}
