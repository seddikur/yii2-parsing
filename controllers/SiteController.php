<?php

namespace app\controllers;

use app\models\NginxLog;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                    'execute-command' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if ($action->id === 'execute-command') {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        
        // Получаем параметры фильтров
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $os = $request->get('os');
        $architecture = $request->get('architecture');
        $sort = $request->get('sort', 'date');
        $order = $request->get('order', 'desc');

        // Валидация дат
        if ($dateFrom && $dateTo) {
            $fromDate = new \DateTime($dateFrom);
            $toDate = new \DateTime($dateTo);
            $diff = $fromDate->diff($toDate);
            
            if ($diff->days > 365) {
                Yii::$app->session->setFlash('error', 'Диапазон дат не может превышать 1 год');
                $dateFrom = null;
                $dateTo = null;
            }
        }

        // Получаем данные для графиков и таблицы
        $dailyStats = NginxLog::getDailyStats($dateFrom, $dateTo, $os, $architecture);
        $topBrowsers = NginxLog::getTopBrowsers($dateFrom, $dateTo, $os, $architecture);
        
        // Получаем детальные данные для таблицы
        $detailedStats = NginxLog::getDetailedStats($dateFrom, $dateTo, $os, $architecture);
        
        // Получаем списки для фильтров
        $availableOs = NginxLog::getAvailableOperatingSystems();
        $availableArchitectures = NginxLog::getAvailableArchitectures();

        // Сортируем данные
        if ($sort && $order) {
            usort($dailyStats, function($a, $b) use ($sort, $order) {
                $result = strcmp($a[$sort], $b[$sort]);
                return $order === 'desc' ? -$result : $result;
            });
        }

        // Подготавливаем данные для графиков
        $chartData = $this->prepareChartData($dailyStats, $topBrowsers);

        return $this->render('index', [
            'dailyStats' => $dailyStats,
            'topBrowsers' => $topBrowsers,
            'detailedStats' => $detailedStats,
            'chartData' => $chartData,
            'availableOs' => $availableOs,
            'availableArchitectures' => $availableArchitectures,
            'filters' => [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'os' => $os,
                'architecture' => $architecture,
                'sort' => $sort,
                'order' => $order,
            ],
        ]);
    }

    /**
     * Подготавливает данные для графиков
     * @param array $dailyStats
     * @param array $topBrowsers
     * @return array
     */
    private function prepareChartData($dailyStats, $topBrowsers)
    {
        $chartData = [
            'requests' => [
                'labels' => [],
                'data' => [],
            ],
            'browsers' => [
                'labels' => [],
                'datasets' => [],
            ],
        ];

        // Данные для графика запросов
        foreach ($dailyStats as $stat) {
            $chartData['requests']['labels'][] = $stat['date'];
            $chartData['requests']['data'][] = (int)$stat['request_count'];
        }

        // Данные для графика браузеров
        $totalRequests = array_sum(array_column($dailyStats, 'request_count'));
        
        if ($totalRequests > 0) {
            foreach ($topBrowsers as $browser) {
                $chartData['browsers']['labels'][] = $browser['browser'];
                $percentage = round(($browser['count'] / $totalRequests) * 100, 2);
                $chartData['browsers']['datasets'][] = [
                    'label' => $browser['browser'],
                    'data' => [$percentage],
                    'backgroundColor' => $this->getRandomColor(),
                ];
            }
        }

        return $chartData;
    }

    /**
     * Генерирует случайный цвет для графиков
     * @return string
     */
    private function getRandomColor()
    {
        $colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
        ];
        return $colors[array_rand($colors)];
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    /**
     * Выполняет консольные команды
     * @return \yii\web\Response
     */
    public function actionExecuteCommand()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $request = Yii::$app->request;
        $command = $request->post('command');
        
        // Отладочная информация
        Yii::info("Получена команда: " . $command, 'terminal');
        
        if (!$command) {
            return [
                'success' => false,
                'output' => 'Команда не указана. POST данные: ' . json_encode($request->post())
            ];
        }

        // Список разрешенных команд
        $allowedCommands = [
            './yii log-parser/create-test-data',
            './yii log-parser/clear',
            './yii migrate',
            './yii migrate --interactive=0',
            './yii site-parser/parse',
            './yii site-parser/help',
            'clear',
            'help'
        ];

        // Проверяем, является ли команда разрешенной
        $isAllowed = false;
        foreach ($allowedCommands as $allowedCommand) {
            if (strpos($command, $allowedCommand) === 0) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            return [
                'success' => false,
                'output' => 'Команда не разрешена для выполнения через веб-интерфейс'
            ];
        }

        try {
            // Проверяем права доступа к runtime
            $runtimePath = Yii::getAlias('@runtime');
            if (!is_writable($runtimePath)) {
                return [
                    'success' => false,
                    'output' => 'Ошибка: директория runtime недоступна для записи. Проверьте права доступа.'
                ];
            }
            
            // Выполняем команду
            $output = [];
            $returnCode = 0;
            
            if (strpos($command, './yii') === 0) {
                // Выполняем Yii команды
                $yiiPath = Yii::getAlias('@app/yii');
                $fullCommand = "php $yiiPath " . substr($command, 6);
                
                exec($fullCommand . ' 2>&1', $output, $returnCode);
            } else {
                // Встроенные команды
                switch ($command) {
                    case 'clear':
                        $output = ['Терминал очищен'];
                        break;
                    case 'help':
                        $output = [
                            'Доступные команды:',
                            '  ./yii log-parser/create-test-data - создать тестовые данные',
                            '  ./yii log-parser/clear - очистить все данные',
                            '  ./yii migrate --interactive=0 - выполнить миграции (автоматически)',
                            '  ./yii site-parser/parse <url> [type] - парсинг сайта',
                            '  ./yii site-parser/help - справка по парсингу',
                            '  clear - очистить терминал',
                            '  help - показать эту справку',
                            '',
                            'Типы парсинга сайтов:',
                            '  all, links, images, text, meta'
                        ];
                        break;
                    default:
                        $output = ['Неизвестная команда'];
                        $returnCode = 1;
                }
            }

            return [
                'success' => $returnCode === 0,
                'output' => implode("\n", $output)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => 'Ошибка выполнения команды: ' . $e->getMessage()
            ];
        }
    }
}
