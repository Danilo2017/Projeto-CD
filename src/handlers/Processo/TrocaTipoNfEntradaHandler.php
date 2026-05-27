<?php

namespace src\handlers\Processo;

use core\Controller;
use src\models\Processo\TrocaTipoNfEntrada;

class TrocaTipoNfEntradaHandler
{
    public static function listarEmpresas(): array
    {
        return TrocaTipoNfEntrada::listarEmpresas();
    }

    public static function listarTipos(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id']);
        return TrocaTipoNfEntrada::listarTipos();
    }

    public static function buscarNf(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'num_nf', 'cod_for']);

        $numNf = (int) $dados['num_nf'];
        if ($numNf <= 0) {
            throw new \Exception('Número de NF inválido.', 400);
        }

        $codFor = trim((string) $dados['cod_for']);

        $result = TrocaTipoNfEntrada::buscarNf((int) $dados['empr_id'], $numNf, $codFor);

        if (!$result['nf']) {
            throw new \Exception('NF não encontrada para a empresa informada.', 404);
        }

        return $result;
    }

    public static function executar(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['nfe_id', 'empr_id', 'tipo_dest_id']);

        $trocarCapa = !empty($dados['trocar_capa']);
        $itemIds    = array_map('intval', (array) ($dados['item_ids'] ?? []));

        if (!$trocarCapa && empty($itemIds)) {
            throw new \Exception('Selecione ao menos a capa ou um item para trocar.', 400);
        }

        return TrocaTipoNfEntrada::trocarTipo(
            (int) $dados['nfe_id'],
            (int) $dados['tipo_dest_id'],
            (int) $dados['empr_id'],
            $trocarCapa,
            $itemIds
        );
    }
}
