# Contexto do Projeto

Este projeto é uma API PHP para controle de recebimento de cargas, agendamento de cargas e geração de recibos de pagamento das descargas. Utiliza uma arquitetura MVC simples, com rotas, controllers, models e views, além de autenticação básica.

## Estrutura
- **core/**: Classes base do sistema (Auth, Controller, Database, Request, Router).
- **public/**: Arquivos públicos, incluindo o ponto de entrada da aplicação e assets (CSS/JS).
- **src/**: Configurações, rotas, controllers, models, utilitários e views.
- **vendor/**: Dependências gerenciadas pelo Composer.
- **Dockerfile / docker-compose.yml**: Arquivos para configuração e execução do ambiente Docker.

## Funcionalidades
- **Controle de Recebimento de Cargas**: Cadastro, consulta e atualização de recebimentos.
- **Agendamento de Cargas**: Permite agendar horários para recebimento de cargas.
- **Recibo de Pagamento das Descargas**: Gera recibos para pagamentos realizados.

## Como Funciona
- As requisições chegam pelo `public/index.php`, que inicializa o roteador e direciona para o controller adequado.
- Controllers processam a lógica de negócio e interagem com os models para acessar o banco de dados.
- Models representam as entidades do sistema (Agendamento, Avisos, Login, Recibo).
- As respostas podem ser em JSON (API) ou HTML (views).

## Como Rodar o Projeto
1. **Pré-requisitos**:
   - Docker e Docker Compose instalados
   - PHP >= 7.4
   - Composer

2. **Instalação**:
   - Clone o repositório
   - Execute `composer install` para instalar as dependências

3. **Rodando com Docker**:
   - Execute `docker-compose up` para subir o ambiente
   - O serviço estará disponível em `http://localhost:8080` (ajuste conforme configuração do docker-compose)

4. **Acesso**:
   - Utilize ferramentas como Postman para testar as rotas da API
   - Endpoints principais:
     - `/api/recebimento` (controle de recebimento)
     - `/api/agendamento` (agendamento de cargas)
     - `/api/recibo` (recibo de pagamento)

## Observações
- As configurações de ambiente estão em `src/Config.php` e `src/Env.php`.
- As rotas estão definidas em `src/routes.php`.
- O projeto segue padrão PSR-4 para autoload.

---

Para dúvidas ou melhorias, consulte o README ou entre em contato com o responsável pelo projeto.
