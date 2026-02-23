<?php

namespace src\controllers\Comissao;

use \core\Controller as ctrl;
use core\Database;
use src\models\Comissao\PontuacaoProduto;
use src\models\Comissao\FaixaComissao;
use src\models\Comissao\CentroTrabalho;
use src\models\Comissao\Recurso;
use src\models\Comissao\Funcionario;
use src\models\Comissao\Vinculo;
use src\models\Comissao\Empresa;

/**
 * Controller de Cadastros do Sistema de Comissao
 * Gerencia cadastro de pontuacoes e faixas de comissao
 */
class ComissaoCadastroController extends ctrl
{
    // ==================== PÃGINAS ====================

    /**
     * PÃ¡gina principal de cadastros
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Cadastros - Sistema de ComissÃ£o',
            'pagina' => 'Cadastros'
        ];

        $this->render('comissao/cadastro', $dados);
    }

    /**
     * PÃ¡gina de cadastro de pontuaÃ§Ã£o de produtos
     */
    public function pontuacaoIndex()
    {
        $dados = [
            'titulo' => 'Cadastro de PontuaÃ§Ã£o UP',
            'pagina' => 'PontuaÃ§Ã£o UP'
        ];

        $this->render('comissao/pontuacao', $dados);
    }

    /**
     * PÃ¡gina de cadastro de faixas de comissÃ£o
     */
    public function faixasIndex()
    {
        $dados = [
            'titulo' => 'Cadastro de Faixas de ComissÃ£o',
            'pagina' => 'Faixas de ComissÃ£o'
        ];

        $this->render('comissao/faixas', $dados);
    }

    /**
     * PÃ¡gina de vÃ­nculo entre FuncionÃ¡rio, Recurso e Centro de Trabalho
     */
    public function vinculoIndex()
    {
        $dados = [
            'titulo' => 'VÃ­nculo FuncionÃ¡rio, Recurso e Centro de Trabalho',
            'pagina' => 'VÃ­nculo'
        ];
        $this->render('comissao/vinculo', $dados);
    }

    // ==================== API PONTUAÃ‡ÃƒO ====================

    /**
     * Listar pontuaÃ§Ãµes (API)
     * Se id for passado, busca apenas esse registro
     * Usa empresa da sessÃ£o se nÃ£o passada via GET
     */
    public function listarPontuacoes()
    {
        try {
            $model = new PontuacaoProduto();
            
            // Se id foi passado, busca apenas esse registro
            $id = $_GET['id'] ?? null;
            if ($id) {
                $pontuacao = $model->buscarPorId($id);
                if (!$pontuacao) {
                    self::response([
                        'success' => false,
                        'error' => 'PontuaÃ§Ã£o nÃ£o encontrada'
                    ], 404);
                    return;
                }
                self::response([
                    'success' => true,
                    'data' => $pontuacao
                ], 200);
                return;
            }
            
            // Usar empresa da sessÃ£o ou GET (se especificada)
            $emprId = $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            
            // Verificar se deve incluir inativos
            $incluirInativas = isset($_GET['incluirInativas']) && $_GET['incluirInativas'] === 'true';
            
            $pontuacoes = $model->listarTodas($emprId);
            
            // Filtrar apenas ativos se nÃ£o incluir inativos
            if (!$incluirInativas) {
                $pontuacoes = array_filter($pontuacoes, fn($p) => $p['ATIVO'] === 'S');
                $pontuacoes = array_values($pontuacoes); // Reindexar
            }

            self::response([
                'success' => true,
                'data' => $pontuacoes,
                'total' => count($pontuacoes),
                'empresa' => $_SESSION['empresa'] ?? null
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar pontuaÃ§Ã£o por ID (API)
     */
    public function buscarPontuacao()
    {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $model = new PontuacaoProduto();
            $pontuacao = $model->buscarPorId($id);

            if (!$pontuacao) {
                throw new \Exception('PontuaÃ§Ã£o nÃ£o encontrada');
            }

            self::response([
                'success' => true,
                'data' => $pontuacao
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Salvar pontuaÃ§Ã£o (API)
     * Usa empresa da sessÃ£o se nÃ£o passada nos dados
     */
    public function salvarPontuacao()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            // LOG DEBUG - Remover depois
            error_log('=== SALVANDO PONTUACAO ===');
            error_log('Dados recebidos: ' . print_r($dados, true));
            
            // Usar empresa da sessÃ£o se nÃ£o informada
            if (empty($dados['empr_id'])) {
                $dados['empr_id'] = $_SESSION['empresa']['id'] ?? null;
            }
            
            self::verificarCamposVazios($dados, [
                'pontuacao_up',
                'dt_vigencia_ini',
                'empr_id'
            ]);
            
            // Preparar dados para o model
            // item_id = TITENS.ID (FK para tabela TITENS)
            // itempr_id = TITENS_EMPR.ID (item por empresa)
            // mascara_id = TMASC_ITEM.ID
            $itemId = !empty($dados['item_id']) ? $dados['item_id'] : null;
            $itemprId = !empty($dados['itempr_id']) ? $dados['itempr_id'] : null;
            $mascaraId = !empty($dados['mascara_id']) ? $dados['mascara_id'] : null;
            $centroTrabId = !empty($dados['centro_trab_id']) ? $dados['centro_trab_id'] : null;
            
            $dadosModel = [
                'empr_id' => $dados['empr_id'],
                'item_id' => $itemId,      // TITENS.ID vai para ITEM_ID (FK)
                'itempr_id' => $itemprId,  // TITENS_EMPR.ID vai para ID_ITEMPR
                'mascara_id' => $mascaraId,
                'centro_trab_id' => $centroTrabId,
                'pontos_up' => $dados['pontuacao_up'] ?? $dados['pontos_up'],
                'dt_vigencia_ini' => $dados['dt_vigencia_ini'],
                'dt_vigencia_fim' => !empty($dados['dt_vigencia_fim']) ? $dados['dt_vigencia_fim'] : null,
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            // Validar que temos item_id
            if (empty($dadosModel['item_id'])) {
                throw new \Exception('Produto Ã© obrigatÃ³rio');
            }
            
            $model = new PontuacaoProduto();
            $id = $model->inserir($dadosModel);

            self::response([
                'success' => true,
                'message' => 'PontuaÃ§Ã£o cadastrada com sucesso',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar pontuaÃ§Ã£o (API)
     */
    public function atualizarPontuacao()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            $id = $dados['id'] ?? null;
            if (!$id) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            self::verificarCamposVazios($dados, [
                'pontuacao_up',
                'dt_vigencia_ini'
            ]);
            
            // Mapear campos do JS para o model
            $dadosModel = [
                'pontos_up' => $dados['pontuacao_up'],
                'dt_vigencia_ini' => $dados['dt_vigencia_ini'],
                'dt_vigencia_fim' => $dados['dt_vigencia_fim'] ?? null,
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            $model = new PontuacaoProduto();
            $model->atualizar($id, $dadosModel);

            self::response([
                'success' => true,
                'message' => 'PontuaÃ§Ã£o atualizada com sucesso'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Excluir pontuaÃ§Ã£o (API)
     */
    public function excluirPontuacao()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            $id = $dados['id'] ?? null;
            if (!$id) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $usuId = $_SESSION['user']['id'] ?? null;
            
            $model = new PontuacaoProduto();
            $model->excluir($id, $usuId);

            self::response([
                'success' => true,
                'message' => 'PontuaÃ§Ã£o excluÃ­da com sucesso'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Importar pontuaÃ§Ãµes de arquivo CSV/Excel (API)
     */
    public function importarPontuacoes()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (empty($dados['linhas']) || !is_array($dados['linhas'])) {
                throw new \Exception('Nenhum dado para importar');
            }
            
            $emprId = $_SESSION['empresa']['id'] ?? null;
            $idUsuario = $_SESSION['user']['id'] ?? null;
            
            if (!$emprId) {
                throw new \Exception('Empresa nÃ£o identificada na sessÃ£o');
            }
            
            $model = new PontuacaoProduto();
            $pdo = Database::getInstance('focco');
            
            $importados = 0;
            $erros = [];
            
            foreach ($dados['linhas'] as $idx => $linha) {
                $numLinha = $idx + 2; // +2 porque idx comeÃ§a em 0 e linha 1 Ã© cabeÃ§alho
                
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
                    
                    // Converter pontos (trocar vÃ­rgula por ponto)
                    $pontosUp = str_replace(',', '.', $pontosUp);
                    
                    // Converter datas DD/MM/AAAA para YYYY-MM-DD
                    $dtIniFormatada = self::converterData($dtIni);
                    $dtFimFormatada = !empty($dtFim) ? self::converterData($dtFim) : null;
                    
                    if (!$dtIniFormatada) {
                        $erros[] = "Linha {$numLinha}: Data inÃ­cio invÃ¡lida ({$dtIni})";
                        continue;
                    }
                    
                    // Buscar ITEM_ID pelo COD_ITEM
                    $sqlItem = "SELECT I.ID AS ITEM_ID, IE.ID AS ITEMPR_ID 
                                FROM FOCCO3I.TITENS I
                                LEFT JOIN FOCCO3I.TITENS_EMPR IE ON IE.ITEM_ID = I.ID AND IE.EMPR_ID = :empr_id
                                WHERE I.COD_ITEM = :cod_item
                                FETCH FIRST 1 ROW ONLY";
                    $stmtItem = $pdo->prepare($sqlItem);
                    $stmtItem->bindValue(':empr_id', $emprId, \PDO::PARAM_INT);
                    $stmtItem->bindValue(':cod_item', $codItem, \PDO::PARAM_STR);
                    $stmtItem->execute();
                    $item = $stmtItem->fetch(\PDO::FETCH_ASSOC);
                    
                    if (!$item) {
                        $erros[] = "Linha {$numLinha}: Produto '{$codItem}' nÃ£o encontrado";
                        continue;
                    }
                    
                    // Buscar ID_CENTRO_TRAB pelo COD_CENTRO
                    $centroTrabId = null;
                    if (!empty($codCentro)) {
                        $sqlCentro = "SELECT ID FROM FOCCO3I.TCENTROS_TRAB WHERE COD_CENTRO = :cod_centro FETCH FIRST 1 ROW ONLY";
                        $stmtCentro = $pdo->prepare($sqlCentro);
                        $stmtCentro->bindValue(':cod_centro', $codCentro, \PDO::PARAM_STR);
                        $stmtCentro->execute();
                        $centro = $stmtCentro->fetch(\PDO::FETCH_ASSOC);
                        if ($centro) {
                            $centroTrabId = $centro['ID'];
                        } else {
                            $erros[] = "Linha {$numLinha}: Centro '{$codCentro}' nÃ£o encontrado (ignorado)";
                        }
                    }
                    
                    // Validar mÃ¡scara se informada
                    $mascaraId = null;
                    if (!empty($idMascara)) {
                        $mascaraId = (int)$idMascara;
                    }
                    
                    // Inserir
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
                    
                    $model->inserir($dadosModel);
                    $importados++;
                    
                } catch (\Exception $e) {
                    $erros[] = "Linha {$numLinha}: " . $e->getMessage();
                }
            }
            
            self::response([
                'success' => true,
                'importados' => $importados,
                'erros' => $erros,
                'total' => count($dados['linhas']),
                'message' => "ImportaÃ§Ã£o concluÃ­da: {$importados} registros importados"
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Converte data DD/MM/AAAA ou YYYY-MM-DD para YYYY-MM-DD
     */
    private static function converterData($data)
    {
        if (empty($data)) return null;
        
        // JÃ¡ estÃ¡ no formato YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $data;
        }
        
        // Formato DD/MM/AAAA
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        
        return null;
    }

    // ==================== API FAIXAS ====================

    /**
     * Listar faixas de comissÃ£o (API)
     */
    public function listarFaixas()
    {
        try {
            // Se veio id, busca faixa especÃ­fica
            $id = $_GET['id'] ?? null;
            if ($id) {
                $model = new FaixaComissao();
                $faixa = $model->buscarPorId($id);
                
                if (!$faixa) {
                    throw new \Exception('Faixa nÃ£o encontrada');
                }
                
                self::response([
                    'success' => true,
                    'data' => $faixa
                ], 200);
                return;
            }
            
            $emprId = $_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);
            $centroTrabId = $_GET['centro_trab_id'] ?? null;
            $incluirInativas = $_GET['incluir_inativas'] ?? false;
            
            $model = new FaixaComissao();
            
            if ($incluirInativas) {
                $faixas = $model->listarTodas($emprId);
            } else {
                $faixas = $model->listarAtivas($emprId, $centroTrabId);
            }

            self::response([
                'success' => true,
                'data' => $faixas,
                'total' => count($faixas)
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar faixa por ID (API)
     */
    public function buscarFaixa()
    {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $model = new FaixaComissao();
            $faixa = $model->buscarPorId($id);

            if (!$faixa) {
                throw new \Exception('Faixa nÃ£o encontrada');
            }

            self::response([
                'success' => true,
                'data' => $faixa
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Salvar faixa de comissÃ£o (API)
     */
    public function salvarFaixa()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            // Mapear campos do JS para os nomes esperados pelo model
            // O model usa 'tipo', nÃ£o 'tipo_faixa'
            $dados['tipo'] = $dados['tipo'] ?? $dados['tipo_faixa'] ?? null;
            $dados['ponto_inicial'] = $dados['ponto_inicial'] ?? $dados['pontoInicial'] ?? null;
            $dados['ponto_final'] = $dados['ponto_final'] ?? $dados['pontoFinal'] ?? null;
            $dados['valor_comissao'] = $dados['valor_comissao'] ?? $dados['valorComissao'] ?? null;
            $dados['centro_trab_id'] = $dados['centro_trab_id'] ?? $dados['centroTrabId'] ?? null;
            $dados['dt_vigencia_ini'] = $dados['dt_vigencia_ini'] ?? $dados['dtVigenciaIni'] ?? null;
            $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?? $dados['dtVigenciaFim'] ?? null;
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            self::verificarCamposVazios($dados, [
                'descricao',
                'tipo',
                'ponto_inicial',
                'valor_comissao',
                'dt_vigencia_ini'
            ]);
            
            // Validar tipo de faixa
            if (!in_array($dados['tipo'], [FaixaComissao::TIPO_PERCENTUAL, FaixaComissao::TIPO_QUANTIDADE])) {
                throw new \Exception('Tipo de faixa invÃ¡lido');
            }
            
            $dados['ponto_final'] = $dados['ponto_final'] ?: null;
            $dados['centro_trab_id'] = $dados['centro_trab_id'] ?: null;
            $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?: null;
            
            $model = new FaixaComissao();
            
            // Verificar se jÃ¡ existe faixa conflitante
            $conflito = $model->verificarConflito($dados);
            if ($conflito) {
                $centroNome = $conflito['DESC_CENTRO'] ? $conflito['COD_CENTRO'] . ' - ' . $conflito['DESC_CENTRO'] : 'Todos';
                throw new \Exception(
                    'JÃ¡ existe uma faixa ativa para este centro de trabalho (' . $centroNome . ') ' .
                    'com pontuaÃ§Ã£o de ' . $conflito['PONTO_INICIAL'] . ' a ' . ($conflito['PONTO_FINAL'] ?? 'âˆž') . ' ' .
                    'que conflita com a vigÃªncia informada.'
                );
            }
            
            $id = $model->inserir($dados);

            self::response([
                'success' => true,
                'message' => 'Faixa cadastrada com sucesso',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar faixa de comissÃ£o (API)
     */
    public function atualizarFaixa()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            $id = $dados['id'] ?? null;
            if (!$id) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            // Mapear campos do JS para os nomes esperados pelo model
            $dados['tipo'] = $dados['tipo'] ?? $dados['tipo_faixa'] ?? null;
            $dados['ponto_inicial'] = $dados['ponto_inicial'] ?? $dados['pontoInicial'] ?? null;
            $dados['ponto_final'] = $dados['ponto_final'] ?? $dados['pontoFinal'] ?? null;
            $dados['valor_comissao'] = $dados['valor_comissao'] ?? $dados['valorComissao'] ?? null;
            $dados['centro_trab_id'] = $dados['centro_trab_id'] ?? $dados['centroTrabId'] ?? null;
            $dados['dt_vigencia_ini'] = $dados['dt_vigencia_ini'] ?? $dados['dtVigenciaIni'] ?? null;
            $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?? $dados['dtVigenciaFim'] ?? null;
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            self::verificarCamposVazios($dados, [
                'descricao',
                'tipo',
                'ponto_inicial',
                'valor_comissao',
                'dt_vigencia_ini'
            ]);
            
            $dados['ponto_final'] = $dados['ponto_final'] ?: null;
            $dados['centro_trab_id'] = $dados['centro_trab_id'] ?: null;
            $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?: null;
            
            $model = new FaixaComissao();
            
            // Verificar se jÃ¡ existe faixa conflitante (excluindo a faixa atual)
            $conflito = $model->verificarConflito($dados, $id);
            if ($conflito) {
                $centroNome = $conflito['DESC_CENTRO'] ? $conflito['COD_CENTRO'] . ' - ' . $conflito['DESC_CENTRO'] : 'Todos';
                throw new \Exception(
                    'JÃ¡ existe uma faixa ativa para este centro de trabalho (' . $centroNome . ') ' .
                    'com pontuaÃ§Ã£o de ' . $conflito['PONTO_INICIAL'] . ' a ' . ($conflito['PONTO_FINAL'] ?? 'âˆž') . ' ' .
                    'que conflita com a vigÃªncia informada.'
                );
            }
            
            $model->atualizar($id, $dados);

            self::response([
                'success' => true,
                'message' => 'Faixa atualizada com sucesso'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Inativar faixa de comissÃ£o (API)
     */
    public function inativarFaixa()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            $id = $dados['id'] ?? null;
            if (!$id) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $usuId = $_SESSION['user']['id'] ?? null;
            
            $model = new FaixaComissao();
            $model->inativar($id, $usuId);

            self::response([
                'success' => true,
                'message' => 'Faixa inativada com sucesso'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // ==================== AUXILIARES ====================

    /**
     * Listar centros de trabalho para select
     * Usa empresa da sessÃ£o se nÃ£o passada via GET
     */
    public function getCentrosTrabalho()
    {
        try {
            // Prioriza parÃ¢metro GET, senÃ£o usa empresa da sessÃ£o
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            
            $model = new CentroTrabalho();
            $centros = $model->listarTodos($emprId);

            self::response([
                'success' => true,
                'data' => $centros,
                'empresa_id' => $emprId
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar funcionÃ¡rios ativos para select
     * Usa empresa da sessÃ£o se nÃ£o passada via GET
     */
    public function getFuncionarios()
    {
        try {
            // Prioriza parÃ¢metro GET, senÃ£o usa empresa da sessÃ£o
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            $busca = $_GET['busca'] ?? null;
            $model = new Funcionario();
            $funcionarios = $model->listarAtivos($emprId, $busca);
            self::response([
                'success' => true,
                'data' => $funcionarios,
                'empresa_id' => $emprId
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar recursos/mÃ¡quinas para select
     * Usa empresa da sessÃ£o se nÃ£o passada via GET
     */
    public function getRecursos()
    {
        try {
            // Prioriza parÃ¢metro GET, senÃ£o usa empresa da sessÃ£o
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            $centroTrabId = $_GET['centroTrabId'] ?? $_GET['centro_trab_id'] ?? $_GET['centro_id'] ?? null;
            
            $model = new Recurso();
            $recursos = $model->listarAtivos($emprId, $centroTrabId);

            self::response([
                'success' => true,
                'data' => $recursos
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar produtos para select
     * Usa empresa da sessÃ£o se nÃ£o passada via GET
     */
    public function getProdutos()
    {
        try {
            // Prioriza parÃ¢metro GET, senÃ£o usa empresa da sessÃ£o
            $emprId = $_GET['empr_id'] ?? $_GET['emprId'] ?? $_SESSION['empresa']['id'] ?? null;
            $termo = $_GET['termo'] ?? null;
            
            $pdo = \core\Database::getInstance('focco');
            
            // Query completa com dados de itens fabricados
            $sql = "SELECT DISTINCT
                        TEMPRESAS.COD_EMP,
                        TITENS.ID AS ID_ITEM,
                        TITENS.COD_ITEM,
                        TITENS.DESC_TECNICA AS DESCRICAO,
                        TMASC_ITEM.ID AS ID_MASCARA,
                        TMASC_ITEM.MASCARA,
                        TITENS_EMPR.ID AS ITEMPR_ID
                    FROM FOCCO3I.TGRP_CLAS_ITE TGRP_CLAS_ITE,
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
            
            $params = [];
            
            if ($emprId) {
                $sql .= " AND TEMPRESAS.ID = :empr_id";
                $params['empr_id'] = $emprId;
            }
            
            if ($termo) {
                $sql .= " AND (UPPER(TITENS.COD_ITEM) LIKE UPPER(:termo) OR UPPER(TITENS.DESC_TECNICA) LIKE UPPER(:termo2))";
                $params['termo'] = '%' . $termo . '%';
                $params['termo2'] = '%' . $termo . '%';
            }
            
            $sql .= " ORDER BY TITENS.DESC_TECNICA ASC";
            $sql .= " FETCH FIRST 200 ROWS ONLY";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            self::response([
                'success' => true,
                'data' => $produtos,
                'total' => count($produtos)
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar produtos para Select2 AJAX
     * Retorna no formato esperado pelo Select2 com paginaÃ§Ã£o
     */
    public function buscarProdutos()
    {
        try {
            $emprId = $_GET['empr_id'] ?? $_GET['emprId'] ?? $_SESSION['empresa']['id'] ?? null;
            $termo = $_GET['term'] ?? $_GET['q'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 30;
            $offset = ($page - 1) * $limit;
            
            $pdo = \core\Database::getInstance('focco');
            
            // Query base
            $sqlBase = "FROM FOCCO3I.TGRP_CLAS_ITE TGRP_CLAS_ITE,
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
            // ITEM_ID (FK para TITENS) = TITENS.ID
            // ID_ITEMPR = TITENS_EMPR.ID
            // ID_MASCARA = TMASC_ITEM.ID
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
            
            // Formatar para Select2
            $results = [];
            foreach ($produtos as $produto) {
                // Formato: COD_ITEM - ID_MASCARA - DESCRIÃ‡ÃƒO - MASCARA
                $idMascaraDisplay = $produto['ID_MASCARA'] ?? '';
                $texto = $produto['COD_ITEM'] . ' - ' . $idMascaraDisplay . ' - ' . $produto['DESCRICAO'];
                if (!empty($produto['MASCARA'])) {
                    $texto .= ' - ' . $produto['MASCARA'];
                }
                
                // ITEM_ID = TITENS.ID (FK para TITENS)
                // ID_ITEMPR = TITENS_EMPR.ID
                // ID_MASCARA = TMASC_ITEM.ID
                $results[] = [
                    'id' => $produto['ITEM_ID'],            // TITENS.ID para ITEM_ID
                    'text' => $texto,
                    'cod_item' => $produto['COD_ITEM'],
                    'descricao' => $produto['DESCRICAO'],
                    'mascara' => $produto['MASCARA'],
                    'id_mascara' => $produto['ID_MASCARA'], // TMASC_ITEM.ID
                    'id_itempr' => $produto['ID_ITEMPR'],   // TITENS_EMPR.ID
                    'id_empresa' => $produto['ID_EMPRESA']  // TEMPRESAS.ID
                ];
            }
            
            // Resposta no formato Select2
            self::response([
                'results' => $results,
                'pagination' => [
                    'more' => ($offset + $limit) < $total
                ],
                'total' => $total
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'results' => [],
                'pagination' => ['more' => false],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== API EMPRESAS ====================

    /**
     * Listar empresas para select
     */
    public function getEmpresas()
    {
        try {
            $model = new Empresa();
            $empresas = $model->listarParaSelect();

            self::response([
                'success' => true,
                'data' => $empresas,
                'total' => count($empresas)
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selecionar empresa e salvar na sessÃ£o
     */
    public function selecionarEmpresa()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (empty($dados['empr_id'])) {
                throw new \Exception('ID da empresa Ã© obrigatÃ³rio');
            }
            
            $model = new Empresa();
            $empresa = $model->buscarPorId($dados['empr_id']);
            
            if (!$empresa) {
                throw new \Exception('Empresa nÃ£o encontrada');
            }
            
            // Salvar empresa na sessÃ£o
            $_SESSION['empresa'] = [
                'id' => $empresa['ID'],
                'codigo' => $empresa['CODIGO'],
                'razao_social' => $empresa['RAZAO_SOCIAL'],
                'nome_fantasia' => $empresa['NOME_FANTASIA']
            ];

            self::response([
                'success' => true,
                'message' => 'Empresa selecionada com sucesso',
                'empresa' => $_SESSION['empresa']
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obter empresa selecionada na sessÃ£o
     */
    public function getEmpresaSelecionada()
    {
        try {
            $empresa = $_SESSION['empresa'] ?? null;
            
            self::response([
                'success' => true,
                'selecionada' => !empty($empresa),
                'empresa' => $empresa
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar vÃ­nculos centro/recurso/funcionÃ¡rio (API)
     */
    public function listarVinculos()
    {
        try {
            error_log('=== LISTAR VINCULOS ===');
            error_log('SESSION empresa: ' . print_r($_SESSION['empresa'] ?? 'NULL', true));
            
            // Obter empresa da sessão para filtrar
            $emprId = $_SESSION['empresa']['id'] ?? null;
            
            $filtros = [
                'id_empr' => $emprId,
                'id_funcionario' => $_GET['funcionario_id'] ?? null,
                'id_recurso' => $_GET['recurso_id'] ?? null,
                'id_centro_trab' => $_GET['centro_id'] ?? null,
            ];
            $vinculos = \src\models\Comissao\Vinculo::listar($filtros) ?: [];
            error_log('Vinculos encontrados: ' . count($vinculos));
            self::response(['success' => true, 'data' => $vinculos], 200);
        } catch (\Throwable $e) {
            error_log('ERRO listarVinculos: ' . $e->getMessage());
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar centros de trabalho que possuem vínculo cadastrado (API)
     * Para uso nos relatórios - carrega apenas centros vinculados
     */
    public function getCentrosComVinculo()
    {
        try {
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            $centros = \src\models\Comissao\Vinculo::listarCentrosComVinculo($emprId);
            self::response([
                'success' => true,
                'data' => $centros,
                'empresa_id' => $emprId
            ], 200);
        } catch (\Throwable $e) {
            error_log('ERRO getCentrosComVinculo: ' . $e->getMessage());
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar recursos que possuem vínculo cadastrado (API)
     * Para uso nos relatórios - carrega apenas recursos vinculados
     */
    public function getRecursosComVinculo()
    {
        try {
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            $centroTrabId = $_GET['centroTrabId'] ?? $_GET['centro_trab_id'] ?? $_GET['centro_id'] ?? null;
            $recursos = \src\models\Comissao\Vinculo::listarRecursosComVinculo($emprId, $centroTrabId);
            self::response([
                'success' => true,
                'data' => $recursos,
                'empresa_id' => $emprId
            ], 200);
        } catch (\Throwable $e) {
            error_log('ERRO getRecursosComVinculo: ' . $e->getMessage());
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar funcionários que possuem vínculo cadastrado (API)
     * Para uso nos relatórios - carrega apenas funcionários vinculados
     */
    public function getFuncionariosComVinculo()
    {
        try {
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            $busca = $_GET['busca'] ?? null;
            $funcionarios = \src\models\Comissao\Vinculo::listarFuncionariosComVinculo($emprId, $busca);
            self::response([
                'success' => true,
                'data' => $funcionarios,
                'empresa_id' => $emprId
            ], 200);
        } catch (\Throwable $e) {
            error_log('ERRO getFuncionariosComVinculo: ' . $e->getMessage());
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Alterar status do vÃ­nculo (ativar/inativar)
     */
    public function alterarStatusVinculo()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            if (empty($dados['id'])) {
                throw new \Exception('ID do vÃ­nculo Ã© obrigatÃ³rio');
            }
            $ativo = ($dados['ativo'] ?? 'N') === 'S' ? 'S' : 'N';
            $ok = \src\models\Comissao\Vinculo::alterarStatus($dados['id'], $ativo);
            if (!$ok) {
                throw new \Exception('Falha ao alterar status do vÃ­nculo');
            }
            self::response(['success' => true, 'message' => 'Status alterado com sucesso'], 200);
        } catch (\Throwable $e) {
            error_log('ERRO alterarStatusVinculo: ' . $e->getMessage());
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Salvar vÃ­nculo centro/recurso/funcionÃ¡rio (API)
     */
    public function salvarVinculo()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            error_log('=== SALVAR VINCULO ===');
            error_log('Dados recebidos: ' . print_r($dados, true));
            
            $tipoVinculo = $dados['tipo_vinculo'] ?? 'N';
            $emprId = $dados['empr_id'] ?? ($_SESSION['empresa']['id'] ?? 1);
            
            // Recurso é obrigatório apenas para vínculo Normal
            if (empty($dados['funcionario_id']) || empty($dados['centro_id'])) {
                throw new \Exception('Funcionário e Centro de Trabalho são obrigatórios');
            }
            if ($tipoVinculo === 'N' && empty($dados['recurso_id'])) {
                throw new \Exception('Recurso é obrigatório para vínculo Normal');
            }
            
            $ok = \src\models\Comissao\Vinculo::inserir(
                $emprId,
                $dados['funcionario_id'], 
                $dados['centro_id'],
                $dados['recurso_id'] ?? null,
                $tipoVinculo
            );
            if (!$ok) {
                throw new \Exception('Falha no INSERT do vínculo no banco de dados');
            }
            self::response([
                'success' => true,
                'message' => 'Vínculo salvo com sucesso'
            ], 201);
        } catch (\Throwable $e) {
            error_log('ERRO salvarVinculo: ' . $e->getMessage());
            error_log('TRACE: ' . $e->getTraceAsString());
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar vínculo existente (API)
     */
    public function atualizarVinculo()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            $tipoVinculo = $dados['tipo_vinculo'] ?? 'N';
            
            if (empty($dados['id']) || empty($dados['funcionario_id']) || empty($dados['centro_id'])) {
                throw new \Exception('ID, Funcionário e Centro de Trabalho são obrigatórios');
            }
            if ($tipoVinculo === 'N' && empty($dados['recurso_id'])) {
                throw new \Exception('Recurso é obrigatório para vínculo Normal');
            }
            
            $ok = \src\models\Comissao\Vinculo::atualizar(
                $dados['id'], 
                $dados['centro_id'],
                $dados['recurso_id'] ?? null,
                $tipoVinculo
            );
            if (!$ok) {
                throw new \Exception('Falha ao atualizar vínculo');
            }
            self::response(['success' => true, 'message' => 'Vínculo atualizado com sucesso'], 200);
        } catch (\Throwable $e) {
            error_log('ERRO atualizarVinculo: ' . $e->getMessage());
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir vÃ­nculo (API)
     */
    public function excluirVinculo()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            if (empty($dados['id'])) {
                throw new \Exception('ID do vÃ­nculo Ã© obrigatÃ³rio');
            }
            $ok = \src\models\Comissao\Vinculo::excluir($dados['id']);
            if (!$ok) {
                throw new \Exception('Falha ao excluir vÃ­nculo');
            }
            self::response(['success' => true, 'message' => 'VÃ­nculo excluÃ­do com sucesso'], 200);
        } catch (\Throwable $e) {
            error_log('ERRO excluirVinculo: ' . $e->getMessage());
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ==================== FALTAS DE FUNCIONÃRIOS ====================

    /**
     * PÃ¡gina de gestÃ£o de faltas
     */
    public function faltasIndex()
    {
        $dados = [
            'titulo' => 'GestÃ£o de Faltas - Sistema de ComissÃ£o',
            'pagina' => 'Faltas'
        ];
        $this->render('comissao/faltas', $dados);
    }

    /**
     * Listar faltas (API)
     */
    public function listarFaltas()
    {
        try {
            $filtros = [
                'id_empr' => $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null,
                'id_funcionario' => $_GET['funcionario_id'] ?? null,
                'dt_inicio' => $_GET['dt_inicio'] ?? null,
                'dt_fim' => $_GET['dt_fim'] ?? null
            ];
            
            $model = new \src\models\Comissao\FaltaFuncionario();
            $faltas = $model->listar($filtros);
            
            self::response([
                'success' => true,
                'data' => $faltas,
                'total' => count($faltas)
            ], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Salvar falta (API)
     */
    public function salvarFalta()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            self::verificarCamposVazios($dados, ['id_funcionario', 'dt_falta']);
            
            $dadosModel = [
                'id_empr' => $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null,
                'id_funcionario' => $dados['id_funcionario'],
                'dt_falta' => $dados['dt_falta'],
                'motivo' => $dados['motivo'] ?? null,
                'tipo_falta' => $dados['tipo_falta'] ?? 'I',
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            $model = new \src\models\Comissao\FaltaFuncionario();
            $id = $model->registrar($dadosModel);
            
            self::response([
                'success' => true,
                'message' => 'Falta registrada com sucesso',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualizar falta (API)
     */
    public function atualizarFalta()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (empty($dados['id'])) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $dadosModel = [
                'dt_falta' => $dados['dt_falta'],
                'motivo' => $dados['motivo'] ?? null,
                'tipo_falta' => $dados['tipo_falta'] ?? 'I',
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            $model = new \src\models\Comissao\FaltaFuncionario();
            $model->atualizar($dados['id'], $dadosModel);
            
            self::response(['success' => true, 'message' => 'Falta atualizada com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir falta (API)
     */
    public function excluirFalta()
    {
        try {
            // Aceita ID via GET (DELETE query string) ou via body JSON
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $dados = json_decode(file_get_contents('php://input'), true);
                $id = $dados['id'] ?? null;
            }
            
            if (empty($id)) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $model = new \src\models\Comissao\FaltaFuncionario();
            $model->excluir($id, $_SESSION['user']['id'] ?? null);
            
            self::response(['success' => true, 'message' => 'Falta excluÃ­da com sucesso'], 200);
        } catch (\Exception $e) {
            error_log('Erro ao excluir falta: ' . $e->getMessage());
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // ==================== RETRABALHO ====================

    /**
     * PÃ¡gina de gestÃ£o de retrabalho
     */
    public function retrabalhoIndex()
    {
        $dados = [
            'titulo' => 'GestÃ£o de Retrabalho - Sistema de ComissÃ£o',
            'pagina' => 'Retrabalho'
        ];
        $this->render('comissao/retrabalho', $dados);
    }

    /**
     * Listar retrabalhos (API)
     */
    public function listarRetrabalhos()
    {
        try {
            $filtros = [
                'id_empr' => $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null,
                'id_funcionario' => $_GET['funcionario_id'] ?? null,
                'id_recurso' => $_GET['recurso_id'] ?? null,
                'dt_inicio' => $_GET['dt_inicio'] ?? null,
                'dt_fim' => $_GET['dt_fim'] ?? null
            ];
            
            $model = new \src\models\Comissao\Retrabalho();
            $retrabalhos = $model->listar($filtros);
            
            self::response([
                'success' => true,
                'data' => $retrabalhos,
                'total' => count($retrabalhos)
            ], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Salvar retrabalho (API)
     */
    public function salvarRetrabalho()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            self::verificarCamposVazios($dados, ['id_funcionario', 'id_item', 'dt_retrabalho', 'quantidade']);
            
            $dadosModel = [
                'id_empr' => $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null,
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
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            $model = new \src\models\Comissao\Retrabalho();
            $id = $model->inserir($dadosModel);
            
            self::response([
                'success' => true,
                'message' => 'Retrabalho registrado com sucesso',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualizar retrabalho (API)
     */
    public function atualizarRetrabalho()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (empty($dados['id'])) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
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
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            $model = new \src\models\Comissao\Retrabalho();
            $model->atualizar($dados['id'], $dadosModel);
            
            self::response(['success' => true, 'message' => 'Retrabalho atualizado com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir retrabalho (API)
     */
    public function excluirRetrabalho()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (empty($dados['id'])) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $model = new \src\models\Comissao\Retrabalho();
            $model->excluir($dados['id'], $_SESSION['user']['id'] ?? null);
            
            self::response(['success' => true, 'message' => 'Retrabalho excluÃ­do com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ==================== VÃNCULO DE APONTAMENTOS SEM RECURSO ====================

    /**
     * PÃ¡gina de vÃ­nculo de apontamentos sem recurso
     */
    public function vinculoApontamentoIndex()
    {
        $dados = [
            'titulo' => 'VÃ­nculo de Apontamentos - Sistema de ComissÃ£o',
            'pagina' => 'VÃ­nculo Apontamentos'
        ];
        $this->render('comissao/vinculo-apontamento', $dados);
    }

    /**
     * Listar apontamentos sem recurso (API)
     */
    public function listarApontamentosSemRecurso()
    {
        try {
            $filtros = [
                'id_empr' => $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null,
                'dt_inicio' => $_GET['dt_inicio'] ?? date('Y-m-01'),
                'dt_fim' => $_GET['dt_fim'] ?? date('Y-m-d'),
                'id_centro_trab' => $_GET['centro_trab_id'] ?? null,
                'apenas_nao_vinculados' => isset($_GET['apenas_nao_vinculados']) && $_GET['apenas_nao_vinculados'] === 'true'
            ];
            
            $model = new \src\models\Comissao\VinculoApontamento();
            $apontamentos = $model->listarApontamentosSemRecurso($filtros);
            
            self::response([
                'success' => true,
                'data' => $apontamentos,
                'total' => count($apontamentos)
            ], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar vÃ­nculos de apontamentos existentes (API)
     */
    public function listarVinculosApontamento()
    {
        try {
            $filtros = [
                'id_empr' => $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null,
                'dt_inicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
                'dt_fim' => $_GET['data_fim'] ?? date('Y-m-d')
            ];
            
            $model = new \src\models\Comissao\VinculoApontamento();
            $vinculos = $model->listarVinculos($filtros);
            
            self::response($vinculos, 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Vincular RECURSO (mÃ¡quina) ao apontamento (API)
     * Insere na tabela TORD_MOV_FAB_MAQ do FOCCO
     */
    public function vincularRecurso()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            self::verificarCamposVazios($dados, ['apontamento_id', 'recurso_id']);
            
            $model = new \src\models\Comissao\VinculoApontamento();
            $result = $model->vincularRecurso($dados['apontamento_id'], $dados['recurso_id']);
            
            if ($result) {
                self::response(['success' => true, 'message' => 'Recurso vinculado com sucesso!'], 200);
            } else {
                self::response(['success' => false, 'message' => 'Erro ao vincular recurso'], 500);
            }
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Vincular apontamento a funcionÃ¡rio (API)
     */
    public function vincularApontamento()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            self::verificarCamposVazios($dados, ['id_apontamento', 'id_funcionario']);
            
            $dadosModel = [
                'id_empr' => $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null,
                'id_apontamento' => $dados['id_apontamento'],
                'id_funcionario' => $dados['id_funcionario'],
                'id_recurso' => $dados['id_recurso'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            $model = new \src\models\Comissao\VinculoApontamento();
            $id = $model->vincular($dadosModel);
            
            self::response([
                'success' => true,
                'message' => 'Apontamento vinculado com sucesso',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Vincular mÃºltiplos apontamentos em lote (API)
     */
    public function vincularApontamentosLote()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            self::verificarCamposVazios($dados, ['apontamentos', 'id_funcionario']);
            
            if (!is_array($dados['apontamentos']) || empty($dados['apontamentos'])) {
                throw new \Exception('Lista de apontamentos invÃ¡lida');
            }
            
            $model = new \src\models\Comissao\VinculoApontamento();
            $resultado = $model->vincularEmLote(
                $dados['apontamentos'],
                $dados['id_funcionario'],
                $dados['id_recurso'] ?? null,
                $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null,
                $_SESSION['user']['id'] ?? null
            );
            
            self::response([
                'success' => true,
                'message' => "Vinculados: {$resultado['total_sucesso']}, Erros: {$resultado['total_erros']}",
                'resultado' => $resultado
            ], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualizar vÃ­nculo de apontamento (API)
     */
    public function atualizarVinculoApontamento()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (empty($dados['id'])) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $model = new \src\models\Comissao\VinculoApontamento();
            $model->atualizar($dados['id'], [
                'id_funcionario' => $dados['id_funcionario'],
                'id_recurso' => $dados['id_recurso'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ]);
            
            self::response(['success' => true, 'message' => 'VÃ­nculo atualizado com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir vÃ­nculo de apontamento (API)
     */
    public function excluirVinculoApontamento()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (empty($dados['id'])) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $model = new \src\models\Comissao\VinculoApontamento();
            $model->excluir($dados['id'], $_SESSION['user']['id'] ?? null);
            
            self::response(['success' => true, 'message' => 'VÃ­nculo excluÃ­do com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ==================== REGRAS ESPECÃFICAS POR FUNCIONÃRIO ====================

    /**
     * PÃ¡gina de gestÃ£o de regras especÃ­ficas
     */
    public function regrasIndex()
    {
        $dados = [
            'titulo' => 'Regras EspecÃ­ficas por FuncionÃ¡rio',
            'pagina' => 'Regras'
        ];
        $this->render('comissao/regras', $dados);
    }

    /**
     * Listar regras especÃ­ficas (API)
     */
    public function listarRegras()
    {
        try {
            $filtros = [
                'id_empr' => $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null,
                'id_funcionario' => $_GET['funcionario_id'] ?? null,
                'id_centro_trab' => $_GET['centro_trab_id'] ?? null,
                'apenas_ativos' => isset($_GET['apenas_ativos']) && $_GET['apenas_ativos'] === 'true',
                'apenas_vigentes' => isset($_GET['apenas_vigentes']) && $_GET['apenas_vigentes'] === 'true'
            ];
            
            $model = new \src\models\Comissao\RegraFuncionario();
            $regras = $model->listar($filtros) ?: [];
            
            self::response([
                'success' => true,
                'data' => $regras,
                'total' => count($regras)
            ], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Buscar regra por ID (API)
     */
    public function buscarRegra()
    {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $model = new \src\models\Comissao\RegraFuncionario();
            $regra = $model->buscarPorId($id);
            
            if (!$regra) {
                throw new \Exception('Regra nÃ£o encontrada');
            }
            
            self::response(['success' => true, 'data' => $regra], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * Salvar regra especÃ­fica (API)
     */
    public function salvarRegra()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            error_log('=== SALVAR REGRA ===');
            error_log('Dados recebidos: ' . print_r($dados, true));
            
            // Para tipo Misto (M), valor_comissao pode ser 0 se só tiver valor fixo
            $camposObrigatorios = ['id_funcionario', 'tipo_comissao', 'dt_vigencia_ini'];
            if ($dados['tipo_comissao'] !== 'M') {
                $camposObrigatorios[] = 'valor_comissao';
            }
            self::verificarCamposVazios($dados, $camposObrigatorios);
            
            $dadosModel = [
                'id_empr' => $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null,
                'id_funcionario' => $dados['id_funcionario'],
                'id_centro_trab' => $dados['id_centro_trab'] ?? null,
                'descricao' => $dados['descricao'] ?? null,
                'tipo_comissao' => $dados['tipo_comissao'],
                'valor_comissao' => $dados['valor_comissao'] ?? 0,
                'valor_fixo' => $dados['valor_fixo'] ?? null,
                'dt_vigencia_ini' => $dados['dt_vigencia_ini'],
                'dt_vigencia_fim' => $dados['dt_vigencia_fim'] ?? null,
                'prioridade' => $dados['prioridade'] ?? 1,
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            error_log('Dados para model: ' . print_r($dadosModel, true));
            
            $model = new \src\models\Comissao\RegraFuncionario();
            $id = $model->inserir($dadosModel);
            
            error_log('Regra inserida com ID: ' . $id);
            
            self::response([
                'success' => true,
                'message' => 'Regra cadastrada com sucesso',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            error_log('ERRO salvarRegra: ' . $e->getMessage());
            error_log('TRACE: ' . $e->getTraceAsString());
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualizar regra especÃ­fica (API)
     */
    public function atualizarRegra()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (empty($dados['id'])) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            // Buscar regra para pegar o funcionÃ¡rio
            $model = new \src\models\Comissao\RegraFuncionario();
            $regraAtual = $model->buscarPorId($dados['id']);
            
            if (!$regraAtual) {
                throw new \Exception('Regra nÃ£o encontrada');
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
                'id_empr' => $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null,
                'id_usuario' => $_SESSION['user']['id'] ?? null
            ];
            
            $model->atualizar($dados['id'], $dadosModel);
            
            self::response(['success' => true, 'message' => 'Regra atualizada com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir regra especÃ­fica (API)
     */
    public function inativarRegra()
    {
        try {
            // ID pode vir via query string (DELETE) ou body JSON
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                $dados = json_decode(file_get_contents('php://input'), true);
                $id = $dados['id'] ?? null;
            }
            
            if (empty($id)) {
                throw new \Exception('ID Ã© obrigatÃ³rio');
            }
            
            $model = new \src\models\Comissao\RegraFuncionario();
            $model->inativar($id, $_SESSION['user']['id'] ?? null);
            
            self::response(['success' => true, 'message' => 'Regra excluÃ­da com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}





