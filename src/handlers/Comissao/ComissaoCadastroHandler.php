<?php

namespace src\handlers\Comissao;

use core\Database;
use src\models\Comissao\PontuacaoProduto;
use src\models\Comissao\FaixaComissao;
use src\models\Comissao\CentroTrabalho;
use src\models\Comissao\Funcionario;
use src\models\Comissao\Recurso;
use src\models\Comissao\Empresa;
use src\models\Comissao\Vinculo;
use src\models\Comissao\FaltaFuncionario;
use src\models\Comissao\Retrabalho;
use src\models\Comissao\VinculoApontamento;
use src\models\Comissao\RegraFuncionario;

/**
 * Handler para lógica de negócio do Cadastro de Comissão
 * Centraliza pontuações, faixas, vínculos, faltas, retrabalhos e regras
 */
class ComissaoCadastroHandler
{
    private PontuacaoProduto $pontuacaoModel;
    private FaixaComissao $faixaModel;
    private CentroTrabalho $centroModel;
    private Funcionario $funcionarioModel;
    private Recurso $recursoModel;
    private Empresa $empresaModel;
    private FaltaFuncionario $faltaModel;
    private Retrabalho $retrabalhoModel;
    private VinculoApontamento $vinculoApontamentoModel;
    private RegraFuncionario $regraModel;

    public function __construct()
    {
        $this->pontuacaoModel = new PontuacaoProduto();
        $this->faixaModel = new FaixaComissao();
        $this->centroModel = new CentroTrabalho();
        $this->funcionarioModel = new Funcionario();
        $this->recursoModel = new Recurso();
        $this->empresaModel = new Empresa();
        $this->faltaModel = new FaltaFuncionario();
        $this->retrabalhoModel = new Retrabalho();
        $this->vinculoApontamentoModel = new VinculoApontamento();
        $this->regraModel = new RegraFuncionario();
    }

    // ==================== PONTUAÇÃO ====================

    /**
     * Listar pontuações cadastradas
     */
    public function listarPontuacoes(?int $emprId, bool $incluirInativas = false): array
    {
        $pontuacoes = $this->pontuacaoModel->listarTodas($emprId);
        
        if (!$incluirInativas) {
            $pontuacoes = array_filter($pontuacoes, fn($p) => $p['ATIVO'] === 'S');
            $pontuacoes = array_values($pontuacoes);
        }
        
        return $pontuacoes;
    }

    /**
     * Buscar pontuação por ID
     */
    public function buscarPontuacao(int $id): ?array
    {
        return $this->pontuacaoModel->buscarPorId($id);
    }

    /**
     * Salvar nova pontuação
     */
    public function salvarPontuacao(array $dados): int
    {
        $dadosModel = [
            'empr_id' => $dados['empr_id'],
            'item_id' => $dados['item_id'] ?? null,
            'itempr_id' => $dados['itempr_id'] ?? null,
            'mascara_id' => $dados['mascara_id'] ?? null,
            'centro_trab_id' => $dados['centro_trab_id'] ?? null,
            'pontos_up' => $dados['pontuacao_up'] ?? $dados['pontos_up'],
            'dt_vigencia_ini' => $dados['dt_vigencia_ini'],
            'dt_vigencia_fim' => !empty($dados['dt_vigencia_fim']) ? $dados['dt_vigencia_fim'] : null,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        if (empty($dadosModel['item_id'])) {
            throw new \Exception('Produto é obrigatório');
        }
        
        return $this->pontuacaoModel->inserir($dadosModel);
    }

    /**
     * Atualizar pontuação existente
     */
    public function atualizarPontuacao(int $id, array $dados): void
    {
        $dadosModel = [
            'pontos_up' => $dados['pontuacao_up'],
            'dt_vigencia_ini' => $dados['dt_vigencia_ini'],
            'dt_vigencia_fim' => $dados['dt_vigencia_fim'] ?? null,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        $this->pontuacaoModel->atualizar($id, $dadosModel);
    }

    /**
     * Excluir pontuação
     */
    public function excluirPontuacao(int $id, ?int $usuarioId): void
    {
        $this->pontuacaoModel->excluir($id, $usuarioId);
    }

    /**
     * Importar pontuações em lote
     */
    public function importarPontuacoes(array $linhas, int $emprId, ?int $idUsuario): array
    {
        $pdo = Database::getInstance('focco');
        $importados = 0;
        $erros = [];
        
        foreach ($linhas as $idx => $linha) {
            $numLinha = $idx + 2;
            
            try {
                $codItem = trim($linha['COD_ITEM'] ?? '');
                $idMascara = trim($linha['ID_MASCARA'] ?? '');
                $codCentro = trim($linha['COD_CENTRO'] ?? '');
                $pontosUp = trim($linha['PONTOS_UP'] ?? '');
                $dtIni = trim($linha['DT_VIGENCIA_INI'] ?? '');
                $dtFim = trim($linha['DT_VIGENCIA_FIM'] ?? '');
                
                if (empty($codItem)) {
                    $erros[] = "Linha {$numLinha}: COD_ITEM vazio";
                    continue;
                }
                if (empty($pontosUp)) {
                    $erros[] = "Linha {$numLinha}: PONTOS_UP vazio";
                    continue;
                }
                if (empty($dtIni)) {
                    $erros[] = "Linha {$numLinha}: DT_VIGENCIA_INI vazio";
                    continue;
                }
                
                $pontosUp = str_replace(',', '.', $pontosUp);
                $dtIniFormatada = $this->converterData($dtIni);
                $dtFimFormatada = !empty($dtFim) ? $this->converterData($dtFim) : null;
                
                if (!$dtIniFormatada) {
                    $erros[] = "Linha {$numLinha}: Data início inválida ({$dtIni})";
                    continue;
                }
                
                // Buscar ITEM_ID pelo COD_ITEM
                $item = $this->buscarItemPorCodigo($pdo, $codItem, $emprId);
                if (!$item) {
                    $erros[] = "Linha {$numLinha}: Produto '{$codItem}' não encontrado";
                    continue;
                }
                
                // Buscar ID_CENTRO_TRAB pelo COD_CENTRO
                $centroTrabId = null;
                if (!empty($codCentro)) {
                    $centro = $this->buscarCentroPorCodigo($pdo, $codCentro);
                    if ($centro) {
                        $centroTrabId = $centro['ID'];
                    } else {
                        $erros[] = "Linha {$numLinha}: Centro '{$codCentro}' não encontrado (ignorado)";
                    }
                }
                
                $mascaraId = !empty($idMascara) ? (int)$idMascara : null;
                
                $dadosModel = [
                    'empr_id' => $emprId,
                    'item_id' => $item['ITEM_ID'],
                    'itempr_id' => $item['ITEMPR_ID'],
                    'mascara_id' => $mascaraId,
                    'centro_trab_id' => $centroTrabId,
                    'pontos_up' => $pontosUp,
                    'dt_vigencia_ini' => $dtIniFormatada,
                    'dt_vigencia_fim' => $dtFimFormatada,
                    'id_usuario' => $idUsuario
                ];
                
                $this->pontuacaoModel->inserir($dadosModel);
                $importados++;
                
            } catch (\Exception $e) {
                $erros[] = "Linha {$numLinha}: " . $e->getMessage();
            }
        }
        
        return [
            'importados' => $importados,
            'erros' => $erros,
            'total' => count($linhas)
        ];
    }

    // ==================== FAIXAS ====================

    /**
     * Listar faixas de comissão
     */
    public function listarFaixas(?int $emprId, ?int $centroTrabId = null, bool $incluirInativas = false): array
    {
        if ($incluirInativas) {
            return $this->faixaModel->listarTodas($emprId);
        }
        return $this->faixaModel->listarAtivas($emprId, $centroTrabId);
    }

    /**
     * Buscar faixa por ID
     */
    public function buscarFaixa(int $id): ?array
    {
        return $this->faixaModel->buscarPorId($id);
    }

    /**
     * Salvar nova faixa
     */
    public function salvarFaixa(array $dados): int
    {
        $dados = $this->normalizarDadosFaixa($dados);
        
        if (!in_array($dados['tipo'], [FaixaComissao::TIPO_PERCENTUAL, FaixaComissao::TIPO_QUANTIDADE])) {
            throw new \Exception('Tipo de faixa inválido');
        }
        
        $conflito = $this->faixaModel->verificarConflito($dados);
        if ($conflito) {
            throw new \Exception($this->formatarMensagemConflito($conflito));
        }
        
        return $this->faixaModel->inserir($dados);
    }

    /**
     * Atualizar faixa existente
     */
    public function atualizarFaixa(int $id, array $dados): void
    {
        $dados = $this->normalizarDadosFaixa($dados);
        
        $conflito = $this->faixaModel->verificarConflito($dados, $id);
        if ($conflito) {
            throw new \Exception($this->formatarMensagemConflito($conflito));
        }
        
        $this->faixaModel->atualizar($id, $dados);
    }

    /**
     * Inativar faixa
     */
    public function inativarFaixa(int $id, ?int $usuarioId): void
    {
        $this->faixaModel->inativar($id, $usuarioId);
    }

    // ==================== DADOS AUXILIARES ====================

    /**
     * Listar centros de trabalho
     */
    public function listarCentrosTrabalho(?int $emprId): array
    {
        return $this->centroModel->listarTodos($emprId);
    }

    /**
     * Listar funcionários ativos
     */
    public function listarFuncionarios(?int $emprId, ?string $busca = null): array
    {
        return $this->funcionarioModel->listarAtivos($emprId, $busca);
    }

    /**
     * Listar recursos/máquinas
     */
    public function listarRecursos(?int $emprId, ?int $centroTrabId = null): array
    {
        return $this->recursoModel->listarAtivos($emprId, $centroTrabId);
    }

    /**
     * Listar empresas para select
     */
    public function listarEmpresas(): array
    {
        return $this->empresaModel->listarParaSelect();
    }

    /**
     * Buscar empresa por ID
     */
    public function buscarEmpresa(int $id): ?array
    {
        return $this->empresaModel->buscarPorId($id);
    }

    /**
     * Buscar produtos com filtro
     */
    public function buscarProdutos(?int $emprId, ?string $termo, int $page = 1, int $limit = 30): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = Database::getInstance('focco');
        
        $sqlBase = $this->getSqlBaseProdutos();
        $params = [];
        
        if ($emprId) {
            $sqlBase .= " AND TEMPRESAS.ID = :empr_id";
            $params['empr_id'] = $emprId;
        }
        
        if ($termo) {
            $sqlBase .= " AND (UPPER(TITENS.COD_ITEM) LIKE UPPER(:termo) 
                          OR UPPER(TITENS.DESC_TECNICA) LIKE UPPER(:termo2)
                          OR UPPER(TMASC_ITEM.MASCARA) LIKE UPPER(:termo3))";
            $params['termo'] = '%' . $termo . '%';
            $params['termo2'] = '%' . $termo . '%';
            $params['termo3'] = '%' . $termo . '%';
        }
        
        // Contar total
        $sqlCount = "SELECT COUNT(DISTINCT TITENS_EMPR.ID || '-' || NVL(TMASC_ITEM.ID, 0)) " . $sqlBase;
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
        
        // Buscar dados paginados
        $sql = "SELECT DISTINCT
                    TEMPRESAS.ID AS ID_EMPRESA,
                    TEMPRESAS.COD_EMP,
                    TITENS.ID AS ITEM_ID,
                    TITENS_EMPR.ID AS ID_ITEMPR,
                    TITENS.COD_ITEM,
                    TITENS.DESC_TECNICA AS DESCRICAO,
                    TMASC_ITEM.ID AS ID_MASCARA,
                    TMASC_ITEM.MASCARA
                " . $sqlBase . "
                ORDER BY TITENS.DESC_TECNICA ASC
                OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'produtos' => $this->formatarProdutosSelect2($produtos),
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ];
    }

    // ==================== VÍNCULOS ====================

    /**
     * Listar vínculos centro/recurso/funcionário
     */
    public function listarVinculos(array $filtros): array
    {
        return Vinculo::listar($filtros) ?: [];
    }

    /**
     * Listar centros que possuem vínculo
     */
    public function listarCentrosComVinculo(?int $emprId): array
    {
        return Vinculo::listarCentrosComVinculo($emprId);
    }

    /**
     * Listar recursos que possuem vínculo
     */
    public function listarRecursosComVinculo(?int $emprId, ?int $centroTrabId = null): array
    {
        return Vinculo::listarRecursosComVinculo($emprId, $centroTrabId);
    }

    /**
     * Listar funcionários que possuem vínculo
     */
    public function listarFuncionariosComVinculo(?int $emprId, ?string $busca = null): array
    {
        return Vinculo::listarFuncionariosComVinculo($emprId, $busca);
    }

    /**
     * Salvar novo vínculo
     */
    public function salvarVinculo(array $dados): bool
    {
        $tipoVinculo = $dados['tipo_vinculo'] ?? 'N';
        $emprId = $dados['empr_id'] ?? 1;
        
        if (empty($dados['funcionario_id']) || empty($dados['centro_id'])) {
            throw new \Exception('Funcionário e Centro de Trabalho são obrigatórios');
        }
        if ($tipoVinculo === 'N' && empty($dados['recurso_id'])) {
            throw new \Exception('Recurso é obrigatório para vínculo Normal');
        }
        
        return Vinculo::inserir(
            $emprId,
            $dados['funcionario_id'],
            $dados['centro_id'],
            $dados['recurso_id'] ?? null,
            $tipoVinculo
        );
    }

    /**
     * Atualizar vínculo existente
     */
    public function atualizarVinculo(int $id, array $dados): bool
    {
        $tipoVinculo = $dados['tipo_vinculo'] ?? 'N';
        
        if (empty($dados['funcionario_id']) || empty($dados['centro_id'])) {
            throw new \Exception('Funcionário e Centro de Trabalho são obrigatórios');
        }
        if ($tipoVinculo === 'N' && empty($dados['recurso_id'])) {
            throw new \Exception('Recurso é obrigatório para vínculo Normal');
        }
        
        return Vinculo::atualizar(
            $id,
            $dados['centro_id'],
            $dados['recurso_id'] ?? null,
            $tipoVinculo
        );
    }

    /**
     * Alterar status do vínculo
     */
    public function alterarStatusVinculo(int $id, string $ativo): bool
    {
        $ativo = $ativo === 'S' ? 'S' : 'N';
        return Vinculo::alterarStatus($id, $ativo);
    }

    /**
     * Excluir vínculo
     */
    public function excluirVinculo(int $id): bool
    {
        return Vinculo::excluir($id);
    }

    // ==================== FALTAS ====================

    /**
     * Listar faltas de funcionários
     */
    public function listarFaltas(array $filtros): array
    {
        return $this->faltaModel->listar($filtros);
    }

    /**
     * Registrar nova falta
     */
    public function salvarFalta(array $dados): int
    {
        $dadosModel = [
            'id_empr' => $dados['id_empr'] ?? null,
            'id_funcionario' => $dados['id_funcionario'],
            'dt_falta' => $dados['dt_falta'],
            'motivo' => $dados['motivo'] ?? null,
            'tipo_falta' => $dados['tipo_falta'] ?? 'I',
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        return $this->faltaModel->registrar($dadosModel);
    }

    /**
     * Atualizar falta
     */
    public function atualizarFalta(int $id, array $dados): void
    {
        $dadosModel = [
            'dt_falta' => $dados['dt_falta'],
            'motivo' => $dados['motivo'] ?? null,
            'tipo_falta' => $dados['tipo_falta'] ?? 'I',
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        $this->faltaModel->atualizar($id, $dadosModel);
    }

    /**
     * Excluir falta
     */
    public function excluirFalta(int $id, ?int $usuarioId): void
    {
        $this->faltaModel->excluir($id, $usuarioId);
    }

    // ==================== RETRABALHO ====================

    /**
     * Listar retrabalhos
     */
    public function listarRetrabalhos(array $filtros): array
    {
        return $this->retrabalhoModel->listar($filtros);
    }

    /**
     * Registrar retrabalho
     */
    public function salvarRetrabalho(array $dados): int
    {
        $dadosModel = [
            'id_empr' => $dados['id_empr'] ?? null,
            'id_funcionario' => $dados['id_funcionario'],
            'id_recurso' => $dados['id_recurso'] ?? null,
            'id_item' => $dados['id_item'],
            'id_mascara' => $dados['id_mascara'] ?? null,
            'id_ordem' => $dados['id_ordem'] ?? null,
            'dt_retrabalho' => $dados['dt_retrabalho'],
            'quantidade' => $dados['quantidade'],
            'motivo' => $dados['motivo'] ?? null,
            'tipo_impacto' => $dados['tipo_impacto'] ?? 'P',
            'valor_impacto' => $dados['valor_impacto'] ?? 0,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        return $this->retrabalhoModel->inserir($dadosModel);
    }

    /**
     * Atualizar retrabalho
     */
    public function atualizarRetrabalho(int $id, array $dados): void
    {
        $dadosModel = [
            'id_funcionario' => $dados['id_funcionario'],
            'id_recurso' => $dados['id_recurso'] ?? null,
            'id_item' => $dados['id_item'],
            'id_mascara' => $dados['id_mascara'] ?? null,
            'id_ordem' => $dados['id_ordem'] ?? null,
            'dt_retrabalho' => $dados['dt_retrabalho'],
            'quantidade' => $dados['quantidade'],
            'motivo' => $dados['motivo'] ?? null,
            'tipo_impacto' => $dados['tipo_impacto'] ?? 'P',
            'valor_impacto' => $dados['valor_impacto'] ?? 0,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        $this->retrabalhoModel->atualizar($id, $dadosModel);
    }

    /**
     * Excluir retrabalho
     */
    public function excluirRetrabalho(int $id, ?int $usuarioId): void
    {
        $this->retrabalhoModel->excluir($id, $usuarioId);
    }

    // ==================== VÍNCULO DE APONTAMENTOS ====================

    /**
     * Listar apontamentos sem recurso
     */
    public function listarApontamentosSemRecurso(array $filtros): array
    {
        return $this->vinculoApontamentoModel->listarApontamentosSemRecurso($filtros);
    }

    /**
     * Listar vínculos de apontamentos
     */
    public function listarVinculosApontamento(array $filtros): array
    {
        return $this->vinculoApontamentoModel->listarVinculos($filtros);
    }

    /**
     * Vincular recurso ao apontamento
     */
    public function vincularRecurso(int $apontamentoId, int $recursoId): bool
    {
        return $this->vinculoApontamentoModel->vincularRecurso($apontamentoId, $recursoId);
    }

    /**
     * Vincular apontamento a funcionário
     */
    public function vincularApontamento(array $dados): int
    {
        $dadosModel = [
            'id_empr' => $dados['id_empr'] ?? null,
            'id_apontamento' => $dados['id_apontamento'],
            'id_funcionario' => $dados['id_funcionario'],
            'id_recurso' => $dados['id_recurso'] ?? null,
            'observacao' => $dados['observacao'] ?? null,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        return $this->vinculoApontamentoModel->vincular($dadosModel);
    }

    /**
     * Vincular apontamentos em lote
     */
    public function vincularApontamentosLote(array $dados): array
    {
        return $this->vinculoApontamentoModel->vincularEmLote(
            $dados['apontamentos'],
            $dados['id_funcionario'],
            $dados['id_recurso'] ?? null,
            $dados['id_empr'] ?? null,
            $dados['id_usuario'] ?? null
        );
    }

    /**
     * Atualizar vínculo de apontamento
     */
    public function atualizarVinculoApontamento(int $id, array $dados): void
    {
        $this->vinculoApontamentoModel->atualizar($id, [
            'id_funcionario' => $dados['id_funcionario'],
            'id_recurso' => $dados['id_recurso'] ?? null,
            'observacao' => $dados['observacao'] ?? null,
            'id_usuario' => $dados['id_usuario'] ?? null
        ]);
    }

    /**
     * Excluir vínculo de apontamento
     */
    public function excluirVinculoApontamento(int $id, ?int $usuarioId): void
    {
        $this->vinculoApontamentoModel->excluir($id, $usuarioId);
    }

    // ==================== REGRAS ESPECÍFICAS ====================

    /**
     * Listar regras específicas
     */
    public function listarRegras(array $filtros): array
    {
        return $this->regraModel->listar($filtros) ?: [];
    }

    /**
     * Buscar regra por ID
     */
    public function buscarRegra(int $id): ?array
    {
        return $this->regraModel->buscarPorId($id);
    }

    /**
     * Salvar nova regra
     */
    public function salvarRegra(array $dados): int
    {
        $dadosModel = [
            'id_empr' => $dados['id_empr'] ?? null,
            'id_funcionario' => $dados['id_funcionario'],
            'id_centro_trab' => $dados['id_centro_trab'] ?? null,
            'descricao' => $dados['descricao'] ?? null,
            'tipo_comissao' => $dados['tipo_comissao'],
            'valor_comissao' => $dados['valor_comissao'] ?? 0,
            'valor_fixo' => $dados['valor_fixo'] ?? null,
            'dt_vigencia_ini' => $dados['dt_vigencia_ini'],
            'dt_vigencia_fim' => $dados['dt_vigencia_fim'] ?? null,
            'prioridade' => $dados['prioridade'] ?? 1,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        return $this->regraModel->inserir($dadosModel);
    }

    /**
     * Atualizar regra existente
     */
    public function atualizarRegra(int $id, array $dados): void
    {
        $regraAtual = $this->regraModel->buscarPorId($id);
        
        if (!$regraAtual) {
            throw new \Exception('Regra não encontrada');
        }
        
        $dadosModel = [
            'id_funcionario' => $dados['id_funcionario'] ?? $regraAtual['ID_FUNCIONARIO'],
            'id_centro_trab' => $dados['id_centro_trab'] ?? null,
            'descricao' => $dados['descricao'] ?? null,
            'tipo_comissao' => $dados['tipo_comissao'],
            'valor_comissao' => $dados['valor_comissao'],
            'dt_vigencia_ini' => $dados['dt_vigencia_ini'],
            'dt_vigencia_fim' => $dados['dt_vigencia_fim'] ?? null,
            'prioridade' => $dados['prioridade'] ?? 1,
            'id_empr' => $dados['id_empr'] ?? null,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        $this->regraModel->atualizar($id, $dadosModel);
    }

    /**
     * Inativar regra
     */
    public function inativarRegra(int $id, ?int $usuarioId): void
    {
        $this->regraModel->inativar($id, $usuarioId);
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Converter data DD/MM/AAAA ou YYYY-MM-DD para YYYY-MM-DD
     */
    private function converterData(?string $data): ?string
    {
        if (empty($data)) return null;
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $data;
        }
        
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        
        return null;
    }

    /**
     * Buscar item pelo código
     */
    private function buscarItemPorCodigo(\PDO $pdo, string $codItem, int $emprId): ?array
    {
        $sql = "SELECT I.ID AS ITEM_ID, IE.ID AS ITEMPR_ID 
                FROM FOCCO3I.TITENS I
                LEFT JOIN FOCCO3I.TITENS_EMPR IE ON IE.ITEM_ID = I.ID AND IE.EMPR_ID = :empr_id
                WHERE I.COD_ITEM = :cod_item
                FETCH FIRST 1 ROW ONLY";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':empr_id', $emprId, \PDO::PARAM_INT);
        $stmt->bindValue(':cod_item', $codItem, \PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar centro pelo código
     */
    private function buscarCentroPorCodigo(\PDO $pdo, string $codCentro): ?array
    {
        $sql = "SELECT ID FROM FOCCO3I.TCENTROS_TRAB WHERE COD_CENTRO = :cod_centro FETCH FIRST 1 ROW ONLY";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cod_centro', $codCentro, \PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Normalizar dados da faixa
     */
    private function normalizarDadosFaixa(array $dados): array
    {
        $dados['tipo'] = $dados['tipo'] ?? $dados['tipo_faixa'] ?? null;
        $dados['ponto_inicial'] = $dados['ponto_inicial'] ?? $dados['pontoInicial'] ?? null;
        $dados['ponto_final'] = $dados['ponto_final'] ?? $dados['pontoFinal'] ?? null;
        $dados['valor_comissao'] = $dados['valor_comissao'] ?? $dados['valorComissao'] ?? null;
        $dados['centro_trab_id'] = $dados['centro_trab_id'] ?? $dados['centroTrabId'] ?? null;
        $dados['dt_vigencia_ini'] = $dados['dt_vigencia_ini'] ?? $dados['dtVigenciaIni'] ?? null;
        $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?? $dados['dtVigenciaFim'] ?? null;
        
        $dados['ponto_final'] = $dados['ponto_final'] ?: null;
        $dados['centro_trab_id'] = $dados['centro_trab_id'] ?: null;
        $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?: null;
        
        return $dados;
    }

    /**
     * Formatar mensagem de conflito de faixa
     */
    private function formatarMensagemConflito(array $conflito): string
    {
        $centroNome = $conflito['DESC_CENTRO'] 
            ? $conflito['COD_CENTRO'] . ' - ' . $conflito['DESC_CENTRO'] 
            : 'Todos';
        
        return 'Já existe uma faixa ativa para este centro de trabalho (' . $centroNome . ') ' .
               'com pontuação de ' . $conflito['PONTO_INICIAL'] . ' a ' . ($conflito['PONTO_FINAL'] ?? '∞') . ' ' .
               'que conflita com a vigência informada.';
    }

    /**
     * SQL base para busca de produtos
     */
    private function getSqlBaseProdutos(): string
    {
        return "FROM FOCCO3I.TGRP_CLAS_ITE TGRP_CLAS_ITE,
                     FOCCO3I.TITENS_ENGENHARIA TITENS_ENGENHARIA,
                     FOCCO3I.TITENS_ENG_CONF TITENS_ENG_CONF,
                     FOCCO3I.TEMPRESAS TEMPRESAS,
                     FOCCO3I.TITENS TITENS,
                     FOCCO3I.TITENS_EMPR TITENS_EMPR,
                     FOCCO3I.TMASC_ITEM TMASC_ITEM,
                     FOCCO3I.TITENS_ESTOQUE TITENS_ESTOQUE,
                     FOCCO3I.TALMOXARIFADOS TALMOXARIFADOS,
                     FOCCO3I.TCAD_COD_BARRA TCAD_COD_BARRA,
                     FOCCO3I.TITENS_CONTABIL TITENS_CONTABIL,
                     FOCCO3I.TCLAS_FISC TCLAS_FISC
                WHERE TGRP_CLAS_ITE.ID = TITENS_ESTOQUE.GRP_CLAS_ID
                  AND TITENS_ENGENHARIA.ID = TITENS_ENG_CONF.ITEG_ID(+)
                  AND TEMPRESAS.ID = TITENS_EMPR.EMPR_ID
                  AND TITENS.ID = TITENS_EMPR.ITEM_ID
                  AND TITENS_EMPR.ID = TITENS_CONTABIL.ITEMPR_ID
                  AND TITENS_EMPR.ID = TITENS_ESTOQUE.ITEMPR_ID
                  AND TITENS_EMPR.ID = TMASC_ITEM.ITEMPR_ID(+)
                  AND TITENS_EMPR.ID = TITENS_ENGENHARIA.ITEMPR_ID(+)
                  AND TMASC_ITEM.ID = TCAD_COD_BARRA.TMASC_ITEM_ID(+)
                  AND TMASC_ITEM.ID = TITENS_ENG_CONF.TMASC_ITEM_ID(+)
                  AND TALMOXARIFADOS.ID = TITENS_ESTOQUE.ALMOX_ID
                  AND TCLAS_FISC.ID = TITENS_CONTABIL.CLAS_FISC_ID
                  AND TITENS_ENGENHARIA.TP_ITEM = 'F'";
    }

    /**
     * Formatar produtos para Select2
     */
    private function formatarProdutosSelect2(array $produtos): array
    {
        $results = [];
        foreach ($produtos as $produto) {
            $idMascaraDisplay = $produto['ID_MASCARA'] ?? '';
            $texto = $produto['COD_ITEM'] . ' - ' . $idMascaraDisplay . ' - ' . $produto['DESCRICAO'];
            if (!empty($produto['MASCARA'])) {
                $texto .= ' - ' . $produto['MASCARA'];
            }
            
            $results[] = [
                'id' => $produto['ITEM_ID'],
                'text' => $texto,
                'cod_item' => $produto['COD_ITEM'],
                'descricao' => $produto['DESCRICAO'],
                'mascara' => $produto['MASCARA'],
                'id_mascara' => $produto['ID_MASCARA'],
                'id_itempr' => $produto['ID_ITEMPR'],
                'id_empresa' => $produto['ID_EMPRESA']
            ];
        }
        return $results;
    }
}
