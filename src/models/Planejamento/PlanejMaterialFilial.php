<?php

namespace src\models\Planejamento;

use core\Database;

/**
 * Consulta Planej. Material por Filial — porte do relatório Focco "pla_mat_filial".
 * Geração: FOCCO3I.GAZIN_PMF.GERAR (resultado em FOCCO3I.TGAZIN_PMF, uma geração ativa por usuário).
 * Interfábrica: FOCCO3I.GAZIN_PMF_GERA_INTERFABRICA (cópia da FOCCO3I.GERA_INTERFABRICA lendo a TGAZIN_PMF).
 * Scripts dos objetos: database/planej-material-filial/.
 * Leituras: FOCCO3I.GAZIN_SQLS planejamento.materialFilial.* (registro em database/gazin-sqls/2026-11-05-planejamento.php).
 * Gravações (geração com parâmetro de saída, seleção/quantidade com contagem e COMMIT, interfábrica): bind aqui no PHP.
 */
class PlanejMaterialFilial
{
    private static function bind(\PDOStatement $st, array $b): void
    {
        foreach ($b as $k => $v) {
            $st->bindValue(':' . $k, $v, $v === null ? \PDO::PARAM_NULL : (is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
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

    private static function exec(string $sql, array $b = []): int
    {
        try {
            $st = Database::getInstance('focco')->prepare($sql);
            self::bind($st, $b);
            $st->execute();
            return $st->rowCount();
        } catch (\PDOException $e) {
            if (preg_match('/ORA-20\d{3}:\s*(.+?)(?:\s*ORA-|\n|$)/s', $e->getMessage(), $m)) throw new \Exception(trim($m[1]), 409);
            throw $e;
        }
    }

    /** "1, 2,3" ou [1,2,3] -> "1,2,3" (somente inteiros positivos). */
    public static function listaInt($v): string
    {
        $itens = is_array($v) ? $v : preg_split('/[^0-9]+/', (string) $v);
        return implode(',', array_unique(array_filter(array_map('intval', $itens), fn($n) => $n > 0)));
    }

    private static function masc(array $l): string
    {
        return self::lit($l['MASC_ID'] === null ? null : (int) $l['MASC_ID']);
    }

    // ── Filtros ──────────────────────────────────────────────────────

    /** Mesmas empresas do Focco: grupo 1003, exceto 6 e 12. */
    public static function empresas(): array
    {
        return self::sql('planejamento.materialFilial.listarEmpresas');
    }

    // ── Geração / sessão do usuário ──────────────────────────────────

    public static function gerar(string $usuario, string $empresas, string $dtIni, string $dtFim): int
    {
        $pdo = Database::getInstance('focco');
        $st  = $pdo->prepare("BEGIN FOCCO3I.GAZIN_PMF.GERAR(:usu, :emp, TO_DATE(:ini, 'YYYY-MM-DD'), TO_DATE(:fim, 'YYYY-MM-DD'), :sessao); END;");
        $sessao = 0;
        $st->bindValue(':usu', mb_substr($usuario, 0, 60));
        $st->bindValue(':emp', $empresas);
        $st->bindValue(':ini', $dtIni);
        $st->bindValue(':fim', $dtFim);
        $st->bindParam(':sessao', $sessao, \PDO::PARAM_INT | \PDO::PARAM_INPUT_OUTPUT, 20);
        try {
            $st->execute();
        } catch (\PDOException $e) {
            if (preg_match('/ORA-20\d{3}:\s*(.+?)(?:\s*ORA-|\n|$)/s', $e->getMessage(), $m)) throw new \Exception(trim($m[1]), 422);
            throw $e;
        }
        return (int) $sessao;
    }

    /** Geração ativa do usuário (ou null). */
    public static function parametros(string $usuario): ?array
    {
        $r = self::sql('planejamento.materialFilial.buscarParametros', ['usuario' => self::lit(mb_substr($usuario, 0, 60))]);
        return $r[0] ?? null;
    }

    public static function listar(int $sessao): array
    {
        // Observação: histórico novo + o do Focco antigo (OTIMIZA.TOBS_PLA_REP_CD_OTM, somente leitura).
        // Sem acesso ao histórico do OTIMIZA: usa só o histórico novo.
        try {
            return self::sql('planejamento.materialFilial.listar', ['sessao' => $sessao]);
        } catch (\RuntimeException $e) {
            return self::sql('planejamento.materialFilial.listarSemHistOtimiza', ['sessao' => $sessao]);
        }
    }

    public static function linha(int $sessao, int $id): ?array
    {
        $r = self::sql('planejamento.materialFilial.buscarLinha', ['sessao' => $sessao, 'id' => $id]);
        return $r[0] ?? null;
    }

    // ── Seleção / quantidade ─────────────────────────────────────────

    public static function selecionar(int $sessao, string $ids, int $selecao): int
    {
        $n = self::exec("UPDATE FOCCO3I.TGAZIN_PMF SET SELECAO = :sel WHERE SESSAO = :s AND ID IN ($ids)",
                        ['sel' => $selecao, 's' => $sessao]);
        Database::getInstance('focco')->exec('COMMIT');
        return $n;
    }

    public static function alterarQtde(int $sessao, int $id, float $qtde): void
    {
        self::exec("UPDATE FOCCO3I.TGAZIN_PMF SET QTDE_ALT = :q WHERE SESSAO = :s AND ID = :id",
                   ['q' => (string) $qtde, 's' => $sessao, 'id' => $id]);
        Database::getInstance('focco')->exec('COMMIT');
    }

    // ── Detalhamentos ────────────────────────────────────────────────

    /** Pedidos pendentes da linha: PDV (cliente 1 / estab. 1) na FL 1, PDC nas demais. */
    public static function pedidos(array $l): array
    {
        $p = ['item_id' => (int) $l['ITEM_ID'], 'masc_id' => self::masc($l)];
        if ((int) $l['EMPR_ID'] === 1) return self::sql('planejamento.materialFilial.listarPedidosVenda', $p);
        return self::sql('planejamento.materialFilial.listarPedidosCompra', $p + ['empr' => (int) $l['EMPR_ID']]);
    }

    public static function pedidoItens(string $tipo, int $pedId): array
    {
        return self::sql($tipo === 'PDV' ? 'planejamento.materialFilial.listarItensPedidoVenda'
                                         : 'planejamento.materialFilial.listarItensPedidoCompra', ['id' => $pedId]);
    }

    /** Saldo nos "outros almoxarifados" da filial (mesma lista do parâmetro da filial). */
    public static function almoxarifados(array $l): array
    {
        return self::sql('planejamento.materialFilial.listarAlmoxarifados',
                         ['empr' => (int) $l['EMPR_ID'], 'item_id' => (int) $l['ITEM_ID'], 'masc_id' => self::masc($l)]);
    }

    public static function necessidade(int $sessao, int $id): ?float
    {
        $r = self::sql('planejamento.materialFilial.buscarNecessidade', ['sessao' => $sessao, 'id' => $id]);
        $v = $r[0]['NEC'] ?? null;
        return $v === null ? null : (float) str_replace(',', '.', $v);
    }

    // ── Resumo dos itens selecionados / interfábrica ─────────────────

    public static function resumo(int $sessao): array
    {
        return self::sql('planejamento.materialFilial.listarResumo', ['sessao' => $sessao]);
    }

    public static function gerarInterfabrica(int $sessao): void
    {
        self::exec("BEGIN FOCCO3I.GAZIN_PMF_GERA_INTERFABRICA(:s); END;", ['s' => (string) $sessao]);
    }

    public static function mensagens(int $sessao): array
    {
        return self::sql('planejamento.materialFilial.listarMensagens', ['sessao' => $sessao]);
    }
}
