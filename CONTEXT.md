# Contexto do Projeto

Este projeto é uma API PHP para controle de recebimento de cargas, agendamento de cargas e geração de recibos de pagamento das descargas. Também inclui um **Sistema de Comissionamento de Produtividade** para acompanhamento de produção de colchões. Utiliza uma arquitetura MVC simples, com rotas, controllers, models e views, além de autenticação básica.

## Estrutura
- **core/**: Classes base do sistema (Auth, Controller, Database, Request, Router).
- **public/**: Arquivos públicos, incluindo o ponto de entrada da aplicação e assets (CSS/JS).
- **src/**: Configurações, rotas, controllers, models, utilitários e views.
- **vendor/**: Dependências gerenciadas pelo Composer.
- **Dockerfile / docker-compose.yml**: Arquivos para configuração e execução do ambiente Docker.
- **docker-compose-comissao.yml**: Docker Compose paralelo para o sistema de comissão (porta 8003).

## Funcionalidades

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

## Histórico de Mudanças Recentes

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
