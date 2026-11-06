# Contexto do Projeto

Portal web interno em PHP com telas operacionais e relatórios da indústria de colchões Gazin. Começou como API de cargas (recebimento, agendamento e recibo de descarga) e com o **Sistema de Comissionamento de Produtividade**. Hoje cobre vários setores: CD, Faturamento, Pedidos, Processo, Cargas, PCP, Apontamento, P&D, Planejamento, Processos Industriais, Qualidade e Manutenção.
Os dados vêm do ERP Focco (Oracle 12.1, schema `FOCCO3I`). A arquitetura é MVC própria (rotas → controllers → handlers → models → views), com autenticação e acesso por perfil.

## Estrutura
- **core/**: classes base (Auth, Controller, Database, Request, Router).
  - `Database::switchParams` faz a troca textual de parâmetros nos SQLs vindos da GAZIN_SQLS.
- **public/**: ponto de entrada (`index.php`) e assets (CSS/JS). É o docroot do Apache.
- **src/routes.php**: rotas.
- **src/controllers|handlers|models/<Módulo>/**: um diretório por módulo (CD, Comissao, Faturamento, Industrial, Manutencao, PCP, PD, Planejamento, Processo, Qualidade).
  - Handlers e models usam métodos estáticos.
- **src/views/pages/<módulo>/** e **src/views/partials/navbar.php** (menu lateral por grupo).
- **src/cli/**: scripts PHP de linha de comando disparados em segundo plano (`nohup php ... &`). No Apache com mod_php, a resposta só é liberada quando o processo termina.
- **database/**: DDL/scripts.
  - **database/gazin-sqls/** contém os SQLs por módulo e `instalar.php`, que grava na `FOCCO3I.GAZIN_SQLS`.
- **vendor/**: dependências do Composer.
- **docker-compose-comissao.yml**: ambiente do portal, container `comissao_colchao`, porta **8050**, PHP 8.1.
- **docker-compose.yml**: API de rotinas original, container `api_rotinas`, porta 8002.

## Regras do Projeto
- **SQL na `FOCCO3I.GAZIN_SQLS`**, buscado por `idsql` (`modulo.tela.consulta`). A exceção são telas em teste.
  - Escritas e chamadas de pacote continuam com binds PDO.
  - Ver [TASK-REFATORACAO.md](TASK-REFATORACAO.md).
- **Não alterar** nada no schema OTIMIZA (OTM) nem em tabelas existentes do Focco. Objetos novos vão no FOCCO3I com prefixo `TGAZIN_` / `GAZIN_`.
- **Sem Python.** Testes e scripts são feitos em PHP dentro do container.
- O **acesso** é por perfil: o prefixo da URL vira o módulo (`Auth::extrairPrefixoUrl`), e os perfis são vinculados na tela Permissões.
- Particularidades do **PDO_OCI**:
  - bind de saída deve começar como `''` (com `null` volta vazio);
  - evitar `PARAM_INT|PARAM_INPUT_OUTPUT` (corrompe a memória do PHP);
  - evitar `MATERIALIZE` com subconsulta escalar sobre a CTE (ORA-03113 no 12.1).

## Funcionalidades

A lista completa de telas, rotas e situação está em [CONTROLE.md](CONTROLE.md).

### Planejamento
- **Planejamento Reposição CD**: sugestão de reposição do CD (objetos `FOCCO3I.GAZIN_PLA_REP_CD*` e job).
- **Follow-up Pedido Compra**: porte do relatório `OTIMIZA.pfollow_up_pdc_otm`, consultado direto, sem tabela temporária.
- **Material por Filial**: planejamento de material por filial, com seleção de vários planejadores.
- **Compra Direta Crossdocking** (em teste): porte da tela do OTM `compra_dir_cross`.
  - Gera a sugestão, cria as demandas independentes (`TDEMANDAS_INDEP`, um número por item) e roda o MRP do plano em segundo plano (`FOCCO3I_JOB.MRP_AUTO`).
  - No fim, mostra a OCP gerada e permite liberá-la para o comprador (`TGAZIN_LIB_ORD_COMPRA`, STATUS 1, equivalente ao FGAZIN_SUP004).

### Centro de Distribuição (CD)
- **Controle de Recebimento de Cargas**: Cadastro, consulta e atualização de recebimentos.
- **Agendamento de Cargas**: Permite agendar horários para recebimento de cargas.
- **Recibo de Pagamento das Descargas**: Gera recibos para pagamentos realizados.

### Sistema de Comissão de Produtividade
- **Dashboard de Comissões**: Visão geral de produtividade, ranking de funcionários e resumo por centro de trabalho.
- **Cadastro de Pontuação (UP)**: Define pontos por unidade produzida (UP) para cada produto.
- **Cadastro de Faixas de Comissão**: Configura faixas de comissão por percentual ou quantidade.
- **Cadastro de Vínculos**: Vincula funcionários a recursos e centros de trabalho (Normal ou Apoio).
- **Cadastro de Regras por Funcionário**: Define valor específico por UP para funcionários específicos.
- **Cadastro de Faltas**: Registra faltas integrais (I) ou parciais (P) que afetam comissão.
- **Relatório Diário**: Acompanhamento de produtividade diária por funcionário, recurso e centro.
- **Relatório de Comissões**: Processamento, aprovação e acompanhamento de comissões calculadas.
- **Relatório por Funcionário**: Histórico detalhado de produtividade individual.
- **Relatório por Centro de Trabalho**: Análise de produtividade por setor.

## Sistema de Comissão - Detalhes Técnicos

### Tabelas Customizadas (Oracle)
O sistema utiliza as seguintes tabelas customizadas (schema FOCCO3I):

1. **TGAZIN_PONTUACAO_PRODUTO**: Armazena pontos UP por produto
2. **TGAZIN_FAIXA_COMISSAO**: Define faixas de comissão (tipo P=percentual, Q=quantidade)
3. **TGAZIN_COMISSAO_CALC**: Armazena comissões calculadas
4. **TGAZIN_VINC_FUNC**: Vínculos funcionário-recurso-centro com tipo (N=Normal, A=Apoio)
5. **TGAZIN_REGRA_FUNC**: Regras específicas de comissão por funcionário
6. **TGAZIN_FALTA_FUNC**: Registro de faltas (I=Integral, P=Parcial)

### Funcionalidade de Funcionários de Apoio (TIPO_VINCULO = 'A')
- **Comportamento**: Funcionários de apoio ganham sobre a produtividade TOTAL do centro de trabalho
- **Recurso**: Para funcionários de apoio, o recurso é OPCIONAL (podem ser vinculados apenas ao centro)
- **Pontuação**: Recebem 100% dos pontos do centro (sem divisão)
- **Faltas**: Funcionários de apoio TAMBÉM sofrem desconto de falta (integral zera, parcial reduz 50%)
- **Regra Específica**: Se tiver regra cadastrada em TGAZIN_REGRA_FUNC, usa essa regra (ignora faixa padrão)

### Múltiplos Funcionários no Mesmo Recurso
- **Comportamento**: Quando dois ou mais funcionários estão vinculados ao mesmo recurso/centro, TODOS aparecem no relatório
- **Pontuação**: Cada funcionário recebe 100% dos pontos (SEM DIVISÃO)
- **Total do Centro**: O total do centro é calculado UMA VEZ por apontamento (evita duplicação)
- **Chave de Apontamento**: centro + recurso + item + ordem + data (para evitar duplicação)

### Desconto de Faltas
- **Falta Integral (I)**: Zera 100% dos pontos do dia
- **Falta Parcial (P)**: Reduz 50% dos pontos do dia
- **Aplicação**: O desconto é aplicado por DIA, não por período
- **Apoio no Período**: Para relatórios de período, o desconto é proporcional aos dias com falta

### Compatibilidade Retroativa
- **Coluna TIPO_VINCULO**: O sistema verifica se a coluna existe antes de usá-la
- **Método verificarColunaApoio()**: Em Vinculo.php, verifica existência da coluna no banco
- **Fallback**: Se a coluna não existir, assume TIPO_VINCULO = 'N' (Normal) para todos

### Scripts SQL Necessários (executar na ordem)
```sql
-- 1. Adicionar coluna TIPO_VINCULO (se tabela já existir)
ALTER TABLE FOCCO3I.TGAZIN_VINC_FUNC ADD TIPO_VINCULO CHAR(1) DEFAULT 'N' NOT NULL;

-- 2. Permitir NULL na coluna ID_RECURSO (para funcionários de apoio)
ALTER TABLE FOCCO3I.TGAZIN_VINC_FUNC MODIFY ID_RECURSO NUMBER(10) NULL;

-- 3. Criar índice para tipo de vínculo
CREATE INDEX FOCCO3I.IX_VINC_TIPO ON FOCCO3I.TGAZIN_VINC_FUNC(TIPO_VINCULO);

-- 4. Adicionar comentário
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC.TIPO_VINCULO IS 'N=Normal, A=Apoio';
```

### Integração com FOCCO ERP
O sistema integra com as seguintes tabelas do FOCCO:
- **TFUNCIONARIOS**: Cadastro de funcionários
- **TCENTROS_TRAB**: Centros de trabalho
- **TMAQUINAS**: Recursos/Máquinas
- **TAPONTAMENTOS_PROD**: Apontamentos de produção
- **TORDENS_PROD**: Ordens de produção
- **TITENS**: Produtos

### Controllers do Sistema de Comissão
- **ComissaoDashboardController**: Dashboard e APIs de resumo
- **ComissaoCadastroController**: CRUD de pontuação, faixas, vínculos, regras e faltas
- **ComissaoRelatorioController**: Relatórios e processamento de comissões

### Models Principais
- **Vinculo.php**: CRUD de vínculos com suporte a tipo (Normal/Apoio)
  - `verificarColunaApoio()`: Verifica se coluna TIPO_VINCULO existe
  - `listarApoioPorCentro()`: Lista funcionários de apoio de um centro
- **ApontamentoProducao.php**: Query de produtividade com JOIN aos vínculos
  - `produtividadeDiaria()`: Retorna TODOS os funcionários vinculados (sem ROW_NUMBER)
- **FaltaFuncionario.php**: CRUD e verificação de faltas
  - `verificarFaltasPeriodo()`: Retorna faltas em um período
- **RegraFuncionario.php**: Regras específicas de comissão
  - `buscarRegraAtiva()`: Busca regra ativa para funcionário/centro/data

### Rotas Principais
- `/comissao` ou `/comissao-dashboard`: Dashboard principal
- `/comissao-pontuacao`: Cadastro de pontuação UP
- `/comissao-faixas`: Cadastro de faixas de comissão
- `/comissao-vinculos`: Cadastro de vínculos (Normal/Apoio)
- `/comissao-regras`: Regras específicas por funcionário
- `/comissao-faltas`: Cadastro de faltas
- `/comissao-relatorio-diario`: Relatório de produtividade diária
- `/comissao-relatorio-comissoes`: Relatório e processamento de comissões
- `/comissao-relatorio-funcionario`: Relatório individual
- `/comissao-relatorio-centro`: Relatório por centro de trabalho

### APIs de Relatório
- `GET /comissao-api-produtividade`: Produtividade diária com suporte a funcionários de apoio
- `GET /comissao-api-comissoes`: Comissões do período com apoio e desconto de faltas
- `GET /comissao-api-funcionario`: Relatório completo por funcionário (Normal ou Apoio)
- `GET /comissao-api-centro`: Relatório completo por centro de trabalho

## Como Funciona
- As requisições chegam pelo `public/index.php`, que inicializa o roteador e direciona para o controller.
- O controller chama o handler, que concentra regra de negócio e validação. O handler usa o model, que acessa o Oracle:
  - leitura pela GAZIN_SQLS (`sql()` + `lit()`);
  - escrita com binds PDO.
- Telas: a view (HTML + JS) chama APIs `POST /<modulo>-api-*`, que respondem em JSON.
- Os resultados pesados ficam em cache em arquivo (`DashboardCache`, `/tmp/fat_dashboard_cache`) ou por usuário.

## Como Rodar o Projeto
1. Configure o ambiente (`src/Env.php` / `.env`). Senhas não vão para o repositório.
2. `docker compose -f docker-compose-comissao.yml up -d --build`
3. Acesse `http://localhost:8050`.
4. Validar sintaxe: `docker exec comissao_colchao php -l <arquivo>`.
5. Gravar SQLs novos: `docker exec comissao_colchao php database/gazin-sqls/instalar.php`.

## Observações
- As configurações de ambiente estão em `src/Config.php` e `src/Env.php`.
- O autoload segue o padrão PSR-4.
- Grafo do código em `graphify-out/` (`graphify query "..."`; depois de alterar código, rodar `graphify update .`).
- Commits só depois de o usuário validar a alteração. Não versionar `.env_exemplo` nem `.claude/`.

---

## Histórico de Mudanças Recentes

### 05–06/11/2026 - Planejamento e GAZIN_SQLS
1. **Migração para GAZIN_SQLS** dos models novos:
   - Follow-up, Reposição CD e Material por Filial (28 `planejamento.*`);
   - Dashboard de Manutenção (19 `manutencao.dashboard.*`).
   - `instalar.php` passou a aceitar parâmetros de exemplo por SQL.
2. **Menus Planejamento e Processos Industriais** (commit a7f41e7). A Reposição CD saiu do P&D.
3. **Material por Filial**: filtro de planejador com seleção múltipla (inclui "(sem planejador)").
4. **Compra Direta Crossdocking** (nova, em teste, SQL no código):
   - sugestão com consumo, saldo e estoque, mais perfil, pedidos, itens do pedido e alteração de status;
   - **Gerar Linha** cria as demandas e dispara o MRP em segundo plano;
   - a tela acompanha o MRP e, no fim, oferece liberar a OCP para o comprador.
   - Correções:
     - número da demanda por item e por empresa (chave única `DEMAND_IND_EMP_NUM_DEM_UK`);
     - a tela mostra só a ordem que cobre a quantidade pedida (o MRP repõe o saldo do dia e o estoque de segurança).

**Arquivos novos (não commitados):**
- `src/controllers/Planejamento/CompraDiretaCrossController.php`
- `src/handlers/Planejamento/CompraDiretaCrossHandler.php`
- `src/models/Planejamento/CompraDiretaCross.php`
- `src/views/pages/planejamento/compra-direta-cross.php`
- `src/cli/compra-direta-cross-mrp.php`

### 19/02/2026 - Sessão de Desenvolvimento
**Funcionalidades implementadas:**

1. **Funcionários de Apoio (TIPO_VINCULO = 'A')**
   - Criada coluna TIPO_VINCULO na tabela TGAZIN_VINC_FUNC
   - Funcionários de apoio ganham 100% dos pontos do centro de trabalho
   - ID_RECURSO é opcional para apoio (pode ser NULL)
   - Interface de cadastro de vínculos atualizada com dropdown de tipo
   - Scripts: `database/alter_vinculo_apoio.sql`

2. **Múltiplos Funcionários no Mesmo Recurso**
   - Removida restrição ROW_NUMBER() que limitava a 1 funcionário por recurso
   - Todos os funcionários vinculados aparecem no relatório com pontuação completa
   - Sem divisão de pontos entre funcionários

3. **Correção de Duplicação de Pontos**
   - Total do centro é calculado uma única vez por apontamento
   - Chave de controle: centro + recurso + item + ordem + data
   - Evita multiplicação de pontos quando há múltiplos funcionários

4. **Desconto de Faltas para Apoio**
   - Funcionários de apoio também sofrem desconto de falta
   - Falta integral: zera pontos do dia
   - Falta parcial: reduz 50%

5. **Relatório por Funcionário para Apoio**
   - Funcionários de apoio agora aparecem no relatório individual
   - Busca todos os apontamentos do centro vinculado

6. **Relatório por Centro de Trabalho**
   - Criada função getRelatorioCentro() que estava faltando
   - Inclui: ranking de funcionários, evolução diária, recursos, comissão estimada

7. **Compatibilidade Retroativa**
   - Sistema funciona mesmo antes de executar os scripts SQL
   - Método verificarColunaApoio() verifica existência da coluna

**Arquivos modificados:**
- `src/models/Vinculo.php` - Suporte a tipo apoio, verificação de coluna
- `src/models/ApontamentoProducao.php` - Query sem ROW_NUMBER
- `src/controllers/ComissaoRelatorioController.php` - Lógica de apoio em todos os relatórios
- `src/controllers/ComissaoCadastroController.php` - CRUD com tipo_vinculo
- `src/views/pages/comissao/vinculo.php` - Dropdown de tipo
- `public/src/js/comissao-vinculo.js` - Toggle de recurso obrigatório
- `database/ddl_tabelas_comissao.sql` - DDL atualizada
- `database/alter_vinculo_apoio.sql` - Script de migração (NOVO)

---

Para dúvidas ou melhorias, consulte o README ou entre em contato com o responsável pelo projeto.
