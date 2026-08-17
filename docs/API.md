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
