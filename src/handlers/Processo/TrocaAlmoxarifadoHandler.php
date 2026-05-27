<?php

namespace src\handlers\Processo;

use core\Controller;
use src\models\Processo\TrocaAlmoxarifado;

class TrocaAlmoxarifadoHandler
{
    public static function listarEmpresas(): array
    {
        return TrocaAlmoxarifado::listarEmpresas();
    }

    public static function listarAlmoxarifados(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id']);
        return TrocaAlmoxarifado::listarAlmoxarifados((int) $dados['empr_id']);
    }

    public static function buscarOrdens(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id']);

        $numeros = [];
        if (!empty($dados['numeros'])) {
            $numeros = array_values(array_filter(
                array_map('intval', preg_split('/[\s,;]+/', (string) $dados['numeros']))
            ));
        }

        $ordens = TrocaAlmoxarifado::buscarOrdens((int) $dados['empr_id'], $numeros);

        return ['ordens' => $ordens, 'total' => count($ordens)];
    }

    public static function executar(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['ordem_ids', 'almox_dest_id', 'empr_id']);

        $ordemIds   = array_map('intval', (array) ($dados['ordem_ids'] ?? []));
        $almoxDest  = (int) $dados['almox_dest_id'];
        $emprId     = (int) $dados['empr_id'];

        if (empty($ordemIds)) {
            throw new \Exception('Nenhuma ordem selecionada', 400);
        }

        $resultados = TrocaAlmoxarifado::trocarAlmoxarifado($ordemIds, $almoxDest, $emprId);
        $sucessos   = count(array_filter($resultados, fn($r) => $r['sucesso']));

        return [
            'resultados' => $resultados,
            'total'      => count($resultados),
            'sucessos'   => $sucessos,
            'erros'      => count($resultados) - $sucessos,
        ];
    }
}
