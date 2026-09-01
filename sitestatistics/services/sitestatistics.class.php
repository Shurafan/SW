<?php

/**
 * Service class for siteStatistics extra.
 */
class siteStatistics
{
    /* @var modX $modx */
    protected $modx;
    protected $config = [];
    public $need2ClearCache = false;
    protected $initialized = false;
    protected $ip_list = [];  //not allowed ip


    /**
     * @param modX $modx
     * @param array $config
     */
    function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;
        $corePath = $this->modx->getOption('sitestatistics_core_path', $config, $this->modx->getOption('core_path') . 'components/sitestatistics/');
        $assetsUrl = $this->modx->getOption('sitestatistics_assets_url', $config, $this->modx->getOption('assets_url') . 'components/sitestatistics/');
        $connectorUrl = $assetsUrl . 'connector.php';

        $this->config = array_merge([
            'assetsUrl' => $assetsUrl,
            'cssUrl' => $assetsUrl . 'css/',
            'jsUrl' => $assetsUrl . 'js/',
            'connectorUrl' => $connectorUrl,

            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'templatesPath' => $corePath . 'elements/templates/',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'processorsPath' => $corePath . 'processors/',

            'countby' => 'day',
        ], $config);
        $ip_list = $this->modx->getOption('stat.not_allowed_ip');
        if (!empty($ip_list)) {
            $this->ip_list = explode(',', $ip_list);
            $this->ip_list = array_map('trim', $this->ip_list);
        }
        $this->modx->addPackage('sitestatistics', $this->config['modelPath']);
        $this->modx->lexicon->load('sitestatistics:default');
    }

    /**
     * @param string|array $key
     * @return $this|mixed
     */
    public function config($key = null)
    {
        if (is_null($key)) {
            return $this->config;
        } elseif (is_array($key)) {
            $this->config = array_merge($this->config, $key);
            return $this;
        } elseif (is_string($key)) {
            return @$this->config[$key];
        }
    }
    /**
     * @param array $sp
     */
    public function initialize($sp = [])
    {
        if (!$this->initialized) {
            $style = $this->modx->getOption('stat.frontend_css', null, '');
            if ($style) {
                $this->modx->regClientCSS($style);
            }
            $this->initialized = true;
        }
        if (isset($sp['count'])) {
            if (!isset($sp['countby'])) {
                $sp['countby'] = str_replace(['byday', 'bymonth', 'byyear'], ['day', 'month', 'year'], $sp['count']);
            }
            unset($sp['count']);
        }
        $this->config = array_merge($this->config, $sp);
    }

    public function initializeMgr($resourceTab = false)
    {
        if ($resourceTab) {
            $this->modx->controller->addLexiconTopic('sitestatistics:default');
            $this->modx->controller->addCss($this->config['cssUrl'] . 'mgr/bootstrap.buttons.css');
            $this->modx->controller->addJavascript($this->config('assetsUrl') . 'js/mgr/sitestatistics.js');
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/misc/utils.js');
            $this->modx->controller->addJavascript($this->config('assetsUrl') . 'js/mgr/widgets/resusers.grid.js');
            $output = '
<style>
    ul.sitestatistics-row-actions .btn {padding: 2px 7px;}
    .action-red {color: darkred !important;}
    .x-grid3-col-actions {padding: 3px 0 3px 5px;}
</style>
<script>
    siteStatistics.config = ' . $this->modx->toJSON($this->config) . ';
    siteStatistics.config.connector_url = "' . $this->config['connectorUrl'] . '";
    Ext.ComponentMgr.onAvailable("modx-resource-tabs", function() {
        this.on("beforerender", function() {
            this.add({
                title: _("stat_tab_title"),
                id: "modx-resource-tabs-statistics",
                border: false,
                items: [{
                    layout: "anchor",
                    border: false,
                    items: [{
                        xtype: "sitestatistics-grid-res-users",
                        anchor: "100%",
                        cls: "main-wrapper",
                        resource: MODx.request.id
                    }]
                }]
            });
        });
    });
</script>';
            $this->modx->controller->addHtml($output);
        } else {
            $assetVer = '20260827j';
            // CSS
            $this->modx->controller->addCss($this->config['cssUrl'] . 'mgr/main.css?v=' . $assetVer);
            $this->modx->controller->addCss($this->config['cssUrl'] . 'mgr/bootstrap.buttons.css?v=' . $assetVer);
            $this->modx->controller->addCss($this->config['cssUrl'] . 'mgr/metrika.css?v=' . $assetVer);
            // JS
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/sitestatistics.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/misc/utils.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/widgets/users.windows.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/widgets/stats.windows.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/widgets/stats.grid.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/widgets/users.grid.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/widgets/onlineusers.grid.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/widgets/metrika.panel.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/widgets/home.panel.js?v=' . $assetVer);
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/sections/home.js?v=' . $assetVer);
            $this->modx->controller->addHtml('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>');
            $this->modx->controller->addHtml('<script>
    Ext.onReady(function() {
        MODx.load({ xtype: "sitestatistics-page-home"});
    });
</script>');
            // Make context combo
            $q = $this->modx->newQuery('modContext');
            $q->select('key');
            $q->where(['key:!=' => 'mgr']);
            $q->prepare();
            $q->stmt->execute();
            $ctx = '[';
            while ($row = $q->stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($ctx != '[') {
                    $ctx .= ',';
                }
                $ctx .= "['" . $row['key'] . "','" . $row['key'] . "']";
            }
            $ctx .= ']';

            $this->modx->controller->addHtml('
<script>
    siteStatistics.config = ' . $this->modx->toJSON($this->config) . ';
    siteStatistics.config.connector_url = "' . $this->config['connectorUrl'] . '";
    siteStatistics.config.periods = ' . "[['day','" . $this->modx->lexicon('day') . "'],['month','" . $this->modx->lexicon('month') . "'],['year','" . $this->modx->lexicon('year') . "']]" . ';
    siteStatistics.config.contexts = ' . $ctx . ';
</script>
');
        }
    }

    /**
     */
    public function defineUserKey()
    {
        $key = 'siteStatistics';
        if ($this->modx->user->id != 0) {
            $query = $this->modx->newQuery('UserStatistics');
            $query->select('user_key');
            $query->where([
                'uid' => $this->modx->user->id,
            ]);
            $user_key = $this->modx->getValue($query->prepare());
            if (!empty($user_key)) {
                $_SESSION[$key] = $user_key;
            }
        }
        if (empty($_COOKIE[$key])) {
            if (empty($_SESSION[$key])) {
                $_SESSION[$key] = md5(MODX_HTTP_HOST . time() . rand());
            }
            $cookieSecure = (boolean)$this->modx->getOption('session_cookie_secure', null, false);
            $cookieHttpOnly = (boolean)$this->modx->getOption('session_cookie_httponly', null, true);
            $cookieDomain = $this->modx->getOption('session_cookie_domain', null, '');
            $cookiePath = $this->modx->getOption('session_cookie_path', null, MODX_BASE_URL);
            setcookie($key, $_SESSION[$key], 0x7FFFFFFF, $cookieDomain, $cookiePath, $cookieSecure, $cookieHttpOnly);
        } elseif (empty($_SESSION[$key])) {
            $_SESSION[$key] = $_COOKIE[$key];
        } elseif ($_SESSION[$key] != $_COOKIE[$key]) {
            $_COOKIE[$key] = $_SESSION[$key];
        }
        $this->modx->setPlaceholder('sitestatistics.userKey', $_SESSION[$key]);
    }

    /**
     * Set page statistics
     * @return boolean
     */
    public function setStatistics()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            return false;
        }
        $userKey = $_SESSION['siteStatistics'] ?? '';
        if ($userKey === '' || $this->isExcludedUser($userKey)) {
            return false;
        }
        $data = [
            'rid' => $this->modx->resource->get('id'),
            'date' => date('Y-m-d'),
            'user_key' => $userKey,
        ];

        /** @var PageStatistics $pageStat */
        if ($pageStat = $this->modx->getObject('PageStatistics', $data)) {
            $query = $this->modx->newQuery('PageStatistics');
            $query->command('update')
                  ->set(['views' => $pageStat->get('views') + 1])
                  ->where($data);
            $tstart = microtime(true);
            if ($query->prepare() && $query->stmt->execute()) {
                $this->modx->queryTime += microtime(true) - $tstart;
                $this->modx->executedQueries++;
            } else {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[siteStatistics] Could not update the page statistics.');
                return false;
            }

        } else {
            $pageStat = $this->modx->newObject('PageStatistics');
            $pageStat->set('rid', $data['rid']);
            $pageStat->set('date', $data['date']);
            $pageStat->set('user_key', $data['user_key']);
            $pageStat->set('month', date('Y-m'));
            $pageStat->set('year', date('Y'));
            $pageStat->set('views', 1);

            if (!$pageStat->save()) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[siteStatistics] Could not add page statistics.');
                return false;
            }
        }
        $this->trackVisitDaily($userKey);
        return true;
    }

    /**
     * @param $resource
     * @return int|void
     */
    public function getPageStatistics($resource = 0)
    {
        $query = $this->modx->newQuery('PageStatistics');
        //$query->setClassAlias('');
        if (!empty($resource) && is_numeric($resource)) {
            $query->where([
                'rid' => $resource,
            ]);
        }
        switch ($this->config['countby']) {
            case 'day':
                $query->groupby('date');
                if (empty($this->config['date'])) {
                    $query->where([
                        'date' => date('Y-m-d'),
                    ]);
                } else {
                    $query->where([
                        'date' => date('Y-m-d', strtotime($this->config['date'])),
                    ]);
                }
                break;
            case 'month':
                $query->groupby('month');
                if (empty($this->config['date'])) {
                    $query->where([
                        'month' => date('Y-m'),
                    ]);
                } else {
                    $query->where([
                        'month' => date('Y-m', strtotime($this->config['date'])),
                    ]);
                }
                break;
            case 'year':
                $query->groupby('year');
                if (empty($this->config['date'])) {
                    $query->where([
                        'year' => date('Y'),
                    ]);
                } else {
                    $query->where([
                        'year' => (int)$this->config['date'],
                    ]);
                }
                break;
        }
        if (($this->config['toPlaceholders'] && $this->config['toPlaceholders'] != 'false') || $this->config['show'] == 'all') {
            $query->select('COUNT(DISTINCT user_key) as users, SUM(views) as views');
            $tstart = microtime(true);
            if ($query->prepare() && $query->stmt->execute()) {
                $this->modx->queryTime += microtime(true) - $tstart;
                $this->modx->executedQueries++;
                $res = $query->stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (empty($res)) {
                $res = ['users' => 0, 'views' => 0];
            }
        } else {
            if (trim($this->config['show']) == 'users') {
                $query->select('COUNT(DISTINCT user_key)');
            } else {
                $query->select('SUM(views)');
            }
            $res = $this->modx->getValue($query->prepare());
            if (empty($res)) {
                $res = 0;
            }
        }
        return $res;
    }

    /**
     * @return array
     */
    public function getSiteStatistics()
    {
        $this->config['show'] = 'all';
        /** @var array $output */
        $output = $this->getPageStatistics();
        $output = $this->modx->getChunk($this->config['tpl'], $output);
        return $output;
    }

    /**
     * Set user statistics
     */
    public function setUserStatistics()
    {
        $user_key = $_SESSION['siteStatistics'];
        if ($this->modx->getCount('UserStatistics', ['user_key' => $user_key])) {
            $query = $this->modx->newQuery('UserStatistics');
            $query->command('update');
            $setData = [
                'date' => date('Y-m-d H:i:s'),
                'rid' => $this->modx->resource->id,
                'context' => $this->modx->context->get('key'),
                'ip' => $this->getUsetIP(),
            ];
            if ($this->modx->user->id != 0) {
                $setData['uid'] = $this->modx->user->id;
            }
            $query->set($setData);
            $query->where(['user_key' => $user_key]);
            $tstart = microtime(true);
            if ($query->prepare() && $query->stmt->execute()) {
                $this->modx->queryTime += microtime(true) - $tstart;
                $this->modx->executedQueries++;
            }
        } else {
            $meta = $this->modx->getFieldMeta('UserStatistics');
            /** @var UserStatistics $userStat */
            $userStat = $this->modx->newObject('UserStatistics');
            $userStat->fromArray([
                    'user_key' => $user_key,
                    'date' => date('Y-m-d H:i:s'),
                    'uid' => $this->modx->user->id,
                    'context' => $this->modx->context->get('key'),
                    'rid' => $this->modx->resource->id,
                    'ip' => $this->getUsetIP(),
                    'user_agent' => $this->limit(htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES), $meta['user_agent']['precision']),
                    'referer' => $this->limit(htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES), $meta['referer']['precision']),
            ], '', true, true);
            if (!$userStat->save()) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[siteStatistics] Could not save online user data.');
            };
        }
    }

    /**
     * @return string
     */
    public function getOnlineUsers()
    {
        $query = $this->modx->newQuery('UserStatistics');
        if ($this->config['fullMode']) {
            $query->leftJoin('modUserProfile', 'Profile');
            $query->leftJoin('modUser', 'User');
            $query->select("Profile.fullname, User.username");

        } else {
            $query->select('uid');
        }
        $time = $this->modx->getOption('stat.online_time', null, 15);
        $query->where("date > NOW() -  INTERVAL '$time' MINUTE");
        if (!empty($this->config['ctx'])) {
            $query->where(['context' => trim($this->config['ctx'])]);
        }
        $tstart = microtime(true);
        $res = [];
        if ($query->prepare() && $query->stmt->execute()) {
            $this->modx->queryTime += microtime(true) - $tstart;
            $this->modx->executedQueries++;
            $res = $query->stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $output = '';
        if ($this->config['fullMode']) {
            $tplItem = $this->modx->getOption('tplItem', $this->config, '@INLINE <p>[[+stat.fullname]]</p>', true);
            if (strpos($tplItem, '@INLINE') === false) {
                if (!$content = $this->modx->getChunk($tplItem)) {
                    $content = '<p>[[+stat.fullname]]</p>';
                }
            } else {
                $content = substr($tplItem, 8);
            }
            $guests = $this->modx->lexicon('stat_online_guests');
            $guestCount = 0;
            foreach ($res as $user) {
                if (empty($user['fullname'])) {
                    $guestCount++;
                    continue;
                }
                $this->modx->setPlaceholders([
                    'fullname' => $user['fullname'],
                    'username' => $user['username'],
                ],
                    'stat.'
                );
                $output .= $this->parseChunk($content);
            }
            if ($guestCount) {
                $this->modx->setPlaceholders([
                    'fullname' => $guests . ": " . $guestCount,
                    'username' => $guests . ": " . $guestCount,
                ],
                    'stat.'
                );
                $output .= $this->parseChunk($content);
            }
            $this->modx->unsetPlaceholders('stat.fullname');
        } else {
            $users = $guests = 0;
            foreach ($res as $user) {
                if ($user['uid']) {
                    $users++;
                } else {
                    $guests++;
                }
            }
            $output = $this->modx->getChunk($this->config['tpl'], ['stat.online_users' => $users, 'stat.online_guests' => $guests]);
        }
        return $output;
    }

    /**
     * @param $chunk
     * @return string
     */
    public function parseChunk($chunk)
    {
        $this->modx->getParser()->processElementTags('', $chunk, false, false, '[[', ']]', [], 10);
        $this->modx->getParser()->processElementTags('', $chunk, true, true, '[[', ']]', [], 10);
        return $chunk;
    }

    /**
     * @return bool
     */
    public function getMessage()
    {
        if ($user = $this->modx->getObject('UserStatistics', ['user_key' => $_SESSION['siteStatistics'], 'show_message' => 1])) {
            $message = $user->get('message');
            $user->set('show_message', 0);
            $user->set('message_showed', time());
            $user->save();
            if ($message) {
                $message = nl2br($message);
                $dlg = $this->modx->getChunk('tpl.siteStatistics.message', ['stat.message' => $message]);
                if (strpos($dlg, '[[') !== false) {
                    $maxIterations = (integer)$this->modx->getOption('parser_max_iterations', null, 10);
                    $this->modx->getParser()->processElementTags('', $dlg, false, false, '[[', ']]', [], $maxIterations);
                    $this->modx->getParser()->processElementTags('', $dlg, true, true, '[[', ']]', [], $maxIterations);
                }
                $script = $dlg . "\n<script>
    function statDialogClose(){
        var statDialog = document.getElementById('sitestat-message-dlg');
        statDialog.firstElementChild.style.opacity=0;
        setTimeout(function(){statDialog.style.display = 'none';},500);
    }
    document.getElementById('message-dlg-close-btn').onclick = statDialogClose;
    setTimeout(function(){
        var statDialog = document.getElementById('sitestat-message-dlg');
        statDialog.style.display = 'block';
        setTimeout(function(){statDialog.firstElementChild.style.opacity=1;},500);
    },1000)</script>";
                $this->modx->regClientHTMLBlock($script);
                if (!$this->initialized) {
                    $this->modx->regClientCSS($this->config['cssUrl'] . 'web/style.css');
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Daily traffic sources table: external|direct|internal|bot
     * @return string
     */
    public function getTrafficTable()
    {
        return $this->modx->escape($this->modx->getOption('table_prefix') . 'stat_traffic_daily');
    }

    /**
     * Ensure traffic aggregate table exists.
     */
    public function ensureTrafficTable()
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $table = $this->getTrafficTable();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `date` date NOT NULL,
            `source` varchar(16) NOT NULL,
            `hits` int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`date`, `source`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->modx->exec($sql);
        $ready = true;
    }

    /**
     * Classify request source like Metrika: external / direct / internal / bot
     * @param string|null $forceSource
     * @return string
     */
    public function classifyTrafficSource($forceSource = null)
    {
        if ($forceSource) {
            return $forceSource;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'empty';
        if ($ua === '') {
            $ua = 'empty';
        }
        $bots = $this->modx->getOption('stat.not_allowed_user_agents');
        if (!empty($bots)) {
            $parts = array_filter(array_map('trim', explode(',', $bots)));
            $quoted = array_map('preg_quote', $parts);
            $pattern = implode('|', $quoted);
            if ($pattern !== '' && preg_match('/(' . $pattern . ')/i', $ua)) {
                return 'bot';
            }
        }

        $referer = trim($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return 'direct';
        }

        $refHost = parse_url($referer, PHP_URL_HOST);
        if (!$refHost) {
            return 'direct';
        }

        $siteHost = parse_url($this->modx->getOption('site_url'), PHP_URL_HOST);
        if (!$siteHost) {
            $siteHost = $_SERVER['HTTP_HOST'] ?? '';
        }
        $norm = static function ($h) {
            return preg_replace('/^www\./i', '', strtolower((string)$h));
        };

        if ($norm($refHost) === $norm($siteHost)) {
            return 'internal';
        }
        return 'external';
    }

    /**
     * Increment daily counter for traffic source.
     * @param string|null $source
     * @return bool
     */
    public function incrementTraffic($source = null)
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return false;
        }

        $userKey = $_SESSION['siteStatistics'] ?? '';
        if ($source !== 'bot' && $userKey !== '' && $this->isExcludedUser($userKey)) {
            return false;
        }

        $this->ensureTrafficTable();
        $source = $this->classifyTrafficSource($source);
        if (!in_array($source, ['external', 'direct', 'internal', 'bot'], true)) {
            $source = 'direct';
        }

        $table = $this->getTrafficTable();
        $date = date('Y-m-d');
        $sql = "INSERT INTO {$table} (`date`, `source`, `hits`) VALUES (:d, :s, 1)
                ON DUPLICATE KEY UPDATE `hits` = `hits` + 1";
        $stmt = $this->modx->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bindValue(':d', $date);
        $stmt->bindValue(':s', $source);
        return (bool)$stmt->execute();
    }

    /**
     * @return int
     */
    public function getBounceSeconds()
    {
        return max(0, (int)$this->modx->getOption('stat.bounce_seconds', null, 15));
    }

    /**
     * Excluded user_key list from setting stat.exclude_users
     * @return array
     */
    public function getExcludeUserKeys()
    {
        $raw = (string)$this->modx->getOption('stat.exclude_users', null, '');
        $items = array_filter(array_map('trim', explode(',', $raw)));
        return array_values(array_unique($items));
    }

    /**
     * Excluded IPs from setting stat.not_allowed_ip
     * @return array
     */
    public function getExcludeIps()
    {
        $raw = (string)$this->modx->getOption('stat.not_allowed_ip', null, '');
        $items = array_filter(array_map('trim', explode(',', $raw)));
        return array_values(array_unique($items));
    }

    /**
     * user_keys that were last seen from excluded IPs (for chart filtering)
     * @return array
     */
    public function getExcludeUserKeysByIp()
    {
        $ips = $this->getExcludeIps();
        if (!$ips) {
            return [];
        }
        $table = $this->modx->getTableName('UserStatistics');
        $placeholders = [];
        $params = [];
        foreach ($ips as $i => $ip) {
            $ph = ':ip' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $ip;
        }
        $sql = "SELECT DISTINCT user_key FROM {$table} WHERE ip IN (" . implode(',', $placeholders) . ')';
        $stmt = $this->modx->prepare($sql);
        if (!$stmt) {
            return [];
        }
        foreach ($params as $ph => $val) {
            $stmt->bindValue($ph, $val);
        }
        $stmt->execute();
        $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strval', $keys)));
    }

    /**
     * @param string $userKey
     * @return bool
     */
    public function isExcludedUser($userKey)
    {
        if ($userKey === '') {
            return false;
        }
        $list = $this->getExcludeUserKeys();
        if (!$list) {
            return false;
        }
        if (in_array($userKey, $list, true)) {
            return true;
        }
        // Also match logged-in username / #uid stored in exclude list
        if (!empty($this->modx->user) && $this->modx->user->id) {
            $username = (string)$this->modx->user->get('username');
            if ($username !== '' && in_array($username, $list, true)) {
                return true;
            }
            if (in_array('#' . $this->modx->user->id, $list, true) || in_array((string)$this->modx->user->id, $list, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string
     */
    public function getVisitTable()
    {
        return $this->modx->escape($this->modx->getOption('table_prefix') . 'stat_visit_daily');
    }

    public function ensureVisitTable()
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $table = $this->getVisitTable();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `date` date NOT NULL,
            `user_key` varchar(32) NOT NULL,
            `views` int(10) unsigned NOT NULL DEFAULT 0,
            `first_hit` datetime NOT NULL,
            `last_hit` datetime NOT NULL,
            PRIMARY KEY (`date`, `user_key`),
            KEY `views` (`views`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->modx->exec($sql);
        $ready = true;
    }

    /**
     * Track first/last hit for bounce threshold.
     * @param string $userKey
     */
    public function trackVisitDaily($userKey)
    {
        if ($userKey === '') {
            return;
        }
        $this->ensureVisitTable();
        $table = $this->getVisitTable();
        $date = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO {$table} (`date`, `user_key`, `views`, `first_hit`, `last_hit`)
                VALUES (:d, :uk, 1, :now1, :now2)
                ON DUPLICATE KEY UPDATE
                    `views` = `views` + 1,
                    `last_hit` = VALUES(`last_hit`)";
        $stmt = $this->modx->prepare($sql);
        if (!$stmt) {
            return;
        }
        $stmt->bindValue(':d', $date);
        $stmt->bindValue(':uk', $userKey);
        $stmt->bindValue(':now1', $now);
        $stmt->bindValue(':now2', $now);
        $stmt->execute();
    }

    /**
     * Clear cache after the user got a message.
     */
    public function clearCache()
    {
        if ($this->need2ClearCache) {
            /** @var xPDOFileCache $cache */
            $cache = $this->modx->cacheManager->getCacheProvider($this->modx->getOption('cache_resource_key', null, 'resource'));
            $cacheKey = $this->modx->resource->getCacheKey($this->modx->context->key);
            $cache->delete($cacheKey, ['deleteTop' => true]);
            $cache->delete($cacheKey);
            $this->need2ClearCache = false;
        }
    }

    /**
     * Get the user IP
     * @return string
     */
    function getUsetIP()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        return $ip;
    }

    /**
     * Check the user IP
     * @return boolean
     */
    function checkIP()
    {
        $user_ip = $this->getUsetIP();
        if (in_array($user_ip, $this->ip_list)) {
            return false;
        }
        return true;
    }

    /**
     * Prepare string for STRICT MODE of MySql.
     * @param string $string
     * @param int $length
     * @return string
     */
    private function limit($string, $length = 250)
    {
        return strlen($string) > $length ? substr($string, 0, $length) : $string;
    }
}