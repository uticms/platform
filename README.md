# uticms/platform

Laravel-пакет для **self-hosted CMS** (не для uticms.ru): registration, sync, entitlements, updates **client**.

**License server** — отдельно: uticms.ru (`app/Services/License/`, API). Этот пакет только **вызывает** API и применяет ответы локально.

Документация server: `../../docs/license-service/` (контракт).  
Документация client: [14-cms-platform.md](../../docs/license-service/14-cms-platform.md).

## Установка в CMS

```json
{
  "repositories": [ 
    { "type": "path", "url": "../devs/platform" }
  ],
  "require": {
    "uticms/platform": "@dev"
  }
}
```

```php
// CoreServiceProvider
$this->app->register(\Uticms\Platform\PlatformServiceProvider::class);
```

## .env (CMS)

```env
PLATFORM_KEY=U-XXXX-XXXX-XXXX-XXXX
PLATFORM_SERVER_URL=https://uticms.ru
PLATFORM_ENV=production
PLATFORM_SERVER_PUBLIC_KEY=base64...
```

## Команды

```bash
php artisan platform:register
php artisan platform:sync
php artisan platform:status
```

## Storage

`storage/app/platform/` — instance keys, certificate JWT, state.json

## Разработка

```bash
composer install
```

## License

Proprietary — see [LICENSE](./LICENSE). All rights reserved by UTICMS.
