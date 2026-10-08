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
        $rows = is_array($result['retorno']) ? $result['retorno'] : [];
        if (empty($rows)) return [];

        // Exclui itens já registrados no monitoramento para este empr_id + cod_item
        try {
            $pdo  = Database::getInstance('focco');
            $stmt = $pdo->query(
                "SELECT TMASC_ITEM_ID FROM TGAZIN_PD_INATIV_PRECO WHERE EMPR_ID = $emprId AND COD_ITEM = $codItem"
            );
            $jaReg = array_flip(array_map('strval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'TMASC_ITEM_ID')));
            $rows  = array_values(array_filter($rows, fn($r) => !isset($jaReg[strval($r['TMASC_ITEM_ID'] ?? '')])));
        } catch (\Exception $_) {
            // tabela ainda não existe — retorna tudo sem filtrar
        }

        return $rows;
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
        $pdo = Database::getInstance('focco');
        try {
            $pdo->beginTransaction();
            // NOWAIT: falha imediatamente se outra sessão Oracle tiver lock na linha
            $chk = $pdo->query(
                "SELECT ID FROM TGAZIN_PD_INATIV_PRECO WHERE ID = $id AND EMPR_ID = $emprId FOR UPDATE NOWAIT"
            );
            if (!$chk || !$chk->fetch()) {
                $pdo->rollBack();
                throw new \Exception('Registro não encontrado.', 404);
            }
            $pdo->exec("DELETE FROM TGAZIN_PD_INATIV_PRECO WHERE ID = $id AND EMPR_ID = $emprId");
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (strpos($e->getMessage(), 'ORA-00054') !== false) {
                throw new \Exception('Não foi possível remover: registro em uso por outra operação. Tente novamente em instantes.', 409);
            }
            throw new \Exception($e->getMessage(), 500);
        }
    }

    public static function excluirVarios(array $ids, int $emprId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) return 0;
        $in  = implode(',', $ids);
        $pdo = Database::getInstance('focco');
        try {
            $pdo->beginTransaction();
            $pdo->query("SELECT ID FROM TGAZIN_PD_INATIV_PRECO WHERE ID IN ($in) AND EMPR_ID = $emprId FOR UPDATE NOWAIT");
            $pdo->exec("DELETE FROM TGAZIN_PD_INATIV_PRECO WHERE ID IN ($in) AND EMPR_ID = $emprId");
            $pdo->commit();
            return count($ids);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (strpos($e->getMessage(), 'ORA-00054') !== false) {
                throw new \Exception('Um ou mais registros estão em uso. Tente novamente em instantes.', 409);
            }
            throw new \Exception($e->getMessage(), 500);
        }
    }

    public static function commit(): void
    {
        Database::getInstance('focco')->exec('COMMIT');
    }
}
