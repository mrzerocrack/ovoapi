# mrzeroc/ovo-api

Fork komunitas dari `namdevel/ovoid-api` dengan update endpoint terbaru dan tester web siap pakai.

## Fitur

- API wrapper OVO (namespace tetap kompatibel: `Namdevel\Ovo`).
- Flow QRIS v2 + fallback legacy yang sudah disesuaikan.
- Tester browser `/ovoid`:
  - login flow (`send_otp -> otp_verify -> get_auth_token`)
  - transaksi/query feature by feature
  - state session otomatis
  - hasil request/response tampil lengkap

## Requirement

- PHP `^8.1`
- Laravel (opsional, hanya jika ingin pakai tester `/ovoid`)

## Instalasi

```bash
composer require mrzeroc/ovo-api
```

## Konfigurasi Laravel

Package akan auto-discover service provider dan otomatis mendaftarkan route tester.

Publish config (opsional):

```bash
php artisan vendor:publish --tag=ovoid-config
```

Publish view tester (opsional, untuk custom UI):

```bash
php artisan vendor:publish --tag=ovoid-views
```

## ENV yang dipakai

```env
OVOID_ALLOW_SENSITIVE_ACTIONS=false
OVOID_TESTER_ENABLED=true
OVOID_TESTER_ROUTE_PREFIX=ovoid
OVOID_TESTER_ROUTE_NAME_PREFIX=ovoid.

OVOID_APP_VERSION=3.153.0
OVOID_CLIENT_ID=ovo_android
OVOID_CHANNEL_CODE=ovo_android
OVOID_OS=Android
OVOID_USER_AGENT="OVO/3.153.0 Android"
OVOID_DEVICE_BRAND=Android
OVOID_DEVICE_MODEL=Android
```

## Akses Tester Web

- URL default: `http://127.0.0.1:8000/ovoid`
- Flow yang disarankan:
  1. `send_otp`
  2. `otp_verify`
  3. `get_auth_token`
  4. lanjut query atau transaksi lain

## Pemakaian di Kode

Namespace tetap sama seperti package lama:

```php
use Namdevel\Ovo;

$client = new Ovo($authToken);
$last = $client->getLastTransactions(5);
```

## Kompatibilitas

Package ini mendeklarasikan:

- `replace: namdevel/ovoid-api`

Jadi migrasi dari package lama bisa langsung dengan perubahan minimal.

## Catatan Penting

- Ini API unofficial/internal, bisa berubah kapan saja dari sisi OVO.
- Gunakan hanya untuk kebutuhan legal dan atas risiko sendiri.
- Untuk aksi finansial nyata, selalu uji di nominal kecil dulu.

## Publish ke Packagist

Shortcut untuk maintainer (dari root repo package):

```bash
./scripts/release.sh v1.0.4
```

1. Push folder package ini ke repo GitHub terpisah, misal: `https://github.com/mrzerocrack/ovoapi`.
2. Pastikan branch utama `main` berisi `composer.json` package ini.
3. Login ke Packagist lalu submit URL repo GitHub.
4. Buat tag release untuk versi stabil, contoh:

```bash
git tag v1.0.4
git push origin v1.0.4
```

5. Setelah sinkron di Packagist, install stabil:

```bash
composer require mrzeroc/ovo-api:^1.0
```
