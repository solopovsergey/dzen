<?php

// пример кода для добавления в файл /bitrix/php_interface/init.php
// статья - https://dzen.ru/a/ZOiP4Lx3n3SpfiXh
// выполнить через админку PHP код - \MergeAccount::addAgent();



class MergeAccount
{
    /**
     * Добавление агента
     *
     * @return false|int
     */
    public static function addAgent()
    {
        return \CAgent::AddAgent('\\' . __CLASS__ . '::agent();', '', 'N', 60 * 60);
    }

    /**
     * Выполнение агента, будет объединять профили автоматически в фоне без вашегоучастия
     * @return string
     */
    public static function agent()
    {
        try {
            $query = \Bitrix\Main\UserTable::query();
            $query->setSelect(['EMAIL']);
            $query->addSelect(new \Bitrix\Main\ORM\Fields\ExpressionField('CNT', 'COUNT(%s)', 'EMAIL'));
            $query->setGroup('EMAIl');
            $query->having('CNT', '>', 1);
            $query->setLimit(10);
            $dbr = $query->exec();

            while ($ar = $dbr->fetch()) {
                self::mergeByEmail($ar['EMAIL']);
            }
        } catch (\Exception $ex) {
            \CEventLog::Log(
                'ERROR',
                'MergeAccount',
                '',
                '',
                sprintf('%s. %s:%s. %s', $ex->getMessage(), $ex->getFile(), $ex->getLine(), $ex->getTraceAsString())
            );
        }


        return '\\' . __METHOD__ . '();';
    }

    /**
     * Объединяет аккаунты пользователя
     *
     * @param $email
     *
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function mergeByEmail($email)
    {
        global $APPLICATION, $USER;
        if (!check_email($email, true)) {
            return false;
        }

        // подклчюаем необходимый модуль
        \Bitrix\Main\Loader::includeModule('sale');

        // собираем всех польвзаотелей с такой почтой
        $arUserId = \Bitrix\Main\UserTable::query()->addSelect('ID')->where('EMAIL', $email)->exec()->fetchAll();
        $arUserId = array_column($arUserId, 'ID');
        asort($arUserId);


        // берем последний добавленый аккаунт и присоединяем к нему остальные
        $userId = array_pop($arUserId);
        if (count($arUserId)) {
            //переносим корзину ----------------------------------------
            $oFUser = new \Bitrix\Sale\FuserTable();
            $dbrFUser = $oFUser::getList([
                'filter' => [
                    'USER_ID' => $arUserId
                ]
            ]);
            while ($arFUser = $dbrFUser->fetch()) {
                $resultUpdate = $oFUser::update($arFUser['ID'], [
                    'USER_ID' => $userId
                ]);
                if (!$resultUpdate->isSuccess()) {
                    throw new \Exception(
                        'Не удалось обновить привязки в корзине для пользователя - '
                        . $arFUser['USER_ID'] . ' - '
                        . implode(' ', $resultUpdate->getErrorMessages())
                    );
                }
            }

            //переносим профили -----------------------------------------
            $oSaleUserProps = new \CSaleOrderUserProps();
            $dbrItems = $oSaleUserProps::GetList(
                ["DATE_UPDATE" => "DESC"],
                [
                    "USER_ID" => $arUserId
                ]
            );
            while ($arItem = $dbrItems->Fetch()) {
                $resultUpdate = $oSaleUserProps::update($arItem['ID'], [
                    'USER_ID' => $userId
                ]);
                if (!$resultUpdate) {
                    $strError = '';
                    if ($ex = $APPLICATION->GetException()) {
                        $strError = $ex->GetString();
                    }
                    throw new \Exception(
                        'Не удалось обновить привязки в профилю для пользователя - ' . $arItem['USER_ID'] . ' - ' . $strError
                    );
                }
            }

            // переносим заказы ---------------------------------
            $oOrder = new \Bitrix\Sale\OrderTable();

            $dbrItems = $oOrder::getList([
                'filter' => [
                    'USER_ID' => $arUserId
                ],
                'select' => [
                    'ID',
                    'USER_ID',
                    'CREATED_BY'
                ]
            ]);
            while ($arItem = $dbrItems->fetch()) {
                $resultUpdate = $oOrder::update($arItem['ID'], [
                    'USER_ID' => $userId,
                    'CREATED_BY' => $userId
                ]);
                if (!$resultUpdate->isSuccess()) {
                    throw new \Exception(
                        'Не удалось обновить привязки в заказам пользователя - ' . $arItem['USER_ID'] . ' - '
                        . implode(' ', $resultUpdate->getErrorMessages())
                    );
                }
            }

            //удаляем ------------------------------------------
            foreach ($arUserId as $userIdDelete) {
                $USER::delete($userIdDelete);
            }
        }

        return true;
    }

}