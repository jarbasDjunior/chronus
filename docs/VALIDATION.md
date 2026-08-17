# Validação do ambiente

Executado em 13/08/2026:

- `php artisan migrate:fresh --seed --force`: concluído com SQLite local para validação;
- `php artisan test`: testes de autenticação, RBAC, movimentos, idempotência, exceção, correção imutável, PDF e XLSX;
- `vendor/bin/pint --test`: código PHP formatado;
- `flutter analyze`: sem problemas;
- `flutter test`: teste responsivo de login aprovado;
- `flutter build apk --debug`: APK gerado em `app-flutter/build/app/outputs/flutter-apk/app-debug.apk`;
- `flutter build windows`: bloqueado no ambiente porque o Visual Studio não possui `atlstr.h`.

Para concluir o build Windows, adicione no Visual Studio Installer o componente **C++ ATL for latest v143 build tools (x86 & x64)** e execute:

```powershell
cd app-flutter
flutter build windows --dart-define=API_URL=http://127.0.0.1:8000/api/v1
```

## Cadastro de veículos de funcionários

1. Inicie a API com `php artisan serve` e o Flutter com o `API_URL` adequado ao dispositivo.
2. Entre como administrador (`admin` / `Chronus@123`).
3. Abra **Registrar**, pesquise um funcionário e toque no cartão dele.
4. Na tela de detalhes, toque em **Adicionar veículo**, preencha placa, modelo, cor e status e salve.
5. Confirme que o veículo aparece na seção **Veículos**. Cadastre um segundo veículo para validar que um funcionário aceita vários veículos.
6. Tente cadastrar `INVALIDA`: a API deve rejeitar a placa e a tela deve mostrar a mensagem de validação.
7. Edite um veículo, altere seu status para inativo e confirme que o chip passa a mostrar **Inativo**.

O build release Android deliberadamente não possui chave de assinatura no repositório. Configure um keystore seguro fora do Git antes de publicar.
