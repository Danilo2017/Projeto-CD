# Projeto Tech — Portal de Rotinas Gazin (Colchões)

Portal web interno em PHP com telas operacionais e relatórios da indústria de colchões: comissão de produtividade, CD, faturamento, processos, PCP, apontamento, P&D, planejamento, processos industriais, qualidade e manutenção.
Os dados vêm do ERP **Focco** (Oracle, schema `FOCCO3I`).

## Stack
- **PHP 8.1** em MVC próprio (`core/`), autoload PSR-4 pelo Composer
- **Oracle 12.1** (FOCCO3I) via PDO_OCI
- **Apache + mod_php** em Docker
- Front-end: Bootstrap 5, Bootstrap Icons e JavaScript puro nas views

## Estrutura do Projeto
- **core/**: classes base (Auth, Controller, Database, Request, Router).
- **public/**: ponto de entrada (`index.php`) e assets (CSS/JS). É o docroot do Apache.
- **src/routes.php**: todas as rotas.
- **src/controllers/<Módulo>/**: recebem a requisição e devolvem HTML ou JSON.
- **src/handlers/<Módulo>/**: regras de negócio e validação (métodos estáticos).
- **src/models/<Módulo>/**: acesso ao banco (métodos estáticos).
- **src/views/pages/<módulo>/**: telas; **src/views/partials/navbar.php**: menu lateral.
- **src/cli/**: scripts de linha de comando chamados em segundo plano (ex.: MRP da Compra Direta Crossdocking).
- **database/**: scripts SQL/DDL; **database/gazin-sqls/**: carga dos SQLs na `FOCCO3I.GAZIN_SQLS` (`instalar.php`).
- Os módulos são CD, Comissao, Faturamento, Industrial, Manutencao, PCP, PD, Planejamento, Processo e Qualidade.

## Como Rodar o Projeto

### Pré-requisitos
- Docker e Docker Compose
- Acesso de rede ao Oracle do Focco
- Arquivo de ambiente configurado (`src/Env.php` / `.env`; não versionar senhas)

### Executando com Docker
```bash
docker compose -f docker-compose-comissao.yml up -d --build
```
- Container: `comissao_colchao`
- Endereço: `http://localhost:8050`
- `docker-compose.yml` (container `api_rotinas`, porta 8002) é o ambiente da API de rotinas original.

### Comandos úteis
```bash
docker exec comissao_colchao php -l src/<arquivo>.php          # validar sintaxe
docker exec comissao_colchao php database/gazin-sqls/instalar.php   # gravar/atualizar SQLs na GAZIN_SQLS
```

## Convenções
- **SQL fica na `FOCCO3I.GAZIN_SQLS`**, buscado por `idsql` (`modulo.tela.consulta`), e não no PHP. A exceção é uma tela ainda em teste (hoje a Compra Direta Crossdocking).
- Handlers e models usam métodos estáticos.
- Não alterar nada no schema OTIMIZA (OTM) nem em tabelas existentes do Focco. Objetos novos são criados no FOCCO3I com prefixo `TGAZIN_` / `GAZIN_`.
- Sem Python: tudo em PHP, dentro do container.
- O acesso é por perfil (tela Permissões), pelo prefixo da URL (`planejamento-`, `pcp-`, `manutencao-`, ...).
- Commits só depois de a alteração ser validada pelo usuário.

## Documentação
- [CONTEXT.md](CONTEXT.md): contexto geral, módulos e detalhes técnicos
- [CONTROLE.md](CONTROLE.md): controle das telas (rotas, objetos de banco e situação)
- [TASK-REFATORACAO.md](TASK-REFATORACAO.md): refatoração GAZIN_SQLS e métodos estáticos- `graphify-out/`: grafo de conhecimento do código (`graphify query "..."`; atualizar com `graphify update .`)

---

Para dúvidas ou sugestões, entre em contato com o responsável pelo projeto.
