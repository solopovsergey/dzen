
-- сохраним текущее значение в переменную
SET @oldSqlModeSession = (SELECT @@SESSION.sql_mode SESSION);

-- сбросим ограничения
SET sql_mode = '';

-- Далее выполняет ся конвертация кодировки таблицы
ALTER   TABLE `db`.`b_vote` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Затем восстанваливается значение режима
SET sql_mode = @oldSqlModeSession;
