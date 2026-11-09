# glavweb/admin-bundle

Sonata 4 extras: AbstractAdmin, Native datagrid, form types, sortable, `make:admin`,
shared Sonata Twig UI.

Не зависит от `framework-bundle`. RequestBag — из `glavweb/extra-bundle`.

## Twig templates

Общий UI админки (defaults через `prependExtension`):

```text
templates/
├── Layout/               # admin_layout (filters right column), list_table_and_filters
├── Core/                 # user_block
├── CRUD/                 # list/edit/show, sort, outer_list_rows_list, …
├── Filter/               # advanced filter page / sidebar / button / list header
├── fieldtypes/           # enum, user, glavweb_image/file, raw_text
├── Menu/                 # knp sidebar
├── macros/
├── Security/             # login (+ layout)
├── SonataAdmin/          # overrides @SonataAdmin (buttons, list actions, …)
├── form/                 # form themes
└── sonata-admin/CRUD/    # media / enum_array field types
```

Ссылки: `@GlavwebAdmin/CRUD/list.html.twig`, `@GlavwebAdmin/Layout/admin_layout.html.twig`,
`@SonataAdmin/Button/edit_button.html.twig`.

### App layout / login / user_block

В приложении — тонкие extends:

```twig
{# templates/admin/layout.html.twig #}
{% extends '@GlavwebAdmin/Layout/admin_layout.html.twig' %}
{% block stylesheets %}
    {{ parent() }}
    {{ encore_entry_link_tags('app') }}
{% endblock %}
```

```twig
{# templates/admin/user_block.html.twig #}
{% extends '@GlavwebAdmin/Core/user_block.html.twig' %}
```

```twig
{# templates/security/login.html.twig — обязателен: его рендерит SecurityController бандла #}
{% extends '@GlavwebAdmin/Security/login.html.twig' %}
{# переопределяйте блоки бандла (логотип, подпись, login_check_path) здесь #}
```

Defaults: `user_block`, `outer_list_rows_list`, list/edit/show/knp_menu — в
`config/packages/sonata_admin.yaml` бандла. Dashboard — в приложении.

`outer_list_rows_list` использует Twig-тесты `archivable` / `archived` (регистрирует
приложение, напр. ArchiveExtension).

## List filters (right column)

`@GlavwebAdmin/Layout/admin_layout.html.twig` кладёт таблицу в `col-md-10`, фильтры в
`col-md-2`. Floating «Применить»: `Resources/public/js/sonata-filter-panel.js`.

В приложении после `assets:install`:

```yaml
# config/packages/sonata_admin.yaml
sonata_admin:
    templates:
        layout: admin/layout.html.twig
    assets:
        extra_stylesheets:
            - bundles/glavwebadmin/css/glavweb-admin.css
        extra_javascripts:
            - bundles/glavwebadmin/js/sonata-filter-panel.js
```

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

`hasDisplayableFilters()` / `isHiddenDisplayableFilter()` — в `AbstractAdmin` (баннер
«Данные фильтрованы!»).

## Case-insensitive StringFilter

`CaseInsensitiveStringFilterFactory` декорирует `sonata.admin.builder.filter.factory`:
строковые фильтры (`StringFilter` / `->add('field')`) по умолчанию с
`force_case_insensitivity => true`. Явно выключить: `'force_case_insensitivity' => false`.
Legacy `case_sensitive` мапится в новую опцию.

Для CallbackFilter: `FilterCallback::applyCaseInsensitiveFullName()` /
`applyCaseInsensitiveContains()`.

`NativeCallbackFilter` зарегистрирован как `native_callback`.

## Delete in list

`DontUseDeleteActionInListExtension` (global) запрещает `delete` в списке и batch, кроме
админок с `WithDeleteActionInList`. Снять на весь проект:

```yaml
glavweb_admin:
    delete_in_list: allow   # default: deny
```

## ModelAutocompleteType: dialog_layout

`Form\Extension\ModelAutocompleteTypeExtension` добавляет опцию `dialog_layout`
(`compact` default | `wide` | `drawer`) для модалки `btn_add`; рендер —
`SonataAdmin/CRUD/Association/edit_modal.html.twig`.

## Translations

Общие домены в бандле: `admin`, `messages` (archive flash), `SonataAdminBundle` (link_action_*,
label__archived, label_group_users), `GlavwebAdminBundle` (advanced_filter, login, user_block,
archive.action_*, image_help.*) — ru/en.

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

Готовый CSS: `Resources/public/css/glavweb-admin.css`. После `assets:install` —
`bundles/glavwebadmin/css/glavweb-admin.css` (см. extra_stylesheets выше).
