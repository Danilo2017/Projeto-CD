<?php

namespace src\handlers\PCP;

use core\Database;

class ApontamentoProducaoHandler
{
    private const FOCCO_API = 'https://focco.gazin.com.br';
    private const ENTITY    = '/api/Entities/Manufatura.Producao.Apontamento.GazinApontamentoProducao';
    private const TOKEN     = 'CfDJ8BAs1rwH-ZVFhgakv4G0c2FxOjreU-c-lrdTuYkzrbNAZagnDxz6FapgzGN0BDrWCGSw89NShSYbf_GjVXdzaQPAcQfYha0n4eOC6417315YoUZ1xVYk_R0mk0g8b3yLdknsGlu4Qc2O0XblxF1okPNq8-NWTccszKGUJ0OkS-acQKE5HA_tjr3i4JGR4LB2zfXJ3AFyXRlLvm_InRFjxDN_AHgJZ9lkyDoPF-cRjjbIaclGALbJRaWmY7hVCsYOyXQqrJaVN7c68J4sS3muE_KyiBeWSKrHqCcBxi-e5Av4-FmjZe3K8kS8NKiZJIg5rEXqhJ0IHEsEzrDk3ea2qs-8JZyHfpnlQhc3IoxYHvSKfT4rexjsEzxIbKBUz6CatTKqX4rUE2vRohfyyjgOhT-ycDHGYuJwzfYM9cjYsmMzRezn0nydn4kISHAgJYT8LCk6JF0fh_ZWeuTUW-oi3ZiNBj_uHT0xNZFfd3dH5IKYfnLGShRH_nLL_DUAXSmeBw9JJ2cBcrxdgIMzepU6RV0';

    // ── Lookups por ID ───────────────────────────────────────────────────────

    public static function buscarFuncionario(int $emprId, int $funcId): ?array
    {
        $pdo  = Database::getInstance('focco');
        $stmt = $pdo->prepare("
            SELECT ID, COD_FUNC, NOME
            FROM FOCCO3I.TFUNCIONARIOS
            WHERE ID = :id AND EMPR_ID = :empr AND ROWNUM = 1
        ");
        $stmt->execute([':id' => $funcId, ':empr' => $emprId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarOperacao(int $emprId, int $operacaoId): ?array
    {
        $pdo  = Database::getInstance('focco');
        $stmt = $pdo->prepare("
            SELECT ID, COD_OPERACAO, DESCRICAO
            FROM FOCCO3I.TOPERACAO
            WHERE ID = :id AND EMPR_ID = :empr AND ROWNUM = 1
        ");
        $stmt->execute([':id' => $operacaoId, ':empr' => $emprId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarMaquina(int $emprId, int $maquinaId): ?array
    {
        $pdo  = Database::getInstance('focco');
        $stmt = $pdo->prepare("
            SELECT ID, COD_MAQUINA, DESCRICAO
            FROM FOCCO3I.TMAQUINAS
            WHERE ID = :id AND EMPR_ID = :empr AND ROWNUM = 1
        ");
        $stmt->execute([':id' => $maquinaId, ':empr' => $emprId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    // ── Lookups por código (scan) ────────────────────────────────────────────

    public static function buscarFuncionarioPorCodigo(int $emprId, string $codigo): ?array
    {
        $pdo  = Database::getInstance('focco');
        $stmt = $pdo->prepare("
            SELECT ID, COD_FUNC, NOME
            FROM FOCCO3I.TFUNCIONARIOS
            WHERE EMPR_ID = :empr
              AND (UPPER(COD_FUNC) = UPPER(:cod) OR TO_CHAR(ID) = :cod2)
              AND ROWNUM = 1
        ");
        $stmt->execute([':empr' => $emprId, ':cod' => $codigo, ':cod2' => $codigo]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarOperacaoPorCodigo(int $emprId, string $codigo): ?array
    {
        $pdo  = Database::getInstance('focco');
        $stmt = $pdo->prepare("
            SELECT ID, COD_OPERACAO, DESCRICAO
            FROM FOCCO3I.TOPERACAO
            WHERE EMPR_ID = :empr
              AND (UPPER(COD_OPERACAO) = UPPER(:cod) OR TO_CHAR(ID) = :cod2)
              AND ROWNUM = 1
        ");
        $stmt->execute([':empr' => $emprId, ':cod' => $codigo, ':cod2' => $codigo]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarMaquinaPorCodigo(int $emprId, string $codigo): ?array
    {
        $pdo  = Database::getInstance('focco');
        // Tenta primeiro com empresa; se não achar, busca em qualquer empresa
        $stmt = $pdo->prepare("
            SELECT * FROM (
                SELECT ID, COD_MAQUINA, DESCRICAO, EMPR_ID,
                       CASE WHEN EMPR_ID = :empr THEN 0 ELSE 1 END AS PRIO
                FROM FOCCO3I.TMAQUINAS
                WHERE (UPPER(COD_MAQUINA) = UPPER(:cod) OR TO_CHAR(ID) = :cod2)
                ORDER BY PRIO
            ) WHERE ROWNUM = 1
        ");
        $stmt->execute([':empr' => $emprId, ':cod' => $codigo, ':cod2' => $codigo]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarOrdem(int $emprId, string $codigo, int $operacaoId = 0): ?array
    {
        // Códigos longos (≥9 dígitos) só existem em TETIQ_PLC — pula busca padrão
        if (is_numeric($codigo) && strlen($codigo) < 9) {
            $pdo  = Database::getInstance('focco');
            // Comparação direta — preserva índices, sem TO_CHAR
            $stmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT r.ID AS ROT_ID,
                           o.ID AS ORDEM_ID,
                           o.NUM_ORDEM,
                           o.TMASC_ITEM_ID AS COD_ITEM,
                           op.DESCRICAO,
                           r.OPERACAO_ID,
                           o.QTDE AS QTDE_PREVISTA,
                           NVL(o.QTDE_ENTREGUE, 0) AS QTDE_APONTADA,
                           o.SITUACAO,
                           r.APONTAMENTO AS PERMITE_APONT,
                           CASE WHEN r.OPERACAO_ID = :ope THEN 0 ELSE 1 END AS PRIO
                    FROM FOCCO3I.TORDENS_ROT r
                    JOIN FOCCO3I.TORDENS o       ON o.ID = r.ORDEM_ID
                    LEFT JOIN FOCCO3I.TOPERACAO op ON op.ID = r.OPERACAO_ID
                    WHERE o.EMPR_ID = :empr
                      AND (o.NUM_ORDEM = :cod OR r.ID = :cod2 OR o.ID = :cod3)
                      AND o.SITUACAO NOT IN ('EN','CA')
                    ORDER BY PRIO, r.APONTAMENTO DESC
                ) WHERE ROWNUM = 1
            ");
            $stmt->execute([
                ':empr' => $emprId,
                ':cod'  => $codigo,
                ':cod2' => $codigo,
                ':cod3' => $codigo,
                ':ope'  => $operacaoId > 0 ? $operacaoId : 0,
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            if ($result) return $result;
        }

        // Fallback: busca pelo código de barras da etiqueta (TETIQ_PLC.COD_BARRA_ORD)
        return static::buscarOrdemPorEtiqueta($emprId, $codigo, $operacaoId);
    }

    public static function buscarOrdemPorEtiqueta(int $emprId, string $codBarra, int $operacaoId = 0): ?array
    {
        $pdo = Database::getInstance('focco');

        // Passo 1: localiza a etiqueta — usa PK (ID) se numérico, senão COD_BARRA_ORD
        $s1 = $pdo->prepare(is_numeric($codBarra)
            ? "SELECT ID, ORDEM_ID, LIDO_ORD, NVL(CANCELADA,0) AS CANCELADA, QTDE AS ETIQ_QTDE, COD_BARRA_ORD
               FROM FOCCO3I.TETIQ_PLC WHERE ID = :cod AND ROWNUM = 1"
            : "SELECT ID, ORDEM_ID, LIDO_ORD, NVL(CANCELADA,0) AS CANCELADA, QTDE AS ETIQ_QTDE, COD_BARRA_ORD
               FROM FOCCO3I.TETIQ_PLC WHERE COD_BARRA_ORD = :cod AND ROWNUM = 1"
        );
        $s1->execute([':cod' => $codBarra]);
        $etiq = $s1->fetch(\PDO::FETCH_ASSOC);
        if (!$etiq) return null;

        // Passo 2: busca o roteiro da ordem, priorizando a operação da sessão
        $s2 = $pdo->prepare("
            SELECT * FROM (
                SELECT :etiq_id   AS ETIQ_ID,
                       :cod_barra AS COD_BARRA_ORD,
                       :lido_ord  AS LIDO_ORD,
                       :cancelada AS CANCELADA,
                       :etiq_qtde AS ETIQ_QTDE,
                       r.ID AS ROT_ID,
                       o.ID AS ORDEM_ID,
                       o.NUM_ORDEM,
                       o.TMASC_ITEM_ID AS COD_ITEM,
                       op.DESCRICAO,
                       r.OPERACAO_ID,
                       o.QTDE AS QTDE_PREVISTA,
                       NVL(o.QTDE_ENTREGUE, 0) AS QTDE_APONTADA,
                       o.SITUACAO,
                       r.APONTAMENTO AS PERMITE_APONT,
                       CASE WHEN r.OPERACAO_ID = :ope THEN 0 ELSE 1 END AS PRIO
                FROM FOCCO3I.TORDENS_ROT r
                JOIN FOCCO3I.TORDENS o       ON o.ID = r.ORDEM_ID
                LEFT JOIN FOCCO3I.TOPERACAO op ON op.ID = r.OPERACAO_ID
                WHERE r.ORDEM_ID = :ordem_id
                  AND o.SITUACAO NOT IN ('EN','CA')
                ORDER BY PRIO, r.APONTAMENTO DESC
            ) WHERE ROWNUM = 1
        ");
        $s2->execute([
            ':etiq_id'   => $etiq['ID'],
            ':cod_barra' => $etiq['COD_BARRA_ORD'],
            ':lido_ord'  => $etiq['LIDO_ORD'],
            ':cancelada' => $etiq['CANCELADA'],
            ':etiq_qtde' => $etiq['ETIQ_QTDE'],
            ':ordem_id'  => $etiq['ORDEM_ID'],
            ':ope'       => $operacaoId > 0 ? $operacaoId : 0,
        ]);
        return $s2->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function marcarEtiquetaLida(int $etiqId): void
    {
        try {
            $pdo = Database::getInstance('focco');
            $pdo->prepare("UPDATE FOCCO3I.TETIQ_PLC SET LIDO_ORD = 1 WHERE ID = :id")
                ->execute([':id' => $etiqId]);
        } catch (\Exception $e) {
            // Silencia erro para não bloquear o fluxo
        }
    }

    // ── Listagem de ordens abertas da sessão ─────────────────────────────────

    public static function listarOrdens(int $emprId, int $funcId, int $operacaoId, int $maquinaId): array
    {
        $pdo  = Database::getInstance('focco');
        $stmt = $pdo->prepare("
            SELECT r.ID AS ROT_ID,
                   o.ID AS ORDEM_ID,
                   o.NUM_ORDEM,
                   o.TMASC_ITEM_ID AS COD_ITEM,
                   op.DESCRICAO,
                   o.QTDE AS QTDE_PREVISTA,
                   NVL(o.QTDE_ENTREGUE, 0) AS QTDE_APONTADA,
                   o.SITUACAO
            FROM FOCCO3I.TORDENS_ROT r
            JOIN FOCCO3I.TORDENS o       ON o.ID = r.ORDEM_ID
            LEFT JOIN FOCCO3I.TOPERACAO op ON op.ID = r.OPERACAO_ID
            WHERE o.EMPR_ID     = :empr
              AND r.OPERACAO_ID  = :ope
              AND o.SITUACAO    NOT IN ('EN','CA')
            ORDER BY o.NUM_ORDEM DESC
            FETCH FIRST 50 ROWS ONLY
        ");
        $stmt->execute([':empr' => $emprId, ':ope' => $operacaoId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Chamada à API Focco ──────────────────────────────────────────────────

    public static function apontar(
        int    $ordemRotId,
        int    $funcId,
        int    $maquinaId,
        float  $quantidade = 1.0,
        string $tipo       = 'TP',
        string $codBarra   = ''
    ): array {
        $token = self::TOKEN;
        $body = [
            'OrdemRoteiro'       => ['ID' => $ordemRotId],
            'Quantidade'         => $quantidade,
            'DataApontamento'    => date('Y-m-d\TH:i:sP'),
            'TipoApontamento'    => ['ID' => $tipo],
            'DataHoraInicio'     => null,
            'DataHoraFim'        => null,
            'Tempo'              => null,
            'QtdeHomens'         => null,
            'Intervalo'          => null,
            'Funcionario'        => ['ID' => $funcId],
            'Final'              => false,
            'Usuario'            => 'ApontamentoAPI',
            'Refugos'            => ['$values' => []],
            'OrigemApontamento'  => 'API',
            'ApontamentoMaquina' => ['Maquina' => ['ID' => $maquinaId]],
        ];
        if ($codBarra !== '') {
            $body['CodigoBarras'] = $codBarra;
        }

        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        $url  = self::FOCCO_API . self::ENTITY;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => 'utf-8',
        ]);

        $tIni    = microtime(true);
        $resp    = curl_exec($ch);
        $tempoMs = (int) round((microtime(true) - $tIni) * 1000);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['ok' => false, 'error' => "cURL: $curlErr", 'tempo_ms' => $tempoMs];
        }

        if ($code === 401) {
            return ['ok' => false, 'error' => 'Token inválido ou expirado (401).', 'tempo_ms' => $tempoMs];
        }

        $decoded = json_decode($resp, true);
        if (!$decoded) {
            return ['ok' => false, 'error' => "Resposta inválida (HTTP $code).", 'tempo_ms' => $tempoMs, 'raw' => $resp];
        }

        // Extrai Value (ID do apontamento criado) e mensagem de erro
        $value    = self::extrairValue($decoded);
        $erroMsg  = self::extrairErro($decoded);
        $sucesso  = ($decoded['Succeeded'] ?? false) === true || ($value && !$erroMsg);

        return [
            'ok'       => $sucesso,
            'value'    => $value,
            'error'    => $sucesso ? null : ($erroMsg ?: 'Erro desconhecido.'),
            'tempo_ms' => $tempoMs,
        ];
    }

    private static function extrairValue(array $resp): ?int
    {
        if (isset($resp['Value']) && $resp['Value']) return (int) $resp['Value'];
        foreach ($resp['InnerSingleStatuses'] ?? [] as $s) {
            $v = self::extrairValue($s);
            if ($v) return $v;
        }
        return null;
    }

    private static function extrairErro(array $resp): string
    {
        $msgs = [];
        $err  = $resp['ErrorMessage'] ?? '';
        if ($err) $msgs[] = $err;
        foreach ($resp['InnerSingleStatuses'] ?? [] as $s) {
            $inner = self::extrairErro($s);
            if ($inner && !in_array($inner, $msgs, true)) $msgs[] = $inner;
        }
        return implode(' | ', array_unique(array_filter($msgs)));
    }
}
