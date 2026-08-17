# Chronus

Sistema híbrido de controle de entrada e saída de pessoas e veículos. O monorepositório contém a API Laravel 12 em `api-laravel`, o aplicativo Flutter em `app-flutter` e a documentação em `docs`.

## Requisitos

- PHP 8.2+, Composer 2 e extensões usuais do Laravel (`pdo_mysql`, `mbstring`, `openssl`)
- MySQL 8
- Flutter 3.41+ e Dart 3.11+
- Android SDK para Android; Visual Studio 2022 com Desktop development with C++ para Windows
- No Windows, instale também o componente individual **C++ ATL for latest v143 build tools (x86 & x64)**, necessário pelo armazenamento seguro

## API

```powershell
cd api-laravel
composer install
Copy-Item .env.example .env
php artisan key:generate
# crie o banco e ajuste DB_* no .env
php artisan migrate --seed
php artisan serve --host=0.0.0.0
```

Produção usa MySQL 8. Testes usam SQLite em memória. Senhas, tokens e `.env` não são versionados. O backend opera em UTC.

Credenciais exclusivas de desenvolvimento:

- Administrador: `admin` / `Chronus@123`
- Porteiro: `portaria` / `Chronus@123`

Troque ou remova essas contas antes de qualquer implantação real.

## Flutter

```powershell
cd app-flutter
flutter pub get
flutter run --dart-define=API_URL=http://10.0.2.2:8000/api/v1
```

No emulador Android, `10.0.2.2` acessa a máquina host. Em dispositivo físico use o IP da rede. No Windows, use `http://127.0.0.1:8000/api/v1`. A fila offline fica em SQLite e o token no armazenamento seguro da plataforma.

## Validação e builds

```powershell
cd api-laravel
php artisan test
vendor/bin/pint --test

cd ../app-flutter
dart format --output=none --set-exit-if-changed lib test
flutter analyze
flutter test
flutter build apk --debug --dart-define=API_URL=http://10.0.2.2:8000/api/v1
flutter build windows --dart-define=API_URL=http://127.0.0.1:8000/api/v1
```

Consulte [API](docs/API.md) e [modelo de dados](docs/ERD.md). Relatórios PDF são gerados pelo Dompdf; XLSX é um pacote Open XML real, com quatro planilhas e células pesquisáveis.
