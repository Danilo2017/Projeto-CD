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
}
