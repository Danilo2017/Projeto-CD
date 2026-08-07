<?php

namespace src\models\Processo;

use core\Database;

class TransferenciaEstoque
{
    public static function listarAlmoxarifados(int $emprId): array
    {
        $result = Database::switchParams('focco', ['empr_id' => $emprId], 'processo.transferencia_estoque.listar_almoxarifados', true);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function buscarSaldo(int $emprId, string $almoxOrig, ?string $codItem = null): array
    {
        $almoxLista     = "'" . trim($almoxOrig) . "'";
        $filtroCodItem  = $codItem ? "AND TITENS.COD_ITEM = " . intval($codItem) : '--';

        $result = Database::switchParams('focco', [
            'empr_id'         => $emprId,
            'almox_lista'     => $almoxLista,
            'filtro_cod_item' => $filtroCodItem,
        ], 'processo.transferencia_estoque.buscar_saldo', true);

        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function executar(int $emprId, array $itens): array
    {
        $pdo  = Database::getInstance('focco');
        $erros = [];
        $ok    = 0;

        $plsql = "BEGIN\n";
        foreach ($itens as $it) {
            $codItem  = (int) $it['cod_item'];
            $idMasc   = (int) $it['id_mascara'];
            $almoxOrg = (int) $it['almox_orig'];
            $almoxDst = (int) $it['almox_dest'];
            $qtde     = (int) $it['qtde'];
            $plsql   .= "  PTRANSFERE_ESTQ_ITENS({$emprId},{$codItem},{$idMasc},{$almoxOrg},{$almoxDst},{$qtde});\n";
        }
        $plsql .= "  COMMIT;\nEND;";

        try {
            $pdo->exec($plsql);
            $ok = count($itens);
        } catch (\Exception $e) {
            $erros[] = $e->getMessage();
        }

        return ['ok' => $ok, 'erros' => $erros];
    }
}
