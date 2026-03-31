<?php

namespace src\handlers\Faturamento;

use src\models\Faturamento\MetaEmpresa;

/**
 * Handler de Meta Empresa
 * Orquestra chamadas ao model e formata dados
 */
class MetaEmpresaHandler
{
    /**
     * Listar todas as metas
     * @param string|null $mesAno
     * @return array
     */
    public static function listar(?string $mesAno = null): array
    {
        $dados = MetaEmpresa::listar($mesAno);
        
        return [
            'sucesso' => true,
            'dados' => $dados,
            'total' => count($dados),
            'ultima_atualizacao' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Buscar meta específica
     * @param int $emprId
     * @param string $mesAno
     * @return array
     */
    public static function buscar(int $emprId, string $mesAno): array
    {
        $dados = MetaEmpresa::buscar($emprId, $mesAno);
        
        if ($dados) {
            return [
                'sucesso' => true,
                'dados' => $dados
            ];
        }
        
        return [
            'sucesso' => false,
            'mensagem' => 'Meta não encontrada'
        ];
    }

    /**
     * Salvar meta (inserir ou atualizar)
     * @param array $dados
     * @return array
     */
    public static function salvar(array $dados): array
    {
        // Validações
        if (empty($dados['empr_id']) || !is_numeric($dados['empr_id'])) {
            return ['sucesso' => false, 'mensagem' => 'Empresa é obrigatória'];
        }
        
        if (empty($dados['mes_ano'])) {
            return ['sucesso' => false, 'mensagem' => 'Mês/Ano é obrigatório'];
        }
        
        if (!isset($dados['meta']) || !is_numeric($dados['meta'])) {
            return ['sucesso' => false, 'mensagem' => 'Meta de faturamento é obrigatória'];
        }
        
        if (!isset($dados['meta_estoque']) || !is_numeric($dados['meta_estoque'])) {
            return ['sucesso' => false, 'mensagem' => 'Meta de estoque é obrigatória'];
        }
        
        $emprId = (int) $dados['empr_id'];
        $mesAno = $dados['mes_ano'];
        $meta = (float) $dados['meta'];
        $metaEstoque = (float) $dados['meta_estoque'];
        
        // Verifica se já existe
        $existente = MetaEmpresa::buscar($emprId, $mesAno);
        
        if ($existente) {
            // Atualizar
            $resultado = MetaEmpresa::atualizar($emprId, $mesAno, $meta, $metaEstoque);
            $acao = 'atualizada';
        } else {
            // Inserir
            $resultado = MetaEmpresa::inserir($emprId, $mesAno, $meta, $metaEstoque);
            $acao = 'cadastrada';
        }
        
        if ($resultado) {
            return [
                'sucesso' => true,
                'mensagem' => "Meta {$acao} com sucesso!"
            ];
        }
        
        return [
            'sucesso' => false,
            'mensagem' => 'Erro ao salvar meta. Tente novamente.'
        ];
    }

    /**
     * Excluir meta
     * @param int $emprId
     * @param string $mesAno
     * @return array
     */
    public static function excluir(int $emprId, string $mesAno): array
    {
        $resultado = MetaEmpresa::excluir($emprId, $mesAno);
        
        if ($resultado) {
            return [
                'sucesso' => true,
                'mensagem' => 'Meta excluída com sucesso!'
            ];
        }
        
        return [
            'sucesso' => false,
            'mensagem' => 'Erro ao excluir meta. Tente novamente.'
        ];
    }

    /**
     * Listar empresas disponíveis
     * @return array
     */
    public static function listarEmpresas(): array
    {
        $dados = MetaEmpresa::listarEmpresas();
        
        return [
            'sucesso' => true,
            'dados' => $dados
        ];
    }
}
