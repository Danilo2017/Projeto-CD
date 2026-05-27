<?php

namespace src\models\Processo;

use core\Database;

class TrocaTipoNfEntrada
{
    public static function buscarNf(int $emprId, int $numNf, string $codFor = ''): array
    {
        $filtroCodFor = '';
        if ($codFor !== '') {
            $safe         = str_replace("'", "''", $codFor);
            $filtroCodFor = "AND t.COD_FOR = '$safe'";
        }

        $resCapa = Database::switchParams('focco', [
            'empr_id'        => $emprId,
            'num_nf'         => $numNf,
            'filtro_cod_for' => $filtroCodFor ?: '--',
        ], 'processo.tipoNfEntrada.buscarCapa', true, false);

        if (!empty($resCapa['error'])) {
            throw new \Exception($resCapa['error']);
        }

        $capa = $resCapa['retorno'][0] ?? null;

        if (!$capa) {
            return ['nf' => null, 'itens' => []];
        }

        $nfeId    = (int) $capa['ID'];
        $resItens = Database::switchParams('focco', ['nfe_id' => $nfeId], 'processo.tipoNfEntrada.buscarItens', true);

        return ['nf' => $capa, 'itens' => $resItens['retorno'] ?? []];
    }

    public static function listarTipos(): array
    {
        $result = Database::switchParams('focco', [], 'processo.tipoNfEntrada.listarTipos', true);
        return $result['retorno'] ?? [];
    }

    public static function trocarTipo(
        int   $nfeId,
        int   $tipoId,
        int   $emprId,
        bool  $trocarCapa,
        array $itemIds
    ): array {
        $capaResult  = null;
        $itensResult = [];
        $sucessos    = 0;
        $erros       = 0;

        if ($trocarCapa) {
            $result = Database::switchParams('focco', [
                'tipo_id' => $tipoId,
                'nfe_id'  => $nfeId,
                'empr_id' => $emprId,
            ], 'processo.tipoNfEntrada.trocarCapa', true, true);

            if (empty($result['error'])) {
                $capaResult = ['sucesso' => true, 'erro' => null];
                $sucessos++;
            } else {
                $capaResult = ['sucesso' => false, 'erro' => $result['error']];
                $erros++;
            }
        }

        foreach ($itemIds as $itemId) {
            $id     = (int) $itemId;
            $result = Database::switchParams('focco', [
                'tipo_id' => $tipoId,
                'id'      => $id,
                'nfe_id'  => $nfeId,
            ], 'processo.tipoNfEntrada.trocarItem', true, true);

            if (empty($result['error'])) {
                $itensResult[] = ['id' => $id, 'sucesso' => true, 'erro' => null];
                $sucessos++;
            } else {
                $itensResult[] = ['id' => $id, 'sucesso' => false, 'erro' => $result['error']];
                $erros++;
            }
        }

        if ($sucessos > 0) {
            Database::getInstance('focco')->exec("COMMIT");
        }

        return [
            'capa_resultado'  => $capaResult,
            'itens_resultado' => $itensResult,
            'sucessos'        => $sucessos,
            'erros'           => $erros,
        ];
    }

    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }
}
