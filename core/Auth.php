<?php
namespace core;

class Auth extends Controller {

    /**
     * Valida se o usuário está logado
     * @param mixed $token (não usado atualmente, mantido para compatibilidade)
     */
    public function validaToken($token) {
        $islogado = $_SESSION['user'] ?? null;
        if (!$islogado || empty($islogado) || !isset($_SESSION['user'])) {
            $this->redirect('login');
            die;
        }
    }

    /**
     * Valida se o usuário tem acesso à rota solicitada
     * @param string $url A URL sendo acessada
     * @return bool true se tem acesso, false caso contrário
     */
    public function validarAcessoRota($url) {
        $user = $_SESSION['user'] ?? null;
        
        if (!$user) {
            return false;
        }
        
        // Extrair o prefixo da URL (primeira parte antes do hífen)
        $prefixoUrl = $this->extrairPrefixoUrl($url);
        
        // Se a URL não tem prefixo de módulo (rota pública ou do sistema), qualquer logado acessa
        if ($prefixoUrl === null) {
            return true;
        }
        
        $rotasPermitidas = $user['rotas_permitidas'] ?? [];
        
        // Se não tem rotas permitidas, não tem acesso a rotas de módulo
        if (empty($rotasPermitidas)) {
            return false;
        }
        
        // Admin (curinga *) tem acesso a tudo
        if (in_array('*', $rotasPermitidas)) {
            return true;
        }
        
        // Verificar se o prefixo da URL está na lista de rotas permitidas
        return in_array($prefixoUrl, $rotasPermitidas);
    }

    /**
     * Extrai o prefixo do módulo da URL
     * Ex: /comissao-relatorio -> 'comissao'
     * Ex: /cd-dashboard -> 'cd'
     * Ex: /permissao -> 'permissao'
     * Ex: /logout -> null (rota sem prefixo de módulo)
     * @param string $url
     * @return string|null O prefixo ou null se não for rota de módulo
     */
    private function extrairPrefixoUrl($url) {
        // Remover barra inicial
        $url = ltrim($url, '/');
        
        // Se URL vazia, não é rota de módulo
        if (empty($url)) {
            return null;
        }
        
        // Lista de rotas sem prefixo de módulo (sistema base)
        $rotasSistema = ['login', 'logout', 'health-check', 'sem-acesso'];
        
        // Rotas que qualquer usuário logado pode acessar (APIs de uso geral no login)
        $rotasPublicasLogado = [
            'comissao-api-empresas',
            'comissao-api-selecionar-empresa', 
            'comissao-api-empresa-selecionada'
        ];
        
        // Verificar se é rota pública para logados
        if (in_array($url, $rotasPublicasLogado)) {
            return null;
        }
        
        // Verificar se é rota do sistema
        foreach ($rotasSistema as $rotaSistema) {
            if ($url === $rotaSistema || strpos($url, $rotaSistema) === 0) {
                return null;
            }
        }
        
        // Extrair prefixo (parte antes do primeiro hífen ou a própria URL)
        $partes = explode('-', $url);
        $prefixo = $partes[0];
        
        // Mapeamento de prefixos que pertencem a outro módulo
        // Ex: 'meta' pertence ao módulo 'faturamento'
        $mapeamentoModulos = [
            'meta' => 'faturamento',
        ];
        
        // Se o prefixo tem um mapeamento, usar o módulo mapeado
        if (isset($mapeamentoModulos[$prefixo])) {
            return $mapeamentoModulos[$prefixo];
        }
        
        // Se o prefixo é igual à URL inteira e não tem hífen, 
        // verificar se é uma rota conhecida de módulo
        $modulosConhecidos = ['cd', 'comissao', 'permissao', 'faturamento', 'admin', 'pedidos', 'processo'];

        if (in_array($prefixo, $modulosConhecidos)) {
            return $prefixo;
        }

        // URL com hífen - usar o prefixo
        if (count($partes) > 1 && in_array($prefixo, $modulosConhecidos)) {
            return $prefixo;
        }
        
        // Rota privada sem prefixo de módulo - qualquer logado acessa
        return null;
    }

    /**
     * Nega acesso e redireciona/retorna erro conforme tipo de rota
     * @param string $url
     */
    public function negarAcesso($url) {
        // Se é uma API, retorna JSON 403
        if (strpos($url, '-api-') !== false || strpos($url, '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Acesso negado. Você não tem permissão para acessar este recurso.'
            ]);
            die;
        }
        
        // Se é página, redireciona
        $this->redirect('sem-acesso');
        die;
    }

    /**
     * Retorna a primeira rota permitida do usuário para redirect inteligente
     * @return string A rota de destino
     */
    public function getRotaInicialUsuario() {
        $user = $_SESSION['user'] ?? null;
        
        if (!$user) {
            return 'login';
        }
        
        $rotasPermitidas = $user['rotas_permitidas'] ?? [];
        
        // Se é admin ou não tem restrição, vai pro módulo padrão
        if (in_array('*', $rotasPermitidas)) {
            return 'comissao-relatorio';
        }
        
        // Mapear prefixo de rota para página inicial do módulo
        $rotasIniciais = [
            'comissao'   => 'comissao-relatorio',
            'cd'         => 'cd-dashboard',
            'faturamento'=> 'faturamento-dashboard',
            'pedidos'    => 'pedidos-transferencia',
            'processo'   => 'processo-troca-almox',
            'permissao'  => 'permissao',
        ];
        
        // Retornar a primeira rota disponível
        foreach ($rotasPermitidas as $prefixo) {
            if (isset($rotasIniciais[$prefixo])) {
                return $rotasIniciais[$prefixo];
            }
        }
        
        // Fallback
        return 'sem-acesso';
    }
}