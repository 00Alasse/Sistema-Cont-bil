<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use DateTimeImmutable;
use DateTimeZone;

final class RelogioSistema implements Relogio
{
    public function hoje(): DateTimeImmutable
    {
        return $this->agora()->setTime(0, 0);
    }

    public function agora(): DateTimeImmutable
    {
        $fuso = new DateTimeZone((string) Config::obter('app.timezone', 'America/Sao_Paulo'));

        return new DateTimeImmutable('now', $fuso);
    }
}
