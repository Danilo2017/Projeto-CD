<?php

namespace src\models\Planejamento;

use core\Database;

/**
 * Planejamento de Reposição CD.
 *
 * Os dados vêm do snapshot FOCCO3I.TGAZIN_PLA_REP_CD, gerado pelo pacote
 * FOCCO3I.GAZIN_PLA_REP_CD (job JOB_GAZIN_PLA_REP_CD a cada 30 min, ou "Recalcular" na tela).
 * Scripts dos objetos: database/plan-rep-cd/.
 * Leituras: FOCCO3I.GAZIN_SQLS planejamento.reposicaoCd.* (registro em database/gazin-sqls/2026-11-05-planejamento.php).
 * Gravações: chamadas ao pacote FOCCO3I.GAZIN_PLA_REP_CD com bind (precisam do erro ORA-20xxx tratado).
 */
class PlanejamentoReposicaoCD
{
    private static function bind(\PDOStatement $stmt, array $binds): void
    {
        foreach ($binds as $k => $v) {
            $stmt->bindValue(':' . $k, $v, $v === null ? \PDO::PARAM_NULL : (is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
    }

    /** Executa um SQL da FOCCO3I.GAZIN_SQLS. */
    private static function sql(string $idsql, array $params = []): array
    {
        $r = Database::switchParams('focco', $params, $idsql, true, false);
        if (!empty($r['error'])) throw new \RuntimeException("Falha no SQL $idsql: " . $r['error']);
        return is_array($r['retorno']) ? $r['retorno'] : [];
    }

    /** Valor -> literal Oracle para a substituição textual do switchParams (null -> NULL; \ $ : = via CHR). */
    private static function lit($v): string
    {
        if ($v === null) return 'NULL';
        if (is_int($v)) return (string) $v;
        $v = strtr(str_replace("'", "''", (string) $v), [
            '\\' => "' || CHR(92) || '", '$' => "' || CHR(36) || '",
            ':'  => "' || CHR(58) || '", '=' => "' || CHR(61) || '",
        ]);
        return "'" . $v . "'";
    }

    /** Executa um bloco PL/SQL; erros de negócio do pacote (ORA-20xxx) viram mensagem limpa. */
    private static function plsql(string $bloco, array $binds): void
    {
        try {
            $stmt = Database::getInstance('focco')->prepare($bloco);
            self::bind($stmt, $binds);
            $stmt->execute();
        } catch (\PDOException $e) {
            if (preg_match('/ORA-20\d{3}:\s*(.+?)(?:\s*ORA-|\n|$)/s', $e->getMessage(), $m)) {
                throw new \Exception(trim($m[1]), 409);
            }
            throw $e;
        }
    }

    // ── Tela principal ────────────────────────────────────────────────

    public static function controle(): array
    {
        $rows = self::sql('planejamento.reposicaoCd.buscarControle');
        return $rows[0] ?? [];
    }

    public static function recalcular(string $usuario): void
    {
        self::plsql("BEGIN FOCCO3I.GAZIN_PLA_REP_CD.GERAR(:usuario); END;", ['usuario' => mb_substr($usuario, 0, 60)]);
    }

    public static function listar(): array
    {
        // Observação: histórico novo (TGAZIN_PLA_REP_CD_OBS) + o do Focco antigo (OTIMIZA.TOBS_PLA_REP_CD_OTM,
        // somente leitura), para as duas telas mostrarem a mesma última observação.
        // Sem acesso ao histórico do OTIMIZA: usa só o histórico novo.
        try {
            return self::sql('planejamento.reposicaoCd.listar');
        } catch (\RuntimeException $e) {
            return self::sql('planejamento.reposicaoCd.listarSemHistOtimiza');
        }
    }

    // ── Detalhamentos ────────────────────────────────────────────────

    public static function demandas(int $itemId, ?string $mascara): array
    {
        return self::sql('planejamento.reposicaoCd.listarDemandas', ['mascara' => self::lit($mascara), 'item_id' => $itemId]);
    }

    /** Saldo, médias de saída e pedidos (15 dias) por filial. Médias = mesma regra da FRET_MEDIA_MOV_OTM. */
    public static function saldoFilial(int $itemId, int $itemEmprId, ?int $mascId, ?string $mascara): array
    {
        return self::sql('planejamento.reposicaoCd.listarSaldoFilial', [
            'mascara' => self::lit($mascara), 'item_id' => $itemId,
            'masc_id' => self::lit($mascId),  'itempr_id' => $itemEmprId,
        ]);
    }

    public static function pedidos(int $itemId, ?int $mascId): array
    {
        return self::sql('planejamento.reposicaoCd.listarPedidos', ['item_id' => $itemId, 'masc_id' => self::lit($mascId)]);
    }

    public static function pedidoItens(int $pedcId): array
    {
        return self::sql('planejamento.reposicaoCd.listarPedidoItens', ['pedc_id' => $pedcId]);
    }

    public static function planejadores(): array
    {
        return self::sql('planejamento.reposicaoCd.listarPlanejadores');
    }

    // ── Alterações (pacote FOCCO3I.GAZIN_PLA_REP_CD) ────────────────────

    public static function alterarEstoqueSeg(int $id, int $estqSeg): void
    {
        self::plsql("BEGIN FOCCO3I.GAZIN_PLA_REP_CD.ALTERAR_ESTOQUE_SEG(:id, :seg); END;", ['id' => $id, 'seg' => $estqSeg]);
    }

    public static function alterarStatus(int $id, string $status, string $obs, string $usuario): void
    {
        self::plsql(
            "BEGIN FOCCO3I.GAZIN_PLA_REP_CD.ALTERAR_STATUS(:id, :status, :obs, :usuario); END;",
            ['id' => $id, 'status' => $status, 'obs' => $obs, 'usuario' => mb_substr($usuario, 0, 60)]
        );
    }

    /** Retorna o nome do planejador gravado. */
    public static function alterarPlanejador(int $id, int $planejadorId): string
    {
        self::plsql(
            "DECLARE v_nome VARCHAR2(100);
             BEGIN FOCCO3I.GAZIN_PLA_REP_CD.ALTERAR_PLANEJADOR(:id, :func_id, v_nome); END;",
            ['id' => $id, 'func_id' => $planejadorId]
        );
        $rows = self::sql('planejamento.reposicaoCd.buscarPlanejador', ['id' => $id]);
        return (string) ($rows[0]['PLANEJADOR'] ?? '');
    }
}
