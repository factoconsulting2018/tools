<?php

/* @var $this yii\web\View */
/* @var $slidesCount int */
/* @var $buttonsCount int */
/* @var $totalConsultas int */
/* @var $usageSummary array */
/* @var $recentHacienda array */
/* @var $chartLabels array */
/* @var $chartSeries array */
/* @var $monthlyRows array */
/* @var $yearlyRows array */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;

$this->title = 'Panel de Administración';
$this->params['breadcrumbs'][] = $this->title;

$chartLabelsJson = Json::encode($chartLabels);
$chartSeriesJson = Json::encode($chartSeries);
$monthlyJson = Json::encode($monthlyRows);
$yearlyJson = Json::encode($yearlyRows);
$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js', ['defer' => true]);
$this->registerJs(
    <<<JS
    document.addEventListener('DOMContentLoaded', function () {
        const labels = $chartLabelsJson;
        const series = $chartSeriesJson;
        const ctx = document.getElementById('usageChart');
        if (ctx && labels.length) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Consultas Hacienda',
                            data: series.hacienda || [],
                            borderColor: '#1E88E5',
                            backgroundColor: 'rgba(30, 136, 229, 0.15)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Visor XML',
                            data: series.xml || [],
                            borderColor: '#8E24AA',
                            backgroundColor: 'rgba(142, 36, 170, 0.15)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Precio del Dólar',
                            data: series.dolar || [],
                            borderColor: '#00897B',
                            backgroundColor: 'rgba(0, 137, 123, 0.15)',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }
                }
            });
        }

        function buildSummaryTable(id, rows, label) {
            const container = document.getElementById(id);
            if (!container) {
                return;
            }
            if (!rows.length) {
                container.innerHTML = '<p class="text-muted">No hay datos registrados todavía.</p>';
                return;
            }

            const header = ['Periodo', 'Consultas Hacienda', 'Visor XML', 'Precio del Dólar'];
            const table = document.createElement('table');
            table.className = 'table table-striped table-sm';
            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');
            header.forEach(function (col) {
                const th = document.createElement('th');
                th.textContent = col;
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            const grouped = {};
            rows.forEach(function (row) {
                const period = row.period;
                if (!grouped[period]) {
                    grouped[period] = { hacienda: 0, xml: 0, dolar: 0 };
                }
                if (row.type === 'hacienda') {
                    grouped[period].hacienda += parseInt(row.total, 10) || 0;
                }
                if (row.type === 'xml') {
                    grouped[period].xml += parseInt(row.total, 10) || 0;
                }
                if (row.type === 'dolar') {
                    grouped[period].dolar += parseInt(row.total, 10) || 0;
                }
            });

            Object.keys(grouped).sort().reverse().forEach(function (period) {
                const tr = document.createElement('tr');
                const tdPeriod = document.createElement('td');
                tdPeriod.textContent = label === 'mensual' ? period : period;
                tr.appendChild(tdPeriod);

                ['hacienda', 'xml', 'dolar'].forEach(function (key) {
                    const td = document.createElement('td');
                    td.textContent = grouped[period][key];
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            container.innerHTML = '';
            container.appendChild(table);
        }

        buildSummaryTable('monthly-usage', $monthlyJson, 'mensual');
        buildSummaryTable('yearly-usage', $yearlyJson, 'anual');
    });
JS
, \yii\web\View::POS_END);
?>

<div class="admin-container">
    <div class="admin-header">
        <h1><?= Html::encode($this->title) ?></h1>
        <p>Gestiona el contenido de tu sitio web</p>
    </div>

    <div class="admin-nav">
        <a href="<?= \yii\helpers\Url::to(['admin/slides']) ?>">
            <i class="material-icons">image</i> Gestionar Slides
        </a>
        <a href="<?= \yii\helpers\Url::to(['admin/buttons']) ?>">
            <i class="material-icons">link</i> Gestionar Botones
        </a>
    </div>

    <div class="row" style="margin-top: 2rem;">
        <div class="col-md-3">
            <div class="admin-metric-card">
                <h2><?= Html::encode($slidesCount) ?></h2>
                <p>Slides Configurados</p>
                <a href="<?= Url::to(['admin/slides']) ?>" class="btn btn-light">Gestionar</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-metric-card">
                <h2><?= Html::encode($buttonsCount) ?></h2>
                <p>Botones Configurados</p>
                <a href="<?= Url::to(['admin/buttons']) ?>" class="btn btn-light">Gestionar</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-metric-card">
                <h2><?= Html::encode($totalConsultas) ?></h2>
                <p>Eventos Registrados</p>
                <span class="metric-subtitle">Consultas, visor XML y dólar</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-metric-card">
                <h2><?= Html::encode($usageSummary['hacienda'] ?? 0) ?></h2>
                <p>Consultas Hacienda</p>
                <span class="metric-subtitle">Últimos registros: <?= Html::encode(count($recentHacienda)) ?></span>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 2rem;">
        <div class="col-md-8">
            <div class="admin-card">
                <div class="admin-card__header">
                    <h3>Uso de funcionalidades (14 días)</h3>
                    <div class="admin-card__actions">
                        <a href="<?= Url::to(['admin/usage-export']) ?>" class="btn btn-primary btn-sm">Descargar CSV</a>
                    </div>
                </div>
                <div class="admin-card__body" style="height: 320px;">
                    <canvas id="usageChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card">
                <div class="admin-card__header">
                    <h3>Resumen por tipo</h3>
                </div>
                <div class="admin-card__body">
                    <ul class="admin-summary-list">
                        <li>
                            <span>Consultas Hacienda</span>
                            <strong><?= Html::encode($usageSummary['hacienda'] ?? 0) ?></strong>
                        </li>
                        <li>
                            <span>Errores Hacienda</span>
                            <strong><?= Html::encode($usageSummary['hacienda_error'] ?? 0) ?></strong>
                        </li>
                        <li>
                            <span>Visor XML</span>
                            <strong><?= Html::encode($usageSummary['xml'] ?? 0) ?></strong>
                        </li>
                        <li>
                            <span>Errores XML</span>
                            <strong><?= Html::encode($usageSummary['xml_error'] ?? 0) ?></strong>
                        </li>
                        <li>
                            <span>Precio del Dólar</span>
                            <strong><?= Html::encode($usageSummary['dolar'] ?? 0) ?></strong>
                        </li>
                        <li>
                            <span>Errores Precio del Dólar</span>
                            <strong><?= Html::encode($usageSummary['dolar_error'] ?? 0) ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 2rem;">
        <div class="col-md-6">
            <div class="admin-card">
                <div class="admin-card__header">
                    <h3>Últimas consultas Hacienda</h3>
                </div>
                <div class="admin-card__body">
                    <?php if (empty($recentHacienda)): ?>
                        <p class="text-muted">Aún no se registran consultas.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Identificación</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentHacienda as $row): ?>
                                        <tr>
                                            <td><?= Html::encode($row['created_at']) ?></td>
                                            <td><?= Html::encode($row['identifier'] ?: 'N/A') ?></td>
                                            <td>
                                                <span class="badge <?= $row['type'] === 'hacienda' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= Html::encode($row['type'] === 'hacienda' ? 'Éxito' : 'Error') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card">
                <div class="admin-card__header">
                    <h3>Resumen Mensual</h3>
                </div>
                <div class="admin-card__body" id="monthly-usage">
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card">
                <div class="admin-card__header">
                    <h3>Resumen Anual</h3>
                </div>
                <div class="admin-card__body" id="yearly-usage">
                </div>
            </div>
        </div>
    </div>
</div>

