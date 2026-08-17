# Modelo de dados

```mermaid
erDiagram
  ROLE ||--o{ USER : possui
  ROLE }o--o{ PERMISSION : concede
  PERSON_CATEGORY ||--o{ PERSON : classifica
  DEPARTMENT ||--o{ PERSON : agrupa
  PERSON }o--o{ VEHICLE : utiliza
  PERSON ||--o{ PERSON_MOVEMENT : movimenta
  VEHICLE ||--o{ VEHICLE_MOVEMENT : movimenta
  ACCESS_LOCATION ||--o{ PERSON_MOVEMENT : registra
  ACCESS_LOCATION ||--o{ VEHICLE_MOVEMENT : registra
  USER ||--o{ PERSON_MOVEMENT : opera
  USER ||--o{ VEHICLE_MOVEMENT : opera
  USER ||--o{ AUDIT_LOG : gera
```

Movimentações são append-only na interface. `uuid` é único e garante idempotência. Correções preservam o original por `corrects_id`, `status` e justificativa. Datas operacionais são armazenadas em UTC; a apresentação usa `America/Sao_Paulo`.
