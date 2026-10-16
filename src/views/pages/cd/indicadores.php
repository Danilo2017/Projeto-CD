<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoCd = $is_admin || in_array('cd', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoCd) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Indicadores Mensais - Recebimento CD',
    'showNavbar' => true,
    'pageActive' => 'cd-indicadores',
    'customCSS' => ['src/css/cd-dashboard.css', 'src/css/cd-indicadores.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="cd-dashboard-container">
    <!-- Filtro de mês -->
    <div class="ind-filtro">
        <button type="button" class="cd-btn-refresh" id="btnMesAnterior"><i class="bi bi-chevron-left"></i></button>
        <label for="filtroMes">Mês de referência:</label>
        <input type="month" id="filtroMes">
        <button type="button" class="cd-btn-refresh" id="btnProximoMes"><i class="bi bi-chevron-right"></i></button>
        <span class="ind-obs">Considera apenas agendamentos recebidos</span>
    </div>

    <!-- Cards de resumo -->
    <div class="cd-metrics-grid">
        <div class="cd-metric-card">
            <div class="cd-metric-icon"><i class="bi bi-truck"></i></div>
            <div class="cd-metric-info">
                <div class="cd-metric-label">TOTAL DE VEÍCULOS</div>
                <div class="cd-metric-value" id="resVeiculos">0</div>
            </div>
        </div>
        <div class="cd-metric-card">
            <div class="cd-metric-icon"><i class="bi bi-box-seam"></i></div>
            <div class="cd-metric-info">
                <div class="cd-metric-label">PESO TOTAL (TON)</div>
                <div class="cd-metric-value" id="resPeso">0</div>
            </div>
        </div>
        <div class="cd-metric-card">
            <div class="cd-metric-icon"><i class="bi bi-boxes"></i></div>
            <div class="cd-metric-info">
                <div class="cd-metric-label">VOLUME TOTAL</div>
                <div class="cd-metric-value" id="resVolume">0</div>
            </div>
        </div>
        <div class="cd-metric-card">
            <div class="cd-metric-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="cd-metric-info">
                <div class="cd-metric-label">VALOR DESCARGA (R$)</div>
                <div class="cd-metric-value" id="resValor">R$ 0,00</div>
            </div>
        </div>
    </div>

    <div class="ind-grid">
        <div class="ind-coluna">
        <!-- Por tipo de produto -->
        <div class="cd-table-section">
            <div class="cd-table-header">INDICADORES POR TIPO DE PRODUTO – MÊS</div>
            <table class="cd-data-table ind-table">
                <thead>
                    <tr>
                        <th>Tipo de produto</th>
                        <th>Veículos</th>
                        <th>Peso (Ton)</th>
                        <th>Volume</th>
                        <th>Valor descarga (R$)</th>
                        <th>% do valor total</th>
                    </tr>
                </thead>
                <tbody id="tbodyTipo">
                    <tr><td colspan="6" class="cd-loading">⏳ Carregando dados...</td></tr>
                </tbody>
                <tfoot id="tfootTipo"></tfoot>
            </table>
        </div>

        <!-- Gráficos por tipo -->
        <div class="ind-graficos ind-graficos-tipo">
            <div class="ind-grafico-card">
                <div class="ind-grafico-titulo">Veículos por tipo</div>
                <div class="ind-grafico-area"><canvas id="graficoVeiculosTipo"></canvas></div>
            </div>
            <div class="ind-grafico-card">
                <div class="ind-grafico-titulo">Valor de descarga por tipo (R$)</div>
                <div class="ind-grafico-area"><canvas id="graficoValorTipo"></canvas></div>
            </div>
            <div class="ind-grafico-card">
                <div class="ind-grafico-titulo">Peso por tipo (Ton)</div>
                <div class="ind-grafico-area"><canvas id="graficoPesoTipo"></canvas></div>
            </div>
            <div class="ind-grafico-card">
                <div class="ind-grafico-titulo">Volume por tipo</div>
                <div class="ind-grafico-area"><canvas id="graficoVolumeTipo"></canvas></div>
            </div>
        </div>

        <!-- Evolução diária -->
        <div class="ind-graficos ind-graficos-diario">
            <div class="ind-grafico-card">
                <div class="ind-grafico-titulo">Evolução diária do valor de descarga (R$)</div>
                <div class="ind-grafico-area ind-grafico-linha"><canvas id="graficoValorDiario"></canvas></div>
            </div>
            <div class="ind-grafico-card">
                <div class="ind-grafico-titulo">Evolução diária do volume recebido</div>
                <div class="ind-grafico-area ind-grafico-linha"><canvas id="graficoVolumeDiario"></canvas></div>
            </div>
        </div>
        </div>

        <!-- Acompanhamento diário -->
        <div class="cd-table-section">
            <div class="cd-table-header">ACOMPANHAMENTO DIÁRIO DO MÊS SELECIONADO</div>
            <table class="cd-data-table ind-table ind-table-compacta">
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Veículos</th>
                        <th>Volume</th>
                        <th>Peso (Ton)</th>
                        <th>Valor descarga (R$)</th>
                    </tr>
                </thead>
                <tbody id="tbodyDiario">
                    <tr><td colspan="5" class="cd-loading">⏳ Carregando dados...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<?= $render('footer', [
    'customJS' => ['src/js/cd-indicadores.js']
]) ?>
