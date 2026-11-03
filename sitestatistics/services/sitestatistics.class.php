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
    /** @var string Cookie/session-independent visitor key (CWV: no PHPSESSID). */
    protected $userKey = '';


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
			$assetVer = '20261001b';
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
            $this->modx->controller->addJavascript($this->config['jsUrl'] . 'mgr/widgets/outreach.panel.js?v=' . $assetVer);
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
     * Visitor key without requiring anonymous PHP session (keeps HTML cacheable).
     * Primary store: cookie `siteStatistics`. $_SESSION synced only if session already active.
     */
    public function defineUserKey()
    {
        $key = 'siteStatistics';
        $userKey = '';

        if ($this->modx->user->id != 0) {
            $query = $this->modx->newQuery('UserStatistics');
            $query->select('user_key');
            $query->where([
                'uid' => $this->modx->user->id,
            ]);
            $fromUser = $this->modx->getValue($query->prepare());
            if (!empty($fromUser)) {
                $userKey = (string) $fromUser;
            }
        }

        if ($userKey === '' && !empty($_COOKIE[$key])) {
            $userKey = (string) $_COOKIE[$key];
        }
        if ($userKey === '' && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[$key])) {
            $userKey = (string) $_SESSION[$key];
        }
        if ($userKey === '') {
            $userKey = md5(MODX_HTTP_HOST . microtime(true) . random_int(0, PHP_INT_MAX));
        }

        if (empty($_COOKIE[$key]) || (string) $_COOKIE[$key] !== $userKey) {
            $cookieSecure = (boolean) $this->modx->getOption('session_cookie_secure', null, false);
            $cookieHttpOnly = (boolean) $this->modx->getOption('session_cookie_httponly', null, true);
            $cookieDomain = (string) $this->modx->getOption('session_cookie_domain', null, '');
            $cookiePath = (string) $this->modx->getOption('session_cookie_path', null, MODX_BASE_URL);
            if ($cookiePath === '') {
                $cookiePath = '/';
            }
            setcookie($key, $userKey, [
                'expires' => 0x7FFFFFFF,
                'path' => $cookiePath,
                'domain' => $cookieDomain,
                'secure' => $cookieSecure,
                'httponly' => $cookieHttpOnly,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[$key] = $userKey;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$key] = $userKey;
        }

        $this->userKey = $userKey;
        $this->modx->setPlaceholder('sitestatistics.userKey', $userKey);
    }

    /** @return string */
    public function getUserKey()
    {
        if ($this->userKey !== '') {
            return $this->userKey;
        }
        if (!empty($_COOKIE['siteStatistics'])) {
            return (string) $_COOKIE['siteStatistics'];
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['siteStatistics'])) {
            return (string) $_SESSION['siteStatistics'];
        }
        return '';
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
        // Боты только в источниках переходов
        if ($this->isBot()) {
            return false;
        }
        $userKey = $this->getUserKey();
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
        // Боты только в источниках переходов
        if ($this->isBot()) {
            return;
        }
        $user_key = $this->getUserKey();
        if ($user_key === '') {
            return;
        }
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
     * Визит по ссылке КП (utm_source=outreach): гость становится пользователем MODX.
     * Данные письма подписаны lead_sig, ключ — OUTREACH_STATS_TOKEN.
     */
    public function applyOutreachLead()
    {
        $source = isset($_GET['utm_source']) ? (string)$_GET['utm_source'] : '';
        if ($source !== 'outreach') {
            return;
        }
        $userKey = $this->getUserKey();
        if ($userKey === '') {
            return;
        }
        $email = trim((string)($_GET['lead_email'] ?? ''));
        $company = trim((string)($_GET['lead_company'] ?? ''));
        $name = trim((string)($_GET['lead_name'] ?? ''));
        $token = trim((string)($_GET['utm_content'] ?? ''));
        $sig = (string)($_GET['lead_sig'] ?? '');
        if ($email === '' || $token === '' || $sig === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $secret = $this->outreachSecret();
        if ($secret === '') {
            return;
        }
        $expected = hash_hmac('sha256', implode("\n", [$email, $company, $name, $token]), $secret);
        if (!hash_equals($expected, $sig)) {
            return;
        }

        $fullname = $name !== '' ? $name : ($company !== '' ? $company : $email);
        $fullname = $this->limit($fullname, 100);
        $company = $this->limit($company, 250);
        try {
            $uid = $this->ensureOutreachUser($email, $fullname, $company);
        } catch (Throwable $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[siteStatistics] Outreach user: ' . $e->getMessage());
            return;
        }
        if (!$uid) {
            return;
        }

        $campaign = $this->limit(trim((string)($_GET['utm_campaign'] ?? '')), 120);
        $referer = $campaign !== '' ? 'outreach:' . $campaign : 'outreach';
        /** @var UserStatistics|null $stat */
        $stat = $this->modx->getObject('UserStatistics', ['user_key' => $userKey]);
        if ($stat) {
            if (!(int)$stat->get('uid')) {
                $stat->set('uid', $uid);
            }
            if ($stat->get('referer') === '' || $stat->get('referer') === null) {
                $stat->set('referer', $referer);
            }
            $stat->save();
            return;
        }

        $meta = $this->modx->getFieldMeta('UserStatistics');
        $stat = $this->modx->newObject('UserStatistics');
        $stat->fromArray([
            'user_key' => $userKey,
            'date' => date('Y-m-d H:i:s'),
            'uid' => $uid,
            'context' => $this->modx->context->get('key'),
            'rid' => $this->modx->resource->id,
            'ip' => $this->getUsetIP(),
            'user_agent' => $this->limit(htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '', ENT_QUOTES), $meta['user_agent']['precision']),
            'referer' => $this->limit($referer, $meta['referer']['precision']),
        ], '', true, true);
        $stat->save();
    }

    /**
     * @return string
     */
    protected function outreachSecret()
    {
        $file = $this->modx->getOption('core_path') . 'components/sitestatistics/config.outreach.php';
        if (!is_file($file)) {
            return '';
        }
        $cfg = include $file;
        return is_array($cfg) ? (string)($cfg['token'] ?? '') : '';
    }

    /**
     * Пользователь для лида рассылки. Не трогает уже существующие учётки сотрудников.
     *
     * @return int
     */
    protected function ensureOutreachUser($email, $fullname, $company)
    {
        $username = 'kp-' . substr(hash('sha256', strtolower($email)), 0, 20);
        /** @var modUser|null $user */
        $user = $this->modx->getObject('modUser', ['username' => $username]);
        if ($user) {
            $profile = $user->getOne('Profile');
            if ($profile && $profile->get('fullname') === '' && $fullname !== '') {
                $profile->set('fullname', $fullname);
                $profile->set('email', $email);
                $profile->save();
            }
            $this->assignJkhGroup($user);
            return (int)$user->get('id');
        }

        /** @var modUser $user */
        $user = $this->modx->newObject('modUser');
        $user->set('username', $username);
        $user->set('active', 1);
        $user->set('hash_class', $this->modx->getOption('hash_class', null, 'hashing.modPBKDF2'));
        $user->set('password', bin2hex(random_bytes(16)));
        /** @var modUserProfile $profile */
        $profile = $this->modx->newObject('modUserProfile');
        $profile->fromArray([
            'fullname' => $fullname,
            'email' => $email,
            'comment' => $company,
            'blocked' => 0,
        ]);
        $user->addOne($profile);
        if (!$user->save()) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[siteStatistics] Could not create outreach user.');
            return 0;
        }
        $this->assignJkhGroup($user);
        return (int)$user->get('id');
    }

    /**
     * Группа «ЖКХ» без доступа в админку — только метка лида.
     *
     * @param modUser $user
     */
    protected function assignJkhGroup($user)
    {
        $name = 'ЖКХ';
        $group = $this->modx->getObject('modUserGroup', ['name' => $name]);
        if (!$group) {
            $group = $this->modx->newObject('modUserGroup');
            $group->fromArray([
                'name' => $name,
                'description' => 'Лиды рассылки коммерческого предложения',
                'parent' => 0,
                'rank' => 0,
                'dashboard' => 1,
            ]);
            if (!$group->save()) {
                return;
            }
        }
        if (!$user->isMember($name)) {
            $user->joinGroup($name, 'Member');
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
        if ($user = $this->modx->getObject('UserStatistics', ['user_key' => $this->getUserKey(), 'show_message' => 1])) {
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
     * Фрагменты UA из stat.not_allowed_user_agents (для ботов).
     * @return array
     */
    public function getBotUserAgentParts()
    {
        $raw = (string)$this->modx->getOption('stat.not_allowed_user_agents', null, '');
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Текущий запрос — бот по списку User-Agent.
     * @param string|null $ua
     * @return bool
     */
    public function isBot($ua = null)
    {
        if ($ua === null) {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'empty';
        }
        if ($ua === '' || $ua === null) {
            $ua = 'empty';
        }
        $parts = $this->getBotUserAgentParts();
        if (!$parts) {
            return false;
        }
        $quoted = array_map('preg_quote', $parts);
        $pattern = implode('|', $quoted);
        return $pattern !== '' && (bool)preg_match('/(' . $pattern . ')/i', (string)$ua);
    }

    /**
     * SQL WHERE (без AND): колонка UA не бот.
     * @param string $column
     * @return string
     */
    public function getExcludeBotsSql($column = 'user_agent')
    {
        $parts = $this->getBotUserAgentParts();
        if (!$parts) {
            return '';
        }
        $col = $column;
        $conds = [];
        foreach ($parts as $part) {
            if (strtolower($part) === 'empty') {
                $conds[] = "({$col} IS NOT NULL AND {$col} != '' AND LOWER({$col}) != 'empty')";
                continue;
            }
            $safe = str_replace(['\\', '%', '_', "'"], ['\\\\', '\\%', '\\_', "''"], $part);
            $conds[] = "{$col} NOT LIKE '%{$safe}%'";
        }
        return $conds ? '(' . implode(' AND ', $conds) . ')' : '';
    }

    /**
     * SQL: исключить user_key ботов (по UA в UserStatistics).
     * @param string $userKeyColumn
     * @return string с ведущим AND или ''
     */
    public function getExcludeBotsUserKeysSql($userKeyColumn = 'user_key')
    {
        $parts = $this->getBotUserAgentParts();
        if (!$parts) {
            return '';
        }
        $botConds = [];
        foreach ($parts as $part) {
            if (strtolower($part) === 'empty') {
                $botConds[] = "(u.user_agent IS NULL OR u.user_agent = '' OR LOWER(u.user_agent) = 'empty')";
                continue;
            }
            $safe = str_replace(['\\', '%', '_', "'"], ['\\\\', '\\%', '\\_', "''"], $part);
            $botConds[] = "u.user_agent LIKE '%{$safe}%'";
        }
        $usersTable = $this->modx->getTableName('UserStatistics');
        return " AND {$userKeyColumn} NOT IN (SELECT u.user_key FROM {$usersTable} u WHERE "
            . implode(' OR ', $botConds) . ')';
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

        if ($this->isBot()) {
            return 'bot';
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

        $userKey = $this->getUserKey();
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