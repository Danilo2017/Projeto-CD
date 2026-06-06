<?php

namespace src\models\Cd;

use core\Database;

class ProjecaoCarga
{
    private static array $campos = [
        'DT_CARREGAMENTO', 'OBSERVACOES', 'NUM_DOCS', 'SITUACAO_CARGA',
        'FROTA', 'PLACAS', 'TIPO_VEICULO', 'MOTORISTA', 'CONTATO', 'SITUACAO',
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
}
