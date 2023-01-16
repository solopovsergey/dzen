<?php

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    "bxmaker.autositemap",
    "onSitemapStep",
    ["CBXmakerEventHandler", 'bxmaker_autositemap_onSitemapStep']
);
$eventManager->addEventHandler(
    "bxmaker.autositemap",
    "onSitemapStepPrepare",
    ["CBXmakerEventHandler", 'bxmaker_autositemap_onSitemapStepPrepare']
);


class CBXmakerEventHandler
{

    /**
     * Поместит ссылку на карту "Сотбит: SEO умного фильтра" в конец файла с картой сайта
     * @param \Bitrix\Main\Event $event
     * @return \Bitrix\Main\EventResult
     */
    public function bxmaker_autositemap_onSitemapStep(\Bitrix\Main\Event $event)
    {
        $NS = $event->getParameter('NS');

        if (!empty($NS['XML_FILES'])) {
            usort($NS['XML_FILES'], function ($row) {
                if (strpos($row, 'sitemap_seometa_') !== false) {
                    return 1;
                }
                return 0;
            });
        }

        $arReturn = [
            'NS' => $NS,
        ];

        $result = new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, $arReturn);
        return $result;
    }


    /**
     * Запускает генерацию карты на основе данных модуля  "Сотбит: SEO умного фильтра" перед использованием в основной карте
     * @param \Bitrix\Main\Event $event
     * @return \Bitrix\Main\EventResult
     */
    public function bxmaker_autositemap_onSitemapStepPrepare(\Bitrix\Main\Event $event)
    {
        $NS = $event->getParameter('NS');
        $arMainSitemap = $event->getParameter('arSitemap');
        $percent = 0;
        $dataKey = 'bxmaker_autositemap_onSitemapStepPrepare_' . $arMainSitemap['ID'];

        if (!isset($NS[$dataKey])) {
            $NS[$dataKey] = [];
        }

        $requestData = &$NS[$dataKey];


        try {
            if (empty($requestData)) {

                //ищем настройки
                $sitemapId = null;
                $dbr = \Sotbit\Seometa\SitemapTable::getList([
                    'filter' => [
                        'SITE_ID' => $arMainSitemap['SITE_ID']
                    ]
                ]);
                if ($ar = $dbr->Fetch()) {

                    $arSettings = unserialize($ar['SETTINGS']);

                    if ($arSettings['FILENAME_INDEX'] === $arMainSitemap["SETTINGS"]["FILENAME_INDEX"]) {
                        $sitemapId = $ar['ID'];
                    }
                }

                if (is_null($sitemapId)) {
                    throw new Exception(sprintf('Для основной карты [ID:%s] сайта %s не найдены настройки в модуле Сотбит: SEO умного фильтра...', $arMainSitemap['ID'], $arMainSitemap["SITE_ID"]));
                }


                $requestData['ID'] = $sitemapId;
                $requestData['limit'] = 10000;
                $requestData['offset'] = 0;
                $requestData['sitemap_index'] = 1;
            }

            if (isset($requestData['data'])) {
                $requestData = json_decode($requestData['data'], true);
            }

            if (!CModule::IncludeModule('sotbit.seometa')) {
                $percent = 100;
            } else {
                $arSitemap = \Sotbit\Seometa\SitemapTable::getById($requestData['ID'])->fetch();
                if ($arSitemap['SITE_ID']) {
                    $optionCountLinksForOperation = \Bitrix\Main\Config\Option::get(
                        \CSeoMeta::MODULE_ID,
                        'SEOMETA_SITEMAP_COUNT_LINKS_FOR_OPERATION',
                        '10000',
                        $arSitemap['SITE_ID']
                    );

                    $optionCountLinks = \Bitrix\Main\Config\Option::get(
                        \CSeoMeta::MODULE_ID,
                        'SEOMETA_SITEMAP_COUNT_LINKS',
                        '50000',
                        $arSitemap['SITE_ID']
                    );

                    $requestData['limit'] = ($optionCountLinksForOperation < $optionCountLinks ? $optionCountLinksForOperation : $optionCountLinks);
                }

                $seometaSitemap = new \CSeoMetaSitemapLight();
                $sitePaths = $seometaSitemap->pathMainSitemap($requestData['ID']);
                $requestData['SITE_ID'] = $sitePaths['site_id'];

                foreach ($requestData as $key => $value) {
                    $seometaSitemap->setRequestData($key, $value);
                }


                if (!is_array($arSitemap) || $sitePaths['TYPE'] === 'ERROR') {
                    throw new Exception('Не найдена карта сайт');
                }

                $arSitemap['SETTINGS'] = unserialize($arSitemap['SETTINGS']);


                if (!$sitePaths['domain_dir']) {
                    throw new Exception('Не удалось определить корневую директорию сайта для карты');
                }

                $SiteUrl = $sitePaths['domain_dir'];

                //делаем бэкап
                if (!isset($requestData['progressbar'])
                    && (new \Sotbit\Seometa\Helper\BackupMethods)->makeBackup($sitePaths['abs_path']) == '') {
                    $seometaSitemap->deleteOldSeometaSitemaps($sitePaths['abs_path']);
                }


                $arrConditionsParams = \Sotbit\Seometa\ConditionTable::getConditionBySiteId($sitePaths['site_id']);

                $filter['ACTIVE'] = 'Y';

                if ($arSitemap['SETTINGS']['EXCLUDE_NOT_SEF'] == 'Y') {
                    $filter['CONDITION_ID'] = [];

                    foreach ($arrConditionsParams as $conditionParam) {
                        $filter['CONDITION_ID'] = array_merge(
                            $filter['CONDITION_ID'],
                            [$conditionParam['ID']]
                        );
                    }
                }

                $arrUrls = \Sotbit\Seometa\SeometaUrlTable::getList(
                    [
                        'select' => [
                            'ID',
                            'NEW_URL',
                            'REAL_URL',
                            'DATE_CHANGE',
                            'CONDITION_ID'
                        ],
                        'filter' => $filter,
                        'order' => ['ID'],
                        'limit' => $requestData['limit'],
                        'offset' => $requestData['offset'] !== 0 ? $requestData['limit'] * $requestData['offset'] : 0
                    ]
                )->fetchAll();

                if ($arrUrls !== false && count($arrUrls) > 0) {
                    $_REQUEST = array_merge($_REQUEST, $requestData);

                    $requestData['data'] = $seometaSitemap->generateSitemap(
                        $arrUrls,
                        $SiteUrl
                    );

                    $return = json_decode($requestData['data'], true);
                    if (preg_match('/([\d]+%)/', $return['progressbar'], $match)) {
                        $percent = min(99, (int)$match[1]);
                    }
                } else {
                    \Sotbit\Seometa\SitemapTable::update(
                        $requestData['ID'],
                        ['DATE_RUN' => new Bitrix\Main\Type\DateTime()]
                    );

                    if (!isset($NS['XML_FILES'])) {
                        $NS['XML_FILES'] = [];
                    }


                    $i = 1;
                    while ($i <= $requestData['sitemap_index']) {
                        $xmlFile = 'sitemap_seometa_' . $requestData['ID'] . '_' . $i . '.xml';
                        if (!in_array($xmlFile, $NS['XML_FILES'])) {
                            $NS['XML_FILES'][] = $xmlFile;
                        }

                        $i++;
                    }
                    $percent = 100;
                }
            }
        } catch (\Throwable $ex) {
            $percent = 100;

            \CEventLog::Add([
                "SEVERITY" => "ERROR",
                "AUDIT_TYPE_ID" => "ERROR_PREPARE",
                "MODULE_ID" => "bxmaker.sitemap",
                "ITEM_ID" => '',
                "DESCRIPTION" => "Ошибка при подготовке дополнительной краты через sotbit.seometa - " . $ex->getMessage() . ' | ' . $ex->getTraceAsString(),
            ]);
        }


        $arReturn = [
            'NS' => $NS,
            'COMPLETE' => $percent >= 100,
            'STATUS' => isset($ex) ? $ex->getMessage() . ' | ' . $ex->getTraceAsString() : sprintf(
                'Подготовка карты для - Сотбит: SEO умного фильтра, выполнено на %d %%',
                $percent
            ),
        ];

        $result = new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, $arReturn);
        return $result;
    }
}
