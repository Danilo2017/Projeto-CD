<?php
// Verificar permissão de acesso
$acessoCd = $_SESSION['user']['acesso_cd'] ?? 'N';
if ($acessoCd !== 'S') {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Dashboard - Aviso de Recebimento',
    'showNavbar' => true,
    'pageActive' => 'dashboard',
    'customCSS' => ['src/css/cd-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0; font-family: \'Arial\', \'Helvetica\', sans-serif;'
]) ?>

<div class="cd-dashboard-container">
    <!-- Cards de Resumo do Mês -->
    <div class="cd-metrics-grid">
        <div class="cd-metric-card">
            <div class="cd-metric-label">TOTAL DO MÊS</div>
            <div class="cd-metric-value" id="totalAvisos">0</div>
        </div>
        <div class="cd-metric-card pendente">
            <div class="cd-metric-label">PENDENTES</div>
            <div class="cd-metric-value" id="totalPendentes">0</div>
        </div>
        <div class="cd-metric-card iniciado">
            <div class="cd-metric-label">INICIADOS</div>
            <div class="cd-metric-value" id="totalIniciados">0</div>
        </div>
        <div class="cd-metric-card finalizado">
            <div class="cd-metric-label">FINALIZADOS</div>
            <div class="cd-metric-value" id="totalFinalizados">0</div>
        </div>
    </div>

    <!-- Container para duas tabelas lado a lado -->
    <div class="cd-tables-container">
        <!-- Tabela de Avisos -->
        <div class="cd-table-section cd-table-avisos">
            <div class="cd-table-header">AVISOS DE RECEBIMENTO - DETALHAMENTO</div>
            <table class="cd-data-table" id="tabelaAvisos">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Almox</th>
                        <th>Placa</th>
                        <th>Chegada</th>
                        <th>Início</th>
                        <th>Término</th>
                        <th>Status</th>
                        <th>Crossdocking</th>
                    </tr>
                </thead>
                <tbody id="tabelaBody">
                    <tr>
                        <td colspan="8" class="cd-loading">⏳ Carregando dados...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tabela de Agendamentos Pendentes -->
        <div class="cd-table-section cd-table-agendamentos">
            <div class="cd-table-header">AGENDAMENTOS PENDENTES</div>
            <table class="cd-data-table" id="tabelaAgendamentos">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Agendamento</th>
                        <th>Fornecedor</th>
                        <th>Descrição</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="tabelaAgendamentosBody">
                    <tr>
                        <td colspan="5" class="cd-loading">⏳ Carregando dados...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/cd-dashboard.js']
]) ?>