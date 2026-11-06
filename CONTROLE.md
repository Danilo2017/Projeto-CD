# Controle das Telas

Situação das telas do portal, por grupo do menu. O acesso é liberado por perfil na tela **Permissões**, e o perfil usa o prefixo da rota.
Atualizado em 06/11/2026.

Legenda da coluna SQL:
- **GAZIN_SQLS**: os SQLs estão na `FOCCO3I.GAZIN_SQLS` (padrão do projeto).
- **código**: os SQLs ainda estão no PHP.

## Planejamento (`planejamento-`)

| Tela | Rota | SQL | Situação |
|---|---|---|---|
| Planejamento Reposição CD | `/planejamento-reposicao-cd` | GAZIN_SQLS (`planejamento.reposicaoCd.*`) | Em produção (commit a7f41e7) |
| Follow-up Pedido Compra | `/planejamento-follow-up-pedido-compra` | GAZIN_SQLS (`planejamento.followUp.*`) | Em produção |
| Material por Filial | `/planejamento-material-filial` | GAZIN_SQLS (`planejamento.materialFilial.*`) | Seleção de vários planejadores: **não commitada** |
| Compra Direta Crossdocking | `/planejamento-compra-direta-cross` | código (até terminar os testes) | **Em teste, não commitada** |

### Compra Direta Crossdocking
- **Origem:** porte da tela do OTM `CLI/Suprimentos/compra_dir_cross`. Nada foi alterado no OTIMIZA.
- **Arquivos:**
  - `src/controllers/Planejamento/CompraDiretaCrossController.php`
  - `src/handlers/Planejamento/CompraDiretaCrossHandler.php`
  - `src/models/Planejamento/CompraDiretaCross.php`
  - `src/views/pages/planejamento/compra-direta-cross.php`
  - `src/cli/compra-direta-cross-mrp.php`
- **APIs** (`POST /planejamento-api-cdc-*`): `filtros`, `gerar`, `listar`, `detalhe`, `status`, `opcoes-linha`, `gerar-linha`, `mrp-status`, `liberar`, `concluir`.
- **Fluxo:**
  1. **Gerar:** consulta de sugestão (consumo, saldo, estoque, sugestão). Fica em cache por usuário por 12 h.
  2. **Perfil / Pedidos / Itens do pedido / Alterar Status:** mesmas consultas e ações do OTM. As observações vão para `FOCCO3I.TGAZIN_PLA_REP_CD_OBS`.
  3. **Gerar Linha:** cria as demandas independentes na `FOCCO3I.TDEMANDAS_INDEP`.
     - Cada item recebe **um número de demanda próprio**, igual ao maior da empresa + 1. O banco não aceita número repetido na mesma empresa (chave única `DEMAND_IND_EMP_NUM_DEM_UK`).
     - O ID da linha vem de `SEQ_ID_TDEMANDAS_INDEP`.
  4. **MRP:** roda logo em seguida, num processo PHP de linha de comando em segundo plano (`FOCCO3I_JOB.MRP_AUTO`), para a tela não travar. A tela acompanha o andamento pela `TLOG_MRP` e pela `TMENS_MRP`.
  5. **Fim do MRP:** a tela mostra as ordens planejadas (OCP) de cada demanda e pergunta se deseja liberar a ordem no FGAZIN_SUP004.
     - Se sim, grava na `TGAZIN_LIB_ORD_COMPRA` com STATUS 1, pelo pacote `DB_SUP_TGAZIN_LIB_ORD_COMPRA`. A ordem vai para o comprador (FGAZIN_SUP005).
- **Regras observadas no MRP:**
  - O MRP repõe o **saldo do dia**, não cada demanda. Outras demandas no mesmo dia e o estoque de segurança geram ordens a mais.
  - Por isso a tela marca só as ordens que somam a quantidade da demanda. As demais ficam em "ver as outras ordens do dia".
  - Item com `EMPR_PROD_ID` preenchido na `TITENS_ENGENHARIA` é planejado como OIF (inter-fábrica), não como OCP.
  - Liberação rejeitada pelo comprador volta a STATUS 0 e é apagada pelo MRP seguinte.
- **Pendências:**
  - Testar "Sim, liberar" de ponta a ponta (SUP004 → SUP005 → pedido de compra).
  - Mover os SQLs para a GAZIN_SQLS.
  - Commitar depois da validação.

## P&D (`pd-`)

| Tela | Rota | SQL | Situação |
|---|---|---|---|
| Inativação de Preço | `/pd-inativacao-preco` | GAZIN_SQLS (`pd.inativacao_preco.*`) | Em produção |
| Análise Itens Criação Vendas | `/pd-analise-itens-criacao` | código | Em produção (c1a6263 / e51f88b) |

## Processos Industriais (`industrial-`)
SQL na GAZIN_SQLS (`industrial.*`) em Peças, Faturamento, GGF, Consumo Molinha e Consumo Molejo Filiais.

| Tela | Rota | Situação |
|---|---|---|
| Peças por Colaborador | `/industrial-pecas-colaborador` | Em produção |
| Faturamento por Colaborador | `/industrial-faturamento-colaborador` | Em produção |
| GGF por Colaborador | `/industrial-ggf-colaborador` | Em produção |
| Comparativo Caixa Box | `/industrial-comparativo-caixa-box` | Em produção |
| Consumo Molinha | `/industrial-consumo-molinha` | Em produção |
| Consumo Molejo Filiais | `/industrial-consumo-molejo-filiais` | Em produção |

## Manutenção (`manutencao-`)

| Tela | Rota | Situação |
|---|---|---|
| Dashboard | `/manutencao-dashboard` | Em produção. SQL na GAZIN_SQLS (`manutencao.dashboard.*`) |
| Gestão de Ordens | `/manutencao-gestao-ordens` | Em produção |
| Liberação de Ordens | `/manutencao-liberacao-ordens` | Em produção |
| Gerar Ordem | `/manutencao-gerar-ordem` | Em produção |
| Configuração de Checklist | `/manutencao-chklist-config` | Em produção |

## Processo (`processo-`)
- **Trocas e transferências:**
  - Troca de Almoxarifado (geral, por carga, por pedido): `/processo-troca-almox`, `/processo-troca-almox-carga`, `/processo-troca-almox-pedido`
  - Troca de Tipo de NF: `/processo-troca-tipo-nf`
  - Transferência de Estoque: `/processo-transferencia-estoque`
- **Relatórios** (`/processo-relatorio-*`): movimentação de estoque (geral, refugo, variação de custo), variação da taxa GGF, consumo Thermoplast, consumo/demanda de espuma, variação de NF-e, despesa por centro de custo.

## Demais grupos
- **Comissão** (`comissao-`): dashboard, cadastros (pontuação UP, faixas, vínculos, regras, faltas) e relatórios. Detalhes em [CONTEXT.md](CONTEXT.md).
- **CD** (`cd-`): dashboard, calendário de agendamento, indicadores e projeção de carga.
  - A Projeção de Carga CD (clientes 5210/5487, WMS85) é separada da tela original.
- **Faturamento** (`faturamento-`, `meta-empresa`):
  - dashboard, meta por empresa, programação, eficiência UEP, análise de tanque;
  - carteira PDV (geral e por filial).
- **Pedidos:** pedidos de transferência (`/pedidos-transferencia`).
- **Cargas:** projeção de carga (`/carga-projecao`).
- **PCP** (`pcp-`):
  - resumo de lote;
  - relatórios por setor (`/pcp-relatorio-*`): caixote, produção, tampo bordado/liso, manta, molas, cordão, borda aço, espuma vertical/horizontal, pillow, FPT, Optron, mesa de corte/faixa, rolo bordado, disco de corte, expedição de rolo, caixa box, Robotec abastecedor.
- **Apontamento:** apontamento de produção (`/apontamento-producao`).
- **Qualidade** (`qualidade-rastreabilidade-*`): rastreabilidade de costura, tampo bordado, linha de montagem, caixote de mola, molas, cordão de molas, borda de molas e fixação de borda.
- **Permissões** (`/permissao`): perfis de acesso e vínculo de usuários.

## O que não está commitado (06/11/2026)
- `src/routes.php` e `src/views/partials/navbar.php`: rotas e menu da Compra Direta Crossdocking.
- `src/views/pages/planejamento/material-filial.php`: seleção de vários planejadores.
- Os arquivos novos da Compra Direta Crossdocking (controller, handler, model, view, `src/cli/`).

---

Consulte o arquivo [CONTEXT.md](CONTEXT.md) para mais detalhes sobre a estrutura geral do projeto.
