# Projeto Tech - API de Controle de Cargas

Este projeto é uma API PHP para controle de recebimento de cargas, agendamento de cargas e geração de recibos de pagamento das descargas.

## Estrutura do Projeto
- **core/**: Classes base do sistema (Auth, Controller, Database, Request, Router).
- **public/**: Arquivos públicos, ponto de entrada da aplicação e assets (CSS/JS).
- **src/**: Configurações, rotas, controllers, models, utilitários e views.
- **vendor/**: Dependências gerenciadas pelo Composer.
- **Dockerfile / docker-compose.yml**: Arquivos para configuração e execução do ambiente Docker.

## Funcionalidades
- Controle de recebimento de cargas
- Agendamento de cargas
- Geração de recibos de pagamento das descargas

## Como Rodar o Projeto

### Pré-requisitos
- Docker e Docker Compose
- PHP >= 7.4
- Composer

### Instalação
1. Clone o repositório
2. Execute `composer install` para instalar as dependências

### Executando com Docker
1. Execute `docker-compose up` para subir o ambiente
2. O serviço estará disponível em `http://localhost:8080` (ajuste conforme configuração do docker-compose)

### Testando a API
Utilize ferramentas como Postman para testar os endpoints:
- `/api/recebimento` (controle de recebimento)
- `/api/agendamento` (agendamento de cargas)
- `/api/recibo` (recibo de pagamento)

## Configuração
- As configurações de ambiente estão em `src/Config.php` e `src/Env.php`
- As rotas estão definidas em `src/routes.php`

## Documentação
Consulte os arquivos:
- [CONTEXT.md](CONTEXT.md): Contexto geral do projeto
- [CONTROLE.md](CONTROLE.md): Detalhes dos módulos de controle de recebimento, agendamento e recibo

---

Para dúvidas ou sugestões, entre em contato com o responsável pelo projeto.