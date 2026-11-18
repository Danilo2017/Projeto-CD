<?php

namespace src\handlers\Expedicao;

use src\models\Expedicao\Expedicao as M;
use src\models\Expedicao\ExpedicaoApuracao;
use src\models\Comissao\Funcionario;

/** Comissão da Expedição — a empresa vem sempre da sessão (cabeçalho), nunca do corpo da requisição. */
class ExpedicaoHandler
{
    private static function empr(): int
    {
        $id = (int) ($_SESSION['empresa']['id'] ?? 0);
        if ($id <= 0) throw new \Exception('Selecione a empresa no cabeçalho.', 422);
        return $id;
    }

    private static function usuario(): ?int
    {
        $u = (int) ($_SESSION['user']['id'] ?? 0);
        return $u > 0 ? $u : null;
    }

    public static function empresa(): array
    {
        return ['empresa' => $_SESSION['empresa'] ?? null];
    }

    public static function funcionarios(array $d): array
    {
        $busca = trim((string) ($d['busca'] ?? ''));
        return ['rows' => Funcionario::listarAtivos(self::empr(), $busca !== '' ? $busca : null)];
    }

    public static function equipes(array $d): array
    {
        return ['rows' => M::equipes(self::empr(), !empty($d['apenas_ativas']))];
    }

    public static function salvarEquipe(array $d): array
    {
        return ['id' => M::salvarEquipe(self::empr(), $d, self::usuario())];
    }

    public static function membros(array $d): array
    {
        $eq = (int) ($d['id_equipe'] ?? 0);
        return ['rows' => M::membros(self::empr(), $eq > 0 ? $eq : null, !empty($d['apenas_ativos']))];
    }

    public static function excluirEquipe(array $d): array
    {
        M::excluirEquipe(self::empr(), (int) ($d['id'] ?? 0));
        return ['ok' => true];
    }

    public static function excluirMembro(array $d): array
    {
        M::excluirMembro(self::empr(), (int) ($d['id'] ?? 0));
        return ['ok' => true];
    }
    public static function excluir(array $d): array
    {
        M::excluir(self::empr(), (string) ($d['tipo'] ?? ''), (int) ($d['id'] ?? 0));
        return ['ok' => true];
    }
    public static function salvarMembro(array $d): array
    {
        return ['id' => M::salvarMembro(self::empr(), $d, self::usuario())];
    }

    public static function regrasEquipe(array $d): array
    {
        return ['rows' => M::regrasEquipe(self::empr(), !empty($d['apenas_ativas']))];
    }

    public static function salvarRegraEquipe(array $d): array
    {
        return ['id' => M::salvarRegraEquipe(self::empr(), $d, self::usuario())];
    }

    public static function regrasFunc(array $d): array
    {
        return ['rows' => M::regrasFunc(self::empr(), !empty($d['apenas_ativas']))];
    }

    public static function salvarRegraFunc(array $d): array
    {
        return ['id' => M::salvarRegraFunc(self::empr(), $d, self::usuario())];
    }

    public static function cargas(array $d): array
    {
        set_time_limit(120);
        $fim = (string) ($d['dt_fim'] ?? date('Y-m-d'));
        $ini = (string) ($d['dt_ini'] ?? date('Y-m-01'));
        return ['rows' => M::cargas(self::empr(), $ini, $fim)];
    }
    public static function faltas(array $d): array
    {
        $func = (int) ($d['id_funcionario'] ?? 0);
        return ['rows' => M::faltas(self::empr(), (string) ($d['dt_ini'] ?? date('Y-m-01')), (string) ($d['dt_fim'] ?? date('Y-m-d')), $func > 0 ? $func : null, !empty($d['apenas_ativas']))];
    }

    public static function salvarFalta(array $d): array
    {
        return M::salvarFalta(self::empr(), $d, self::usuario());
    }
    public static function apurar(array $d): array
    {
        set_time_limit(120);
        $empr = self::empr();
        return ExpedicaoApuracao::apurar($empr, (string) ($d['dt_ini'] ?? ''), (string) ($d['dt_fim'] ?? ''));
    }
}
