<?php

namespace src\controllers\Comissao;

use \core\Controller as ctrl;
use \core\Request;
use src\handlers\Comissao\ComissaoCadastroHandler;

/**
 * Controller de Cadastros do Sistema de Comissao
 * Responsável por orquestrar requisições, delegando lógica de negócio ao Handler
 */
class ComissaoCadastroController extends ctrl
{
    // ==================== PÁGINAS ====================

    /**
     * Página principal de cadastros
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Cadastros - Sistema de Comissão',
            'pagina' => 'Cadastros'
        ];

        $this->render('comissao/cadastro', $dados);
    }

    /**
     * Página de cadastro de pontuação de produtos
     */
    public function pontuacaoIndex()
    {
        $dados = [
            'titulo' => 'Cadastro de Pontuação UP',
            'pagina' => 'Pontuação UP'
        ];

        $this->render('comissao/pontuacao', $dados);
    }

    /**
     * Página de cadastro de faixas de comissão
     */
    public function faixasIndex()
    {
        $dados = [
            'titulo' => 'Cadastro de Faixas de Comissão',
            'pagina' => 'Faixas de Comissão'
        ];

        $this->render('comissao/faixas', $dados);
    }

    /**
     * Página de vínculo entre Funcionário, Recurso e Centro de Trabalho
     */
    public function vinculoIndex()
    {
        $dados = [
            'titulo' => 'Vínculo Funcionário, Recurso e Centro de Trabalho',
            'pagina' => 'Vínculo'
        ];
        $this->render('comissao/vinculo', $dados);
    }

    // ==================== API PONTUAÇÃO ====================

    /**
     * Listar pontuações (API)
     * Se id for passado, busca apenas esse registro
     * Usa empresa da sessão se não passada via GET
     */
    public function listarPontuacoes()
    {
        try {
            // Se id foi passado, busca apenas esse registro
            $id = $_GET['id'] ?? null;
            if ($id) {
                $pontuacao = ComissaoCadastroHandler::buscarPontuacao((int)$id);
                if (!$pontuacao) {
                    self::response([
                        'success' => false,
                        'error' => 'Pontuação não encontrada'
                    ], 404);
                    return;
                }
                self::response([
                    'success' => true,
                    'data' => $pontuacao
                ], 200);
                return;
            }
            
            // Usar empresa da sessão ou GET (se especificada)
            $emprId = $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            
            // Verificar se deve incluir inativos
            $incluirInativas = isset($_GET['incluirInativas']) && $_GET['incluirInativas'] === 'true';
            
            $pontuacoes = ComissaoCadastroHandler::listarPontuacoes($emprId ? (int)$emprId : null, $incluirInativas);

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
     * Buscar pontuação por ID (API)
     */
    public function buscarPontuacao()
    {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new \Exception('ID é obrigatório');
            }
            
            $pontuacao = ComissaoCadastroHandler::buscarPontuacao((int)$id);

            if (!$pontuacao) {
                throw new \Exception('Pontuação não encontrada');
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
     * Salvar pontuação (API)
     * Usa empresa da sessão se não passada nos dados
     */
    public function salvarPontuacao()
    {
        try {
            $dados = Request::getJsonBody();
            
            // Usar empresa da sessão se não informada
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
                throw new \Exception('Produto é obrigatório');
            }
            
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            $id = ComissaoCadastroHandler::salvarPontuacao($dados);

            self::response([
                'success' => true,
                'message' => 'Pontuação cadastrada com sucesso',
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
     * Atualizar pontuação (API)
     */
    public function atualizarPontuacao()
    {
        try {
            $dados = Request::getJsonBody();
            
            $id = $dados['id'] ?? null;
            if (!$id) {
                throw new \Exception('ID é obrigatório');
            }
            
            self::verificarCamposVazios($dados, [
                'pontuacao_up',
                'dt_vigencia_ini'
            ]);
            
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            ComissaoCadastroHandler::atualizarPontuacao((int)$id, $dados);

            self::response([
                'success' => true,
                'message' => 'Pontuação atualizada com sucesso'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Excluir pontuação (API)
     */
    public function excluirPontuacao()
    {
        try {
            $dados = Request::getJsonBody();
            
            $id = $dados['id'] ?? null;
            if (!$id) {
                throw new \Exception('ID é obrigatório');
            }
            
            $usuId = $_SESSION['user']['id'] ?? null;
            
            ComissaoCadastroHandler::excluirPontuacao((int)$id, $usuId);

            self::response([
                'success' => true,
                'message' => 'Pontuação excluída com sucesso'
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Importar pontuações de arquivo CSV/Excel (API)
     */
    public function importarPontuacoes()
    {
        try {
            $dados = Request::getJsonBody();
            
            if (empty($dados['linhas']) || !is_array($dados['linhas'])) {
                throw new \Exception('Nenhum dado para importar');
            }
            
            $emprId = $_SESSION['empresa']['id'] ?? null;
            $idUsuario = $_SESSION['user']['id'] ?? null;
            
            if (!$emprId) {
                throw new \Exception('Empresa não identificada na sessão');
            }
            
            $resultado = ComissaoCadastroHandler::importarPontuacoes($dados['linhas'], (int)$emprId, $idUsuario);
            
            self::response([
                'success' => true,
                'importados' => $resultado['importados'],
                'atualizados' => $resultado['atualizados'],
                'erros' => $resultado['erros'],
                'total' => $resultado['total'],
                'message' => "Importação concluída: {$resultado['importados']} novos, {$resultado['atualizados']} atualizados"
            ], 200);
            
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }


    // ==================== API FAIXAS ====================

    /**
     * Listar faixas de comissão (API)
     */
    public function listarFaixas()
    {
        try {
            // Se veio id, busca faixa específica
            $id = $_GET['id'] ?? null;
            if ($id) {
                $faixa = ComissaoCadastroHandler::buscarFaixa((int)$id);
                
                if (!$faixa) {
                    throw new \Exception('Faixa não encontrada');
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
            
            if ($incluirInativas) {
                $faixas = ComissaoCadastroHandler::listarFaixas($emprId ? (int)$emprId : null);
            } else {
                $faixas = ComissaoCadastroHandler::listarFaixas($emprId ? (int)$emprId : null, $centroTrabId ? (int)$centroTrabId : null);
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
                throw new \Exception('ID é obrigatório');
            }
            
            $faixa = ComissaoCadastroHandler::buscarFaixa((int)$id);

            if (!$faixa) {
                throw new \Exception('Faixa não encontrada');
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
     * Salvar faixa de comissão (API)
     */
    public function salvarFaixa()
    {
        try {
            $dados = Request::getJsonBody();
            
            // Mapear campos do JS para os nomes esperados pelo model
            // O model usa 'tipo', não 'tipo_faixa'
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
            if (!in_array($dados['tipo'], ['P', 'Q'])) {
                throw new \Exception('Tipo de faixa inválido');
            }
            
            $dados['ponto_final'] = $dados['ponto_final'] ?: null;
            $dados['centro_trab_id'] = $dados['centro_trab_id'] ?: null;
            $dados['dt_vigencia_fim'] = $dados['dt_vigencia_fim'] ?: null;
            
            // Verificar se já existe faixa conflitante
            $conflito = /* conflito verificado no handler */ null;
            if ($conflito) {
                $centroNome = $conflito['DESC_CENTRO'] ? $conflito['COD_CENTRO'] . ' - ' . $conflito['DESC_CENTRO'] : 'Todos';
                throw new \Exception(
                    'Já existe uma faixa ativa para este centro de trabalho (' . $centroNome . ') ' .
                    'com pontuação de ' . $conflito['PONTO_INICIAL'] . ' a ' . ($conflito['PONTO_FINAL'] ?? '∞') . ' ' .
                    'que conflita com a vigência informada.'
                );
            }
            
            $id = ComissaoCadastroHandler::salvarFaixa($dados);

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
     * Atualizar faixa de comissão (API)
     */
    public function atualizarFaixa()
    {
        try {
            $dados = Request::getJsonBody();
            
            $id = $dados['id'] ?? null;
            if (!$id) {
                throw new \Exception('ID é obrigatório');
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
            
            // Verificar se já existe faixa conflitante (excluindo a faixa atual)
            $conflito = /* conflito verificado no handler */ null;
            if ($conflito) {
                $centroNome = $conflito['DESC_CENTRO'] ? $conflito['COD_CENTRO'] . ' - ' . $conflito['DESC_CENTRO'] : 'Todos';
                throw new \Exception(
                    'Já existe uma faixa ativa para este centro de trabalho (' . $centroNome . ') ' .
                    'com pontuação de ' . $conflito['PONTO_INICIAL'] . ' a ' . ($conflito['PONTO_FINAL'] ?? '∞') . ' ' .
                    'que conflita com a vigência informada.'
                );
            }
            
            ComissaoCadastroHandler::atualizarFaixa((int)$id, $dados);

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
     * Inativar faixa de comissão (API)
     */
    public function inativarFaixa()
    {
        try {
            $dados = Request::getJsonBody();
            
            $id = $dados['id'] ?? null;
            if (!$id) {
                throw new \Exception('ID é obrigatório');
            }
            
            $usuId = $_SESSION['user']['id'] ?? null;
            
            ComissaoCadastroHandler::inativarFaixa((int)$id, $usuId);

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
     * Usa empresa da sessão se não passada via GET
     */
    public function getCentrosTrabalho()
    {
        try {
            // Prioriza parâmetro GET, senão usa empresa da sessão
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            
            $centros = ComissaoCadastroHandler::listarCentrosTrabalho($emprId ? (int)$emprId : null);

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
     * Listar funcionários ativos para select
     * Usa empresa da sessão se não passada via GET
     */
    public function getFuncionarios()
    {
        try {
            // Prioriza parâmetro GET, senão usa empresa da sessão
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            $busca = $_GET['busca'] ?? null;
            $funcionarios = ComissaoCadastroHandler::listarFuncionarios($emprId ? (int)$emprId : null, $busca);
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
     * Listar recursos/máquinas para select
     * Usa empresa da sessão se não passada via GET
     */
    public function getRecursos()
    {
        try {
            // Prioriza parâmetro GET, senão usa empresa da sessão
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null;
            $centroTrabId = $_GET['centroTrabId'] ?? $_GET['centro_trab_id'] ?? $_GET['centro_id'] ?? null;
            
            $recursos = ComissaoCadastroHandler::listarRecursos($emprId ? (int)$emprId : null, $centroTrabId ? (int)$centroTrabId : null);

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
     * Usa empresa da sessão se não passada via GET
     */
    public function getProdutos()
    {
        try {
            $emprId = $_GET['empr_id'] ?? $_GET['emprId'] ?? $_SESSION['empresa']['id'] ?? null;
            $termo = $_GET['termo'] ?? null;
            
            $produtos = ComissaoCadastroHandler::listarProdutos(
                $emprId ? (int)$emprId : null,
                $termo
            );

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
     * Retorna no formato esperado pelo Select2 com paginação
     */
    public function buscarProdutos()
    {
        try {
            $emprId = $_GET['empr_id'] ?? $_GET['emprId'] ?? $_SESSION['empresa']['id'] ?? null;
            $termo = $_GET['term'] ?? $_GET['q'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            
            $resultado = ComissaoCadastroHandler::buscarProdutos(
                $emprId ? (int)$emprId : null,
                $termo ?: null,
                $page
            );

            self::response([
                'results' => $resultado['produtos'],
                'pagination' => [
                    'more' => $resultado['hasMore']
                ],
                'total' => $resultado['total']
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
            $empresas = ComissaoCadastroHandler::listarEmpresas();

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
     * Selecionar empresa e salvar na sessão
     */
    public function selecionarEmpresa()
    {
        try {
            $dados = Request::getJsonBody();
            
            if (empty($dados['empr_id'])) {
                throw new \Exception('ID da empresa é obrigatório');
            }
            
            $empresa = ComissaoCadastroHandler::buscarEmpresa((int)$dados['empr_id']);
            
            if (!$empresa) {
                throw new \Exception('Empresa não encontrada');
            }
            
            // Salvar empresa na sessão
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
     * Obter empresa selecionada na sessão
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
     * Listar vínculos centro/recurso/funcionário (API)
     */
    public function listarVinculos()
    {
        try {
            // Obter empresa da sessão para filtrar
            $emprId = $_SESSION['empresa']['id'] ?? null;
            
            $filtros = [
                'id_empr' => $emprId,
                'id_funcionario' => $_GET['funcionario_id'] ?? null,
                'id_recurso' => $_GET['recurso_id'] ?? null,
                'id_centro_trab' => $_GET['centro_id'] ?? null,
            ];
            $vinculos = \src\models\Comissao\Vinculo::listar($filtros) ?: [];
            self::response(['success' => true, 'data' => $vinculos], 200);
        } catch (\Throwable $e) {
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
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Alterar status do vínculo (ativar/inativar)
     */
    public function alterarStatusVinculo()
    {
        try {
            $dados = Request::getJsonBody();
            if (empty($dados['id'])) {
                throw new \Exception('ID do vínculo é obrigatório');
            }
            $ativo = ($dados['ativo'] ?? 'N') === 'S' ? 'S' : 'N';
            $ok = \src\models\Comissao\Vinculo::alterarStatus($dados['id'], $ativo);
            if (!$ok) {
                throw new \Exception('Falha ao alterar status do vínculo');
            }
            self::response(['success' => true, 'message' => 'Status alterado com sucesso'], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Salvar vínculo centro/recurso/funcionário (API)
     */
    public function salvarVinculo()
    {
        try {
            $dados = Request::getJsonBody();
            
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
            $dados = Request::getJsonBody();
            
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
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir vínculo (API)
     */
    public function excluirVinculo()
    {
        try {
            $dados = Request::getJsonBody();
            if (empty($dados['id'])) {
                throw new \Exception('ID do vínculo é obrigatório');
            }
            $ok = \src\models\Comissao\Vinculo::excluir($dados['id']);
            if (!$ok) {
                throw new \Exception('Falha ao excluir vínculo');
            }
            self::response(['success' => true, 'message' => 'Vínculo excluído com sucesso'], 200);
        } catch (\Throwable $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ==================== FALTAS DE FUNCIONÁRIOS ====================

    /**
     * Página de gestão de faltas
     */
    public function faltasIndex()
    {
        $dados = [
            'titulo' => 'Gestão de Faltas - Sistema de Comissão',
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
            
            $faltas = ComissaoCadastroHandler::listarFaltas($filtros);
            
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
            $dados = Request::getJsonBody();
            
            self::verificarCamposVazios($dados, ['id_funcionario', 'dt_falta']);
            
            $dados['id_empr'] = $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null;
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            $id = ComissaoCadastroHandler::salvarFalta($dados);
            
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
            $dados = Request::getJsonBody();
            
            if (empty($dados['id'])) {
                throw new \Exception('ID é obrigatório');
            }
            
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            ComissaoCadastroHandler::atualizarFalta($dados['id'], $dados);
            
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
                $dados = Request::getJsonBody();
                $id = $dados['id'] ?? null;
            }
            
            if (empty($id)) {
                throw new \Exception('ID é obrigatório');
            }
            
            ComissaoCadastroHandler::excluirFalta((int)$id, $_SESSION['user']['id'] ?? null);
            
            self::response(['success' => true, 'message' => 'Falta excluída com sucesso'], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // ==================== RETRABALHO ====================

    /**
     * Página de gestão de retrabalho
     */
    public function retrabalhoIndex()
    {
        $dados = [
            'titulo' => 'Gestão de Retrabalho - Sistema de Comissão',
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
            
            $retrabalhos = ComissaoCadastroHandler::listarRetrabalhos($filtros);
            
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
            $dados = Request::getJsonBody();
            
            self::verificarCamposVazios($dados, ['id_funcionario', 'id_item', 'dt_retrabalho', 'quantidade']);
            
            $dados['id_empr'] = $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null;
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            $id = ComissaoCadastroHandler::salvarRetrabalho($dados);
            
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
            $dados = Request::getJsonBody();
            
            if (empty($dados['id'])) {
                throw new \Exception('ID é obrigatório');
            }
            
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            ComissaoCadastroHandler::atualizarRetrabalho((int)$dados['id'], $dados);
            
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
            $dados = Request::getJsonBody();
            
            if (empty($dados['id'])) {
                throw new \Exception('ID é obrigatório');
            }
            
            ComissaoCadastroHandler::excluirRetrabalho((int)$dados['id'], $_SESSION['user']['id'] ?? null);
            
            self::response(['success' => true, 'message' => 'Retrabalho excluído com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ==================== VÁ�NCULO DE APONTAMENTOS SEM RECURSO ====================

    /**
     * Página de vínculo de apontamentos sem recurso
     */
    public function vinculoApontamentoIndex()
    {
        $dados = [
            'titulo' => 'Vínculo de Apontamentos - Sistema de Comissão',
            'pagina' => 'Vínculo Apontamentos'
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
            
            $apontamentos = ComissaoCadastroHandler::listarApontamentosSemRecurso($filtros);
            
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
     * Listar vínculos de apontamentos existentes (API)
     */
    public function listarVinculosApontamento()
    {
        try {
            $filtros = [
                'id_empr' => $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null,
                'dt_inicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
                'dt_fim' => $_GET['data_fim'] ?? date('Y-m-d')
            ];
            
            $vinculos = ComissaoCadastroHandler::listarVinculosApontamento($filtros);
            
            self::response($vinculos, 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Vincular RECURSO (máquina) ao apontamento (API)
     * Insere na tabela TORD_MOV_FAB_MAQ do FOCCO
     */
    public function vincularRecurso()
    {
        try {
            $dados = Request::getJsonBody();
            
            self::verificarCamposVazios($dados, ['apontamento_id', 'recurso_id']);
            
            $result = ComissaoCadastroHandler::vincularRecurso($dados['apontamento_id'], $dados['recurso_id']);
            
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
     * Vincular apontamento a funcionário (API)
     */
    public function vincularApontamento()
    {
        try {
            $dados = Request::getJsonBody();
            
            self::verificarCamposVazios($dados, ['id_apontamento', 'id_funcionario']);
            
            $dados['id_empr'] = $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null;
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            $id = ComissaoCadastroHandler::vincularApontamento($dados);
            
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
     * Vincular múltiplos apontamentos em lote (API)
     */
    public function vincularApontamentosLote()
    {
        try {
            $dados = Request::getJsonBody();
            
            self::verificarCamposVazios($dados, ['apontamentos', 'id_funcionario']);
            
            if (!is_array($dados['apontamentos']) || empty($dados['apontamentos'])) {
                throw new \Exception('Lista de apontamentos inválida');
            }
            
            $dados['id_empr'] = $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null;
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            $resultado = ComissaoCadastroHandler::vincularApontamentosLote($dados);
            
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
     * Atualizar vínculo de apontamento (API)
     */
    public function atualizarVinculoApontamento()
    {
        try {
            $dados = Request::getJsonBody();
            
            if (empty($dados['id'])) {
                throw new \Exception('ID é obrigatório');
            }
            
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            ComissaoCadastroHandler::atualizarVinculoApontamento((int)$dados['id'], $dados);
            
            self::response(['success' => true, 'message' => 'Vínculo atualizado com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir vínculo de apontamento (API)
     */
    public function excluirVinculoApontamento()
    {
        try {
            $dados = Request::getJsonBody();
            
            if (empty($dados['id'])) {
                throw new \Exception('ID é obrigatório');
            }
            
            ComissaoCadastroHandler::excluirVinculoApontamento((int)$dados['id'], $_SESSION['user']['id'] ?? null);
            
            self::response(['success' => true, 'message' => 'Vínculo excluído com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ==================== REGRAS ESPECÁ�FICAS POR FUNCIONÁRIO ====================

    /**
     * Página de gestão de regras específicas
     */
    public function regrasIndex()
    {
        $dados = [
            'titulo' => 'Regras Específicas por Funcionário',
            'pagina' => 'Regras'
        ];
        $this->render('comissao/regras', $dados);
    }

    /**
     * Listar regras específicas (API)
     */
    public function listarRegras()
    {
        try {
            $incluirInativas = isset($_GET['incluir_inativas']) && $_GET['incluir_inativas'] == '1';
            $filtros = [
                'id_empr' => $_GET['empr_id'] ?? $_SESSION['empresa']['id'] ?? null,
                'id_funcionario' => $_GET['funcionario_id'] ?? null,
                'id_centro_trab' => $_GET['centro_trab_id'] ?? null,
                'apenas_ativos' => isset($_GET['apenas_ativos']) && $_GET['apenas_ativos'] === 'true',
                'apenas_vigentes' => isset($_GET['apenas_vigentes']) && $_GET['apenas_vigentes'] === 'true',
                'status' => $incluirInativas ? '' : 'S'
            ];
            
            $regras = ComissaoCadastroHandler::listarRegras($filtros);
            
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
                throw new \Exception('ID é obrigatório');
            }
            
            $regra = ComissaoCadastroHandler::buscarRegra((int)$id);
            
            if (!$regra) {
                throw new \Exception('Regra não encontrada');
            }
            
            self::response(['success' => true, 'data' => $regra], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * Salvar regra específica (API)
     */
    public function salvarRegra()
    {
        try {
            $dados = Request::getJsonBody();
            
            // Para tipo Misto (M), valor_comissao pode ser 0 se só tiver valor fixo
            $camposObrigatorios = ['id_funcionario', 'tipo_comissao', 'dt_vigencia_ini'];
            if ($dados['tipo_comissao'] !== 'M') {
                $camposObrigatorios[] = 'valor_comissao';
            }
            self::verificarCamposVazios($dados, $camposObrigatorios);
            
            $dados['id_empr'] = $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null;
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            $id = ComissaoCadastroHandler::salvarRegra($dados);
            
            self::response([
                'success' => true,
                'message' => 'Regra cadastrada com sucesso',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualizar regra específica (API)
     */
    public function atualizarRegra()
    {
        try {
            $dados = Request::getJsonBody();
            
            if (empty($dados['id'])) {
                throw new \Exception('ID é obrigatório');
            }
            
            $dados['id_empr'] = $dados['id_empr'] ?? $_SESSION['empresa']['id'] ?? null;
            $dados['id_usuario'] = $_SESSION['user']['id'] ?? null;
            
            ComissaoCadastroHandler::atualizarRegra((int)$dados['id'], $dados);
            
            self::response(['success' => true, 'message' => 'Regra atualizada com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir regra específica (API)
     */
    public function inativarRegra()
    {
        try {
            // ID pode vir via query string (DELETE) ou body JSON
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                $dados = Request::getJsonBody();
                $id = $dados['id'] ?? null;
            }
            
            if (empty($id)) {
                throw new \Exception('ID é obrigatório');
            }
            
            ComissaoCadastroHandler::inativarRegra((int)$id, $_SESSION['user']['id'] ?? null);
            
            self::response(['success' => true, 'message' => 'Regra excluída com sucesso'], 200);
        } catch (\Exception $e) {
            self::response(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}







