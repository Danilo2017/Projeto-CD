<?php

namespace src\models\CD;

use core\Database;
use src\utils\GetSqlFocco;

/**
 * Anexos (comprovantes) do agendamento de recebimento
 * BLOB via OCI8 — mesmo padrão de ProjecaoCarga
 */
class AgendamentoAnexo
{
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

    public static function salvar(int $agendamentoId, string $nomeOrig, string $mimeType, int $tamanho, string $conteudo, string $usuario): int
    {
        $conn = self::oci8Conn();
        $stmt  = oci_parse($conn, GetSqlFocco::getSql('cd.agendamento.anexo.inserir'));
        $blob  = oci_new_descriptor($conn, OCI_D_LOB);
        $retId = 0;

        oci_bind_by_name($stmt, ':agendamento_id', $agendamentoId);
        oci_bind_by_name($stmt, ':nome_orig',      $nomeOrig, 500);
        oci_bind_by_name($stmt, ':mime_type',      $mimeType, 200);
        oci_bind_by_name($stmt, ':tamanho',        $tamanho);
        oci_bind_by_name($stmt, ':usuario',        $usuario, 100);
        oci_bind_by_name($stmt, ':ret_id',         $retId, 32, SQLT_INT);
        oci_bind_by_name($stmt, ':blob',           $blob, -1, OCI_B_BLOB);

        if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($stmt);
            oci_close($conn);
            throw new \Exception('Erro ao salvar anexo: ' . $e['message']);
        }
        $blob->save($conteudo);
        oci_commit($conn);

        oci_free_statement($stmt);
        $blob->free();
        oci_close($conn);

        return (int) $retId;
    }

    public static function listar(int $agendamentoId): array
    {
        $conn = self::oci8Conn();
        $stmt = oci_parse($conn, GetSqlFocco::getSql('cd.agendamento.anexo.listar'));
        oci_bind_by_name($stmt, ':agendamento_id', $agendamentoId);
        oci_execute($stmt);

        $rows = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = $row;
        }

        oci_free_statement($stmt);
        oci_close($conn);
        return $rows;
    }

    public static function download(int $id): ?array
    {
        $conn = self::oci8Conn();
        $stmt = oci_parse($conn, GetSqlFocco::getSql('cd.agendamento.anexo.download'));
        oci_bind_by_name($stmt, ':id', $id);
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

    public static function excluir(int $id): void
    {
        $conn = self::oci8Conn();
        $stmt = oci_parse($conn, GetSqlFocco::getSql('cd.agendamento.anexo.excluir'));
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);
        oci_commit($conn);
        oci_free_statement($stmt);
        oci_close($conn);
    }

    /**
     * Quantidade de anexos por agendamento: [agendamento_id => qtd]
     */
    public static function contarPorAgendamento(): array
    {
        $result = Database::switchParams('focco', [], 'cd.agendamento.anexo.contar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        $contagem = [];
        foreach ($result['retorno'] ?: [] as $row) {
            $contagem[(int) $row['AGENDAMENTO_ID']] = (int) $row['QTD'];
        }
        return $contagem;
    }
}
