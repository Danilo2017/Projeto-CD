<?php

namespace src\models\Processo;

use core\Database;

class TrocaAlmoxarifado
{
    public static function buscarOrdens(int $emprId, array $numeros = []): array
    {
        $filtroNumeros = '';
        if (!empty($numeros)) {
            $inClause      = implode(',', array_map('intval', $numeros));
            $filtroNumeros = "AND T.NUM_ORDEM IN ($inClause)";
        }

        $result = Database::switchParams('focco', [
            'empr_id'        => $emprId,
            'filtro_numeros' => $filtroNumeros ?: '--',
        ], 'processo.almoxarifado.buscarOrdens', true);

        return $result['retorno'] ?? [];
    }

    public static function listarAlmoxarifados(int $emprId): array
    {
        $result = Database::switchParams('focco', ['empr_id' => $emprId], 'processo.almoxarifado.listarAlmoxarifados', true);
        return $result['retorno'] ?? [];
    }

    public static function trocarAlmoxarifado(array $ordemIds, int $almoxDestId, int $emprId): array
    {
        $resultados = [];

        foreach ($ordemIds as $ordemId) {
            $id     = (int) $ordemId;
            $result = Database::switchParams('focco', [
                'almox_dest_id' => $almoxDestId,
                'id'            => $id,
                'empr_id'       => $emprId,
            ], 'processo.almoxarifado.trocar', true, true);

            if (empty($result['error'])) {
                $resultados[] = ['id' => $id, 'sucesso' => true, 'erro' => null];
            } else {
                $resultados[] = ['id' => $id, 'sucesso' => false, 'erro' => $result['error']];
            }
        }

        $temSucesso = !empty(array_filter($resultados, fn($r) => $r['sucesso']));
        if ($temSucesso) {
            Database::getInstance('focco')->exec("COMMIT");
        }

        return $resultados;
    }

    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }
}
