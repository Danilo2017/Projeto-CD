# TASK — Refatoração (Rodada 2)

> Reconstruído em 05/11/2026 a partir do texto colado no Claude Code em 16/06/2026 (duas mensagens: seção 1 às 12:15, seção 2 e critérios de aceite às 13:18). O arquivo original não foi encontrado. O conteúdo abaixo é o texto daquelas mensagens, só formatado em Markdown.

## 1. Remover SQLs hardcoded do código — usar focco3i.gazin_sqls

### Problema

Models e handlers têm SQLs escritos diretamente no PHP (strings com SELECT, INSERT, UPDATE). Isso exige deploy toda vez que um SQL precisa mudar.

### Solução

Todos os SQLs devem ser armazenados na tabela `focco3i.gazin_sqls`. O código PHP só referencia o `idsql` e usa `Database::switchParams()` para executar.

```php
// Antes — SQL hardcoded no PHP:
$sql = "SELECT * FROM TFUNCIONARIOS WHERE ATIVO = 1 AND COD_FUNC = :cod";
$result = $pdo->prepare($sql);

// Depois — SQL no banco, PHP só orquestra:
$result = Database::switchParams('focco', ['cod' => $cod], 'funcionario.buscarPorCodigo', exec: true);
```

### Convenção de nomenclatura dos idsql

```
{modulo}.{entidade}.{acao}

exemplos:
  cd.agendamento.listar
  cd.agendamento.inserir
  cd.recibo.buscarPorNumero
  comissao.pontuacao.listarAtivas
  comissao.faixa.verificarConflito
  comissao.comissao.salvarCalculo
  auth.perfil.buscarPorUsuario
```

### Parâmetros condicionais no SQL

Para cláusulas opcionais (ex: filtros que podem ou não existir), o padrão é substituir um placeholder textual completo:

```sql
-- SQL no banco (idsql = comissao.relatorio.listar):
SELECT * FROM TGAZIN_COMISSAO_CALC
WHERE EMPRESA_ID = :empresa
:filtro_status
:filtro_periodo
ORDER BY DT_CALCULO DESC
```

```php
// PHP monta os fragmentos e passa como parâmetro:
$params = [
    'empresa'        => $empresaId,
    'filtro_status'  => $status  ? "AND STATUS = '$status'"          : '--',
    'filtro_periodo' => $periodo ? "AND DT_CALCULO >= DATE '$periodo'" : '--',
];
Database::switchParams('focco', $params, 'comissao.relatorio.listar', exec: true);
```

O `--` no Oracle funciona como comentário de linha, então o fragmento vira linha morta sem quebrar o SQL.

### O que precisa ser feito

- Levantar todos os SQLs inline dos models (`src/models/CD/`, `src/models/Comissao/`, etc.)
- Inserir cada SQL na `focco3i.gazin_sqls` com o idsql seguindo a convenção acima
- Substituir os SQLs inline por chamadas a `Database::switchParams()` com o respectivo idsql
- Remover `sqlDireto` e queries diretas nos models

### Extra (recomendado): Tela de manutenção de SQLs para admin

Criar módulo interno acessível apenas por usuários com perfil ADMIN para visualizar e editar os SQLs do sistema sem precisar de deploy.

Tabelas necessárias:

```sql
-- Tabela de auditoria de alterações nos SQLs
CREATE TABLE FOCCO3I.TGAZIN_SQL_LOG (
    IDLOG          NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    IDSQL          VARCHAR2(100)  NOT NULL,
    SQL_ANTERIOR   CLOB,
    SQL_NOVO       CLOB,
    USUARIO        VARCHAR2(50)   NOT NULL,
    DT_ALTERACAO   DATE           DEFAULT SYSDATE NOT NULL,
    OBSERVACAO     VARCHAR2(500)
);
```

Funcionalidades da tela:

- Listar todos os SQLs com idsql, prévia do SQL e data da última alteração
- Editar o SQL com editor de texto (realce de sintaxe SQL opcional)
- Antes de salvar: executar dry-run (`exec: false` no `switchParams`) para validar sintaxe
- Salvar com registro automático em `TGAZIN_SQL_LOG` (SQL anterior + novo + usuário + observação)
- Visualizar histórico de alterações por idsql

Rota: `/admin-sqls` — protegida por perfil ADMIN

## 2. Handlers e Models devem usar métodos estáticos

### Problema

Handlers e models são instanciados com `new` e têm construtores, o que carrega a classe inteira na memória mesmo quando apenas um método é necessário.

### Solução

Converter todos os métodos de handlers e models para `static`. Chamadas viram:

```php
// Antes:
$handler = new ComissaoCadastroHandler();
$resultado = $handler->listarPontuacoes($empresa, $filtros);

// Depois:
$resultado = ComissaoCadastroHandler::listarPontuacoes($empresa, $filtros);
```

### Regras

- Remover construtores dos handlers e models (se existirem)
- Todos os métodos `public` viram `public static`
- Métodos auxiliares privados viram `private static`
- Atributos de instância viram atributos de classe (`static $cache = []`) — já é o padrão atual em alguns models como `ApontamentoProducao`
- Controllers chamam via `NomeHandler::metodo()` diretamente

### O que precisa ser feito

- `src/handlers/CD/CDCalendarioHandler.php` — converter para static
- `src/handlers/CD/CDDashboardHandler.php` — converter para static
- `src/handlers/Comissao/ComissaoCadastroHandler.php` — converter para static
- `src/handlers/Comissao/ComissaoRelatorioHandler.php` — converter para static
- `src/handlers/Comissao/ComissaoDashboardHandler.php` — converter para static
- Todos os models em `src/models/CD/` e `src/models/Comissao/` que ainda tiverem métodos de instância
- Atualizar controllers para usar `::` em vez de `->` nas chamadas

> Nota: `ApontamentoProducao.php`, `Comissao.php` e outros já têm boa parte dos métodos como static. A conversão será incremental — verificar arquivo por arquivo.

## Critérios de aceite

> Situação conferida em 05/11/2026 (auditoria por tokenizer PHP em 104 handlers/models e consulta da gazin_sqls).

- [ ] Zero SQLs hardcoded nos models e handlers — todos no banco via idsql
  - 14 arquivos commitados ainda com SQL no PHP (GazinSqls.php é exceção: é a própria tela de SQLs): ComissaoCadastroHandler, FaturamentoProgramacaoHandler, ApontamentoProducaoHandler, ProjecaoCarga, PontuacaoProduto, CarteiraPDV, CarteiraPDVFilial, OrdemChecklist, OrdemManutencao, RelatorioProd, AnaliseItensCriacao, InativacaoPreco, PerfilAcesso, DespesaCentroCusto
  - os 9 novos (sem commit) já migrados em 05/11/2026 — ver "Migração dos models novos" abaixo
- [ ] Convenção de nomenclatura `{modulo}.{entidade}.{acao}` seguida em todos os idsql
  - os 261 idsql usados existem na gazin_sqls; 58 fogem do padrão (snake_case, hífen ou 4 partes)
- [x] Tela `/admin-sqls` funcional, acessível apenas para ADMIN, com log de alterações
  - TGAZIN_SQL_LOG sem registros: os SQLs foram incluídos por script, não pela tela
- [x] Todos os métodos de handlers são static
- [x] Todos os métodos de models são static
- [x] Nenhum `new NomeHandler()` ou `new NomeModel()` nos controllers
- [x] Encoding corrigido em `ComissaoCadastroController.php`
- [x] `PermissaoController` gerencia `TGAZIN_USUARIO_PERFIL` / `TGAZIN_PERFIL_ACESSO`
- [x] `PermissaoUsuario.php` removido
- [x] `buscarPermissoesLegado()` removido do `Login.php`
- [ ] Views não leem `$_SESSION` diretamente
  - 7 ocorrências: manutencao (chklist-config, dashboard, gerar-ordem, gestao-ordens, liberacao-ordens), pcp/apontamento-producao, pcp/apontamento-producao-operacao

### Migração dos models novos (05/11/2026)

62 SQLs incluídos na `focco3i.gazin_sqls` (somente INSERT de idsql novos, com registro na `TGAZIN_SQL_LOG`; nenhum SQL existente alterado), todos no padrão `{modulo}.{entidade}.{acao}`:

| Registro (database/gazin-sqls/) | idsql | Models |
| --- | --- | --- |
| 2026-11-05-industrial.php | 15 `industrial.*` | PecasColaborador, FaturamentoColaborador, GgfColaborador, ConsumoMolinha, ConsumoMolejoFiliais |
| 2026-11-05-planejamento.php | 6 `planejamento.followUp.*`, 9 `planejamento.reposicaoCd.*`, 13 `planejamento.materialFilial.*` | FollowUpPedidoCompra, PlanejamentoReposicaoCD, PlanejMaterialFilial |
| 2026-11-05-manutencao.php | 19 `manutencao.dashboard.*` | DashboardManutencao |

- Instalação: `php database/gazin-sqls/instalar.php <registro> [--aplicar]` (compila cada SQL antes; idsql existente com SQL diferente não é sobrescrito).
- Execução: `Database::switchParams`. Textos digitados vão como literal com aspas dobradas e `\ $ : =` via `CHR()`, porque a troca de parâmetros é textual.
- Conferência: cada método de leitura rodado no model antigo e no novo no mesmo instante, com os mesmos filtros (inclusive textos com aspas e caracteres especiais) — sem nenhuma diferença.
- Continuam no PHP com bind, por serem gravações/chamadas de pacote: Reposição CD (`GAZIN_PLA_REP_CD.GERAR`/`ALTERAR_*`), Material por Filial (`GAZIN_PMF.GERAR` com parâmetro de saída, `UPDATE TGAZIN_PMF` de seleção/quantidade, `GAZIN_PMF_GERA_INTERFABRICA`).

### Ajustes feitos em 05/11/2026

- `core/Auth.php`: módulos `planejamento` e `industrial` incluídos na lista de módulos conhecidos (as APIs desses módulos estavam liberadas para qualquer usuário logado) e na página inicial por perfil.
- Removida a rota temporária `/tmp-insert-vertical-espuma` (`AdminSqlsController::tmpInsertVerticalEspuma`): qualquer usuário logado podia sobrescrever o SQL `pcp.relatorioProd.verticalEspuma` e apagar o cache de SQLs.
