<?php
// Verificar acesso ao módulo faturamento usando variáveis injetadas pelo Controller
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoFaturamento) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Dashboard - Faturamento Indústrias',
    'showNavbar' => true,
    'pageActive' => 'faturamento',
    'customCSS' => ['src/css/faturamento-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="faturamento-container">
    <!-- Header -->
    <header class="faturamento-header">
        <div class="faturamento-logo">
            <img src="https://systemcolchoes.blob.core.windows.net/site-gazin-colchoes/prod/Logo_Gazin_6a2b1ee6aa.png" alt="Gazin Colchões" onerror="this.style.display='none'">
        </div>
        <div class="faturamento-title-section">
            <h1 class="faturamento-title">FATURAMENTO INDÚSTRIAS</h1>
            <div class="faturamento-update-info">
                Última atualização: <span id="ultima-atualizacao">--:--:--</span> | Próxima em: <span id="proximo-update">5:00</span>
            </div>
        </div>
        <div class="faturamento-meta-box">
            <div class="faturamento-meta-label">META</div>
            <div class="faturamento-meta-value" id="metaGeral">Carregando...</div>
        </div>
    </header>

    <!-- Info Bar -->
    <div class="faturamento-info-bar">
        <div class="faturamento-info-item">
            <span class="faturamento-info-label">[Cód Emp]</span>
        </div>
        <div class="faturamento-info-item">
            <span class="faturamento-info-value">-</span>
        </div>
        <div class="faturamento-info-item">
            <span class="faturamento-info-date" id="dataHoraAtual">--/--/---- --:--:--</span>
        </div>
    </div>

    <!-- Métricas Principais -->
    <div class="faturamento-metrics-grid">
        <div class="faturamento-metric-card">
            <div class="faturamento-metric-label">FATURAMENTO BRUTO ACUMULADO</div>
            <div class="faturamento-metric-value" id="fatBruto">Carregando...</div>
        </div>
        <div class="faturamento-metric-card negative">
            <div class="faturamento-metric-label">DEVOLUÇÃO ACUMULADA</div>
            <div class="faturamento-metric-value" id="devolucao">Carregando...</div>
        </div>
        <div class="faturamento-metric-card">
            <div class="faturamento-metric-label">FATURAMENTO LÍQUIDO ACUMULADO</div>
            <div class="faturamento-metric-value" id="fatLiquido">Carregando...</div>
        </div>
        <div class="faturamento-metric-card">
            <div class="faturamento-metric-label">FATURAMENTO MÉDIA DIA</div>
            <div class="faturamento-metric-value" id="mediaDia">Carregando...</div>
        </div>
        <div class="faturamento-metric-card">
            <div class="faturamento-metric-label">META DIÁRIA</div>
            <div class="faturamento-metric-value" id="metaDiaria">Carregando...</div>
        </div>
        <div class="faturamento-metric-card">
            <div class="faturamento-metric-label">META ATUAL/DIÁRIA</div>
            <div class="faturamento-metric-value" id="metaAtualDiaria">Carregando...</div>
        </div>
        <div class="faturamento-metric-card highlight">
            <div class="faturamento-metric-label">% DA META ATINGIDA</div>
            <div class="faturamento-metric-value" id="percMeta">Carregando...</div>
        </div>
    </div>

    <!-- Pedidos em Carteira -->
    <div class="faturamento-section-header">PEDIDOS EM CARTEIRA</div>
    
    <div class="faturamento-pedidos-grid">
        <div class="faturamento-pedido-card">
            <div class="faturamento-pedido-label">PEDIDOS LIBERADOS</div>
            <div class="faturamento-pedido-value" id="pedidosLiberados">-</div>
        </div>
        <div class="faturamento-pedido-card">
            <div class="faturamento-pedido-label">PEDIDOS EM CARGA</div>
            <div class="faturamento-pedido-value" id="pedidosEmCarga">-</div>
        </div>
        <div class="faturamento-pedido-card">
            <div class="faturamento-pedido-label">PEDIDOS SEM CARGA</div>
            <div class="faturamento-pedido-value" id="pedidosSemCarga">-</div>
        </div>
        <div class="faturamento-pedido-card">
            <div class="faturamento-pedido-label">PEDIDOS PLANEJADO</div>
            <div class="faturamento-pedido-value" id="pedidosPlanejado">-</div>
        </div>
        <div class="faturamento-pedido-card">
            <div class="faturamento-pedido-label">PEDIDOS PLAN + FAT LIQ</div>
            <div class="faturamento-pedido-value" id="pedidosPlanFatLiq">-</div>
        </div>
        <div class="faturamento-pedido-card highlight">
            <div class="faturamento-pedido-label">% DA META</div>
            <div class="faturamento-pedido-value" id="percMetaPedidos">-</div>
        </div>
    </div>

    <!-- Layout com Painel de Vendas e FAT LIQ x META -->
    <div class="faturamento-content-layout">
        <!-- Painel de Vendas Indústria -->
        <div class="faturamento-iframe-section">
            <div class="faturamento-iframe-header">PAINEL DE VENDAS INDÚSTRIA</div>
            <div class="faturamento-painel-vendas-tabela">
                <table class="faturamento-tabela-painel" id="tabela-painel-vendas">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Meta</th>
                            <th>Faturamento</th>
                            <th>% Atingido</th>
                            <th>Planejado</th>
                            <th>Fat Proj</th>
                            <th>% Proj</th>
                            <th>Carteira</th>
                            <th>Meta Estoq</th>
                            <th>Estoq Atual</th>
                            <th>% Estoq</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-painel-vendas-body">
                        <tr>
                            <td colspan="11" class="faturamento-loading">Carregando...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FAT LIQ X META -->
        <div class="faturamento-table-section">
            <div class="faturamento-iframe-header">FAT LIQ X META</div>
            <table class="faturamento-tabela-painel fat-meta">
                <thead>
                    <tr>
                        <th>Local</th>
                        <th>FAT LIQ</th>
                        <th>META</th>
                        <th>% ATINGIDO</th>
                    </tr>
                </thead>
                <tbody id="fat-meta-table">
                    <tr>
                        <td colspan="4" class="faturamento-loading">Carregando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="src/js/faturamento-dashboard.js?v=<?= time() ?>"></script>

<?= $render('footer') ?>
