<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aviso de Recebimento</title>
    <link rel="stylesheet" href="<?= $base ?>src/css/cd-dashboard.css">
</head>
<body style="background: #f0f0f0; margin: 0; padding: 0; font-family: 'Arial', 'Helvetica', sans-serif;">
    <div class="cd-dashboard-container">
        <!-- Header -->
        <header class="cd-header">
            <div class="cd-logo">
                <img src="https://systemcolchoes.blob.core.windows.net/site-gazin-colchoes/prod/Logo_Gazin_6a2b1ee6aa.png" alt="Gazin Colchões" onerror="this.style.display='none'">
            </div>
            <div style="flex: 1; text-align: center;">
                <h1 class="cd-title">AVISO DE RECEBIMENTO</h1>
                <div style="font-size: 10px; color: rgba(255,255,255,0.8); margin-top: 2px;">
                    Última atualização: <span id="ultima-atualizacao">--:--:--</span>
                </div>
            </div>
            <div style="width: 100px;"></div>
            <div style="position: absolute; top: 22px; right: 32px; z-index: 10;">
                <a href="<?= $base ?>logout" style="background: #d9534f; color: #fff; padding: 7px 22px; font-size: 1.08rem; border-radius: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: none; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.2s;"
                   onmouseover="this.style.background='#c9302c'" onmouseout="this.style.background='#d9534f'">
                    <i class="bi bi-box-arrow-right" style="font-size: 1.2em;"></i> Sair
                </a>
            </div>
        </header>

        <!-- Filtros -->
        <div class="cd-info-bar">
            <div class="cd-info-item">
                <a href="<?= $base ?>cd" class="cd-btn-menu active">🏠 Dashboard</a>
                <a href="<?= $base ?>cd-calendario" class="cd-btn-menu">📅 Agendamento</a>
                <button id="btnAtualizar" class="cd-btn-refresh" onclick="carregarDados()">🔄 Atualizar</button>
            </div>
            <div class="cd-info-item">
                <span class="cd-info-value" id="dateTime">--/--/---- --:--:--</span>
            </div>
        </div>

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

    <script>
        const BASE = '<?= $base ?>';
        
        // Atualizar horário em tempo real
        function atualizarHora() {
            var agora = new Date();
            var dia = agora.getDate();
            var mes = agora.getMonth() + 1;
            var ano = agora.getFullYear();
            var hora = agora.getHours();
            var min = agora.getMinutes();
            var seg = agora.getSeconds();
            
            if (dia < 10) dia = '0' + dia;
            if (mes < 10) mes = '0' + mes;
            if (hora < 10) hora = '0' + hora;
            if (min < 10) min = '0' + min;
            if (seg < 10) seg = '0' + seg;
            
            var elemDateTime = document.getElementById('dateTime');
            if (elemDateTime) {
                elemDateTime.textContent = dia + '/' + mes + '/' + ano + ' ' + hora + ':' + min + ':' + seg;
            }
        }
        
        atualizarHora();
        setInterval(atualizarHora, 1000);
    </script>
    <script src="<?= $base ?>src/js/cd-dashboard.js"></script>
</body>
</html>