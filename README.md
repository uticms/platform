# uticms/platform

Laravel-пакет для **self-hosted UTICMS CMS**: registration, sync, entitlements, updates (client SDK).

License server — **uticms.ru** (отдельный продукт). Этот пакет только вызывает API и применяет ответы локально.

## Установка

```bash
composer require uticms/platform
```

Для dev до публикации в Packagist — VCS repository в `composer.json` проекта CMS.

`PlatformServiceProvider` подключается **автоматически** (Laravel package discovery).

## .env клиента (минимум для prod)

```env
PLATFORM_KEY=U-XXXX-XXXX-XXXX-XXXX
```

| Переменная | Кто заполняет |
|------------|---------------|
| `PLATFORM_KEY` | **Клиент** — ключ после покупки |
| `PLATFORM_SERVER_PUBLIC_KEY` | **Никто** — default в `config/platform.php` |
| `PLATFORM_SERVER_URL` | **Никто** (prod) — default `https://uticms.ru` |
| Instance keys, certificate, state | **Platform** → `storage/app/platform/` |

Platform **не пишет в `.env`** после регистрации.

Опционально (dev / staging):

```env
PLATFORM_SERVER_URL=https://staging.uticms.ru
PLATFORM_SERVER_PUBLIC_KEY=…
```

## Команды

```bash
php artisan platform:register
php artisan platform:register --force   # повторная регистрация (revoked, сбой activate/confirm)
php artisan platform:sync
php artisan platform:sync --force     # вне расписания / до renew cert
php artisan platform:status           # exit code: 0 active, 1 grace, 2 restricted, 3 revoked/banned/unregistered
```

`--key=U-…` и `--domain=shop.example.com` — если нужно явно указать ключ или домен (не из `APP_URL`).

## Storage

```
storage/app/platform/
├── instance.key / instance.pub
├── certificate.jwt
└── state.json          # installation_id, flags, revoke_reason, last_heartbeat_at, …
```

## Разработка пакета

```bash
composer install
```

## License 

Proprietary — see [LICENSE](./LICENSE). All rights reserved by UTICMS.
