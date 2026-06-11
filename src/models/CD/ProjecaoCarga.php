<?php

namespace src\models\Cd;

use core\Database;

class ProjecaoCarga
{
    private static array $campos = [
        'DT_CARREGAMENTO', 'OBSERVACOES', 'NUM_DOCS', 'SITUACAO_CARGA',
        'FROTA', 'PLACAS', 'TIPO_VEICULO', 'MOTORISTA', 'CONTATO', 'SITUACAO',
        'SITUACAO_CAMINHAO',
    ];

    public static function listar(int $emprId, string $dataFiltro = '', string $wmsSchema = 'FOCCOWMS14A'): array
    {
        if (!$dataFiltro) $dataFiltro = date('Y-m-d');
        if (!$wmsSchema)  $wmsSchema  = 'FOCCOWMS14A';
        $result = Database::switchParams('focco', [
            'empr_id'     => $emprId,
            'data_filtro' => $dataFiltro,
            'wms_schema'  => $wmsSchema,
        ], 'cd.carga.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function salvar(int $emprId, int $numCarga, array $dados, string $usuario): array
    {
        $pdo = Database::getInstance('focco');

        $stmt = $pdo->prepare(
            'SELECT DT_CARREGAMENTO, OBSERVACOES, NUM_DOCS, SITUACAO_CARGA,
                    FROTA, PLACAS, TIPO_VEICULO, MOTORISTA, CONTATO, SITUACAO
             FROM FOCCO3I.TGAZIN_CARGA_AGEND
             WHERE EMPR_ID = :e AND NUM_CARGA = :c'
        );
        $stmt->execute([':e' => $emprId, ':c' => $numCarga]);
        $atual = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

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
                'INSERT INTO FOCCO3I.TGAZIN_CARGA_AGEND_LOG
                 (EMPR_ID, NUM_CARGA, CAMPO, VALOR_ANTES, VALOR_DEPOIS, USUARIO, DT_ALTERACAO)
                 VALUES (:e, :c, :campo, :antes, :depois, :usuario, SYSDATE)'
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
        $pdo    = Database::getInstance('focco');
        $status = 'AGUARDANDO DOCUMENTAÇÃO';
        $in     = implode(',', array_map('intval', $numCargas));

        $pdo->prepare(
            "UPDATE FOCCO3I.TGAZIN_CARGA_AGEND
             SET SITUACAO_CAMINHAO = :s, DT_ALTERACAO = SYSDATE
             WHERE EMPR_ID = $emprId AND NUM_CARGA IN ($in)
               AND (SITUACAO_CAMINHAO IS NULL
                    OR SITUACAO_CAMINHAO NOT IN ('AGUARDANDO DOCUMENTAÇÃO','FINALIZADO'))"
        )->execute([':s' => $status]);

        $pdo->prepare(
            "INSERT INTO FOCCO3I.TGAZIN_CARGA_AGEND (EMPR_ID, NUM_CARGA, SITUACAO_CAMINHAO, DT_CADASTRO)
             SELECT $emprId, C.CARGA, :s, SYSDATE
             FROM FOCCO3I.TCARGAS C
             WHERE C.EMPR_ID = $emprId AND C.CARGA IN ($in)
               AND NOT EXISTS (
                   SELECT 1 FROM FOCCO3I.TGAZIN_CARGA_AGEND A
                   WHERE A.EMPR_ID = $emprId AND A.NUM_CARGA = C.CARGA
               )"
        )->execute([':s' => $status]);

        $pdo->exec('COMMIT');
    }

    public static function marcarFinalizado(int $emprId, int $numCarga): void
    {
        $conn   = self::oci8Conn();
        $status = 'FINALIZADO';

        $chk = oci_parse($conn,
            'SELECT SITUACAO_CAMINHAO FROM FOCCO3I.TGAZIN_CARGA_AGEND
             WHERE EMPR_ID = :e AND NUM_CARGA = :c'
        );
        oci_bind_by_name($chk, ':e', $emprId);
        oci_bind_by_name($chk, ':c', $numCarga);
        oci_execute($chk, OCI_NO_AUTO_COMMIT);
        $row   = oci_fetch_assoc($chk);
        oci_free_statement($chk);

        if ($row === false) {
            // linha não existe — INSERT
            $ins = oci_parse($conn,
                'INSERT INTO FOCCO3I.TGAZIN_CARGA_AGEND
                 (EMPR_ID, NUM_CARGA, SITUACAO_CAMINHAO, DT_CADASTRO)
                 VALUES (:e, :c, :s, SYSDATE)'
            );
            oci_bind_by_name($ins, ':e', $emprId);
            oci_bind_by_name($ins, ':c', $numCarga);
            oci_bind_by_name($ins, ':s', $status, 100);
            oci_execute($ins, OCI_NO_AUTO_COMMIT);
            oci_free_statement($ins);
        } elseif (($row['SITUACAO_CAMINHAO'] ?? '') !== 'DISPONÍVEL') {
            // linha existe e não é DISPONÍVEL — UPDATE
            $upd = oci_parse($conn,
                'UPDATE FOCCO3I.TGAZIN_CARGA_AGEND
                 SET SITUACAO_CAMINHAO = :s, DT_ALTERACAO = SYSDATE
                 WHERE EMPR_ID = :e AND NUM_CARGA = :c'
            );
            oci_bind_by_name($upd, ':s', $status, 100);
            oci_bind_by_name($upd, ':e', $emprId);
            oci_bind_by_name($upd, ':c', $numCarga);
            oci_execute($upd, OCI_NO_AUTO_COMMIT);
            oci_free_statement($upd);
        }
        oci_commit($conn);
        oci_close($conn);
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
        $sql  = "INSERT INTO FOCCO3I.TGAZIN_CARGA_ANEXO
                    (EMPR_ID, NUM_CARGA, NOME_ORIG, MIME_TYPE, TAMANHO, CONTEUDO, USUARIO)
                 VALUES (:empr_id, :num_carga, :nome_orig, :mime_type, :tamanho, EMPTY_BLOB(), :usuario)
                 RETURNING ID, CONTEUDO INTO :ret_id, :blob";

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
        $stmt = oci_parse($conn,
            "SELECT ID, NOME_ORIG, MIME_TYPE, TAMANHO, USUARIO,
                    TO_CHAR(DT_CADASTRO,'DD/MM/YYYY HH24:MI') AS DT_CADASTRO
             FROM FOCCO3I.TGAZIN_CARGA_ANEXO
             WHERE EMPR_ID = :empr_id AND NUM_CARGA = :num_carga
             ORDER BY DT_CADASTRO DESC"
        );
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
        $stmt = oci_parse($conn,
            "SELECT NOME_ORIG, MIME_TYPE, CONTEUDO
             FROM FOCCO3I.TGAZIN_CARGA_ANEXO
             WHERE ID = :id AND EMPR_ID = :empr_id"
        );
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

    public static function excluirAnexo(int $emprId, int $id): void
    {
        $conn = self::oci8Conn();
        $stmt = oci_parse($conn,
            "DELETE FROM FOCCO3I.TGAZIN_CARGA_ANEXO WHERE ID = :id AND EMPR_ID = :empr_id"
        );
        oci_bind_by_name($stmt, ':id',      $id);
        oci_bind_by_name($stmt, ':empr_id', $emprId);
        oci_execute($stmt);
        oci_commit($conn);
        oci_free_statement($stmt);
        oci_close($conn);
    }
}
