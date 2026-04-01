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
    // ==================== PONTUAÇÃO ====================

    /**
     * Listar pontuações cadastradas
     */
    public static function listarPontuacoes(?int $emprId, bool $incluirInativas = false): array
    {
        $pontuacoes = PontuacaoProduto::listarTodas($emprId);
        
        if (!$incluirInativas) {
            $pontuacoes = array_filter($pontuacoes, fn($p) => $p['ATIVO'] === 'S');
            $pontuacoes = array_values($pontuacoes);
        }
        
        return $pontuacoes;
    }

    /**
     * Buscar pontuação por ID
     */
    public static function buscarPontuacao(int $id): ?array
    {
        return PontuacaoProduto::buscarPorId($id);
    }

    /**
     * Salvar nova pontuação
     */
    public static function salvarPontuacao(array $dados): int
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
        
        return PontuacaoProduto::inserir($dadosModel);
    }

    /**
     * Atualizar pontuação existente
     */
    public static function atualizarPontuacao(int $id, array $dados): void
    {
        $dadosModel = [
            'pontos_up' => $dados['pontuacao_up'],
            'dt_vigencia_ini' => $dados['dt_vigencia_ini'],
            'dt_vigencia_fim' => $dados['dt_vigencia_fim'] ?? null,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        PontuacaoProduto::atualizar($id, $dadosModel);
    }

    /**
     * Excluir pontuação
     */
    public static function excluirPontuacao(int $id, ?int $usuarioId): void
    {
        PontuacaoProduto::excluir($id, $usuarioId);
    }

    /**
     * Importar pontuações em lote (OTIMIZADO)
     * Usa uma única conexão PDO, prepared statements reutilizáveis,
     * pré-carregamento de duplicatas em batch e transação única.
     */
    public static function importarPontuacoes(array $linhas, int $emprId, ?int $idUsuario): array
    {
        $pdo = Database::getInstance('focco');
        $importados = 0;
        $atualizados = 0;
        $erros = [];

        // Cache para evitar consultas repetidas ao banco
        $cacheItens = [];
        $cacheCentros = [];
        $cacheMascaras = [];

        // ===== FASE 1: Validação e resolução de lookups (sem transação) =====

        // Prepared statements reutilizáveis para lookups (1 prepare, N executes)
        $stmtItem = $pdo->prepare(
            "SELECT I.ID AS ITEM_ID, IE.ID AS ITEMPR_ID 
             FROM FOCCO3I.TITENS I
             LEFT JOIN FOCCO3I.TITENS_EMPR IE ON IE.ITEM_ID = I.ID AND IE.EMPR_ID = :empr_id
             WHERE I.COD_ITEM = :cod_item
             FETCH FIRST 1 ROW ONLY"
        );
        $stmtCentro = $pdo->prepare(
            "SELECT ID FROM FOCCO3I.TCENTROS_TRAB WHERE COD_CENTRO = :cod_centro AND EMPR_ID = :empr_id FETCH FIRST 1 ROW ONLY"
        );
        $stmtMascara = $pdo->prepare(
            "SELECT 1 FROM FOCCO3I.TMASC_ITEM WHERE ID = :id FETCH FIRST 1 ROW ONLY"
        );

        // Pré-validar todas as linhas e resolver lookups
        $linhasValidas = [];
        foreach ($linhas as $idx => $linha) {
            $numLinha = $idx + 2;
            
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
                $erros[] = "Linha {$numLinha}: PONTOS_UP vazio (Item: {$codItem})";
                continue;
            }
            if (empty($dtIni)) {
                $erros[] = "Linha {$numLinha}: DT_VIGENCIA_INI vazio (Item: {$codItem})";
                continue;
            }
            
            $pontosUp = str_replace(',', '.', $pontosUp);
            $dtIniFormatada = self::converterData($dtIni);
            $dtFimFormatada = !empty($dtFim) ? self::converterData($dtFim) : null;
            
            if (!$dtIniFormatada) {
                $erros[] = "Linha {$numLinha}: Data início inválida '{$dtIni}' (Item: {$codItem})";
                continue;
            }
            
            // Buscar ITEM_ID pelo COD_ITEM (com cache + prepared statement)
            if (!isset($cacheItens[$codItem])) {
                $stmtItem->execute([':empr_id' => $emprId, ':cod_item' => $codItem]);
                $cacheItens[$codItem] = $stmtItem->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
            $item = $cacheItens[$codItem];
            if (!$item) {
                $erros[] = "Linha {$numLinha}: Produto COD_ITEM={$codItem} não encontrado na tabela TITENS";
                continue;
            }
            
            // Buscar ID_CENTRO_TRAB pelo COD_CENTRO (com cache)
            $centroTrabId = null;
            if (!empty($codCentro)) {
                $cacheKeyCentro = $codCentro . '_' . $emprId;
                if (!isset($cacheCentros[$cacheKeyCentro])) {
                    $stmtCentro->execute([':cod_centro' => $codCentro, ':empr_id' => $emprId]);
                    $cacheCentros[$cacheKeyCentro] = $stmtCentro->fetch(\PDO::FETCH_ASSOC) ?: null;
                }
                $centro = $cacheCentros[$cacheKeyCentro];
                if ($centro) {
                    $centroTrabId = $centro['ID'];
                } else {
                    $erros[] = "Linha {$numLinha}: Centro de Trabalho '{$codCentro}' não encontrado (Item: {$codItem})";
                }
            }
            
            // Validar máscara (com cache)
            $mascaraId = !empty($idMascara) ? (int)$idMascara : null;
            if ($mascaraId !== null) {
                if (!isset($cacheMascaras[$mascaraId])) {
                    $stmtMascara->execute([':id' => $mascaraId]);
                    $cacheMascaras[$mascaraId] = $stmtMascara->fetch(\PDO::FETCH_ASSOC) ? true : false;
                }
                if (!$cacheMascaras[$mascaraId]) {
                    $erros[] = "Linha {$numLinha}: Máscara ID_MASCARA={$idMascara} não existe na tabela TMASC_ITEM (Item: {$codItem})";
                    continue;
                }
            }
            
            $linhasValidas[] = [
                'numLinha' => $numLinha,
                'codItem' => $codItem,
                'item' => $item,
                'mascaraId' => $mascaraId,
                'centroTrabId' => $centroTrabId,
                'pontosUp' => $pontosUp,
                'dtIniFormatada' => $dtIniFormatada,
                'dtFimFormatada' => $dtFimFormatada,
            ];
        }

        if (empty($linhasValidas)) {
            return [
                'importados' => 0,
                'atualizados' => 0,
                'erros' => $erros,
                'total' => count($linhas)
            ];
        }

        // ===== FASE 2: Pré-carregar TODAS as duplicatas da empresa em uma única query =====
        $stmtDuplicatas = $pdo->prepare(
            "SELECT PP.ID_PONTUACAO, PP.ITEM_ID, PP.ID_MASCARA, PP.ID_CENTRO_TRAB
             FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
             WHERE PP.ATIVO = 'S' AND PP.ID_EMPR = :empr_id"
        );
        $stmtDuplicatas->execute([':empr_id' => $emprId]);
        $todasDuplicatas = $stmtDuplicatas->fetchAll(\PDO::FETCH_ASSOC);

        // Indexar duplicatas por chave composta: item_id|mascara_id|centro_trab_id
        $mapaDuplicatas = [];
        foreach ($todasDuplicatas as $dup) {
            $chave = $dup['ITEM_ID'] . '|' . ($dup['ID_MASCARA'] ?? 'NULL') . '|' . ($dup['ID_CENTRO_TRAB'] ?? 'NULL');
            $mapaDuplicatas[$chave] = $dup['ID_PONTUACAO'];
        }
        unset($todasDuplicatas);

        // ===== FASE 3: Executar INSERTs e UPDATEs em transação única =====
        $stmtInsert = $pdo->prepare(
            "INSERT INTO FOCCO3I.TGAZIN_PONTUACAO_PRODUTO 
             (ID_PONTUACAO, ID_EMPR, ITEM_ID, ID_ITEMPR, ID_MASCARA, ID_CENTRO_TRAB, PONTOS_UP, DT_VIGENCIA_INI, DT_VIGENCIA_FIM, ATIVO, DT_CADASTRO, ID_USUARIO_CAD)
             VALUES (FOCCO3I.SEQ_GAZIN_PONTUACAO_PROD.NEXTVAL, :empr_id, :item_id, :itempr_id, :mascara_id, :centro_trab_id, :pontos_up, TO_DATE(:dt_ini, 'YYYY-MM-DD'), TO_DATE(:dt_fim, 'YYYY-MM-DD'), 'S', SYSDATE, :id_usuario)"
        );
        $stmtUpdate = $pdo->prepare(
            "UPDATE FOCCO3I.TGAZIN_PONTUACAO_PRODUTO 
             SET PONTOS_UP = :pontos_up, DT_VIGENCIA_INI = TO_DATE(:dt_ini, 'YYYY-MM-DD'), DT_VIGENCIA_FIM = TO_DATE(:dt_fim, 'YYYY-MM-DD'), DT_ALTERACAO = SYSDATE, ID_USUARIO_ALT = :id_usuario
             WHERE ID_PONTUACAO = :id_pontuacao"
        );

        $pdo->beginTransaction();
        try {
            foreach ($linhasValidas as $lv) {
                $chave = $lv['item']['ITEM_ID'] . '|' . ($lv['mascaraId'] ?? 'NULL') . '|' . ($lv['centroTrabId'] ?? 'NULL');
                
                if (isset($mapaDuplicatas[$chave]) && $mapaDuplicatas[$chave] !== 'new') {
                    // UPDATE - apenas se já existia no banco (não é 'new' do mesmo CSV)
                    $stmtUpdate->bindValue(':pontos_up', $lv['pontosUp']);
                    $stmtUpdate->bindValue(':dt_ini', $lv['dtIniFormatada']);
                    $stmtUpdate->bindValue(':dt_fim', $lv['dtFimFormatada']);
                    $stmtUpdate->bindValue(':id_usuario', $idUsuario, $idUsuario === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                    $stmtUpdate->bindValue(':id_pontuacao', $mapaDuplicatas[$chave], \PDO::PARAM_INT);
                    $stmtUpdate->execute();
                    $atualizados++;
                } elseif (!isset($mapaDuplicatas[$chave])) {
                    // INSERT - tratar NULLs explicitamente para evitar ORA-01722
                    $stmtInsert->bindValue(':empr_id', $emprId, \PDO::PARAM_INT);
                    $stmtInsert->bindValue(':item_id', $lv['item']['ITEM_ID'], \PDO::PARAM_INT);
                    $stmtInsert->bindValue(':itempr_id', $lv['item']['ITEMPR_ID'], $lv['item']['ITEMPR_ID'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                    $stmtInsert->bindValue(':mascara_id', $lv['mascaraId'], $lv['mascaraId'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                    $stmtInsert->bindValue(':centro_trab_id', $lv['centroTrabId'], $lv['centroTrabId'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                    $stmtInsert->bindValue(':pontos_up', $lv['pontosUp']);
                    $stmtInsert->bindValue(':dt_ini', $lv['dtIniFormatada']);
                    $stmtInsert->bindValue(':dt_fim', $lv['dtFimFormatada']);
                    $stmtInsert->bindValue(':id_usuario', $idUsuario, $idUsuario === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                    $stmtInsert->execute();
                    $importados++;
                    // Registrar no mapa para evitar duplicatas em linhas subsequentes do mesmo CSV
                    $mapaDuplicatas[$chave] = 'new';
                }
                // Se mapaDuplicatas[$chave] === 'new', ignora linha duplicada do mesmo CSV
            }
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            $erros[] = "Erro na transação de importação: " . $e->getMessage();
        }
        
        return [
            'importados' => $importados,
            'atualizados' => $atualizados,
            'erros' => $erros,
            'total' => count($linhas)
        ];
    }

    // ==================== FAIXAS ====================

    /**
     * Listar faixas de comissão
     */
    public static function listarFaixas(?int $emprId, ?int $centroTrabId = null, bool $incluirInativas = false): array
    {
        if ($incluirInativas) {
            return FaixaComissao::listarTodas($emprId);
        }
        return FaixaComissao::listarAtivas($emprId, $centroTrabId);
    }

    /**
     * Buscar faixa por ID
     */
    public static function buscarFaixa(int $id): ?array
    {
        return FaixaComissao::buscarPorId($id);
    }

    /**
     * Salvar nova faixa
     */
    public static function salvarFaixa(array $dados): int
    {
        $dados = self::normalizarDadosFaixa($dados);
        
        if (!in_array($dados['tipo'], [FaixaComissao::TIPO_PERCENTUAL, FaixaComissao::TIPO_QUANTIDADE])) {
            throw new \Exception('Tipo de faixa inválido');
        }
        
        $conflito = FaixaComissao::verificarConflito($dados);
        if ($conflito) {
            throw new \Exception(self::formatarMensagemConflito($conflito));
        }
        
        return FaixaComissao::inserir($dados);
    }

    /**
     * Atualizar faixa existente
     */
    public static function atualizarFaixa(int $id, array $dados): void
    {
        $dados = self::normalizarDadosFaixa($dados);
        
        $conflito = FaixaComissao::verificarConflito($dados, $id);
        if ($conflito) {
            throw new \Exception(self::formatarMensagemConflito($conflito));
        }
        
        FaixaComissao::atualizar($id, $dados);
    }

    /**
     * Inativar faixa
     */
    public static function inativarFaixa(int $id, ?int $usuarioId): void
    {
        FaixaComissao::inativar($id, $usuarioId);
    }

    // ==================== DADOS AUXILIARES ====================

    /**
     * Listar centros de trabalho
     */
    public static function listarCentrosTrabalho(?int $emprId): array
    {
        return CentroTrabalho::listarTodos($emprId);
    }

    /**
     * Listar funcionários ativos
     */
    public static function listarFuncionarios(?int $emprId, ?string $busca = null): array
    {
        return Funcionario::listarAtivos($emprId, $busca);
    }

    /**
     * Listar recursos/máquinas
     */
    public static function listarRecursos(?int $emprId, ?int $centroTrabId = null): array
    {
        return Recurso::listarAtivos($emprId, $centroTrabId);
    }

    /**
     * Listar empresas para select
     */
    public static function listarEmpresas(): array
    {
        return Empresa::listarParaSelect();
    }

    /**
     * Buscar empresa por ID
     */
    public static function buscarEmpresa(int $id): ?array
    {
        return Empresa::buscarPorId($id);
    }

    /**
     * Listar produtos (sem paginação, limite 200)
     */
    public static function listarProdutos(?int $emprId, ?string $termo): array
    {
        $pdo = Database::getInstance('focco');
        
        $sqlBase = self::getSqlBaseProdutos();
        $params = [];
        
        if ($emprId) {
            $sqlBase .= " AND TEMPRESAS.ID = :empr_id";
            $params['empr_id'] = $emprId;
        }
        
        if ($termo) {
            $sqlBase .= " AND (UPPER(TITENS.COD_ITEM) LIKE UPPER(:termo) OR UPPER(TITENS.DESC_TECNICA) LIKE UPPER(:termo2))";
            $params['termo'] = '%' . $termo . '%';
            $params['termo2'] = '%' . $termo . '%';
        }
        
        $sql = "SELECT DISTINCT
                    TEMPRESAS.COD_EMP,
                    TITENS.ID AS ID_ITEM,
                    TITENS.COD_ITEM,
                    TITENS.DESC_TECNICA AS DESCRICAO,
                    TMASC_ITEM.ID AS ID_MASCARA,
                    TMASC_ITEM.MASCARA,
                    TITENS_EMPR.ID AS ITEMPR_ID
                " . $sqlBase . "
                ORDER BY TITENS.DESC_TECNICA ASC
                FETCH FIRST 200 ROWS ONLY";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Buscar produtos com filtro e paginação (formato Select2)
     */
    public static function buscarProdutos(?int $emprId, ?string $termo, int $page = 1, int $limit = 30): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = Database::getInstance('focco');
        
        $sqlBase = self::getSqlBaseProdutos();
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
            'produtos' => self::formatarProdutosSelect2($produtos),
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ];
    }

    // ==================== VÍNCULOS ====================

    /**
     * Listar vínculos centro/recurso/funcionário
     */
    public static function listarVinculos(array $filtros): array
    {
        return Vinculo::listar($filtros) ?: [];
    }

    /**
     * Listar centros que possuem vínculo
     */
    public static function listarCentrosComVinculo(?int $emprId): array
    {
        return Vinculo::listarCentrosComVinculo($emprId);
    }

    /**
     * Listar recursos que possuem vínculo
     */
    public static function listarRecursosComVinculo(?int $emprId, ?int $centroTrabId = null): array
    {
        return Vinculo::listarRecursosComVinculo($emprId, $centroTrabId);
    }

    /**
     * Listar funcionários que possuem vínculo
     */
    public static function listarFuncionariosComVinculo(?int $emprId, ?string $busca = null): array
    {
        return Vinculo::listarFuncionariosComVinculo($emprId, $busca);
    }

    /**
     * Salvar novo vínculo
     */
    public static function salvarVinculo(array $dados): bool
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
    public static function atualizarVinculo(int $id, array $dados): bool
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
    public static function alterarStatusVinculo(int $id, string $ativo): bool
    {
        $ativo = $ativo === 'S' ? 'S' : 'N';
        return Vinculo::alterarStatus($id, $ativo);
    }

    /**
     * Excluir vínculo
     */
    public static function excluirVinculo(int $id): bool
    {
        return Vinculo::excluir($id);
    }

    // ==================== FALTAS ====================

    /**
     * Listar faltas de funcionários
     */
    public static function listarFaltas(array $filtros): array
    {
        return FaltaFuncionario::listar($filtros);
    }

    /**
     * Registrar nova falta
     */
    public static function salvarFalta(array $dados): int
    {
        $dadosModel = [
            'id_empr' => $dados['id_empr'] ?? null,
            'id_funcionario' => $dados['id_funcionario'],
            'dt_falta' => $dados['dt_falta'],
            'motivo' => $dados['motivo'] ?? null,
            'tipo_falta' => $dados['tipo_falta'] ?? 'I',
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        return FaltaFuncionario::registrar($dadosModel);
    }

    /**
     * Atualizar falta
     */
    public static function atualizarFalta(int $id, array $dados): void
    {
        $dadosModel = [
            'dt_falta' => $dados['dt_falta'],
            'motivo' => $dados['motivo'] ?? null,
            'tipo_falta' => $dados['tipo_falta'] ?? 'I',
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        FaltaFuncionario::atualizar($id, $dadosModel);
    }

    /**
     * Excluir falta
     */
    public static function excluirFalta(int $id, ?int $usuarioId): void
    {
        FaltaFuncionario::excluir($id, $usuarioId);
    }

    // ==================== RETRABALHO ====================

    /**
     * Listar retrabalhos
     */
    public static function listarRetrabalhos(array $filtros): array
    {
        return Retrabalho::listar($filtros);
    }

    /**
     * Registrar retrabalho
     */
    public static function salvarRetrabalho(array $dados): int
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
        
        return Retrabalho::inserir($dadosModel);
    }

    /**
     * Atualizar retrabalho
     */
    public static function atualizarRetrabalho(int $id, array $dados): void
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
        
        Retrabalho::atualizar($id, $dadosModel);
    }

    /**
     * Excluir retrabalho
     */
    public static function excluirRetrabalho(int $id, ?int $usuarioId): void
    {
        Retrabalho::excluir($id, $usuarioId);
    }

    // ==================== VÍNCULO DE APONTAMENTOS ====================

    /**
     * Listar apontamentos sem recurso
     */
    public static function listarApontamentosSemRecurso(array $filtros): array
    {
        return VinculoApontamento::listarApontamentosSemRecurso($filtros);
    }

    /**
     * Listar vínculos de apontamentos
     */
    public static function listarVinculosApontamento(array $filtros): array
    {
        return VinculoApontamento::listarVinculos($filtros);
    }

    /**
     * Vincular recurso ao apontamento
     */
    public static function vincularRecurso(int $apontamentoId, int $recursoId): bool
    {
        return VinculoApontamento::vincularRecurso($apontamentoId, $recursoId);
    }

    /**
     * Vincular apontamento a funcionário
     */
    public static function vincularApontamento(array $dados): int
    {
        $dadosModel = [
            'id_empr' => $dados['id_empr'] ?? null,
            'id_apontamento' => $dados['id_apontamento'],
            'id_funcionario' => $dados['id_funcionario'],
            'id_recurso' => $dados['id_recurso'] ?? null,
            'observacao' => $dados['observacao'] ?? null,
            'id_usuario' => $dados['id_usuario'] ?? null
        ];
        
        return VinculoApontamento::vincular($dadosModel);
    }

    /**
     * Vincular apontamentos em lote
     */
    public static function vincularApontamentosLote(array $dados): array
    {
        return VinculoApontamento::vincularEmLote(
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
    public static function atualizarVinculoApontamento(int $id, array $dados): void
    {
        VinculoApontamento::atualizar($id, [
            'id_funcionario' => $dados['id_funcionario'],
            'id_recurso' => $dados['id_recurso'] ?? null,
            'observacao' => $dados['observacao'] ?? null,
            'id_usuario' => $dados['id_usuario'] ?? null
        ]);
    }

    /**
     * Excluir vínculo de apontamento
     */
    public static function excluirVinculoApontamento(int $id, ?int $usuarioId): void
    {
        VinculoApontamento::excluir($id, $usuarioId);
    }

    // ==================== REGRAS ESPECÍFICAS ====================

    /**
     * Listar regras específicas
     */
    public static function listarRegras(array $filtros): array
    {
        return RegraFuncionario::listar($filtros) ?: [];
    }

    /**
     * Buscar regra por ID
     */
    public static function buscarRegra(int $id): ?array
    {
        return RegraFuncionario::buscarPorId($id);
    }

    /**
     * Salvar nova regra
     */
    public static function salvarRegra(array $dados): int
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
        
        return RegraFuncionario::inserir($dadosModel);
    }

    /**
     * Atualizar regra existente
     */
    public static function atualizarRegra(int $id, array $dados): void
    {
        $regraAtual = RegraFuncionario::buscarPorId($id);
        
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
        
        RegraFuncionario::atualizar($id, $dadosModel);
    }

    /**
     * Inativar regra
     */
    public static function inativarRegra(int $id, ?int $usuarioId): void
    {
        RegraFuncionario::inativar($id, $usuarioId);
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Converter data DD/MM/AAAA ou YYYY-MM-DD para YYYY-MM-DD
     */
    private static function converterData(?string $data): ?string
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
    private static function buscarItemPorCodigo(\PDO $pdo, string $codItem, int $emprId): ?array
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
     * Buscar centro pelo código e empresa
     */
    private static function buscarCentroPorCodigo(\PDO $pdo, string $codCentro, int $emprId): ?array
    {
        $sql = "SELECT ID FROM FOCCO3I.TCENTROS_TRAB WHERE COD_CENTRO = :cod_centro AND EMPR_ID = :empr_id FETCH FIRST 1 ROW ONLY";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cod_centro', $codCentro, \PDO::PARAM_STR);
        $stmt->bindValue(':empr_id', $emprId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Verificar se uma máscara existe na tabela TMASC_ITEM
     */
    private static function verificarMascaraExiste(\PDO $pdo, int $mascaraId): bool
    {
        $sql = "SELECT 1 FROM FOCCO3I.TMASC_ITEM WHERE ID = :id FETCH FIRST 1 ROW ONLY";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $mascaraId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ? true : false;
    }

    /**
     * Normalizar dados da faixa
     */
    private static function normalizarDadosFaixa(array $dados): array
    {
        $dados['tipo'] = $dados['tipo'] ?? $dados['tipo_faixa'] ?? null;
        $dados['ponto_inicial'] = $dados['ponto_inicial'] ?? $dados['pontoInicial'] ?? null;
        $dados['ponto_final'] = $dados['ponto_final'] ?? $dados['pontoFinal'] ?? null;
        $dados['valor_comissao'] = $dados['valor_comissao'] ?? $dados['valorComissao'] ?? null;
        $dados['centro_trab_id'] = $dados['centro_trab_id'] ?? $dados['centroTrabId'] ?? null;
        $dados['dt_vigencia_ini'] = $dados['dt_vigencia_ini'] ?? $dados['dtVigenciaIni'] ?? null;
        $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?? $dados['dtVigenciaFim'] ?? null;
        $dados['tipo_funcionario'] = $dados['tipo_funcionario'] ?? $dados['tipoFuncionario'] ?? 'T';
        
        $dados['ponto_final'] = $dados['ponto_final'] ?: null;
        $dados['centro_trab_id'] = $dados['centro_trab_id'] ?: null;
        $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?: null;
        
        return $dados;
    }

    /**
     * Formatar mensagem de conflito de faixa
     */
    private static function formatarMensagemConflito(array $conflito): string
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
    private static function getSqlBaseProdutos(): string
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
    private static function formatarProdutosSelect2(array $produtos): array
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
