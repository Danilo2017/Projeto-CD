<?php

namespace src\models\Manutencao;

use core\Database;

class OrdemChecklist
{
    public static function listarPorMaquina(int $maqId): array
    {
        $res = Database::switchParams('focco', [], null, true, false, null,
            "SELECT ID, DESCRICAO, ATIVO FROM TGAZIN_CHKLIST_MANUT
              WHERE MAQUINA_ID = $maqId AND ATIVO = 'S'
              ORDER BY ID");
        return $res['retorno'] ?: [];
    }

    public static function listarTodosPorMaquina(int $maqId): array
    {
        $res = Database::switchParams('focco', [], null, true, false, null,
            "SELECT ID, DESCRICAO, ATIVO FROM TGAZIN_CHKLIST_MANUT
              WHERE MAQUINA_ID = $maqId
              ORDER BY ID");
        return $res['retorno'] ?: [];
    }

    public static function salvar(int $maqId, string $descricao): void
    {
        $desc = str_replace("'", "''", trim($descricao));
        $res = Database::switchParams('focco', [], null, true, false, null,
            "INSERT INTO TGAZIN_CHKLIST_MANUT (MAQUINA_ID, DESCRICAO) VALUES ($maqId, '$desc')");
        if (!empty($res['error'])) throw new \Exception('Erro ao salvar item: ' . $res['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function excluir(int $id): void
    {
        $res = Database::switchParams('focco', [], null, true, false, null,
            "DELETE FROM TGAZIN_CHKLIST_MANUT WHERE ID = $id");
        if (!empty($res['error'])) throw new \Exception('Erro ao excluir item: ' . $res['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function registrarRespostas(int $ordemId, array $itens): void
    {
        foreach ($itens as $chkId => $conferido) {
            $chkId    = (int) $chkId;
            $conf     = $conferido ? 'S' : 'N';
            Database::switchParams('focco', [], null, true, false, null,
                "INSERT INTO TGAZIN_CHKLIST_ORDEM (ORDEM_ID, CHKLIST_ID, CONFERIDO)
                 VALUES ($ordemId, $chkId, '$conf')");
        }
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function listarMaquinas(int $emprId): array
    {
        $res = Database::switchParams('focco', [], null, true, false, null,
            "SELECT ID, COD_MAQUINA, DESCRICAO,
                    COD_MAQUINA||' - '||DESCRICAO NOME
               FROM FOCCO3I.TMAQUINAS
              WHERE EMPR_ID = $emprId
              ORDER BY COD_MAQUINA");
        return $res['retorno'] ?: [];
    }
}
