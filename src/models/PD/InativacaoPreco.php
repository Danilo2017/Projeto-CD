<?php

namespace src\models\PD;

use core\Database;

class InativacaoPreco
{
    public static function buscarItens(int $emprId, int $codItem): array
    {
        $result = Database::switchParams('focco', [
            'empr_id'  => $emprId,
            'cod_item' => $codItem,
        ], 'pd.inativacao_preco.buscar_itens', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    // Inativa imediatamente um item nas tabelas de preço
    public static function inativarItemPreco(int $emprId, int $codItem, int $tmascItemId): void
    {
        $result = Database::switchParams('focco', [
            'tmasc_item_id' => $tmascItemId,
            'empr_id'       => $emprId,
            'cod_item'      => $codItem,
        ], 'pd.inativacao_preco.inativar', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
    }

    // Registra na tabela de monitoramento para o job validar periodicamente
    // Falha silenciosamente se a tabela ainda não existir
    public static function registrarMonitoramento(int $emprId, int $codItem, int $tmascItemId, string $descTecnica, string $mascara): void
    {
        try {
            // Verifica duplicata
            $chk = Database::switchParams('focco', [
                'empr_id'       => $emprId,
                'cod_item'      => $codItem,
                'tmasc_item_id' => $tmascItemId,
            ], 'pd.inativacao_preco.verificar_duplicata', true);
            if (!empty($chk['error'])) return; // tabela não existe — ignora
            $rows = is_array($chk['retorno']) ? $chk['retorno'] : [];
            if (((int) ($rows[0]['QTD'] ?? 0)) > 0) return;

            $descSafe    = str_replace("'", "''", $descTecnica);
            $mascaraSafe = str_replace("'", "''", $mascara);

            Database::switchParams('focco', [
                'empr_id'       => $emprId,
                'cod_item'      => $codItem,
                'tmasc_item_id' => $tmascItemId,
                'desc_tecnica'  => $descSafe,
                'mascara'       => $mascaraSafe,
            ], 'pd.inativacao_preco.inserir_monitoramento', true);
        } catch (\Exception $_) {
            // Tabela não existe ainda — o job funcionará quando ela for criada
        }
    }

    // Listagem para exibição na tela — retorna [] silenciosamente se tabela não existir
    // QTD_ATIVOS: conta preços SIT=1 em TPRECOSVEN_IT (0 = inativo confirmado, >0 = reativado)
    public static function listarCadastros(int $emprId): array
    {
        try {
            $result = Database::switchParams('focco', [
                'empr_id' => $emprId,
            ], 'pd.inativacao_preco.listar_cadastros', true);
            if (!empty($result['error'])) return [];
            return is_array($result['retorno']) ? $result['retorno'] : [];
        } catch (\Exception $_) {
            return [];
        }
    }

    // Pedidos com saldo para a máscara — exibidos ao clicar no status
    public static function buscarPedidosPendentes(int $tmascItemId): array
    {
        $result = Database::switchParams('focco', [
            'tmasc_item_id' => $tmascItemId,
        ], 'pd.inativacao_preco.buscar_pedidos_pendentes', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    // Filiais disponíveis para seleção na tela
    public static function listarFiliais(): array
    {
        try {
            $result = Database::switchParams('focco', [], 'pd.inativacao_preco.listar_filiais', true);
            if (!empty($result['error'])) return [];
            return is_array($result['retorno']) ? $result['retorno'] : [];
        } catch (\Exception $_) {
            return [];
        }
    }

    public static function excluirItem(int $id, int $emprId): void
    {
        $result = Database::switchParams('focco', [
            'id'      => $id,
            'empr_id' => $emprId,
        ], 'pd.inativacao_preco.excluir', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function commit(): void
    {
        Database::getInstance('focco')->exec('COMMIT');
    }
}
