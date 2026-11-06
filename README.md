# glavweb/admin-bundle

Sonata 4 extras: AbstractAdmin, Native datagrid, form types, sortable, `make:admin`,
shared Sonata Twig UI.

Не зависит от `framework-bundle`. RequestBag — из `glavweb/extra-bundle`.

## Twig templates

Общий UI админки (defaults через `prependExtension`):

```text
templates/
├── CRUD/                 # list/edit/show, sort, show/_*, actions
├── Filter/               # advanced filter page / sidebar / button / list header
├── fieldtypes/           # enum, user, glavweb_image/file, raw_text
├── Menu/                 # knp sidebar
├── macros/
├── Security/             # login
├── SonataAdmin/          # overrides @SonataAdmin (buttons, list actions, …)
├── form/                 # form themes
└── sonata-admin/CRUD/    # media / enum_array field types
```

Ссылки: `@GlavwebAdmin/CRUD/list.html.twig`, `@SonataAdmin/Button/edit_button.html.twig`.

## Advanced filters

Отдельная страница фильтров поверх Sonata list (`AdvancedDatagridMapper` + AdvancedFilter / обычные
фильтры).

1. В Admin: `configureAdvancedDatagridFilters(AdvancedDatagridMapper $mapper)` — `with`/`add`/`end`.
2. Расширенный фильтр реализует `AdvancedFilterInterface` (тег `glavweb.advanced_filter`).
   Форма — любые поля в `buildForm` (одно или несколько).
3. Непустой mapper → авто роут `advanced_filter`, кнопка и list-header.
4. Controller: `AdvancedFilterActionTrait::renderAdvancedFilter()`.
5. Контекст: `Admin` + `getAdvancedFilterContextAttributes()` (company, …).

Полная инструкция (codeum): `doc/cookbook/admin_advanced_filters.md`.

## Case-insensitive StringFilter

`CaseInsensitiveStringFilterFactory` декорирует `sonata.admin.builder.filter.factory`:
строковые фильтры (`StringFilter` / `->add('field')`) по умолчанию с
`force_case_insensitivity => true`. Явно выключить: `'force_case_insensitivity' => false`.
Legacy `case_sensitive` мапится в новую опцию.

Для CallbackFilter: `FilterCallback::applyCaseInsensitiveFullName()` /
`applyCaseInsensitiveContains()`.

`sonata_admin.templates` list/edit/show/knp_menu и field types задаются в
`config/packages/*.yaml` бандла. Layout/user_block/dashboard — в приложении.

## Admin theme

Bundle — первоисточник переиспользуемой темы AdminLTE/Bootstrap 3 и Sonata core:

```text
Resources/assets/styles/
├── index.scss
├── tokens/
├── mixins/
├── theme/
└── sonata/
```

Sass entrypoint:

```scss
@use 'path/to/glavweb/admin-bundle/Resources/assets/styles';
```

Токены имеют значения по умолчанию и могут быть настроены до подключения
entrypoint:

```scss
@use 'path/to/glavweb/admin-bundle/Resources/assets/styles/tokens' with (
    $crm-color-primary: #3c8dbc,
    $crm-font-family: ('Arial', sans-serif),
);
@use 'path/to/glavweb/admin-bundle/Resources/assets/styles';
```

Готовый CSS публикуется в `Resources/public/css/glavweb-admin.css`. В этом
репозитории он воспроизводимо обновляется общей клиентской сборкой:

```bash
cd client-src
npm run build
```

Для генерации только готового CSS можно выполнить `npm run build:admin-theme`.
Проектные domain-стили, Sonata bridge и legacy misc намеренно не входят в
bundle и подключаются приложением после bundle entrypoint.

В проекте без Sass после `assets:install` готовый CSS подключается после
стандартных стилей Sonata/AdminLTE:

```twig
<link
    rel="stylesheet"
    href="{{ asset('bundles/glavwebadmin/css/glavweb-admin.css') }}"
>
```
