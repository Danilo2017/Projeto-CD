<?php

namespace src\controllers\Expedicao;

use core\Controller;
use src\handlers\Expedicao\ExpedicaoHandler as H;

class ExpedicaoController extends Controller
{
    public function cadastro(): void           { $this->render('expedicao/cadastro'); }
    public function equipesIndex(): void       { $this->render('expedicao/equipes'); }
    public function regrasEquipeIndex(): void  { $this->render('expedicao/regras-equipe'); }
    public function regrasFuncIndex(): void    { $this->render('expedicao/regras-funcionario'); }
    public function dashboard(): void          { $this->render('expedicao/dashboard'); }
    public function cargasIndex(): void        { $this->render('expedicao/cargas'); }
    public function relatorio(): void          { $this->render('expedicao/relatorio'); }
    public function relComissoes(): void       { $this->render('expedicao/relatorio-comissoes'); }
    public function relDiario(): void          { $this->render('expedicao/relatorio-diario'); }

    private function exec(callable $fn): void
    {
        try {
            self::response($fn(self::getBody() ?? []), 200);
        } catch (\Throwable $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            $ok = $e instanceof \Exception && !($e instanceof \RuntimeException);
            if (!$ok) error_log('[Expedicao] ' . $e->getMessage());
            self::response(['error' => $ok ? $e->getMessage() : 'Erro ao processar a solicitação.'], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }

    public function empresa(): void           { $this->exec(fn() => H::empresa()); }
    public function funcionarios(): void      { $this->exec(fn($d) => H::funcionarios($d)); }
    public function equipes(): void           { $this->exec(fn($d) => H::equipes($d)); }
    public function equipeSalvar(): void      { $this->exec(fn($d) => H::salvarEquipe($d)); }
    public function equipeExcluir(): void     { $this->exec(fn($d) => H::excluirEquipe($d)); }
    public function membroExcluir(): void     { $this->exec(fn($d) => H::excluirMembro($d)); }
    public function excluir(): void           { $this->exec(fn($d) => H::excluir($d)); }
    public function membros(): void           { $this->exec(fn($d) => H::membros($d)); }
    public function membroSalvar(): void      { $this->exec(fn($d) => H::salvarMembro($d)); }
    public function regrasEquipe(): void      { $this->exec(fn($d) => H::regrasEquipe($d)); }
    public function regraEquipeSalvar(): void { $this->exec(fn($d) => H::salvarRegraEquipe($d)); }
    public function regrasFunc(): void        { $this->exec(fn($d) => H::regrasFunc($d)); }
    public function regraFuncSalvar(): void   { $this->exec(fn($d) => H::salvarRegraFunc($d)); }
    public function cargas(): void            { $this->exec(fn($d) => H::cargas($d)); }
    public function faltasIndex(): void       { $this->render('expedicao/faltas'); }
    public function faltas(): void            { $this->exec(fn($d) => H::faltas($d)); }
    public function faltaSalvar(): void       { $this->exec(fn($d) => H::salvarFalta($d)); }
    public function apurar(): void            { $this->exec(fn($d) => H::apurar($d)); }
}
