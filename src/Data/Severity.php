<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Data;

enum Severity: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Warning = 'warning';
}
