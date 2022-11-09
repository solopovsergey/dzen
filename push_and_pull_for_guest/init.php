<?php

$eventManager = \Bitrix\Main\EventManager::getInstance();

//для автоподключения
$eventManager->addEventHandler(
    "pull",
    "OnGetDependentModule",
    ["CSolopovSergeyDzenPushAndPullForGuest", 'pullOnGetDependentModule']
);

// для установки идентификатора  для гостя
$eventManager->addEventHandler(
    "main",
    "OnProlog",
    ["CSolopovSergeyDzenPushAndPullForGuest", 'mainOnProlog']
);

class CSolopovSergeyDzenPushAndPullForGuest
{

    // нужно заменить на свое значение
    const MODULE_ID = 'main';

    public static function pullOnGetDependentModule()
    {
        return [
            'MODULE_ID' => self::MODULE_ID,
            'USE' => ["PUBLIC_SECTION"],
        ];
    }


    public static function mainOnProlog()
    {
        global $USER;

        //сразу подключим библиотеку для клиентской стороны,
        // можно перенести в header.php шаблона сайта
        \Bitrix\Main\UI\Extension::load('pull.client');

        // проверим чтобы еще не был определен
        if (defined('PULL_USER_ID')) {
            return;
        }

        // проверим чтобы польвзаотель был не атворизован
        if ($USER->IsAuthorized()) {
            return;
        }

        // обычная загрузка страницы, берем идентфикиатор от модуля веб аналитики (он уникальный)
        if (isset($_SESSION['SESS_GUEST_ID'])) {
            define('PULL_USER_ID', -1 * abs($_SESSION['SESS_GUEST_ID']));
            return;
        }

        // загрузка парамтеров конфигурации для push&pull в котором отклчюен учет статистики
        // используется при загрузке конфигурации, перемменная $_SESSION['SESS_GUEST_ID'] в запросе таком отсутствует
        $req = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        if ((int)$req->getCookie('GUEST_ID')) {
            define('PULL_USER_ID', -1 * (int)$req->getCookie('GUEST_ID'));
        }
    }


    /**
     * Вернет идентфикиатор получателя уведомления для текущего пользователя
     * в зависимости от авторизованности пользователя
     * @return int|null
     */
    public static function getPullUserId()
    {
        if (defined('PULL_USER_ID')) {
            return constant('PULL_USER_ID');
        }

        if (is_object($GLOBALS['USER'])) {
            return (int)$GLOBALS['USER']->GetID();
        }

        return null;
    }

    /**
     * Отправка получателю команду с парамтерами
     * @param string $command
     * @param array $arParams
     * @param int $recipientId -  получен от метода getPullUserId()
     * @return bool
     * @throws \Bitrix\Main\LoaderException
     */
    public static function sendCommand($recipientId, $command, $arParams = [])
    {
        if (!\Bitrix\Main\Loader::includeModule('pull')) {
            return false;
        }
        if (!\CPullOptions::GetNginxStatus()) {
            return false;
        }

        $arMessage = [
            'module_id' => self::MODULE_ID,
            'command' => $command,
            'params' => $arParams,
        ];

        $result = \Bitrix\Pull\Event::add($recipientId, $arMessage);
        if (!$result) {
            //..
        }
        return true;

    }

}
