<?php

namespace src\handlers\PD;

use src\models\PD\InativacaoPreco;

class InativacaoPrecoHandler
{
    public static function buscarItens(array $dados): array
    {
        $emprId  = (int) ($dados['empr_id']  ?? 0);
        $codItem = (int) ($dados['cod_item'] ?? 0);

        if ($emprId <= 0) throw new \Exception('Empresa inválida.', 400);
        if ($codItem <= 0) throw new \Exception('Informe o código do item.', 400);

        $rows = InativacaoPreco::buscarItens($emprId, $codItem);
        return ['success' => true, 'rows' => $rows, 'total' => count($rows)];
    }

    public static function listarCadastros(array $dados): array
    {
        $emprId = (int) ($dados['empr_id'] ?? 0);
        if ($emprId <= 0) throw new \Exception('Empresa inválida.', 400);

        $rows = InativacaoPreco::listarCadastros($emprId);
        return ['success' => true, 'rows' => $rows, 'total' => count($rows)];
    }

    public static function cadastrarItens(array $dados): array
    {
        $emprId = (int) ($dados['empr_id'] ?? 0);
        $itens  = $dados['itens'] ?? [];

        if ($emprId <= 0) throw new \Exception('Empresa inválida.', 400);
        if (empty($itens) || !is_array($itens)) throw new \Exception('Nenhum item selecionado.', 400);

        $inseridos  = 0;
        $duplicados = 0;

        foreach ($itens as $item) {
            $codItem     = (int) ($item['cod_item']      ?? 0);
            $tmascItemId = (int) ($item['tmasc_item_id'] ?? 0);
            $descTecnica = trim((string) ($item['desc_tecnica'] ?? ''));
            $mascara     = trim((string) ($item['mascara']      ?? ''));

            if ($codItem <= 0 || $tmascItemId <= 0) continue;

            if (InativacaoPreco::verificarExistencia($emprId, $codItem, $tmascItemId)) {
                $duplicados++;
                continue;
            }

            InativacaoPreco::cadastrarItem($emprId, $codItem, $tmascItemId, $descTecnica, $mascara);
            $inseridos++;
        }

        $msg = "Cadastrado(s): {$inseridos} item(ns).";
        if ($duplicados > 0) $msg .= " Já existia(m): {$duplicados}.";

        return ['success' => true, 'inseridos' => $inseridos, 'duplicados' => $duplicados, 'message' => $msg];
    }

    public static function excluirItem(array $dados): array
    {
        $emprId = (int) ($dados['empr_id'] ?? 0);
        $id     = (int) ($dados['id']      ?? 0);

        if ($emprId <= 0) throw new \Exception('Empresa inválida.', 400);
        if ($id <= 0)     throw new \Exception('ID inválido.', 400);

        InativacaoPreco::excluirItem($id, $emprId);
        return ['success' => true, 'message' => 'Item removido da fila de inativação.'];
    }

    public static function processarInativacao(array $dados): array
    {
        $emprId = (int) ($dados['empr_id'] ?? 0);
        if ($emprId <= 0) throw new \Exception('Empresa inválida.', 400);

        $qtd = InativacaoPreco::processarInativacao($emprId);
        return ['success' => true, 'message' => "Inativação executada. Registros de preço atualizados."];
    }
}
