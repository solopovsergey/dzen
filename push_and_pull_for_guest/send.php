<?php

// пример отправки команды с сервера на клиент (в браузер)
\CSolopovSergeyDzenPushAndPullForGuest::sendCommand(
    \CSolopovSergeyDzenPushAndPullForGuest::getPullUserId(),
    'hello',
    [
        'time' => time(),
        'text' => 'world'
    ]
);

//или

if (\Bitrix\Main\Loader::includeModule('pull') && \CPullOptions::GetNginxStatus()) {
    $userId = 0;
    if (defined('PULL_USER_ID')) {
        $userId = constant('PULL_USER_ID');
    } elseif (is_object($GLOBALS['USER'])) {
        $userId = (int)$GLOBALS['USER']->GetID();
    }

    $arMessage = [
        'module_id' => 'main',
        'command' => 'hello',
        'params' => [
            'time' => time(),
            'text' => 'world'
        ],
    ];

    $result = \Bitrix\Pull\Event::add($userId, $arMessage);
    if (!$result) {
        //.. error
    }
}

