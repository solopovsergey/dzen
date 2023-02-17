## Выбор записей с отметкой наличия потомков

Как выбрать только страны и флаг наличия потомков

| ID  | NAME               | PARENT_ID |
|-----|--------------------|-----------|
| 1   | Россия             | null      |
| 2   | Московская область | 1         |
| 3   | Тульская область   | 1         |
| 4   | Калужская область  | 1         |
| 5   | Беларусь           | null      |


```php
// список местоположений
$query =  Location\NodeTable::query()
    ->where('PARENT_ID', null)
    ->addGroup('ID');

$query->registerRuntimeField(
    new  \Bitrix\Main\ORM\Fields\Relations\Reference(
        'CHILD',
      \Location\NodeTable::class,
        Query::filter()
            ->whereColumn('this.ID', 'ref.PARENT_ID')
    )
);

$query->setSelect(['ID', 'NAME', 'PARENT_ID']);
$query->addSelect(
    new \Bitrix\Main\ORM\Fields\ExpressionField('HAS_CHILD', 'IF(MAX(%s) IS NOT NULL, 1, 0)', 'CHILD.ID'),
    "HAS_CHILD" 
);
$arResult = $query->exec()->fetchAll(); 
```

### Результат

| ID  | NAME               | PARENT_ID | HAS_CHILD |
|-----|--------------------|-----------|-----------|
| 1   | Россия             | null      | 1         |
| 5   | Беларусь           | null      | 0         |

