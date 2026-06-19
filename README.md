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

```bash
composer require uticms/platform 
```

`PlatformServiceProvider` подключается **автоматически** (package discovery, см. `extra.laravel` в `composer.json`).

## Что клиент вводит сам vs что platform делает сам

| | Кто | Куда |
|---|-----|------|
| **`PLATFORM_KEY`** (`U-…`) | **Клиент** — ключ из ЛК / email после покупки | `.env` или `platform:register --key=…` |
| **`PLATFORM_SERVER_PUBLIC_KEY`** | **Никто** — уже в `config/platform.php` dist | default в config, `.env` не нужен |
| **`PLATFORM_SERVER_URL`** | **Никто** (prod) | default `https://uticms.ru` |
| Instance keys (Ed25519) | **Platform** при первом `register` | `storage/app/platform/instance.key` + `.pub` |
| Certificate JWT | **Platform** после `register` / `sync` | `storage/app/platform/certificate.jwt` |
| installation_id, flags | **Platform** | `storage/app/platform/state.json` |

Platform **не пишет в `.env`** после регистрации — только в `storage/app/platform/`.  
В `.env` клиента для prod обычно **одна строка**: `PLATFORM_KEY=U-…`.

## .env (CMS, минимум для prod)

```env
PLATFORM_KEY=U-XXXX-XXXX-XXXX-XXXX
```

Опционально (dev / staging / другой регион):

```env
PLATFORM_SERVER_URL=https://staging.uticms.ru
PLATFORM_SERVER_PUBLIC_KEY=…   # override встроенного default
```

## Команды

```bash
php artisan platform:register          # или --key=U-… --domain=shop.example.com
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
