/**
 * Dashboard: gráficos Chart.js + telemetria (polling de 60s em
 * dashboard.stats). O payload inicial vem de window.DASHBOARD (render
 * do servidor); cada refresh reaplica KPIs, gráficos, previsões,
 * listagens (HTML pronto do servidor) e indicadores secundários.
 * Pausa quando a aba fica oculta e sinaliza falhas sem descartar o
 * último estado válido.
 */
(function () {
    'use strict';

    var cfg = window.DASHBOARD;

    if (!cfg || !cfg.statsUrl) {
        return;
    }

    var charts = {};
    var timer = null;
    var busy = false;

    var KPI = {
        total: { format: function (v) { return num(v, 0); } },
        conformidade: { format: function (v) { return num(v, 1) + '%'; } },
        atraso: { format: function (v) { return num(v, 0); } },
        alta: { format: function (v) { return num(v, 0); } }
    };

    var TENDENCIA = { alta: 'Em alta', queda: 'Em queda', estavel: 'Estável' };

    function num(v, dec) {
        if (v === null || v === undefined || isNaN(v)) {
            return '—';
        }

        return Number(v).toLocaleString('pt-BR', {
            minimumFractionDigits: dec,
            maximumFractionDigits: dec
        });
    }

    function text(selector, value) {
        var el = document.querySelector(selector);

        if (el) {
            el.textContent = value;
        }
    }

    function swap(id, html) {
        var el = document.getElementById(id);

        if (el && typeof html === 'string') {
            el.innerHTML = html;
        }
    }

    function deltaHtml(delta, tone, dec, suffix) {
        if (delta === null || delta === undefined) {
            return '<span class="muted">sem base anterior</span>';
        }

        var color = tone === 'neutral'
            ? '#475569'
            : (delta > 0
                ? (tone === 'good' ? '#15803d' : '#b91c1c')
                : (delta < 0 ? (tone === 'good' ? '#b91c1c' : '#15803d') : '#64748b'));
        var label = (delta > 0 ? '+' : '') + num(delta, dec) + (suffix || '');

        return '<span style="color:' + color + '">' + label + '</span> <span class="muted">vs mês anterior</span>';
    }

    function setTelemetry(state) {
        var dot = document.getElementById('telemetry-dot');
        var msg = document.getElementById('telemetry-msg');

        if (!dot || !msg) {
            return;
        }

        if (state === 'loading') {
            dot.style.color = '#f59e0b';
            msg.textContent = '· atualizando…';
            msg.className = 'muted';
        } else if (state === 'error') {
            dot.style.color = '#ef4444';
            msg.textContent = '· falha ao atualizar, exibindo último resultado';
            msg.className = '';
            msg.style.color = '#b91c1c';
        } else {
            dot.style.color = '#16a34a';
            msg.textContent = '';
            msg.style.color = '';
        }
    }

    function makeChart(id, config) {
        var el = document.getElementById(id);

        if (!el || typeof Chart === 'undefined') {
            return null;
        }

        return new Chart(el, config);
    }

    function updateChart(chart, labels, values) {
        if (!chart) {
            return;
        }

        chart.data.labels = labels;
        chart.data.datasets[0].data = values;
        chart.update('none');
    }

    function initCharts(s) {
        if (typeof Chart === 'undefined' || !s || !s.charts) {
            return;
        }

        charts.status = makeChart('chart-status', {
            type: 'doughnut',
            data: {
                labels: s.charts.status.labels,
                datasets: [{
                    data: s.charts.status.values,
                    backgroundColor: ['#94a3b8', '#3b82f6', '#8b5cf6', '#ef4444', '#16a34a'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        charts.criticidade = makeChart('chart-criticidade', {
            type: 'bar',
            data: {
                labels: s.charts.criticidade.labels,
                datasets: [{
                    label: 'NCs',
                    data: s.charts.criticidade.values,
                    backgroundColor: ['#ef4444', '#f59e0b', '#16a34a'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        charts.evolucao = makeChart('chart-evolucao', {
            type: 'line',
            data: {
                labels: s.charts.evolucao.labels,
                datasets: [{
                    label: 'Conformidade',
                    data: s.charts.evolucao.values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        ticks: { callback: function (v) { return v + '%'; } }
                    }
                }
            }
        });

        charts.prazos = makeChart('chart-prazos', {
            type: 'bar',
            data: {
                labels: s.charts.prazos.labels,
                datasets: [{
                    label: 'NCs',
                    data: s.charts.prazos.values,
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#94a3b8'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    function apply(s) {
        if (s.generated_at) {
            text('#telemetry-time', s.generated_at);
        }

        if (s.kpi) {
            Object.keys(KPI).forEach(function (key) {
                if (s.kpi[key] === undefined) {
                    return;
                }

                text('[data-kpi="' + key + '"]', KPI[key].format(s.kpi[key]));

                var deltaEl = document.querySelector('[data-kpi-delta="' + key + '"]');

                if (deltaEl) {
                    deltaEl.innerHTML = deltaHtml(
                        s.kpi[key + '_delta'],
                        deltaEl.getAttribute('data-tone'),
                        Number(deltaEl.getAttribute('data-decimals')),
                        deltaEl.getAttribute('data-suffix')
                    );
                }
            });
        }

        if (s.charts) {
            updateChart(charts.status, s.charts.status.labels, s.charts.status.values);
            updateChart(charts.criticidade, s.charts.criticidade.labels, s.charts.criticidade.values);
            updateChart(charts.evolucao, s.charts.evolucao.labels, s.charts.evolucao.values);
            updateChart(charts.prazos, s.charts.prazos.labels, s.charts.prazos.values);
        }

        if (s.previsao) {
            text('[data-pred="projecao30"]', num(s.previsao.projecao30, 1) + '%');
            text('[data-pred="projecao60"]', num(s.previsao.projecao60, 1) + '%');
            text('[data-pred="projecao90"]', num(s.previsao.projecao90, 1) + '%');
            text('[data-pred="risco7"]', num(s.previsao.risco7, 0));
            text('[data-pred="tendencia"]', TENDENCIA[s.previsao.tendencia] || TENDENCIA.estavel);
        }

        if (s.secundarios) {
            Object.keys(s.secundarios).forEach(function (key) {
                var el = document.querySelector('[data-sec="' + key + '"]');

                if (!el) {
                    return;
                }

                var v = s.secundarios[key];

                if (key === 'prontuarioMedia') {
                    el.textContent = (v === null || v === undefined) ? '—' : num(Math.round(v), 0) + '%';
                } else {
                    el.textContent = num(v, 0);
                }
            });
        }

        if (s.html) {
            swap('alerts-panel', s.html.alertas);
            swap('setores-panel', s.html.setores);
            swap('responsaveis-panel', s.html.responsaveis);
            swap('evidencias-panel', s.html.evidencias);
        }
    }

    function refresh() {
        if (busy) {
            return;
        }

        busy = true;
        setTelemetry('loading');

        fetch(cfg.statsUrl, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (data) {
                apply(data);
                setTelemetry('ok');
            })
            .catch(function () {
                setTelemetry('error');
            })
            .then(function () {
                busy = false;
            });
    }

    function start() {
        if (!timer) {
            timer = setInterval(refresh, 60000);
        }
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    initCharts(cfg.initial);

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            refresh();
            start();
        }
    });

    start();
})();
