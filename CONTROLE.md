# Controle de Recebimento de Cargas

Este módulo permite o cadastro, consulta e atualização dos recebimentos de cargas. Ele é responsável por registrar todas as cargas recebidas, associando informações como transportadora, motorista, horário de chegada, produtos e status do recebimento.

## Funcionalidades
- Cadastro de recebimento de carga
- Consulta de cargas recebidas
- Atualização do status do recebimento
- Geração de relatórios de recebimento

## Endpoints Sugeridos
- `GET /api/recebimento` - Lista todos os recebimentos
- `POST /api/recebimento` - Cadastra um novo recebimento
- `PUT /api/recebimento/{id}` - Atualiza um recebimento
- `GET /api/recebimento/{id}` - Consulta detalhes de um recebimento

## Exemplo de Payload
```json
{
  "transportadora": "Transportes XYZ",
  "motorista": "João Silva",
  "data_chegada": "2026-01-15T08:00:00",
  "produtos": ["Produto A", "Produto B"],
  "status": "Pendente"
}
```

---

# Agendamento de Cargas

Este módulo permite agendar horários para recebimento de cargas, evitando conflitos e otimizando o fluxo logístico.

## Funcionalidades
- Agendamento de horário para recebimento
- Consulta de agendamentos futuros
- Cancelamento ou alteração de agendamento

## Endpoints Sugeridos
- `GET /api/agendamento` - Lista todos os agendamentos
- `POST /api/agendamento` - Cria um novo agendamento
- `PUT /api/agendamento/{id}` - Altera um agendamento
- `DELETE /api/agendamento/{id}` - Cancela um agendamento

## Exemplo de Payload
```json
{
  "transportadora": "Transportes XYZ",
  "motorista": "João Silva",
  "data_agendada": "2026-01-16T10:00:00",
  "produtos": ["Produto A", "Produto B"]
}
```

---

# Recibo de Pagamento das Descargas

Este módulo gera recibos para pagamentos realizados referentes às descargas das cargas recebidas.

## Funcionalidades
- Geração de recibo de pagamento
- Consulta de recibos emitidos
- Impressão de recibos

## Endpoints Sugeridos
- `GET /api/recibo` - Lista todos os recibos
- `POST /api/recibo` - Gera um novo recibo
- `GET /api/recibo/{id}` - Consulta detalhes de um recibo

## Exemplo de Payload
```json
{
  "recebimento_id": 123,
  "valor": 250.00,
  "data_pagamento": "2026-01-15T12:00:00",
  "responsavel": "Financeiro"
}
```

---

Consulte o arquivo CONTEXT.md para mais detalhes sobre a estrutura geral do projeto.