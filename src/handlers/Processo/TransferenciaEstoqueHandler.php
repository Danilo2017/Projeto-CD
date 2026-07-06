<?php

namespace src\handlers\Processo;

use src\models\Processo\TransferenciaEstoque;

class TransferenciaEstoqueHandler
{
    public static function listarAlmoxarifados(int $emprId): array
    {
        return TransferenciaEstoque::listarAlmoxarifados($emprId);
    }

    public static function buscarSaldo(array $dados): array
    {
        $emprId   = (int) ($dados['empr_id']   ?? 0);
        $almoxOrg = trim($dados['almox_orig']   ?? '');

        if (!$emprId)   throw new \Exception('Empresa não informada.', 400);
        if (!$almoxOrg) throw new \Exception('Almoxarifado origem não informado.', 400);

        $codItem = isset($dados['cod_item']) && $dados['cod_item'] !== '' ? $dados['cod_item'] : null;

        $rows = TransferenciaEstoque::buscarSaldo($emprId, $almoxOrg, $codItem);
        return ['success' => true, 'data' => $rows, 'total' => count($rows)];
    }

    public static function executar(array $dados): array
    {
        $emprId = (int) ($dados['empr_id'] ?? 0);
        $itens  = $dados['itens'] ?? [];

        if (!$emprId)      throw new \Exception('Empresa não informada.', 400);
        if (empty($itens)) throw new \Exception('Nenhum item para transferir.', 400);

        foreach ($itens as $i => $it) {
            if (!isset($it['cod_item'], $it['id_mascara'], $it['almox_orig'], $it['almox_dest'], $it['qtde'])) {
                throw new \Exception("Item #{$i} incompleto.", 400);
            }
            $qtde  = (int) $it['qtde'];
            $saldo = (int) ($it['saldo'] ?? 0);
            if ($qtde <= 0)     throw new \Exception("Item #{$i}: quantidade deve ser maior que zero.", 400);
            if ($qtde > $saldo) throw new \Exception("Item {$it['cod_item']}/Másc.{$it['id_mascara']}: qtde ({$qtde}) excede saldo ({$saldo}).", 400);
        }

        $result = TransferenciaEstoque::executar($emprId, $itens);
        return ['success' => true] + $result;
    }
}
