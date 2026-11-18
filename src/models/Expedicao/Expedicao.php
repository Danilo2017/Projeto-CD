<?php

namespace src\models\Expedicao;

use core\Database;

/**
 * Comissão da Expedição — cadastros e cargas fechadas (sistema paralelo ao de produção, tudo por empresa).
 * Tabelas novas FOCCO3I.TGAZIN_EXP_* (database/expedicao/01-tabelas.sql). SQLs na FOCCO3I.GAZIN_SQLS: expedicao.*
 * Só LÊ a TCARGAS (Focco) e o cadastro de faltas da produção; nada existente é alterado.
 */
class Expedicao
{
    private const SEQUENCIAS = ['EQUIPE', 'EQUIPE_FUNC', 'REGRA_EQUIPE', 'REGRA_FUNC', 'FALTA'];

    /** Executa um SQL da FOCCO3I.GAZIN_SQLS (switchParams substitui os :nomes pelo texto dos parâmetros). */
    public static function sql(string $idsql, array $params = []): array
    {
        $r = Database::switchParams('focco', $params, $idsql, true, false);
        if (!empty($r['error'])) throw new \RuntimeException("Falha no SQL $idsql: " . $r['error']);
        return is_array($r['retorno']) ? $r['retorno'] : [];
    }

    // ── Validação / literais seguros ─────────────────────────────────

    /** Texto -> literal Oracle (aspas dobradas; \ $ : = saem como CHR() por causa do preg_replace do switchParams). */
    public static function lit(?string $v): string
    {
        if ($v === null || trim($v) === '') return 'NULL';
        $v = str_replace("'", "''", trim($v));
        $v = strtr($v, ['\\' => "' || CHR(92) || '", '$' => "' || CHR(36) || '", ':' => "' || CHR(58) || '", '=' => "' || CHR(61) || '"]);
        return "'" . $v . "'";
    }

    /** YYYY-MM-DD válido ou exceção 422. */
    public static function data($v, string $campo = 'Data'): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new \Exception("$campo inválida.", 422);
        }
        return $v;
    }

    /** Data opcional -> literal SQL (NULL ou TO_DATE). */
    public static function dataOpc($v, string $campo = 'Data'): string
    {
        if ($v === null || trim((string) $v) === '') return 'NULL';
        return "TO_DATE('" . self::data($v, $campo) . "', 'YYYY-MM-DD')";
    }

    /** Número decimal não negativo (aceita vírgula). */
    public static function valor($v, string $campo = 'Valor'): string
    {
        $v = str_replace(',', '.', trim((string) $v));
        if ($v === '') return '0';
        if (!is_numeric($v) || (float) $v < 0 || (float) $v > 999999999) throw new \Exception("$campo inválido.", 422);
        return (string) round((float) $v, 4);
    }

    private static function nextval(string $seq): int
    {
        if (!in_array($seq, self::SEQUENCIAS, true)) throw new \InvalidArgumentException('Sequência inválida.');
        $r = self::sql('expedicao.comum.nextval', ['seq' => 'SEQ_GAZIN_EXP_' . $seq]);
        return (int) ($r[0]['ID'] ?? 0);
    }

    private static function filtroAtivo(bool $apenasAtivos, string $alias): string
    {
        return $apenasAtivos ? "AND $alias.ATIVO = 'S'" : '--';
    }

    private static function usuario($u): string
    {
        return $u ? (string) (int) $u : 'NULL';
    }

    private static function ativo($v): string
    {
        return ($v === 'N' || $v === false || $v === 0 || $v === '0') ? "'N'" : "'S'";
    }

    // ── Equipes ──────────────────────────────────────────────────────

    public static function equipes(int $emprId, bool $apenasAtivos = false): array
    {
        return self::sql('expedicao.equipe.listar', ['id_empr' => $emprId, 'filtro_ativo' => self::filtroAtivo($apenasAtivos, 'E')]);
    }

    public static function salvarEquipe(int $emprId, array $d, ?int $usuario): int
    {
        $nome = trim((string) ($d['nome'] ?? ''));
        $tipo = (string) ($d['tipo'] ?? '');
        if ($nome === '' || mb_strlen($nome) > 100) throw new \Exception('Informe o nome da equipe (até 100 caracteres).', 422);
        if (!in_array($tipo, ['E', 'S'], true)) throw new \Exception('Tipo da equipe deve ser Expedição ou Separação.', 422);
        $p = ['id_empr' => $emprId, 'nome' => self::lit($nome), 'tipo' => "'$tipo'", 'id_usuario' => self::usuario($usuario)];
        $id = (int) ($d['id'] ?? 0);
        if ($id > 0) {
            self::sql('expedicao.equipe.atualizar', $p + ['id' => $id, 'ativo' => self::ativo($d['ativo'] ?? 'S')]);
            return $id;
        }
        $id = self::nextval('EQUIPE');
        self::sql('expedicao.equipe.inserir', $p + ['id' => $id]);
        return $id;
    }

    /** Equipe existe e é da empresa. */
    private static function exigirEquipe(int $emprId, int $equipeId): void
    {
        foreach (self::equipes($emprId) as $e) {
            if ((int) $e['ID_EQUIPE'] === $equipeId) return;
        }
        throw new \Exception('Equipe não encontrada nesta empresa.', 422);
    }

    /** Exclui a equipe junto com seus funcionários e regras (equipe e individuais ligadas a ela). */
    public static function excluirEquipe(int $emprId, int $id): void
    {
        self::exigirEquipe($emprId, $id);
        $p = ['id' => $id, 'id_empr' => $emprId];
        foreach (['excluirMembros', 'excluirRegras', 'excluirRegrasFunc', 'excluir'] as $acao) self::sql("expedicao.equipe.$acao", $p);
    }

    public static function excluirMembro(int $emprId, int $id): void
    {
        if ($id <= 0) throw new \Exception('Funcionário da equipe inválido.', 422);
        self::sql('expedicao.membro.excluir', ['id' => $id, 'id_empr' => $emprId]);
    }
    /** Exclui um registro de cadastro da empresa (regraEquipe | regraFunc | falta). */
    public static function excluir(int $emprId, string $tipo, int $id): void
    {
        if (!in_array($tipo, ['regraEquipe', 'regraFunc', 'falta'], true) || $id <= 0) throw new \Exception('Registro inválido.', 422);
        self::sql("expedicao.$tipo.excluir", ['id' => $id, 'id_empr' => $emprId]);
    }
    // ── Funcionários da equipe ───────────────────────────────────────

    public static function membros(int $emprId, ?int $equipeId = null, bool $apenasAtivos = false): array
    {
        return self::sql('expedicao.membro.listar', [
            'id_empr'       => $emprId,
            'filtro_equipe' => $equipeId ? 'AND M.ID_EQUIPE = ' . (int) $equipeId : '--',
            'filtro_ativo'  => self::filtroAtivo($apenasAtivos, 'M'),
        ]);
    }

    public static function salvarMembro(int $emprId, array $d, ?int $usuario): int
    {
        $equipe = (int) ($d['id_equipe'] ?? 0);
        $func   = (int) ($d['id_funcionario'] ?? 0);
        if ($equipe <= 0 || $func <= 0) throw new \Exception('Informe a equipe e o funcionário.', 422);
        self::exigirEquipe($emprId, $equipe);
        $ini = self::data($d['dt_ini'] ?? '', 'Data de início');
        $fim = self::dataOpc($d['dt_fim'] ?? null, 'Data de fim');
        if (!empty($d['dt_fim']) && $d['dt_fim'] < $ini) throw new \Exception('A data de fim não pode ser anterior ao início.', 422);
        $p = ['id_empr' => $emprId, 'dt_ini' => "'$ini'", 'dt_fim' => $fim, 'id_usuario' => self::usuario($usuario)];
        $id = (int) ($d['id'] ?? 0);
        if ($id > 0) {
            self::sql('expedicao.membro.atualizar', $p + ['id' => $id, 'ativo' => self::ativo($d['ativo'] ?? 'S')]);
            return $id;
        }
        foreach (self::membros($emprId, $equipe, true) as $m) {
            if ((int) $m['ID_FUNCIONARIO'] === $func && ($m['DT_FIM'] === null || $m['DT_FIM'] === '' || $m['DT_FIM'] >= $ini)) {
                throw new \Exception('Este funcionário já está ativo nesta equipe; ajuste o período existente.', 422);
            }
        }
        $id = self::nextval('EQUIPE_FUNC');
        self::sql('expedicao.membro.inserir', $p + ['id' => $id, 'id_equipe' => $equipe, 'id_funcionario' => $func]);
        return $id;
    }

    // ── Regras da equipe ─────────────────────────────────────────────

    public static function regrasEquipe(int $emprId, bool $apenasAtivos = false): array
    {
        return self::sql('expedicao.regraEquipe.listar', ['id_empr' => $emprId, 'filtro_ativo' => self::filtroAtivo($apenasAtivos, 'R')]);
    }

    public static function salvarRegraEquipe(int $emprId, array $d, ?int $usuario): int
    {
        $equipe = (int) ($d['id_equipe'] ?? 0);
        self::exigirEquipe($emprId, $equipe);
        $m3 = self::valor($d['vlr_m3'] ?? 0, 'Valor por ponto (m³)');
        $car = self::valor($d['vlr_carga'] ?? 0, 'Valor por carga');
        if ((float) $m3 == 0.0 && (float) $car == 0.0) throw new \Exception('Informe o valor por ponto (m³) e/ou o valor por carga.', 422);
        $ini = self::data($d['dt_ini'] ?? '', 'Início da vigência');
        if (!empty($d['dt_fim']) && $d['dt_fim'] < $ini) throw new \Exception('O fim da vigência não pode ser anterior ao início.', 422);
        $p = [
            'id_empr' => $emprId, 'id_equipe' => $equipe, 'vlr_m3' => $m3, 'vlr_carga' => $car,
            'dt_ini' => "'$ini'", 'dt_fim' => self::dataOpc($d['dt_fim'] ?? null, 'Fim da vigência'),
            'obs' => self::lit(mb_substr((string) ($d['obs'] ?? ''), 0, 200)), 'id_usuario' => self::usuario($usuario),
        ];
        $id = (int) ($d['id'] ?? 0);
        if ($id > 0) {
            self::sql('expedicao.regraEquipe.atualizar', $p + ['id' => $id, 'ativo' => self::ativo($d['ativo'] ?? 'S')]);
            return $id;
        }
        $id = self::nextval('REGRA_EQUIPE');
        self::sql('expedicao.regraEquipe.inserir', $p + ['id' => $id]);
        return $id;
    }

    // ── Regras por funcionário ───────────────────────────────────────

    public static function regrasFunc(int $emprId, bool $apenasAtivos = false): array
    {
        return self::sql('expedicao.regraFunc.listar', ['id_empr' => $emprId, 'filtro_ativo' => self::filtroAtivo($apenasAtivos, 'R')]);
    }

    public static function salvarRegraFunc(int $emprId, array $d, ?int $usuario): int
    {
        $func = (int) ($d['id_funcionario'] ?? 0);
        if ($func <= 0) throw new \Exception('Informe o funcionário.', 422);
        $equipe = (int) ($d['id_equipe'] ?? 0);
        if ($equipe > 0) self::exigirEquipe($emprId, $equipe);
        $fixo = self::valor($d['vlr_fixo'] ?? 0, 'Valor fixo');
        $m3   = self::valor($d['vlr_m3'] ?? 0, 'Valor por m³');
        if ((float) $fixo == 0.0 && (float) $m3 == 0.0) throw new \Exception('Informe o valor fixo e/ou o valor por m³.', 422);
        $ini = self::data($d['dt_ini'] ?? '', 'Início da vigência');
        if (!empty($d['dt_fim']) && $d['dt_fim'] < $ini) throw new \Exception('O fim da vigência não pode ser anterior ao início.', 422);
        $p = [
            'id_empr' => $emprId, 'id_funcionario' => $func, 'id_equipe' => $equipe > 0 ? $equipe : 'NULL',
            'vlr_fixo' => $fixo, 'vlr_m3' => $m3, 'dt_ini' => "'$ini'", 'dt_fim' => self::dataOpc($d['dt_fim'] ?? null, 'Fim da vigência'),
            'obs' => self::lit(mb_substr((string) ($d['obs'] ?? ''), 0, 200)), 'id_usuario' => self::usuario($usuario),
        ];
        $id = (int) ($d['id'] ?? 0);
        if ($id > 0) {
            self::sql('expedicao.regraFunc.atualizar', $p + ['id' => $id, 'ativo' => self::ativo($d['ativo'] ?? 'S')]);
            return $id;
        }
        $id = self::nextval('REGRA_FUNC');
        self::sql('expedicao.regraFunc.inserir', $p + ['id' => $id]);
        return $id;
    }

    // ── Faltas (cadastro próprio da Expedição) ───────────────────────

    public static function faltas(int $emprId, string $ini, string $fim, ?int $funcionarioId = null, bool $apenasAtivas = false): array
    {
        return self::sql('expedicao.falta.listar', [
            'id_empr' => $emprId, 'dt_ini' => "'" . self::data($ini, 'Data inicial') . "'", 'dt_fim' => "'" . self::data($fim, 'Data final') . "'",
            'filtro_func'  => $funcionarioId ? 'AND F.ID_FUNCIONARIO = ' . (int) $funcionarioId : '--',
            'filtro_ativo' => self::filtroAtivo($apenasAtivas, 'F'),
        ]);
    }

    /**
     * Registra falta de um dia ou de um período (dt_fim opcional). Um registro por dia; se o dia já tem falta ativa,
     * o tipo/motivo é atualizado; se estava inativa, é reativada. Máximo de 92 dias por lançamento.
     * @return array{criadas:int, atualizadas:int}
     */
    public static function salvarFalta(int $emprId, array $d, ?int $usuario): array
    {
        $tipo = (string) ($d['tipo'] ?? '');
        if (!in_array($tipo, ['I', 'P'], true)) throw new \Exception('Escolha o tipo da falta (integral ou parcial).', 422);
        $motivo = self::lit(mb_substr((string) ($d['motivo'] ?? ''), 0, 500));
        $user = self::usuario($usuario);

        $id = (int) ($d['id'] ?? 0);
        if ($id > 0) { // edição de uma falta existente (tipo, motivo, situação)
            self::sql('expedicao.falta.atualizar', ['id' => $id, 'id_empr' => $emprId, 'tipo' => "'$tipo'", 'motivo' => $motivo, 'ativo' => self::ativo($d['ativo'] ?? 'S'), 'id_usuario' => $user]);
            return ['criadas' => 0, 'atualizadas' => 1];
        }

        $func = (int) ($d['id_funcionario'] ?? 0);
        if ($func <= 0) throw new \Exception('Informe o funcionário.', 422);
        $ini = self::data($d['dt_ini'] ?? '', 'Data inicial');
        $fim = trim((string) ($d['dt_fim'] ?? '')) === '' ? $ini : self::data($d['dt_fim'], 'Data final');
        if ($fim < $ini) throw new \Exception('A data final não pode ser anterior à inicial.', 422);
        $dias = (int) ((strtotime($fim) - strtotime($ini)) / 86400) + 1;
        if ($dias > 92) throw new \Exception('O período pode ter no máximo 92 dias por lançamento.', 422);
        $pularFds = !empty($d['pular_fim_semana']);

        $existentes = [];
        foreach (self::faltas($emprId, $ini, $fim, $func) as $f) $existentes[$f['DT_FALTA']] = $f;

        $criadas = $atualizadas = 0;
        for ($t = strtotime($ini); $t <= strtotime($fim); $t = strtotime('+1 day', $t)) {
            if ($pularFds && (int) date('N', $t) >= 6) continue;
            $dia = date('Y-m-d', $t);
            if (isset($existentes[$dia])) {
                self::sql('expedicao.falta.atualizar', ['id' => (int) $existentes[$dia]['ID_FALTA'], 'id_empr' => $emprId, 'tipo' => "'$tipo'", 'motivo' => $motivo, 'ativo' => "'S'", 'id_usuario' => $user]);
                $atualizadas++;
            } else {
                self::sql('expedicao.falta.inserir', ['id' => self::nextval('FALTA'), 'id_empr' => $emprId, 'id_funcionario' => $func, 'dt_falta' => "'$dia'", 'tipo' => "'$tipo'", 'motivo' => $motivo, 'id_usuario' => $user]);
                $criadas++;
            }
        }
        return ['criadas' => $criadas, 'atualizadas' => $atualizadas];
    }
    // ── Cargas fechadas (lidas do Focco; nada é gravado aqui) ────────

    /**
     * Cargas hoje fechadas (TCARGAS.SIT_PLC = F) da empresa cuja data de fechamento cai no período.
     * A data vem do log do Focco (F3I_LOG_TCARGAS): é a do último fechamento (se a carga foi reaberta e fechada de novo, vale o último).
     * Cargas fechadas antes de 08/10/2026 não têm log e não aparecem.
     */
    public static function cargas(int $emprId, string $ini, string $fim): array
    {
        return self::sql('expedicao.carga.fechadas', [
            'id_empr' => $emprId, 'dt_ini' => "'" . self::data($ini, 'Data inicial') . "'", 'dt_fim' => "'" . self::data($fim, 'Data final') . "'",
        ]);
    }
}
