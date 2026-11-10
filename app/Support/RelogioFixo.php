<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class RelogioFixo implements Relogio
{
    public function __construct(private readonly DateTimeImmutable $data)
    {
    }

    public function hoje(): DateTimeImmutable
    {
        return $this->data->setTime(0, 0);
    }

    public function agora(): DateTimeImmutable
    {
        return $this->data;
    }
}
