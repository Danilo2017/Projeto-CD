<?php

namespace src\models\CD;

use core\Database;

class AvisosRecebimento
{
    public static function listarAvisosHoje()
    {
        $result = Database::switchParams('focco', [], 'cd.avisos.listarHoje', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    public static function getTotaisMes()
    {
        $result = Database::switchParams('focco', [], 'cd.avisos.totaisMes', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        $row = $result['retorno'][0] ?? [];
        return [
            'total' => (int)($row['TOTAL'] ?? 0),
            'pendentes' => (int)($row['PENDENTES'] ?? 0),
            'iniciados' => (int)($row['INICIADOS'] ?? 0),
            'finalizados' => (int)($row['FINALIZADOS'] ?? 0)
        ];
    }
}

