<?php

declare(strict_types=1);

namespace BenTools\DoctrinePopoTracker;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class TrackChanges
{
}
