<?php

namespace src\models\Faturamento;

use core\Database;

/**
 * Model de Meta Empresa
 * Gerencia metas de faturamento e estoque por empresa/mês
 */
class MetaEmpresa
{
    public static function listar(?string $mesAno = null): array
    {
        $params = [
            'filtro_mes_ano' => $mesAno
                ? "AND TRUNC(ME.MES_ANO, 'MM') = TO_DATE('" . preg_replace('/[^0-9-]/', '', $mesAno) . "-01', 'YYYY-MM-DD')"
                : '--',
        ];
        $result = Database::switchParams('focco', $params, 'faturamento.meta.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function buscar(int $emprId, string $mesAno): ?array
    {
        $params = [
            'empr_id' => intval($emprId),
            'mes_ano' => "'" . preg_replace('/[^0-9-]/', '', $mesAno) . "-01'",
        ];
        $result = Database::switchParams('focco', $params, 'faturamento.meta.buscar', true);
        return $result['retorno'][0] ?? null;
    }

    public static function inserir(int $emprId, string $mesAno, float $meta, float $metaEstoque): bool
    {
        $params = [
            'empr_id'      => intval($emprId),
            'mes_ano'      => "'" . preg_replace('/[^0-9-]/', '', $mesAno) . "-01'",
            'meta'         => $meta,
            'meta_estoque' => $metaEstoque,
        ];
        $result = Database::switchParams('focco', $params, 'faturamento.meta.inserir', true, true);
        return empty($result['error']);
    }

    public static function atualizar(int $emprId, string $mesAno, float $meta, float $metaEstoque): bool
    {
        $params = [
            'empr_id'      => intval($emprId),
            'mes_ano'      => "'" . preg_replace('/[^0-9-]/', '', $mesAno) . "-01'",
            'meta'         => $meta,
            'meta_estoque' => $metaEstoque,
        ];
        $result = Database::switchParams('focco', $params, 'faturamento.meta.atualizar', true, true);
        return empty($result['error']);
    }

    public static function excluir(int $emprId, string $mesAno): bool
    {
        $params = [
            'empr_id' => intval($emprId),
            'mes_ano' => "'" . preg_replace('/[^0-9-]/', '', $mesAno) . "-01'",
        ];
        $result = Database::switchParams('focco', $params, 'faturamento.meta.excluir', true, true);
        return empty($result['error']);
    }

    public static function listarEmpresas(): array
    {
        // Lista fixa de empresas conhecidas
        return [
            ['EMPR_ID' => '1',  'NOME_EMPRESA' => '1 - DOURADINA PR'],
            ['EMPR_ID' => '2',  'NOME_EMPRESA' => '2 - VILHENA RO'],
            ['EMPR_ID' => '3',  'NOME_EMPRESA' => '3 - CANDELÁRIA RS'],
            ['EMPR_ID' => '4',  'NOME_EMPRESA' => '4 - F. SANTANA BA'],
            ['EMPR_ID' => '5',  'NOME_EMPRESA' => '5 - JACIARA MT'],
            ['EMPR_ID' => '6',  'NOME_EMPRESA' => '6 - COMPLEMENTO'],
            ['EMPR_ID' => '7',  'NOME_EMPRESA' => '7 - ITATINGA CE'],
            ['EMPR_ID' => '8',  'NOME_EMPRESA' => '8 - FILIAL 8'],
            ['EMPR_ID' => '9',  'NOME_EMPRESA' => '9 - S. GUIOMARD AC'],
            ['EMPR_ID' => '10', 'NOME_EMPRESA' => '10 - MOLAS DOURAD.'],
            ['EMPR_ID' => '11', 'NOME_EMPRESA' => '11 - MOLAS CAND.'],
            ['EMPR_ID' => '13', 'NOME_EMPRESA' => '13 - ELOI MENDES MG'],
            ['EMPR_ID' => '14', 'NOME_EMPRESA' => '14 - ARAGUATINS TO'],
            ['EMPR_ID' => '15', 'NOME_EMPRESA' => '15 - PATOS MINAS MG'],
            ['EMPR_ID' => '16', 'NOME_EMPRESA' => '16 - PATOS DE MINAS MG'],
        ];
    }
}
