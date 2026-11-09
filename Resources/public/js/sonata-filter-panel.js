(function ($) {
    'use strict';

    function debounce(fn, wait) {
        var timeout;

        return function () {
            var context = this;
            var args = arguments;

            clearTimeout(timeout);
            timeout = setTimeout(function () {
                fn.apply(context, args);
            }, wait);
        };
    }

    $(document).ready(function () {
        $('.js-open-filters').on('click', function () {
            $('.js-grid').toggleClass('open');
        });

        var $floatingPanel = $('.sonata-filter-floating-panel');
        if (!$floatingPanel.length) {
            return;
        }

        var $inputs = $('.sonata-filter-form').find(
            'input[name^="filter["], select[name^="filter["], [id^="dp_"]'
        );

        $inputs.each(function (i, input) {
            if (input.getAttribute('type') === 'hidden') {
                var observer = new MutationObserver(function (mutations) {
                    if (mutations[0].attributeName === 'value') {
                        $(input).trigger('change');
                    }
                });

                observer.observe(input, {
                    attributes: true
                });
            }
        });

        var hideFloatingPanel = debounce(function () {
            $floatingPanel.hide();
        }, 10000);

        var showFloatingPanel = debounce(function (event) {
            var $group = $(event.target).closest('.form-group');
            if (!$group.length) {
                return;
            }

            $floatingPanel.show();
            $floatingPanel.css({ top: $group.position().top + 'px' });
            hideFloatingPanel();
        }, 500);

        $inputs.on('change ifToggled input', showFloatingPanel);
    });
})(jQuery);
