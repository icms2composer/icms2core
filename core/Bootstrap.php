<?php
namespace icms2\core;

use icms2\core\Core as cmsCore;
use icms2\core\Config as cmsConfig;
use icms2\core\Cache as cmsCache;
use icms2\core\EventsManager as cmsEventsManager;
use icms2\core\Debugging as cmsDebugging;
use icms2\core\User as cmsUser;

class Bootstrap {
    static function run($configFile) {
        $configFile = realpath($configFile);
        // Определяем корень
        define('PATH', dirname($configFile));

        // оставлено для совместимости, если кто-то использовал эту константу
        // в CMS не используется нигде
        define('ROOT', rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR));

        // Устанавливаем кодировку
        mb_internal_encoding('UTF-8');

        // Инициализируем конфиг
        $config = cmsConfig::getInstance();

        // дебаг отключен - скрываем все сообщения об ошибках
        if(!$config->debug){

            error_reporting(0);

        } else {

            @ini_set('display_errors', 1);
            @ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);

            // включаем отладку
            cmsDebugging::enable();

        }

        // Проверяем, что система установлена
        if (!$config->isReady()){
            die('Config not found!');
        }

        // Стартуем сессию если константа SESSION_START объявлена
        if(defined('SESSION_START')){

            // Устанавливаем директорию сессий
            cmsUser::setSessionSavePath($config->session_save_handler, $config->session_save_path);

            cmsUser::sessionStart($config->cookie_domain);

            // таймзона сессии
            $session_time_zone = cmsUser::sessionGet('user:time_zone');

            // если таймзона в сессии отличается от дефолтной
            if($session_time_zone && $session_time_zone != $config->time_zone){
                $config->set('time_zone', $session_time_zone);
            }

        }

        // Устанавливаем часовую зону
        date_default_timezone_set($config->time_zone);

        // Подключаем все необходимые классы и библиотеки
        /*cmsCore::loadLib('html.helper');
        cmsCore::loadLib('strings.helper');
        cmsCore::loadLib('files.helper');
        cmsCore::loadLib('spyc.class');*/

        // Инициализируем ядро
        $core = cmsCore::getInstance();

        // Подключаем базу
        $core->connectDB();

        // соединение не установлено? Показываем ошибку
        if(!$core->db->ready()){

            cmsCore::loadLanguage();

            return cmsCore::error($core->db->connectError());

        }

        // Запускаем кеш
        cmsCache::getInstance()->start();

        cmsEventsManager::hook('core_start');

        // Загружаем локализацию
        cmsCore::loadLanguage();

        // устанавливаем локаль языка
        if(function_exists('lang_setlocale')){
            lang_setlocale();
        }

        // устанавливаем локаль MySQL
        $core->db->setLcMessages();

        /////////////////////////////////////////////////////////////////////

        if ($config->emulate_lag) { usleep(350000); }

        //Запускаем роутинг
        $core->route($_SERVER['REQUEST_URI']);

        // Инициализируем шаблонизатор
        $template = cmsTemplate::getInstance();

        cmsEventsManager::hook('engine_start');

        // загружаем и устанавливаем страницы для текущего URI
        $core->loadMatchedPages();

        // Проверяем доступ
        if(cmsEventsManager::hook('page_is_allowed', true)){

            //Запускаем контроллер
            $core->runController();

        }

        // формируем виджеты
        $core->runWidgets();

        //Выводим готовую страницу
        $template->renderPage();

        cmsEventsManager::hook('engine_stop');

        // Останавливаем кеш
        cmsCache::getInstance()->stop();

    }
}


