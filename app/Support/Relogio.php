<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

interface Relogio
{
    public function hoje(): DateTimeImmutable;

    public function agora(): DateTimeImmutable;
}
