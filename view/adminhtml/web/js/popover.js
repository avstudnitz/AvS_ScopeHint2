/**
 * ScopeHint grouped expandable popover.
 *
 * Registered via data-mage-init so Magento runs init() automatically.
 * Uses addEventListener only — no inline handlers, no eval. CSP-safe.
 */
define(['jquery'], function ($) {
    'use strict';

    function Popover(config, element) {
        this.$root    = $(element);
        this.$trigger = this.$root.find('.scopehint-trigger').first();
        this.$panel   = this.$root.find('.scopehint-panel').first();
        this.$search  = this.$root.find('.scopehint-search').first();
        this.$close   = this.$root.find('.scopehint-close').first();

        this._outsideHandler = null;

        this._bind();
    }

    Popover.prototype._bind = function () {
        var self = this;

        // Block click propagation from anywhere inside the popover so that grid
        // row-click handlers (admin product grid navigates to edit on row click)
        // do not fire when the user interacts with the trigger or the panel.
        this.$root.on('click', function (e) {
            e.stopPropagation();
        });

        this.$trigger.on('click', function (e) {
            e.preventDefault();
            self._toggle();
        });

        this.$close.on('click', function (e) {
            e.preventDefault();
            self._close();
        });

        this.$panel.on('keydown', function (e) {
            if (e.key === 'Escape') {
                self._close();
            }
        });

        if (this.$search.length) {
            this.$search.on('input', function () {
                self._filter($(this).val());
            });
        }

        // Up/Down arrow navigation between <summary> elements
        this.$panel.on('keydown', 'summary', function (e) {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') {
                return;
            }
            e.preventDefault();

            var $summaries = self.$panel.find('summary').filter(function () {
                return $(this).closest('[data-search-hidden="true"]').length === 0;
            });

            var currentIndex = $summaries.index(this);
            var nextIndex = e.key === 'ArrowDown'
                ? currentIndex + 1
                : currentIndex - 1;

            if (nextIndex >= 0 && nextIndex < $summaries.length) {
                $summaries.eq(nextIndex).trigger('focus');
            }
        });
    };

    Popover.prototype._toggle = function () {
        var expanded = this.$trigger.attr('aria-expanded') === 'true';
        if (expanded) {
            this._close();
        } else {
            this._open();
        }
    };

    Popover.prototype._open = function () {
        this.$trigger.attr('aria-expanded', 'true');
        this.$panel.removeAttr('hidden');

        if (this.$search.length) {
            this.$search.trigger('focus');
        }

        this._registerOutsideClick();
    };

    Popover.prototype._close = function () {
        this.$trigger.attr('aria-expanded', 'false');
        this.$panel.attr('hidden', '');
        this.$trigger.trigger('focus');
        this._unregisterOutsideClick();
    };

    Popover.prototype._registerOutsideClick = function () {
        var self = this;
        this._outsideHandler = function (e) {
            if (!self.$root[0].contains(e.target)) {
                self._close();
            }
        };
        document.addEventListener('click', this._outsideHandler, true);
    };

    Popover.prototype._unregisterOutsideClick = function () {
        if (this._outsideHandler) {
            document.removeEventListener('click', this._outsideHandler, true);
            this._outsideHandler = null;
        }
    };

    Popover.prototype._filter = function (query) {
        var needle = (query || '').toLowerCase().trim();

        this.$panel.find('[data-website-code]').each(function () {
            var $website    = $(this);
            var $storeItems = $website.find('ul > li[data-store-code]');
            var visibleCount = 0;

            $storeItems.each(function () {
                var $li   = $(this);
                var text  = $li.text().toLowerCase();
                var match = needle === '' || text.indexOf(needle) !== -1;

                if (match) {
                    $li.removeAttr('data-search-hidden');
                    visibleCount++;
                } else {
                    $li.attr('data-search-hidden', 'true');
                }
            });

            var $summary     = $website.find('summary').first();
            var summaryMatch = needle === '' || $summary.text().toLowerCase().indexOf(needle) !== -1;

            if (!summaryMatch && visibleCount === 0) {
                $website.attr('data-search-hidden', 'true');
            } else {
                $website.removeAttr('data-search-hidden');
                if (needle !== '' && visibleCount > 0) {
                    $website.find('details').first().attr('open', '');
                }
            }
        });
    };

    return {
        'AvS_ScopeHint/js/popover': function (config, element) {
            new Popover(config, element);
        }
    };
});
