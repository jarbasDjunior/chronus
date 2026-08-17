# API Chronus v1

Base: `/api/v1`. Todas as rotas protegidas usam `Authorization: Bearer <token>` e retornam JSON; listas usam a paginação padrão do Laravel.

## Autenticação

- `POST /auth/login` — `{ "login":"portaria", "password":"...", "device_name":"Android" }`
- `GET /auth/me`
- `POST /auth/logout`

## Operação

- `GET /dashboard`
- `GET /people?search=ana&per_page=20`
- `GET /vehicles?search=ABC1D23`
- CRUD: `/people`, `/vehicles`, `/categories`, `/departments`, `/locations`
- `GET /people/{id}` — detalhes do funcionário com a lista de veículos
- `POST /vehicles` — cadastra placa, modelo, cor, status e `person_ids`; placas aceitas: `ABC1234` e `ABC1D23`
- `POST /movements/person`
- `POST /movements/vehicle`
- `POST /movements/{person|vehicle}/{id}/correct` — preserva o original e exige justificativa
- `GET /movements/{person|vehicle}?from=&to=&type=&operator_id=&access_location_id=`
- `GET /presence/{person|vehicle}`
- `POST /sync`
- `GET /reports/pdf` e `GET /reports/xlsx` com os mesmos filtros
- `GET /audit`

## Porteiros terceirizados e turnos

- CRUD `/security-companies` — empresas prestadoras de segurança
- CRUD `/gatekeepers` — porteiros separados dos funcionários, com vínculo opcional a um usuário
- `GET /gatekeeper-shifts/current` — turno aberto do porteiro autenticado
- `POST /gatekeeper-shifts/start` — inicia o turno; exige `access_location_id`
- `POST /gatekeeper-shifts/break/start` — inicia o intervalo de almoço
- `POST /gatekeeper-shifts/break/end` — encerra o intervalo após pelo menos 1 hora
- `POST /gatekeeper-shifts/finish` — encerra o turno
- `GET /gatekeeper-shifts` — histórico próprio; administradores e auditores podem consultar todos

Um usuário de portaria precisa estar vinculado a um registro ativo em `gatekeepers`. Não é possível abrir dois turnos simultâneos nem encerrar o turno durante um intervalo aberto.

Os cadastros de empresas terceirizadas e porteiros exigem a permissão `registrations.manage` inclusive para consulta. No aplicativo, o módulo **Cadastros** é exibido exclusivamente para o papel `admin` e permite administrar funcionários, porteiros e terceirizadas.

Exemplo de movimento:

```json
{
  "uuid": "1f6e5218-375d-4f20-a370-36a834ed88dd",
  "person_id": 1,
  "type": "entry",
  "occurred_at": "2026-08-13T11:00:00Z",
  "access_location_id": 1,
  "vehicle_id": 1,
  "origin": "offline"
}
```

Repetir o mesmo UUID retorna o registro existente. Estados consecutivos incompatíveis retornam `422`. Exceções exigem `force: true`, permissão `movements.correct` e `correction_reason`.
