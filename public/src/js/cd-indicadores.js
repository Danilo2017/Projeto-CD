/**
 * CD Indicadores Mensais - JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    const filtro = document.getElementById('filtroMes');
    const hoje = new Date();
    filtro.value = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}`;

    filtro.addEventListener('change', carregarIndicadores);
    document.getElementById('btnMesAnterior').addEventListener('click', () => deslocarMes(-1));
    document.getElementById('btnProximoMes').addEventListener('click', () => deslocarMes(1));

    carregarIndicadores();
});

/**
 * Avança/volta o mês do filtro
 */
function deslocarMes(direcao) {
    const filtro = document.getElementById('filtroMes');
    const [ano, mes] = filtro.value.split('-').map(Number);
    const data = new Date(ano, mes - 1 + direcao, 1);
    filtro.value = `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}`;
    carregarIndicadores();
}

/**
 * Busca os indicadores do mês selecionado
 */
async function carregarIndicadores() {
    const mes = document.getElementById('filtroMes').value;
    if (!mes) return;

    try {
        const response = await fetch(BASE + 'cd-api-indicadores?mes=' + encodeURIComponent(mes));
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Erro ao carregar indicadores');
        }

        renderizarResumo(result.data.resumo);
        renderizarPorTipo(result.data.por_tipo, result.data.resumo);
        renderizarDiario(result.data.diario);
        renderizarGraficos(result.data.por_tipo, result.data.diario);
    } catch (error) {
        console.error('Erro ao carregar indicadores:', error);
        document.getElementById('tbodyTipo').innerHTML = `<tr><td colspan="6" class="cd-loading">${error.message}</td></tr>`;
        document.getElementById('tfootTipo').innerHTML = '';
        document.getElementById('tbodyDiario').innerHTML = `<tr><td colspan="5" class="cd-loading">${error.message}</td></tr>`;
    }
}

function renderizarResumo(resumo) {
    document.getElementById('resVeiculos').textContent = formatarInteiro(resumo.veiculos);
    document.getElementById('resPeso').textContent = formatarNumero(resumo.peso_ton, 2);
    document.getElementById('resVolume').textContent = formatarNumero(resumo.volume, 2);
    document.getElementById('resValor').textContent = formatarMoeda(resumo.valor);
}

function renderizarPorTipo(tipos, resumo) {
    document.getElementById('tbodyTipo').innerHTML = tipos.map(t => `
        <tr>
            <td>${t.tipo}</td>
            <td>${formatarInteiro(t.veiculos)}</td>
            <td>${formatarNumero(t.peso_ton, 2)}</td>
            <td>${formatarNumero(t.volume, 2)}</td>
            <td>${formatarMoeda(t.valor)}</td>
            <td>${formatarNumero(t.percentual, 1)}%</td>
        </tr>
    `).join('');

    document.getElementById('tfootTipo').innerHTML = `
        <tr class="ind-total">
            <td>TOTAL DO MÊS</td>
            <td>${formatarInteiro(resumo.veiculos)}</td>
            <td>${formatarNumero(resumo.peso_ton, 2)}</td>
            <td>${formatarNumero(resumo.volume, 2)}</td>
            <td>${formatarMoeda(resumo.valor)}</td>
            <td>${resumo.valor > 0 ? '100,0%' : '0,0%'}</td>
        </tr>
    `;
}

function renderizarDiario(dias) {
    document.getElementById('tbodyDiario').innerHTML = dias.map(d => `
        <tr class="${d.veiculos === 0 ? 'ind-zerado' : ''}">
            <td>${d.dia}</td>
            <td>${formatarInteiro(d.veiculos)}</td>
            <td>${formatarNumero(d.volume, 0)}</td>
            <td>${formatarNumero(d.peso_ton, 3)}</td>
            <td>${formatarMoeda(d.valor)}</td>
        </tr>
    `).join('');
}

// ========================================
// GRÁFICOS
// ========================================

const COR_SERIE = '#1d5fa8';
const COR_GRADE = '#eef1f5';
const COR_TEXTO = '#6b7280';
const COR_ROTULO = '#1a1d2e';

const graficos = {};

function renderizarGraficos(tipos, dias) {
    if (typeof Chart === 'undefined') return;

    const rotulosTipo = tipos.map(t => t.tipo);
    const rotulosDia = dias.map(d => d.dia);

    graficoBarras('graficoVeiculosTipo', rotulosTipo, tipos.map(t => t.veiculos), formatarInteiro);
    graficoBarras('graficoValorTipo', rotulosTipo, tipos.map(t => t.valor), formatarMoeda);
    graficoBarras('graficoPesoTipo', rotulosTipo, tipos.map(t => t.peso_ton), v => formatarNumero(v, 2));
    graficoBarras('graficoVolumeTipo', rotulosTipo, tipos.map(t => t.volume), v => formatarNumero(v, 0));

    graficoLinha('graficoValorDiario', rotulosDia, dias.map(d => d.valor), formatarMoeda);
    graficoLinha('graficoVolumeDiario', rotulosDia, dias.map(d => d.volume), v => formatarNumero(v, 0));
}

/**
 * Recria o gráfico no canvas (destrói o anterior ao trocar de mês)
 */
function criarGrafico(id, config) {
    if (graficos[id]) graficos[id].destroy();
    graficos[id] = new Chart(document.getElementById(id), config);
}

function opcoesEixos(formatador) {
    return {
        x: {
            grid: { display: false },
            ticks: { color: COR_TEXTO, font: { size: 11 } }
        },
        y: {
            beginAtZero: true,
            grace: '15%',
            grid: { color: COR_GRADE },
            border: { display: false },
            ticks: { color: COR_TEXTO, font: { size: 10 }, maxTicksLimit: 5, callback: v => formatador(v) }
        }
    };
}

function graficoBarras(id, rotulos, valores, formatador) {
    criarGrafico(id, {
        type: 'bar',
        data: {
            labels: rotulos,
            datasets: [{
                data: valores,
                backgroundColor: COR_SERIE,
                borderRadius: { topLeft: 4, topRight: 4 },
                borderSkipped: 'bottom',
                maxBarThickness: 48
            }]
        },
        plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : [],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => formatador(ctx.parsed.y) } },
                datalabels: {
                    anchor: 'end',
                    align: 'end',
                    offset: 2,
                    color: COR_ROTULO,
                    font: { size: 11, weight: '600' },
                    formatter: v => formatador(v)
                }
            },
            scales: opcoesEixos(formatador)
        }
    });
}

function graficoLinha(id, rotulos, valores, formatador) {
    criarGrafico(id, {
        type: 'line',
        data: {
            labels: rotulos,
            datasets: [{
                data: valores,
                borderColor: COR_SERIE,
                backgroundColor: COR_SERIE,
                borderWidth: 2,
                tension: 0,
                pointRadius: 2,
                pointHoverRadius: 5,
                pointHitRadius: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => formatador(ctx.parsed.y) } }
            },
            scales: {
                ...opcoesEixos(formatador),
                x: {
                    grid: { display: false },
                    ticks: { color: COR_TEXTO, font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 11 }
                }
            }
        }
    });
}

function formatarInteiro(valor) {
    return Number(valor || 0).toLocaleString('pt-BR');
}

function formatarNumero(valor, casas) {
    return Number(valor || 0).toLocaleString('pt-BR', {minimumFractionDigits: casas, maximumFractionDigits: casas});
}

function formatarMoeda(valor) {
    return Number(valor || 0).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
}
