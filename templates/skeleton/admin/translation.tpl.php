<?php
$fieldsTemplate = '';
foreach ($fields as $fieldName => $fieldOptions) {
    if (!$fieldOptions['column_name']) {
        continue;
    }

    $columnName = $fieldOptions['column_name'];
    $translation = $fieldOptions['translation'];

    $fieldsTemplate .= "    label_$columnName: $translation\n";
}
?>
filter:
<?php echo $fieldsTemplate; ?>

list:
    label__action: ''
<?php echo $fieldsTemplate; ?>

form:
<?php echo $fieldsTemplate; ?>

show:
<?php echo $fieldsTemplate; ?>

export:
<?php echo $fieldsTemplate; ?>

dashboard:
    label_<?php echo $normalizedServiceId; ?>: <?php echo $modelClass->getShortName()."\n"; ?>

breadcrumb:
    link_<?php echo $normalizedServiceId; ?>_list: <?php echo $modelClass->getShortName()."\n"; ?>
    link_<?php echo $normalizedServiceId; ?>_create: Создать
    link_<?php echo $normalizedServiceId; ?>_delete: Удалить

<?php
foreach ($fields as $fieldName => $fieldOptions) {
    if (!isset($fieldOptions['enum_choices'])) {
        continue;
    }

    $columnName = $fieldOptions['column_name'];

    echo "    $columnName:\n";
    foreach ($fieldOptions['enum_choices'] as $enumChoice) {
        echo "        $enumChoice: $enumChoice\n";
    }
}
?>
