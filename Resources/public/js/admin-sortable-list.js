(function ($) {
    'use strict';

    function persistMovedRow($table, first, direction, $row) {
        $table.find('.js-sortable-move').each(function (index, item) {
            $(item).attr('data-current-position', String(first + index * direction));
        });

        var $moved = $row.find('.js-sortable-move');
        var url = String($moved.attr('data-url') || '').replace(
            'NEW_POSITION',
            String($moved.attr('data-current-position'))
        );

        if (!url) {
            return;
        }

        $.ajax({
            type: 'GET',
            url: url,
            dataType: 'json'
        });
    }

    function bindJqueryUiSortable($table, first, direction) {
        var $body = $(document.body);

        $table.find('tbody').sortable({
            handle: '.js-sortable-move',
            axis: 'y',
            cancel: 'input,textarea,select,option,button:not(.js-sortable-move)',
            tolerance: 'pointer',
            revert: 100,
            cursor: 'move',
            zIndex: 1,
            helper: function (_e, ui) {
                ui.children().each(function () {
                    var $cell = $(this);
                    $cell.width($cell.width());
                });

                return ui;
            },
            start: function () {
                $body.addClass('is-dragging');
            },
            stop: function () {
                setTimeout(function () {
                    $body.removeClass('is-dragging');
                }, 100);
            },
            update: function (_event, ui) {
                persistMovedRow($table, first, direction, ui.item);
            }
        });
    }

    function bindHtml5Sortable($table, first, direction) {
        var tbody = $table.children('tbody').get(0);
        if (!tbody || typeof Sortable === 'undefined' || typeof Sortable.create !== 'function') {
            return false;
        }

        var $body = $(document.body);

        Sortable.create(tbody, {
            draggable: 'tr',
            handle: '.js-sortable-move',
            animation: 100,
            ghostClass: 'sortable-ghost',
            onStart: function () {
                $body.addClass('is-dragging');
            },
            onEnd: function (evt) {
                setTimeout(function () {
                    $body.removeClass('is-dragging');
                }, 100);

                if (evt.oldIndex === evt.newIndex) {
                    return;
                }

                persistMovedRow($table, first, direction, $(evt.item));
            }
        });

        return true;
    }

    function initSortableList($table) {
        if ($table.data('sortable-list-initialized')) {
            return;
        }

        var movers = $table.find('.js-sortable-move');
        if (movers.length <= 1) {
            return;
        }

        var first = parseInt(movers.first().attr('data-current-position'), 10);
        var last = parseInt(movers.last().attr('data-current-position'), 10);
        var direction = first <= last ? 1 : -1;

        $table.data('sortable-list-initialized', true);

        if ($.fn.sortable) {
            bindJqueryUiSortable($table, first, direction);
            return;
        }

        if (!bindHtml5Sortable($table, first, direction)) {
            $table.removeData('sortable-list-initialized');
        }
    }

    $(function () {
        $('table.sonata-ba-list').each(function () {
            initSortableList($(this));
        });
    });
})(jQuery);
