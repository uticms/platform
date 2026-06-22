# uticms/platform

Laravel-пакет для **self-hosted UTICMS CMS**: registration, sync, entitlements, updates (client SDK).

License server — **uticms.ru** (отдельный продукт). Этот пакет только вызывает API и применяет ответы локально.

## Установка


```bash
composer require uticms/platform
```

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
php artisan platform:sync
php artisan platform:status
```

`--key=U-…` и `--domain=shop.example.com` — если нужно явно указать ключ или домен (не из `APP_URL`).

## Storage

```
storage/app/platform/
├── instance.key / instance.pub
├── certificate.jwt
└── state.json
```

## Разработка пакета

```bash
composer install
```

## License

Proprietary — see [LICENSE](./LICENSE). All rights reserved by UTICMS.
