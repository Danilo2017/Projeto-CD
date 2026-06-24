<?php

namespace src\models\CD;

use core\Database;
use src\utils\GetSqlFocco;

class ProjecaoCarga
{
    private static array $campos = [
        'DT_CARREGAMENTO', 'OBSERVACOES', 'NUM_DOCS', 'SITUACAO_CARGA',
        'FROTA', 'PLACAS', 'TIPO_VEICULO', 'MOTORISTA', 'CONTATO', 'SITUACAO',
        'SITUACAO_CAMINHAO', 'DOCA',
    ];

    public static function listar(int $emprId, string $dataInicio = '', string $dataFim = '', string $wmsSchema = 'FOCCOWMS14A'): array
    {
        $hoje = date('Y-m-d');
        if (!$dataInicio) $dataInicio = $hoje;
        if (!$dataFim)    $dataFim    = $hoje;
        if (!$wmsSchema)  $wmsSchema  = 'FOCCOWMS14A';

        $sql = "SELECT
    E.COD_EMP, E.ID AS EMPR_ID, C.CARGA AS NUM_CARGA,
    TO_CHAR(C.DT_GERACAO,'DD/MM/YYYY') AS DT_GERACAO,
    C.DESCRICAO,
    ROUND(SUM(((IP.VLR_LIQ + NVL(IP.VLR_ACRES,0)) - NVL(IP.VLR_DESC_PDV,0)) * IPC.QTDE_SLDO),  2) AS VALOR_PENDENTE,
    ROUND(SUM(((IP.VLR_LIQ + NVL(IP.VLR_ACRES,0)) - NVL(IP.VLR_DESC_PDV,0)) * IPC.QTDE_ATEND), 2) AS VALOR_FATURADO,
    NVL(C.CUBAGEM_TOT,0) AS CUBAGEM,
    TO_CHAR(A.DT_CARREGAMENTO,'DD/MM/YYYY') AS DT_CARREGAMENTO,
    A.OBSERVACOES, A.NUM_DOCS, A.SITUACAO_CARGA, A.FROTA, A.PLACAS,
    A.TIPO_VEICULO, A.MOTORISTA, A.CONTATO, A.SITUACAO_CAMINHAO,
    MAX(W.AREA_COD_AREA) AS DOCA,
    NVL(A.SITUACAO,'PENDENTE') AS SITUACAO,
    A.USUARIO AS USUARIO_AGEND,
    TO_CHAR(A.DT_ALTERACAO,'DD/MM/YYYY HH24:MI') AS DT_ALTERACAO,
    C.POS_PLC,
    MAX(W.STATUS_WMS) AS STATUS_WMS,
    (
      SELECT LISTAGG(cidade || ' - ' || uf || ' - ' || cubagem, ' | ')
             WITHIN GROUP (ORDER BY seq_min)
      FROM (
          SELECT tc.CIDADE AS cidade,
                 uf.UF    AS uf,
                 ROUND(SUM(ipc2.QTDE_SLDO * ipc2.CUBAGEM), 2) AS cubagem,
                 MIN(ipc2.SEQ) AS seq_min
          FROM TITENS_PLC ipc2
          JOIN TITENS_PDV ip2       ON ip2.ID  = ipc2.ITPDV_ID
          JOIN TPEDIDOS_VENDA pdv2  ON pdv2.ID = ip2.PDV_ID
          JOIN TESTABELECIMENTOS est ON est.ID  = pdv2.EST_ID_FAT
          JOIN TCIDADES tc           ON tc.ID   = est.CID_ID
          JOIN TUFS uf               ON uf.ID   = tc.UF_ID
          WHERE ipc2.PLC_ID = C.ID
          GROUP BY tc.CIDADE, uf.UF
      )
    ) AS ROTA
FROM TCARGAS C
INNER JOIN TITENS_PLC IPC ON IPC.PLC_ID = C.ID
INNER JOIN TITENS_PDV IP  ON IP.ID = IPC.ITPDV_ID
INNER JOIN TPEDIDOS_VENDA PDV ON PDV.ID = IP.PDV_ID AND PDV.CLI_ID <> 5210
INNER JOIN TMASC_ITEM MI  ON MI.ID  = IP.TMASC_ITEM_ID
INNER JOIN TITENS_COMERCIAL ICM ON ICM.ID = IP.ITCM_ID
INNER JOIN TITENS_EMPR IE  ON IE.ID  = ICM.ITEMPR_ID
INNER JOIN TEMPRESAS E     ON E.ID   = IE.EMPR_ID
INNER JOIN TITENS T        ON T.ID   = IE.ITEM_ID
LEFT JOIN FOCCO3I.TGAZIN_CARGA_AGEND A ON A.EMPR_ID = E.ID AND A.NUM_CARGA = C.CARGA
LEFT JOIN (
    SELECT DISTINCT CARGA, EMPR_ID
    FROM FOCCO3I.F3I_LOG_TCARGAS
    WHERE TRUNC(DTA_OPERACAO_LOG) BETWEEN TRUNC(TO_DATE(':data_inicio','YYYY-MM-DD'))
                                      AND TRUNC(TO_DATE(':data_fim','YYYY-MM-DD'))
      AND POS_PLC IN ('FT','FP')
      AND IND_TIPO_LOG = 2
) LG ON LG.CARGA = C.CARGA AND LG.EMPR_ID = C.EMPR_ID
LEFT JOIN (
    SELECT NUM_CARGA,
           AREA_COD_AREA,
           CASE SITUACAO_WMS
               WHEN '1' THEN 'Importada WMS'
               WHEN '3' THEN 'Em Separação'
               WHEN '6' THEN 'Encerrada'
               WHEN '9' THEN 'Excluída'
               ELSE NULL
           END AS STATUS_WMS
    FROM (
        SELECT NUM_CARGA, SITUACAO_WMS, AREA_COD_AREA,
               ROW_NUMBER() OVER (PARTITION BY NUM_CARGA ORDER BY DTHR DESC NULLS LAST) AS RN
        FROM :wms_schema.WMS_CARGAS
    ) WHERE RN = 1
) W ON W.NUM_CARGA = TO_CHAR(C.CARGA)
WHERE (
    (C.POS_PLC = 'PE' AND TRUNC(C.DT_GERACAO) <= TRUNC(TO_DATE(':data_fim','YYYY-MM-DD')))
    OR
    (C.POS_PLC IN ('FT','FP') AND (
        LG.CARGA IS NOT NULL
        OR TRUNC(C.DT_GERACAO) BETWEEN TRUNC(TO_DATE(':data_inicio','YYYY-MM-DD'))
                                    AND TRUNC(TO_DATE(':data_fim','YYYY-MM-DD'))
    ))
)
AND E.ID = :empr_id
GROUP BY
    E.COD_EMP, E.ID, C.ID, C.CARGA, C.DT_GERACAO, C.DESCRICAO, C.CUBAGEM_TOT,
    A.DT_CARREGAMENTO, A.OBSERVACOES, A.NUM_DOCS, A.SITUACAO_CARGA, A.FROTA, A.PLACAS,
    A.TIPO_VEICULO, A.MOTORISTA, A.CONTATO, A.SITUACAO_CAMINHAO,
    A.SITUACAO, A.USUARIO, A.DT_ALTERACAO, C.POS_PLC
ORDER BY C.POS_PLC ASC, C.CARGA DESC";

        $result = Database::switchParams('focco', [
            'empr_id'     => $emprId,
            'data_inicio' => $dataInicio,
            'data_fim'    => $dataFim,
            'wms_schema'  => $wmsSchema,
        ], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function salvar(int $emprId, int $numCarga, array $dados, string $usuario): array
    {
        $pdo = Database::getInstance('focco');

        $result = Database::switchParams('focco',
            ['empr_id' => $emprId, 'num_carga' => $numCarga],
            'cd.carga.buscarAtual', true);
        $atual = ($result['retorno'][0] ?? null) ?: null;

        $logs = [];

        if ($atual === null) {
            $setCols = ['EMPR_ID', 'NUM_CARGA', 'USUARIO', 'DT_CADASTRO'];
            $setVals = [':empr_id', ':num_carga', ':usuario', 'SYSDATE'];
            $bind    = [':empr_id' => $emprId, ':num_carga' => $numCarga, ':usuario' => $usuario];

            foreach (self::$campos as $campo) {
                $key   = strtolower($campo);
                $valor = $dados[$key] ?? null;
                if ($valor !== null && $valor !== '') {
                    $setCols[] = $campo;
                    $setVals[] = $campo === 'DT_CARREGAMENTO'
                        ? "TO_DATE(:$key,'YYYY-MM-DD')"
                        : ":$key";
                    $bind[":$key"] = $valor;
                    $logs[] = ['campo' => $campo, 'antes' => null, 'depois' => $valor];
                }
            }

            $pdo->prepare(
                'INSERT INTO FOCCO3I.TGAZIN_CARGA_AGEND (' . implode(',', $setCols) . ')
                 VALUES (' . implode(',', $setVals) . ')'
            )->execute($bind);
        } else {
            $sets = ['DT_ALTERACAO = SYSDATE', 'USUARIO = :usuario'];
            $bind = [':empr_id' => $emprId, ':num_carga' => $numCarga, ':usuario' => $usuario];

            foreach (self::$campos as $campo) {
                $key      = strtolower($campo);
                $novo     = ($dados[$key] ?? '') ?: null;
                $anterior = $atual[$campo] ?? null;

                if ($campo === 'DT_CARREGAMENTO') {
                    $sets[] = "DT_CARREGAMENTO = TO_DATE(:$key,'YYYY-MM-DD')";
                } else {
                    $sets[] = "$campo = :$key";
                }
                $bind[":$key"] = $novo;

                $antStr = $anterior instanceof \PDO ? stream_get_contents($anterior) : (string)($anterior ?? '');
                $novStr = (string)($novo ?? '');
                if ($antStr !== $novStr) {
                    $logs[] = ['campo' => $campo, 'antes' => $antStr ?: null, 'depois' => $novo];
                }
            }

            $pdo->prepare(
                'UPDATE FOCCO3I.TGAZIN_CARGA_AGEND SET ' . implode(', ', $sets) .
                ' WHERE EMPR_ID = :empr_id AND NUM_CARGA = :num_carga'
            )->execute($bind);
        }

        foreach ($logs as $log) {
            $pdo->prepare(
                GetSqlFocco::getSql('cd.carga.log.inserir')
            )->execute([
                ':e'       => $emprId,
                ':c'       => $numCarga,
                ':campo'   => $log['campo'],
                ':antes'   => $log['antes'],
                ':depois'  => $log['depois'],
                ':usuario' => $usuario,
            ]);
        }

        $pdo->exec('COMMIT');
        return ['alteracoes' => count($logs)];
    }

    public static function listarLog(int $emprId, int $numCarga): array
    {
        $result = Database::switchParams('focco', [
            'empr_id'   => $emprId,
            'num_carga' => $numCarga,
        ], 'cd.carga.log.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }

    // ── Transições automáticas de situação do caminhão ───────────────────────

    public static function marcarAguardandoDocumentacao(int $emprId, array $numCargas): void
    {
        if (!$numCargas) return;
        $params = [
            'empr_id' => $emprId,
            'in_list' => implode(',', array_map('intval', $numCargas)),
        ];
        Database::switchParams('focco', $params, 'cd.carga.marcar_aguardando_upd', true);
        Database::switchParams('focco', $params, 'cd.carga.marcar_aguardando_ins', true);
    }

    public static function marcarFinalizado(int $emprId, int $numCarga): void
    {
        $params = ['empr_id' => $emprId, 'num_carga' => $numCarga];

        $result = Database::switchParams('focco', $params, 'cd.carga.marcar_finalizado_sel', true);
        $row    = $result['retorno'][0] ?? false;

        if ($row === false) {
            Database::switchParams('focco', $params, 'cd.carga.marcar_finalizado_ins', true);
        } elseif (($row['SITUACAO_CAMINHAO'] ?? '') !== 'DISPONÍVEL') {
            Database::switchParams('focco', $params, 'cd.carga.marcar_finalizado_upd', true);
        }
    }

    // ── Anexos (OCI8 / BLOB) ──────────────────────────────────────────────────

    private static function oci8Conn()
    {
        $tns = sprintf(
            '(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=%s)))(CONNECT_DATA=(SERVICE_NAME=%s)))',
            \src\Config::FOCCO_HOST, \src\Config::FOCCO_PORT, \src\Config::FOCCO_DATABASE
        );
        $conn = oci_connect(\src\Config::FOCCO_USER, \src\Config::FOCCO_PASS, $tns, 'AL32UTF8');
        if (!$conn) {
            $e = oci_error();
            throw new \Exception('OCI8 connection error: ' . $e['message']);
        }
        return $conn;
    }

    public static function salvarAnexo(int $emprId, int $numCarga, string $nomeOrig, string $mimeType, int $tamanho, string $conteudo, string $usuario): int
    {
        $conn = self::oci8Conn();
        $sql  = GetSqlFocco::getSql('cd.carga.anexo.inserir');

        $stmt  = oci_parse($conn, $sql);
        $blob  = oci_new_descriptor($conn, OCI_D_LOB);
        $retId = 0;

        oci_bind_by_name($stmt, ':empr_id',   $emprId);
        oci_bind_by_name($stmt, ':num_carga', $numCarga);
        oci_bind_by_name($stmt, ':nome_orig', $nomeOrig, 500);
        oci_bind_by_name($stmt, ':mime_type', $mimeType, 200);
        oci_bind_by_name($stmt, ':tamanho',   $tamanho);
        oci_bind_by_name($stmt, ':usuario',   $usuario, 100);
        oci_bind_by_name($stmt, ':ret_id',    $retId,   32, SQLT_INT);
        oci_bind_by_name($stmt, ':blob',      $blob,    -1, OCI_B_BLOB);

        oci_execute($stmt, OCI_NO_AUTO_COMMIT);
        $blob->save($conteudo);
        oci_commit($conn);

        oci_free_statement($stmt);
        $blob->free();
        oci_close($conn);

        return (int) $retId;
    }

    public static function listarAnexos(int $emprId, int $numCarga): array
    {
        $conn = self::oci8Conn();
        $stmt = oci_parse($conn, GetSqlFocco::getSql('cd.carga.anexo.listar'));
        oci_bind_by_name($stmt, ':empr_id',   $emprId);
        oci_bind_by_name($stmt, ':num_carga', $numCarga);
        oci_execute($stmt);

        $rows = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = $row;
        }

        oci_free_statement($stmt);
        oci_close($conn);
        return $rows;
    }

    public static function downloadAnexo(int $emprId, int $id): ?array
    {
        $conn = self::oci8Conn();
        $stmt = oci_parse($conn, GetSqlFocco::getSql('cd.carga.anexo.download'));
        oci_bind_by_name($stmt, ':id',      $id);
        oci_bind_by_name($stmt, ':empr_id', $emprId);
        oci_execute($stmt);

        $row = oci_fetch_assoc($stmt);
        if (!$row) {
            oci_free_statement($stmt);
            oci_close($conn);
            return null;
        }

        $conteudo = $row['CONTEUDO'] ? $row['CONTEUDO']->load() : '';
        oci_free_statement($stmt);
        oci_close($conn);

        return ['NOME_ORIG' => $row['NOME_ORIG'], 'MIME_TYPE' => $row['MIME_TYPE'], 'CONTEUDO' => $conteudo];
    }

    public static function listarRota(int $emprId, int $numCarga): array
    {
        $result = Database::switchParams('focco', [
            'empr_id'   => $emprId,
            'num_carga' => $numCarga,
        ], 'cd.carga.rota.listar', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function salvarSequenciaRota(int $plcId, array $sequencias): void
    {
        $pdo  = Database::getInstance('focco');
        $sql  = GetSqlFocco::getSql('cd.carga.rota.salvarSeq');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare($sql);
            foreach ($sequencias as $idx => $item) {
                $pdvId   = (int) $item['pdv_id'];
                $novaSeq = isset($item['seq']) ? (int) $item['seq'] : $idx + 1;
                $stmt->execute([':nova_seq' => $novaSeq, ':plc_id' => $plcId, ':pdv_id' => $pdvId]);
            }
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function excluirAnexo(int $emprId, int $id): void
    {
        $conn = self::oci8Conn();
        $stmt = oci_parse($conn, GetSqlFocco::getSql('cd.carga.anexo.excluir'));
        oci_bind_by_name($stmt, ':id',      $id);
        oci_bind_by_name($stmt, ':empr_id', $emprId);
        oci_execute($stmt);
        oci_commit($conn);
        oci_free_statement($stmt);
        oci_close($conn);
    }

    public static function listarItens(int $emprId, int $numCarga): array
    {
        $sql = "SELECT TITENS.COD_ITEM                    COD_ITEM,
       TITENS.DESC_TECNICA                DESC_TECNICA,
       TMASC_ITEM.ID                      ID,
       TMASC_ITEM.MASCARA                 MASCARA,
       SUM(TITENS_PLC.QTDE)               QTDE_CARGA,
       MAX(MAN_EST_RETORNA_SALDO_ITEM(TITENS_EMPR.EMPR_ID, TITENS.ID, 998, SYSDATE, TMASC_ITEM.ID, NULL, NULL, NULL, 1, 0)) ESTOQUE_998,
       MAX(MAN_EST_RETORNA_SALDO_ITEM(TITENS_EMPR.EMPR_ID, TITENS.ID,  90, SYSDATE, TMASC_ITEM.ID, NULL, NULL, NULL, 1, 0)) ESTOQUE_90,
       MAX(MAN_EST_RETORNA_SALDO_ITEM(TITENS_EMPR.EMPR_ID, TITENS.ID, 997, SYSDATE, TMASC_ITEM.ID, NULL, NULL, NULL, 1, 0)) ESTOQUE_997,
       NULL                               G_TOTAL_GERAL
  FROM TITENS_ESTOQUE        TITENS_ESTOQUE,
       TITENS_PDV             TITENS_PDV,
       TITENS_COMERCIAL       TITENS_COMERCIAL,
       TITENS_EMPR            TITENS_EMPR,
       TITENS                 TITENS,
       TITENS_PLC             TITENS_PLC,
       TMASC_ITEM             TMASC_ITEM,
       TITENS_PLANEJAMENTO    TITENS_PLANEJAMENTO,
       TALMOXARIFADOS         TALMOXARIFADOS,
       TCARGAS                TCARGAS
 WHERE TITENS_PDV.ID          = TITENS_PLC.ITPDV_ID
   AND TITENS_COMERCIAL.ID    = TITENS_PDV.ITCM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS_EMPR.ID         = TITENS_COMERCIAL.ITEMPR_ID
   AND TITENS_EMPR.ID         = TITENS_ESTOQUE.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TITENS_PDV.TMASC_ITEM_ID
   AND TALMOXARIFADOS.ID      = TITENS_ESTOQUE.ALMOX_ID
   AND TCARGAS.ID             = TITENS_PLC.PLC_ID
   AND TCARGAS.EMPR_ID        = :empr_id
   AND TCARGAS.CARGA          = :num_carga
 GROUP BY TITENS.COD_ITEM,
          TITENS.DESC_TECNICA,
          TMASC_ITEM.ID,
          TMASC_ITEM.MASCARA";

        $result = Database::switchParams('focco', [
            'empr_id'   => $emprId,
            'num_carga' => $numCarga,
        ], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function listarItensExpedicao(int $numCarga, string $wmsSchema = 'FOCCOWMS14A'): array
    {
        if (!$wmsSchema) $wmsSchema = 'FOCCOWMS14A';

        $sql = "SELECT ws.NUM_CARGA,
       CASE ws.SITUACAO_WMS
           WHEN '1' THEN 'Importada WMS'
           WHEN '3' THEN 'Em Separação'
           WHEN '6' THEN 'Encerrada'
           WHEN '9' THEN 'Excluída'
           ELSE 'Desconhecida'
       END AS DESCRICAO_STATUS,
       pe.NUM_PEDIDO,
       i.CODIGO,
       i.DESCRICAO,
       lpe.QTDE,
       lpe.QTDE_EXECUTADA,
       lpe.QTDE_EXECUTADA_ORIGINAL AS QTDE_DISTRIBUIDA,
       NVL(lpe.QTDE / NULLIF(lpe.QTDE_EXECUTADA_ORIGINAL,0),0)*100 AS PERCENTUAL
  FROM :wms_schema.WMS_CARGAS ws,
       :wms_schema.PEDIDOS_ERP pe,
       :wms_schema.LINHAS_PEDIDOS_ERP lpe,
       :wms_schema.ITEM i
 WHERE i.CODIGO      = lpe.ITEM
   AND lpe.PEDIDO_ID = pe.ID
   AND pe.CARGA_ID   = ws.ID
   AND ws.NUM_CARGA  = :num_carga";

        $result = Database::switchParams('focco', [
            'wms_schema' => $wmsSchema,
            'num_carga'  => $numCarga,
        ], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
