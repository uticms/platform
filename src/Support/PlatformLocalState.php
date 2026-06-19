<?php

namespace Uticms\Platform\Support;

enum PlatformLocalState: string
{
    case Active = 'active';
    case Grace = 'grace';
    case Restricted = 'restricted';
    case Banned = 'banned';
    case Unregistered = 'unregistered';

    public function bannerMessage(): ?string
    {
        return match ($this) {
            self::Active => null,
            self::Grace => 'Не удалось связаться с сервером обновлений. Работа продолжается в режиме grace.',
            self::Restricted => 'Срок действия сертификата истёк. Обновления недоступны; сайт работает.',
            self::Banned => 'Регистрация заблокирована. Свяжитесь с поддержкой UTICMS.',
            self::Unregistered => 'Система не зарегистрирована. Укажите PLATFORM_KEY и выполните platform:register.',
        };
    }

    public function statusExitCode(): int
    {
        return match ($this) {
            self::Active => 0,
            self::Grace => 1,
            self::Restricted => 2,
            self::Banned, self::Unregistered => 3,
        };
    }
}
