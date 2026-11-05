<?php

namespace src\models\Manutencao;

use core\Database;

/**
 * Dashboard de Ordens de Manutenção — porte do painel OTM "CLI/dashboard_ordens_manut".
 *
 * Mesmas regras das rotinas originais, executadas direto (somente leitura, sem tabela de sessão):
 *  - OTIMIZA.PDASHBOARD_DISTR_MANUT_OTM  -> distribuicao()
 *  - OTIMIZA.PDASHBOARD_ORDENS_FUNC_OTM  -> funcHoras()
 *  - OTIMIZA.FQTD_ORDENS_MAN_OTM         -> CTEs FQ_F / FQ_A / FQ_T
 *  - FOCCO3I.VGAZIN_FUNC_ORDEM_MANUT_OTM -> funcOrdens()
 *  - OTIMIZA.VGAZIN_FUNC_ORDEM_DET_OTM   -> drill 'func_ordens'
 *  - FOCCO3I.TESTOQUE_MANUTENCAO_JOB_OTM -> estoque() (alimentada pelo job JOB_OTM_ESTQ_MANUT)
 * Período: "TO_DATE(campo) BETWEEN ini AND fim" do OTM = campo >= ini AND campo < fim + 1.
 *
 * SQLs na FOCCO3I.GAZIN_SQLS: manutencao.dashboard.* (registro em database/gazin-sqls/2026-11-05-manutencao.php).
 */
class DashboardManutencao
{
    // ── infraestrutura ───────────────────────────────────────────────

    /** Executa um SQL da FOCCO3I.GAZIN_SQLS. */
    private static function sql(string $idsql, array $params = []): array
    {
        $r = Database::switchParams('focco', $params, $idsql, true, false);
        if (!empty($r['error'])) throw new \RuntimeException("Falha no SQL $idsql: " . $r['error']);
        return is_array($r['retorno']) ? $r['retorno'] : [];
    }

    /** Texto -> literal Oracle para a substituição textual do switchParams (\ $ : = via CHR). */
    private static function lit(string $v): string
    {
        $v = strtr(str_replace("'", "''", $v), [
            '\\' => "' || CHR(92) || '", '$' => "' || CHR(36) || '",
            ':'  => "' || CHR(58) || '", '=' => "' || CHR(61) || '",
        ]);
        return "'" . $v . "'";
    }

    private static function data(string $d): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
            throw new \InvalidArgumentException("Data inválida: $d", 400);
        }
        return "DATE '$d'";
    }

    /** Parâmetros comuns: empresa e período (datas validadas). */
    private static function periodo(int $e, string $di, string $df): array
    {
        return ['empresa' => $e, 'dt_ini' => self::data($di), 'dt_fim' => self::data($df)];
    }

    // ── KPIs + OS Geradas x Atendidas + OS Preventivas ───────────────

    /**
     * Geradas/Atendidas: blocos GERADA_ATEND (distintas por nº ordem + data, só ordens com serviço/máquina).
     * Preventivas: bloco GERADA_ATEND_PREV (Programadas = TPERFIL_ITENS_CALC plano 1).
     */
    public static function resumo(int $e, string $di, string $df): array
    {
        return self::sql('manutencao.dashboard.resumo', self::periodo($e, $di, $df))[0] ?? [];
    }

    // ── Distribuição por tipo (PDASHBOARD_DISTR_MANUT_OTM) ───────────

    /**
     * Por tipo: QTD = "Quantidade Ordens" do OTM (contagem de ordens fechadas da máquina/tipo),
     * VALOR = material + serviço de terceiros, MINUTOS = horas de serviço x 60 (bloco DET_GRUPO).
     * PERC_QTD truncado em 2 casas, como no OTM.
     */
    public static function distribuicao(int $e, string $di, string $df): array
    {
        return self::sql('manutencao.dashboard.distribuicao', self::periodo($e, $di, $df));
    }

    // ── Empresa / Grupo / Valor (bloco GRUPO) ────────────────────────

    public static function grupos(int $e, string $di, string $df): array
    {
        return self::sql('manutencao.dashboard.listarGrupos', self::periodo($e, $di, $df));
    }

    // ── Funcionário / Ordens Fechada / Aberta (bloco ORDENS_FUNC) ────

    /** Funcionários da VGAZIN_FUNC_ORDEM_MANUT_OTM no período (= quem tem fechada ou aberta). */
    public static function funcOrdens(int $e, string $di, string $df): array
    {
        return self::sql('manutencao.dashboard.listarFuncOrdens', self::periodo($e, $di, $df));
    }

    // ── Funcionário / Min. Disp. / Exec. / % / MTBF / MTTR (PDASHBOARD_ORDENS_FUNC_OTM) ──

    public static function funcHoras(int $e, string $di, string $df): array
    {
        return self::sql('manutencao.dashboard.listarFuncHoras', self::periodo($e, $di, $df));
    }

    // ── Custos Manutenção de Máquinas e Equipamentos (gráfico do painel OTM) ──

    /**
     * Mesmo SQL do gráfico OTM (capturado do v$sql do painel):
     *  - Orçado: OTIMIZA.TORCAMENTO_MENSAL_CUSTO_OTM (período dentro de um mês -> mês inteiro).
     *  - Mov. Estoque: contabilidade, contas reduzidas 1541/1550, lançamentos EST (débito - crédito).
     *  - Serv. Terceiros - Compra Direta: mesmas contas, lançamentos NFE/OUT (débito - crédito).
     *  - Realizado: Mov. Estoque + Serv. Terceiros.
     */
    public static function custos(int $e, string $di, string $df): array
    {
        $p = self::periodo($e, $di, $df);
        $mesmoMes = substr($di, 0, 7) === substr($df, 0, 7);
        $p['orc_ini'] = $mesmoMes ? "TRUNC({$p['dt_ini']}, 'MM')" : $p['dt_ini'];
        $p['orc_fim'] = $mesmoMes ? "LAST_DAY({$p['dt_ini']})"    : $p['dt_fim'];
        return self::sql('manutencao.dashboard.buscarCustos', $p)[0] ?? [];
    }

    // ── Estoque manutenção (bloco ESTOQUE; snapshot diário do job) ───

    public static function estoque(): array
    {
        return self::sql('manutencao.dashboard.listarEstoque');
    }

    // ── Drill-downs (janelas que abrem ao clicar) ────────────────────

    public static function drill(string $tipo, int $e, string $di, string $df, array $p): array
    {
        $per = self::periodo($e, $di, $df);

        switch ($tipo) {
            // GRUPO -> "Detalhar Grupo": máquinas do grupo
            case 'grupo':
                return self::sql('manutencao.dashboard.drillGrupo', $per + ['grupo' => self::lit((string) ($p['g'] ?? ''))]);

            // DISTRIBUIDO -> DET_GRUPO: máquinas do tipo de manutenção
            case 'tipo':
                return self::sql('manutencao.dashboard.drillTipo', $per + ['tipo' => self::lit((string) ($p['tip'] ?? ''))]);

            // DET_GRUPO -> DET_GRUPO_DETALHAR: ordens da máquina no tipo
            case 'maquina':
                return self::sql('manutencao.dashboard.drillMaquina', $per + [
                    'tp_os'   => self::lit((string) ($p['tp'] ?? '')),
                    'maquina' => self::lit((string) ($p['maq'] ?? '')),
                ]);

            // DET_GRUPO_DETALHAR -> DET_DETALHAR_ORDEM: material x serviço de terceiros
            case 'ordem':
                return self::sql('manutencao.dashboard.drillOrdem', ['ordem_id' => (int) ($p['o'] ?? 0)]);

            // GERADA_ATEND -> GERADA_ATEND_DET (Geradas / Atendidas) e GERADA_ATEND_PREV_DET (Liberadas / Realizadas)
            case 'geradas':         return self::sql('manutencao.dashboard.drillGeradas', $per);
            case 'atendidas':       return self::sql('manutencao.dashboard.drillAtendidas', $per);
            case 'prev_liberadas':  return self::sql('manutencao.dashboard.drillPrevLiberadas', $per);
            case 'prev_realizadas': return self::sql('manutencao.dashboard.drillPrevRealizadas', $per);

            // GERADA_ATEND_PREV_DET 'Prev. Programadas'
            case 'prev_programadas':
                return self::sql('manutencao.dashboard.drillPrevProgramadas', $per);

            // ATEND_FUNC -> ATEND_FUNC_DET: ordens do funcionário (minutos)
            case 'func_horas':
                return self::sql('manutencao.dashboard.drillFuncHoras', $per + ['func_id' => (int) ($p['f'] ?? 0)]);

            // ORDENS_FUNC -> ORDENS_FUNC_DET (VGAZIN_FUNC_ORDEM_DET_OTM): abertas (execução) + fechadas (responsável)
            case 'func_ordens':
                return self::sql('manutencao.dashboard.drillFuncOrdens', $per + ['func_id' => (int) ($p['f'] ?? 0)]);

            // ESTOQUE -> ESTOQUE_ITENS
            case 'estoque':
                return self::sql('manutencao.dashboard.drillEstoque', ['empresa' => (int) ($p['emp'] ?? 0)]);
        }
        throw new \InvalidArgumentException('Detalhe inválido', 400);
    }
}
