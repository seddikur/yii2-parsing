<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $dailyStats array */
/* @var $topBrowsers array */
/* @var $detailedStats array */
/* @var $chartData array */
/* @var $availableOs array */
/* @var $availableArchitectures array */
/* @var $filters array */

$this->title = 'Анализ логов Nginx';
?>

<div class="site-index">
    <div class="jumbotron text-center bg-transparent">
        <h1 class="display-4">Анализ логов Nginx</h1>
        <p class="lead">Веб-приложение для парсинга и анализа логов веб-сервера nginx</p>
    </div>

    <div class="body-content">
        <!-- Фильтры -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Фильтры</h5>
                    </div>
                    <div class="card-body">
                        <?php $form = ActiveForm::begin([
                            'method' => 'get',
                            'options' => ['class' => 'form-inline'],
                        ]); ?>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Дата от:</label>
                                <input type="date" name="date_from" class="form-control" value="<?= $filters['dateFrom'] ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Дата до:</label>
                                <input type="date" name="date_to" class="form-control" value="<?= $filters['dateTo'] ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ОС:</label>
                                <select name="os" class="form-control">
                                    <option value="">Все</option>
                                    <?php foreach ($availableOs as $os): ?>
                                        <option value="<?= $os ?>" <?= $filters['os'] === $os ? 'selected' : '' ?>>
                                            <?= $os ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Архитектура:</label>
                                <select name="architecture" class="form-control">
                                    <option value="">Все</option>
                                    <?php foreach ($availableArchitectures as $arch): ?>
                                        <option value="<?= $arch ?>" <?= $filters['architecture'] === $arch ? 'selected' : '' ?>>
                                            <?= $arch ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <?= Html::submitButton('Применить', ['class' => 'btn btn-primary']) ?>
                                    <?= Html::a('Сбросить', ['site/index'], ['class' => 'btn btn-secondary']) ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Графики -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Количество запросов по дням</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="requestsChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Доля популярных браузеров (%)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="browsersChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Таблица статистики по дням -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Статистика по дням</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dailyStats)): ?>
                            <div class="alert alert-info">
                                Нет данных для отображения. Используйте консольную команду для загрузки логов.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>
                                                <?= Html::a('Дата', Url::current(['sort' => 'date', 'order' => $filters['sort'] === 'date' && $filters['order'] === 'asc' ? 'desc' : 'asc'])) ?>
                                                <?php if ($filters['sort'] === 'date'): ?>
                                                    <i class="fas fa-sort-<?= $filters['order'] === 'asc' ? 'up' : 'down' ?>"></i>
                                                <?php endif; ?>
                                            </th>
                                            <th>
                                                <?= Html::a('Число запросов', Url::current(['sort' => 'request_count', 'order' => $filters['sort'] === 'request_count' && $filters['order'] === 'asc' ? 'desc' : 'asc'])) ?>
                                                <?php if ($filters['sort'] === 'request_count'): ?>
                                                    <i class="fas fa-sort-<?= $filters['order'] === 'asc' ? 'up' : 'down' ?>"></i>
                                                <?php endif; ?>
                                            </th>
                                            <th>Самый популярный URL</th>
                                            <th>Самый популярный браузер</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dailyStats as $stat): ?>
                                            <tr>
                                                <td><?= Html::encode($stat['date']) ?></td>
                                                <td><?= Html::encode($stat['request_count']) ?></td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 300px;" title="<?= Html::encode($stat['most_popular_url']) ?>">
                                                        <?= Html::encode($stat['most_popular_url']) ?>
                                                    </span>
                                                </td>
                                                <td><?= Html::encode($stat['most_popular_browser']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Детальная таблица логов -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Детальные данные логов</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($detailedStats)): ?>
                            <div class="alert alert-info">
                                Нет детальных данных для отображения.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>IP адрес</th>
                                            <th>Операционная система</th>
                                            <th>Браузер</th>
                                            <th>URL</th>
                                            <th>Дата/время</th>
                                            <th>User-Agent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detailedStats as $stat): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary"><?= Html::encode($stat['ip_address']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= Html::encode($stat['operating_system']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?= Html::encode($stat['browser']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= Html::encode($stat['url']) ?>">
                                                        <?= Html::encode($stat['url']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?= Html::encode(date('d.m.Y H:i:s', strtotime($stat['request_datetime']))) ?></small>
                                                </td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 300px;" title="<?= Html::encode($stat['user_agent']) ?>">
                                                        <small><?= Html::encode($stat['user_agent']) ?></small>
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
        </div>

        <!-- Терминал -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Терминал</h5>
                    </div>
                    <div class="card-body">
                        <div class="terminal-container">
                            <div id="terminal-output" class="terminal-output"></div>
                            <div class="terminal-input-container">
                                <span class="terminal-prompt">$</span>
                                <input type="text" id="terminal-input" class="terminal-input" placeholder="Введите команду (например: ./yii log-parser/create-test-data)" autocomplete="off">
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6>Доступные команды:</h6>
                            <ul>
                                <li><code>./yii log-parser/create-test-data</code> - создать тестовые данные</li>
                                <li><code>./yii log-parser/clear</code> - очистить все данные</li>
                                <li><code>./yii migrate --interactive=0</code> - выполнить миграции (автоматически)</li>
                                <li><code>./yii site-parser/parse https://forthill.ru</code> - парсинг сайта</li>
                                <li><code>./yii site-parser/parse https://forthill.ru links</code> - только ссылки</li>
                                <li><code>./yii site-parser/parse https://forthill.ru images</code> - только изображения</li>
                                <li><code>./yii site-parser/help</code> - справка по парсингу</li>
                                <li><code>clear</code> - очистить терминал</li>
                                <li><code>help</code> - показать справку</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Регистрируем Chart.js
$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js', ['position' => \yii\web\View::POS_HEAD]);

// Регистрируем CSS для терминала
$this->registerCss("
.terminal-container {
    background-color: #1e1e1e;
    border-radius: 8px;
    padding: 15px;
    font-family: 'Courier New', monospace;
    color: #ffffff;
    min-height: 300px;
    max-height: 500px;
    overflow-y: auto;
}

.terminal-output {
    margin-bottom: 10px;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.terminal-input-container {
    display: flex;
    align-items: center;
    margin-top: 10px;
}

.terminal-prompt {
    color: #00ff00;
    margin-right: 10px;
    font-weight: bold;
}

.terminal-input {
    background: transparent;
    border: none;
    color: #ffffff;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    flex: 1;
    outline: none;
}

.terminal-input::placeholder {
    color: #888888;
}

.terminal-command {
    color: #00ff00;
    font-weight: bold;
}

.terminal-success {
    color: #00ff00;
}

.terminal-error {
    color: #ff0000;
}

.terminal-info {
    color: #ffff00;
}

.terminal-warning {
    color: #ff8800;
}
");
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // График запросов
    const requestsCtx = document.getElementById('requestsChart').getContext('2d');
    new Chart(requestsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartData['requests']['labels']) ?>,
            datasets: [{
                label: 'Количество запросов',
                data: <?= json_encode($chartData['requests']['data']) ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // График браузеров
    const browsersCtx = document.getElementById('browsersChart').getContext('2d');
    new Chart(browsersCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chartData['browsers']['labels']) ?>,
            datasets: [{
                data: <?= json_encode(array_column($chartData['browsers']['datasets'], 'data')) ?>,
                backgroundColor: <?= json_encode(array_column($chartData['browsers']['datasets'], 'backgroundColor')) ?>
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Терминал
    const terminalOutput = document.getElementById('terminal-output');
    const terminalInput = document.getElementById('terminal-input');
    const commandHistory = [];
    let historyIndex = -1;

    // Приветственное сообщение
    appendToTerminal('Добро пожаловать в терминал анализа логов Nginx!', 'info');
    appendToTerminal('Введите команду для выполнения...', 'info');
    appendToTerminal('');

    // Обработка ввода команды
    terminalInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const command = terminalInput.value.trim();
            if (command) {
                executeCommand(command);
                commandHistory.push(command);
                historyIndex = commandHistory.length;
                terminalInput.value = '';
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (historyIndex > 0) {
                historyIndex--;
                terminalInput.value = commandHistory[historyIndex];
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (historyIndex < commandHistory.length - 1) {
                historyIndex++;
                terminalInput.value = commandHistory[historyIndex];
            } else if (historyIndex === commandHistory.length - 1) {
                historyIndex++;
                terminalInput.value = '';
            }
        }
    });

    // Фокус на поле ввода при клике на терминал
    document.querySelector('.terminal-container').addEventListener('click', function() {
        terminalInput.focus();
    });

    function appendToTerminal(text, type = '') {
        const div = document.createElement('div');
        div.className = type ? `terminal-${type}` : '';
        div.textContent = text;
        terminalOutput.appendChild(div);
        terminalOutput.scrollTop = terminalOutput.scrollHeight;
    }

    function executeCommand(command) {
        appendToTerminal(`$ ${command}`, 'command');
        
        // Обработка встроенных команд
        if (command === 'clear') {
            terminalOutput.innerHTML = '';
            appendToTerminal('Добро пожаловать в терминал анализа логов Nginx!', 'info');
            appendToTerminal('Введите команду для выполнения...', 'info');
            appendToTerminal('');
            return;
        }

        if (command === 'help') {
            appendToTerminal('Доступные команды:', 'info');
            appendToTerminal('  ./yii log-parser/create-test-data - создать тестовые данные', 'info');
            appendToTerminal('  ./yii log-parser/clear - очистить все данные', 'info');
            appendToTerminal('  ./yii migrate --interactive=0 - выполнить миграции (автоматически)', 'info');
            appendToTerminal('  ./yii site-parser/parse <url> [type] - парсинг сайта', 'info');
            appendToTerminal('  ./yii site-parser/help - справка по парсингу', 'info');
            appendToTerminal('  clear - очистить терминал', 'info');
            appendToTerminal('  help - показать эту справку', 'info');
            appendToTerminal('');
            appendToTerminal('Типы парсинга сайтов:', 'info');
            appendToTerminal('  all, links, images, text, meta', 'info');
            appendToTerminal('');
            appendToTerminal('Примеры парсинга:', 'info');
            appendToTerminal('  ./yii site-parser/parse https://example.com', 'info');
            appendToTerminal('  ./yii site-parser/parse https://example.com links', 'info');
            appendToTerminal('  ./yii site-parser/parse https://example.com images', 'info');
            appendToTerminal('');
            return;
        }

        // Выполнение команд через AJAX
        appendToTerminal('Выполняю команду...', 'info');
        
        fetch('<?= Url::to(['site/execute-command']) ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                '<?= Yii::$app->request->csrfParam ?>': '<?= Yii::$app->request->csrfToken ?>',
                'command': command
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                appendToTerminal(data.output, 'success');
            } else {
                appendToTerminal(data.output, 'error');
            }
            appendToTerminal('');
        })
        .catch(error => {
            appendToTerminal(`Ошибка выполнения команды: ${error.message}`, 'error');
            appendToTerminal('');
        });
    }
});
</script>
