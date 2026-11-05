<?php

namespace src\models\Planejamento;

use core\Database;

/**
 * Follow-up Pedido Compra — porte do relatório Focco (OTIMIZA.PFOLLOW_UP_PDC_OTM).
 * Mesma consulta da procedure, executada direto (sem tabela temporária), só leitura em FOCCO3I.
 * SQLs na FOCCO3I.GAZIN_SQLS: planejamento.followUp.* (registro em database/gazin-sqls/2026-11-05-planejamento.php).
 */
class FollowUpPedidoCompra
{
    /** Executa um SQL da FOCCO3I.GAZIN_SQLS. */
    private static function sql(string $idsql, array $params = []): array
    {
        $r = Database::switchParams('focco', $params, $idsql, true, false);
        if (!empty($r['error'])) throw new \RuntimeException("Falha no SQL $idsql: " . $r['error']);
        return is_array($r['retorno']) ? $r['retorno'] : [];
    }

    /**
     * Texto digitado -> literal Oracle seguro para a substituição textual do switchParams.
     * Aspas são dobradas; \ $ : = saem como CHR() para o preg_replace/str_replace do switchParams
     * não interpretarem nem trocarem nada dentro do texto.
     */
    private static function lit(string $v): string
    {
        $v = str_replace("'", "''", $v);
        $v = strtr($v, [
            '\\' => "' || CHR(92) || '",
            '$'  => "' || CHR(36) || '",
            ':'  => "' || CHR(58) || '",
            '='  => "' || CHR(61) || '",
        ]);
        return "'" . $v . "'";
    }

    /** "1, 2,3" ou [1,2,3] -> "1,2,3" (somente inteiros positivos; seguro para IN (...)). */
    public static function listaInt($v): string
    {
        $itens = is_array($v) ? $v : preg_split('/[^0-9]+/', (string) $v);
        $nums  = array_filter(array_map('intval', $itens), fn($n) => $n > 0);
        return implode(',', array_unique($nums));
    }

    // ── Listas para os filtros ───────────────────────────────────────

    public static function empresas(): array
    {
        return self::sql('planejamento.followUp.listarEmpresas');
    }

    public static function classificacoes(): array
    {
        return self::sql('planejamento.followUp.listarClassificacoes');
    }

    public static function tiposNota(): array
    {
        return self::sql('planejamento.followUp.listarTiposNota');
    }

    public static function fornecedores(string $termo): array
    {
        $termo = trim($termo);
        return self::sql('planejamento.followUp.buscarFornecedores', [
            'cod'   => self::lit($termo),
            'texto' => self::lit('%' . mb_strtoupper($termo) . '%'),
        ]);
    }

    public static function mascaras(string $codItem, string $empresas): array
    {
        $empresas = self::listaInt($empresas);
        if ($empresas === '') return [];
        return self::sql('planejamento.followUp.listarMascaras', ['cod' => self::lit($codItem), 'empresas' => $empresas]);
    }

    // ── Consulta principal ───────────────────────────────────────────

    /**
     * @param string      $empresas  lista de TEMPRESAS.ID já validada (listaInt)
     * @param string|null $situacao  'P', 'A' ou null (todas)
     */
    public static function listar(string $empresas, ?string $situacao, string $clas, string $fornecedores,
                                  string $tiposNota, ?string $codItem, string $mascaras): array
    {
        $empresas = self::listaInt($empresas);
        if ($empresas === '') return [];
        // listas reaplicadas no listaInt: o texto vai direto para dentro do SQL
        $clas = self::listaInt($clas); $fornecedores = self::listaInt($fornecedores);
        $tiposNota = self::listaInt($tiposNota); $mascaras = self::listaInt($mascaras);

        return self::sql('planejamento.followUp.listar', [
            'empresas'          => $empresas,
            'filtro_situacao'   => $situacao !== null ? 'AND TPEDC_ITEM.POSICAO = ' . self::lit($situacao) : '--',
            'filtro_fornecedor' => $fornecedores !== '' ? "AND TFORNECEDORES.ID IN ($fornecedores)" : '--',
            'filtro_tipo_nota'  => $tiposNota !== ''    ? "AND TTIPOS_NF_ENT.ID IN ($tiposNota)" : '--',
            'filtro_item'       => $codItem !== null    ? 'AND TITENS.COD_ITEM = ' . self::lit($codItem) : '--',
            'filtro_mascara'    => $mascaras !== ''     ? "AND TPEDC_ITEM.MASC_ITEM_ID IN ($mascaras)" : '--',
            'filtro_clas'       => $clas !== ''         ? "AND TGRP_CLAS_ITE.ID IN ($clas)" : '--',
        ]);
    }
}
