<?php

namespace src\handlers\Comissao;

use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Comissao;
use src\models\Comissao\FaixaComissao;
use src\models\Comissao\CentroTrabalho;
use src\models\Comissao\Recurso;
use src\models\Comissao\Funcionario;
use src\models\Comissao\FaltaFuncionario;
use src\models\Comissao\RegraFuncionario;
use src\models\Comissao\Vinculo;

/**
 * Handler para lógica de negócio de Relatórios de Comissão
 * 
 * Responsabilidades:
 * - Chamar models para obter dados
 * - Aplicar regras de negócio (cálculos, faltas, descontos)
 * - Montar e formatar dados de resposta
 */
class ComissaoRelatorioHandler
{
    /**
     * Obter produtividade diária com validações de cadastro
     * 
     * @param string $data Data no formato Y-m-d
     * @param int $emprId ID da empresa
     * @param int|null $recursoId ID do recurso (opcional)
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @return array Dados formatados
     */
    public function getProdutividadeDiaria(string $data, int $emprId, ?int $recursoId = null, ?int $centroTrabId = null): array
    {
        $model = new ApontamentoProducao();
        $dados = $model->produtividadeDiaria($data, $emprId, $recursoId, $centroTrabId);

        $apontamentos = [];
        $produtividadePorFunc = [];
        $funcionariosUnicos = [];
        
        $resumo = [
            'TOTAL_REGISTROS' => count($dados),
            'TOTAL_QTD_PRODUZIDA' => 0,
            'TOTAL_PONTOS' => 0,
            'TOTAL_FUNCIONARIOS' => 0,
            'TOTAL_SEM_PONTUACAO' => 0,
            'TOTAL_SEM_FAIXA' => 0,
            'TOTAL_SEM_VINCULO' => 0
        ];

        // OTIMIZADO: Extrair IDs únicos de funcionários e buscar todas as faltas de uma vez
        $funcIds = array_unique(array_filter(array_map(fn($item) => $item['ID_FUNCIONARIO'] ?? null, $dados)));
        
        $faltaModel = new FaltaFuncionario();
        $faltasCache = [];
        
        if (!empty($funcIds)) {
            // Usa método batch para buscar todas as faltas em uma única query
            $faltasPorFunc = $faltaModel->verificarFaltasPeriodoBatch(array_values($funcIds), $data, $data, $emprId);
            
            // Mapear para cache de acesso rápido
            foreach ($faltasPorFunc as $funcId => $faltas) {
                $faltasCache[$funcId] = !empty($faltas) ? $faltas[0] : null;
            }
        }

        foreach ($dados as $item) {
            $funcId = $item['ID_FUNCIONARIO'] ?? null;
            $quantidade = floatval($item['QUANTIDADE'] ?? 0);
            $pontosUp = floatval($item['PONTOS_UP'] ?? 0);
            $totalPontos = $quantidade * $pontosUp;
            
            // Verificar falta (cache já preenchido acima)
            $temFalta = false;
            $tipoFalta = null;
            if ($funcId && isset($faltasCache[$funcId]) && $faltasCache[$funcId]) {
                $temFalta = true;
                $tipoFalta = $faltasCache[$funcId]['TIPO_FALTA'] ?? null;
            }
            
            // Validações
            $temPontuacao = ($item['TEM_PONTUACAO'] ?? 'N') === 'S';
            $temFaixa = ($item['TEM_FAIXA'] ?? 'N') === 'S';
            $temVinculo = ($item['TEM_VINCULO'] ?? 'N') === 'S';
            
            // Calcular pontos com desconto de falta
            $pontosComDesconto = $this->calcularPontosComDesconto($totalPontos, $temFalta, $tipoFalta);
            
            // Mapeamento para apontamentos (detalhamento)
            $apontamentos[] = [
                'FUNCIONARIO' => $item['NOME_FUNCIONARIO'] ?? 'Sem vínculo',
                'FUNC_ID' => $funcId,
                'PRODUTO' => $item['DESC_PRODUTO'] ?? '',
                'CODIGO_PRODUTO' => $item['CODIGO_PRODUTO'] ?? '',
                'MASCARA' => $item['MASCARA'] ?? '',
                'CENTRO_TRAB' => $item['COD_CENTRO'] ? ($item['COD_CENTRO'] . ' - ' . $item['DESC_CENTRO']) : '-',
                'OPERACAO' => $item['DESC_OPERACAO'] ?? '-',
                'RECURSO' => $item['COD_MAQUINA'] ? ($item['COD_MAQUINA'] . ' - ' . $item['DESC_MAQUINA']) : '-',
                'QUANTIDADE' => $quantidade,
                'PONTOS_UP' => $pontosUp,
                'TOTAL_PONTOS' => $totalPontos,
                'PONTOS_COM_DESCONTO' => $pontosComDesconto,
                'TEM_PONTUACAO' => $temPontuacao,
                'TEM_FAIXA' => $temFaixa,
                'TEM_VINCULO' => $temVinculo,
                'TEM_FALTA' => $temFalta,
                'TIPO_FALTA' => $tipoFalta
            ];
            
            // Resumo
            $resumo['TOTAL_QTD_PRODUZIDA'] += $quantidade;
            $resumo['TOTAL_PONTOS'] += $pontosComDesconto;
            if (!$temPontuacao) $resumo['TOTAL_SEM_PONTUACAO']++;
            if (!$temFaixa) $resumo['TOTAL_SEM_FAIXA']++;
            if (!$temVinculo) $resumo['TOTAL_SEM_VINCULO']++;
            
            // Agrupar por funcionário para tabela de produtividade
            $funcKey = $funcId ?: 'sem_vinculo_' . ($item['ID_MAQUINA'] ?? 0) . '_' . ($item['ID_CENTRO_TRAB'] ?? 0);
            if (!isset($produtividadePorFunc[$funcKey])) {
                $funcionariosUnicos[] = $funcKey;
                $produtividadePorFunc[$funcKey] = [
                    'NOME' => $item['NOME_FUNCIONARIO'] ?? 'Sem vínculo',
                    'CODIGO' => $item['COD_FUNCIONARIO'] ?? '-',
                    'FUNC_ID' => $funcId,
                    'CENTRO_TRABALHO' => $item['COD_CENTRO'] ? ($item['COD_CENTRO'] . ' - ' . $item['DESC_CENTRO']) : '-',
                    'RECURSO' => $item['COD_MAQUINA'] ? ($item['COD_MAQUINA'] . ' - ' . $item['DESC_MAQUINA']) : '-',
                    'QTD_ITENS' => 0,
                    'QTD_PRODUZIDA' => 0,
                    'TOTAL_PONTOS' => 0,
                    'TEM_PONTUACAO' => true,
                    'TEM_FAIXA' => $temFaixa,
                    'TEM_VINCULO' => $temVinculo,
                    'TEM_FALTA' => $temFalta,
                    'TIPO_FALTA' => $tipoFalta
                ];
            }
            $produtividadePorFunc[$funcKey]['QTD_ITENS']++;
            $produtividadePorFunc[$funcKey]['QTD_PRODUZIDA'] += $quantidade;
            $produtividadePorFunc[$funcKey]['TOTAL_PONTOS'] += $pontosComDesconto;
            if (!$temPontuacao) $produtividadePorFunc[$funcKey]['TEM_PONTUACAO'] = false;
        }
        
        $resumo['TOTAL_FUNCIONARIOS'] = count($funcionariosUnicos);

        return [
            'resumo' => $resumo,
            'produtividade' => array_values($produtividadePorFunc),
            'apontamentos' => $apontamentos
        ];
    }

    /**
     * Obter comissões calculadas para um período
     * 
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @param string|null $status Filtro de status (opcional)
     * @return array Dados formatados
     */
    public function getComissoes(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null, ?string $status = null): array
    {
        $comissaoModel = new Comissao();
        // OTIMIZADO: Usa versão batch que faz ~6 queries ao invés de N*5 queries
        $resultado = $comissaoModel->calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

        $funcionarios = $resultado['funcionarios'] ?? [];
        $periodoFormatado = date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim));
        
        $comissoes = [];
        foreach ($funcionarios as $func) {
            $faixaDesc = $this->formatarDescricaoFaixa($func);
            
            $comissoes[] = [
                'FUNC_ID' => $func['func_id'] ?? null,
                'CODIGO_FUNC' => $func['cod_func'] ?? '',
                'COD_FUNC' => $func['cod_func'] ?? '',
                'NOME_FUNC' => $func['nome_func'] ?? '',
                'PERIODO' => $periodoFormatado,
                'CENTRO_TRAB_ID' => $func['centro_trab_id'] ?? null,
                'CENTRO_TRABALHO' => $func['cod_centro'] ? ($func['cod_centro'] . ' - ' . $func['desc_centro']) : 'SEM CENTRO',
                'TOTAL_PONTOS' => $func['total_pontos_apos_falta'] ?? $func['total_pontos_bruto'] ?? 0,
                'VALOR_COMISSAO' => $func['valor_comissao_final'] ?? 0,
                'FAIXA_DESCRICAO' => $faixaDesc,
                'DIAS_TRABALHADOS' => $func['dias_trabalhados'] ?? 0,
                'DIAS_COM_FALTA' => $func['dias_com_falta'] ?? 0,
                'TEM_FALTA' => ($func['dias_com_falta'] ?? 0) > 0,
                'USA_REGRA_ESPECIFICA' => $func['usa_regra_especifica'] ?? false,
                'STATUS' => 'P'
            ];
        }

        // Calcular resumo e agrupamento por centro
        $resumo = $this->calcularResumoComissoes($comissoes);
        $porCentro = $this->agruparPorCentro($comissoes);

        // Filtrar por status se especificado
        if ($status) {
            $comissoes = array_filter($comissoes, fn($c) => ($c['STATUS'] ?? 'P') === $status);
        }

        return [
            'resumo' => $resumo,
            'porCentro' => array_values($porCentro),
            'comissoes' => array_values($comissoes)
        ];
    }

    /**
     * Obter detalhes de uma comissão específica
     * 
     * @param int $funcId ID do funcionário
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @return array Dados formatados
     */
    public function getComissaoDetalhes(int $funcId, string $dataInicio, string $dataFim, ?int $centroTrabId = null): array
    {
        // Buscar empresa do vínculo do funcionário
        $vinculos = Vinculo::listar(['id_funcionario' => $funcId, 'ativo' => 'S']);
        $emprIdApontamentos = !empty($vinculos) ? ($vinculos[0]['ID_EMPR'] ?? null) : null;

        $model = new ApontamentoProducao();
        $apontamentosBrutos = $model->listarApontamentosVinculados($funcId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);
        
        $apontamentos = [];
        foreach ($apontamentosBrutos as $ap) {
            $apontamentos[] = [
                'CODIGO' => $ap['CODIGO_PRODUTO'] ?? '-',
                'DESCRICAO' => $ap['DESC_PRODUTO'] ?? '-',
                'MASCARA' => $ap['MASCARA'] ?? '-',
                'RECURSO' => $ap['DESC_MAQUINA'] ?? '-',
                'QUANTIDADE' => $ap['QUANTIDADE'] ?? 0,
                'PONTOS_UP' => $ap['PONTOS_UP'] ?? 0,
                'PONTOS' => $ap['TOTAL_PONTOS'] ?? 0
            ];
        }

        return ['apontamentos' => $apontamentos];
    }

    /**
     * Processar comissões (cálculo simples)
     * 
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @param int|null $usuId ID do usuário que processou
     * @return array Resultado do processamento
     */
    public function processarComissoes(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null, ?int $usuId = null): array
    {
        $comissaoModel = new Comissao();
        $resultado = $comissaoModel->calcularComissaoTodos($dataInicio, $dataFim, $emprId, $centroTrabId);

        $processadas = 0;
        
        foreach ($resultado as $calc) {
            $dadosCalc = [
                'funcionario_id' => $calc['FUNC_ID'] ?? $calc['funcionario_id'],
                'faixa_id' => $calc['FAIXA_ID'] ?? $calc['faixa_id'],
                'dt_inicio' => $dataInicio,
                'dt_fim' => $dataFim,
                'total_pontos' => $calc['TOTAL_PONTOS'] ?? $calc['total_pontos'],
                'valor_comissao' => $calc['VALOR_COMISSAO'] ?? $calc['valor_comissao'],
                'id_usuario_proc' => $usuId
            ];
            
            if ($comissaoModel->salvarCalculo($dadosCalc)) {
                $processadas++;
            }
        }

        return [
            'message' => 'Comissões processadas com sucesso',
            'processadas' => $processadas
        ];
    }

    /**
     * Processar comissões completo (com todas as regras)
     * 
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @param int|null $usuId ID do usuário que processou
     * @return array Resultado do processamento
     */
    public function processarComissoesCompleto(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null, ?int $usuId = null): array
    {
        $comissaoModel = new Comissao();
        // OTIMIZADO: Usa versão batch que faz ~6 queries ao invés de N*5 queries
        $resultado = $comissaoModel->calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

        $funcionarios = $resultado['funcionarios'] ?? [];
        $processadas = 0;
        $totais = [
            'total_funcionarios' => count($funcionarios),
            'total_com_falta' => 0,
            'total_com_retrabalho' => 0,
            'total_com_regra_especifica' => 0,
            'total_geral_comissao' => 0
        ];
        
        foreach ($funcionarios as $calc) {
            // Contabilizar totais
            if (!empty($calc['dias_com_falta']) && $calc['dias_com_falta'] > 0) {
                $totais['total_com_falta']++;
            }
            if (!empty($calc['total_retrabalho']) && $calc['total_retrabalho'] > 0) {
                $totais['total_com_retrabalho']++;
            }
            if (!empty($calc['usa_regra_especifica']) && $calc['usa_regra_especifica']) {
                $totais['total_com_regra_especifica']++;
            }
            $totais['total_geral_comissao'] += $calc['valor_comissao_final'] ?? $calc['VALOR_COMISSAO'] ?? 0;

            // Salvar
            $dadosCalc = [
                'funcionario_id' => $calc['func_id'] ?? $calc['FUNC_ID'],
                'faixa_id' => $calc['faixa_aplicada']['ID_FAIXA'] ?? null,
                'dt_inicio' => $dataInicio,
                'dt_fim' => $dataFim,
                'total_pontos' => $calc['total_pontos_apos_falta'] ?? $calc['TOTAL_PONTOS'],
                'valor_comissao' => $calc['valor_comissao_final'] ?? $calc['VALOR_COMISSAO'],
                'id_usuario_proc' => $usuId
            ];
            
            if ($comissaoModel->salvarCalculo($dadosCalc)) {
                $processadas++;
            }
        }

        return [
            'message' => 'Comissões processadas com sucesso',
            'processadas' => $processadas,
            'resultado' => $totais
        ];
    }

    /**
     * Aprovar comissões
     * 
     * @param array $comissoes Array de comissões a aprovar
     * @param int $usuId ID do usuário
     * @return array Resultado
     */
    public function aprovarComissoes(array $comissoes, int $usuId): array
    {
        $comissaoModel = new Comissao();
        $aprovadas = 0;

        foreach ($comissoes as $comissao) {
            $idComissao = $comissao['ID_COMISSAO'] ?? $comissao['id_comissao'] ?? null;
            
            if ($idComissao) {
                if ($comissaoModel->aprovar($idComissao, $usuId)) {
                    $aprovadas++;
                }
            } else {
                // Criar e aprovar nova comissão
                $dadosCalc = [
                    'funcionario_id' => $comissao['FUNC_ID'] ?? $comissao['func_id'],
                    'faixa_id' => $comissao['FAIXA_ID'] ?? $comissao['faixa_id'],
                    'dt_inicio' => $comissao['DATA_INICIO'] ?? $comissao['data_inicio'],
                    'dt_fim' => $comissao['DATA_FIM'] ?? $comissao['data_fim'],
                    'total_pontos' => $comissao['TOTAL_PONTOS'] ?? $comissao['total_pontos'],
                    'valor_comissao' => $comissao['VALOR_COMISSAO'] ?? $comissao['valor_comissao'],
                    'id_usuario_proc' => $usuId,
                    'status' => Comissao::STATUS_APROVADO
                ];
                
                if ($comissaoModel->salvarCalculo($dadosCalc)) {
                    $aprovadas++;
                }
            }
        }

        return [
            'message' => "{$aprovadas} comissão(ões) aprovada(s)"
        ];
    }

    /**
     * Cancelar comissões
     * 
     * @param array $comissoes Array de comissões a cancelar
     * @param int $usuId ID do usuário
     * @param string|null $motivo Motivo do cancelamento
     * @return array Resultado
     */
    public function cancelarComissoes(array $comissoes, int $usuId, ?string $motivo = null): array
    {
        $comissaoModel = new Comissao();
        $canceladas = 0;

        foreach ($comissoes as $comissao) {
            $idComissao = $comissao['ID_COMISSAO'] ?? $comissao['id_comissao'] ?? null;
            
            if ($idComissao) {
                if ($comissaoModel->cancelar($idComissao, $usuId, $motivo)) {
                    $canceladas++;
                }
            }
        }

        return [
            'message' => "{$canceladas} comissão(ões) cancelada(s)"
        ];
    }

    /**
     * Obter relatório detalhado por funcionário
     * 
     * @param int $funcionarioId ID do funcionário
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @return array Dados completos do relatório
     */
    public function getRelatorioFuncionario(int $funcionarioId, string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null): array
    {
        // Buscar dados do funcionário
        $funcModel = new Funcionario();
        $funcionario = $funcModel->buscarPorId($funcionarioId);

        if (!$funcionario) {
            throw new \Exception('Funcionário não encontrado');
        }

        // Buscar vínculo do funcionário
        $filtrosVinculo = ['id_funcionario' => $funcionarioId, 'ativo' => 'S'];
        $vinculos = Vinculo::listar($filtrosVinculo) ?: [];
        $vinculo = !empty($vinculos) ? $vinculos[0] : null;
        
        // Se não informou centro, usar o do vínculo
        if (!$centroTrabId && $vinculo) {
            $centroTrabId = $vinculo['ID_CENTRO_TRAB'];
        }
        
        // Usar a empresa do vínculo para buscar apontamentos (se existir vínculo)
        $emprIdApontamentos = $vinculo ? $vinculo['ID_EMPR'] : $emprId;

        // Buscar pontos por dia
        $apontModel = new ApontamentoProducao();
        $diario = $apontModel->pontosPorDiaFuncionario($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);

        // Buscar apontamentos detalhados
        $apontamentos = $apontModel->listarApontamentosVinculados($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);

        // Verificar faltas
        $faltaModel = new FaltaFuncionario();
        $faltas = $faltaModel->verificarFaltasPeriodo($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos);
        $diasComFalta = $this->mapearDiasComFalta($faltas);

        // Calcular comissão
        $comissaoModel = new Comissao();
        $resultadoComissao = $comissaoModel->calcularComissaoCompleta(
            $funcionarioId,
            $dataInicio,
            $dataFim,
            $emprIdApontamentos,
            $centroTrabId
        );
        
        $totalComissao = $resultadoComissao['valor_comissao_final'] ?? 0;
        $faixaAplicada = $resultadoComissao['faixa_aplicada'] ?? null;
        $regraAplicada = $resultadoComissao['regra_aplicada'] ?? null;
        $valorPorPonto = $this->getValorPorPonto($faixaAplicada, $regraAplicada);

        // Processar diário com faltas e comissões
        $diario = $this->processarDiarioComFaltas($diario, $diasComFalta, $valorPorPonto);

        // Calcular totais
        $totalPontos = array_sum(array_column($diario, 'TOTAL_PONTOS_ORIGINAL'));
        $totalPontosAposFalta = array_sum(array_column($diario, 'PONTOS_COM_DESCONTO'));

        // Montar resumo
        $resumo = [
            'TOTAL_APONTAMENTOS' => count($apontamentos),
            'TOTAL_PONTOS' => $totalPontos,
            'TOTAL_PONTOS_APOS_FALTA' => $totalPontosAposFalta,
            'TOTAL_COMISSAO' => $totalComissao,
            'VALOR_COMISSAO_BRUTO' => $resultadoComissao['valor_comissao_bruto'] ?? 0,
            'DESCONTO_RETRABALHO' => $resultadoComissao['desconto_retrabalho'] ?? 0,
            'MEDIA_DIARIA' => count($diario) > 0 ? $totalPontos / count($diario) : 0,
            'DIAS_TRABALHADOS' => count($diario),
            'DIAS_COM_FALTA' => count($faltas),
            'FAIXA_APLICADA' => $faixaAplicada,
            'REGRA_APLICADA' => $regraAplicada,
            'USA_REGRA_ESPECIFICA' => $resultadoComissao['usa_regra_especifica'] ?? false,
            'VALOR_POR_PONTO' => $valorPorPonto
        ];

        // Buscar comissões já salvas
        $comissoesSalvas = $comissaoModel->listarCalculos(null, $dataInicio, $dataFim, $funcionarioId);

        return [
            'funcionario' => $funcionario,
            'resumo' => $resumo,
            'diario' => $diario,
            'apontamentos' => $apontamentos,
            'comissoes' => $comissoesSalvas,
            'vinculos' => $vinculos,
            'faltas' => $faltas
        ];
    }

    // ==================== MÉTODOS AUXILIARES (privados) ====================

    /**
     * Calcular pontos com desconto de falta
     */
    private function calcularPontosComDesconto(float $totalPontos, bool $temFalta, ?string $tipoFalta): float
    {
        if (!$temFalta) {
            return $totalPontos;
        }

        if ($tipoFalta === 'I') {
            // Falta integral: zera os pontos
            return 0;
        }

        // Falta parcial: 50% dos pontos
        return $totalPontos * 0.5;
    }

    /**
     * Formatar descrição da faixa aplicada
     */
    private function formatarDescricaoFaixa(array $func): string
    {
        if (!empty($func['faixa_aplicada']['descricao'])) {
            return $func['faixa_aplicada']['descricao'];
        }
        
        if (!empty($func['regra_aplicada']['descricao'])) {
            return 'Regra: ' . $func['regra_aplicada']['descricao'];
        }
        
        return 'Sem faixa';
    }

    /**
     * Calcular resumo das comissões
     */
    private function calcularResumoComissoes(array $comissoes): array
    {
        $resumo = [
            'TOTAL_FUNCIONARIOS' => count($comissoes),
            'TOTAL_PONTOS' => 0,
            'TOTAL_COMISSAO' => 0,
            'PENDENTES' => count($comissoes),
            'APROVADOS' => 0,
            'CANCELADOS' => 0
        ];

        foreach ($comissoes as $c) {
            $resumo['TOTAL_PONTOS'] += $c['TOTAL_PONTOS'];
            $resumo['TOTAL_COMISSAO'] += $c['VALOR_COMISSAO'];
        }

        return $resumo;
    }

    /**
     * Agrupar comissões por centro de trabalho
     */
    private function agruparPorCentro(array $comissoes): array
    {
        $porCentro = [];
        
        foreach ($comissoes as $c) {
            $centro = $c['CENTRO_TRABALHO'];
            if (!isset($porCentro[$centro])) {
                $porCentro[$centro] = [
                    'CENTRO_TRABALHO' => $centro,
                    'TOTAL_FUNCIONARIOS' => 0,
                    'TOTAL_PONTOS' => 0,
                    'TOTAL_COMISSAO' => 0
                ];
            }
            $porCentro[$centro]['TOTAL_FUNCIONARIOS']++;
            $porCentro[$centro]['TOTAL_PONTOS'] += $c['TOTAL_PONTOS'];
            $porCentro[$centro]['TOTAL_COMISSAO'] += $c['VALOR_COMISSAO'];
        }

        return $porCentro;
    }

    /**
     * Mapear dias com falta para array indexado por data
     */
    private function mapearDiasComFalta(array $faltas): array
    {
        $diasComFalta = [];
        
        foreach ($faltas as $f) {
            $dtFalta = $f['DT_FALTA'] ?? null;
            if ($dtFalta) {
                // Converter formato Oracle para YYYY-MM-DD se necessário
                if (preg_match('/^\d{2}-[A-Z]{3}-\d{2}$/', $dtFalta)) {
                    $dtFalta = date('Y-m-d', strtotime($dtFalta));
                }
                $diasComFalta[$dtFalta] = [
                    'tipo' => $f['TIPO_FALTA'],
                    'motivo' => $f['MOTIVO'] ?? null
                ];
            }
        }

        return $diasComFalta;
    }

    /**
     * Obter valor por ponto da faixa ou regra
     */
    private function getValorPorPonto(?array $faixaAplicada, ?array $regraAplicada): float
    {
        if ($faixaAplicada) {
            return floatval($faixaAplicada['valor'] ?? 0);
        }
        
        if ($regraAplicada) {
            return floatval($regraAplicada['valor'] ?? 0);
        }

        return 0;
    }

    /**
     * Processar diário adicionando informações de falta e comissão
     */
    private function processarDiarioComFaltas(array $diario, array $diasComFalta, float $valorPorPonto): array
    {
        foreach ($diario as &$dia) {
            // Normalizar campo DATA
            $dataStr = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
            $dia['DATA'] = $dataStr;
            $pontosDia = floatval($dia['TOTAL_PONTOS'] ?? 0);
            $dia['TOTAL_PONTOS_ORIGINAL'] = $pontosDia;
            
            // Verificar falta
            if ($dataStr && isset($diasComFalta[$dataStr])) {
                $dia['TEM_FALTA'] = true;
                $dia['TIPO_FALTA'] = $diasComFalta[$dataStr]['tipo'];
                $dia['MOTIVO_FALTA'] = $diasComFalta[$dataStr]['motivo'];
                
                if ($diasComFalta[$dataStr]['tipo'] === 'I') {
                    $dia['PONTOS_COM_DESCONTO'] = 0;
                    $dia['COMISSAO_DIA'] = 0;
                } else {
                    $pontosComDesconto = $pontosDia * 0.5;
                    $dia['PONTOS_COM_DESCONTO'] = $pontosComDesconto;
                    $dia['COMISSAO_DIA'] = $pontosComDesconto * $valorPorPonto;
                }
            } else {
                $dia['TEM_FALTA'] = false;
                $dia['TIPO_FALTA'] = null;
                $dia['MOTIVO_FALTA'] = null;
                $dia['PONTOS_COM_DESCONTO'] = $pontosDia;
                $dia['COMISSAO_DIA'] = $pontosDia * $valorPorPonto;
            }
        }

        return $diario;
    }
}
