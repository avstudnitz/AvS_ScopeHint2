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

        this._outsideHandler    = null;
        this._keyHandler        = null;
        this._repositionHandler = null;
        this._portaled          = false;
        this._panelOrigParent   = null;
        this._panelOrigNext     = null;

        this._bind();
    }

    /**
     * Magento's toggleValueElements() disables every button and input in the
     * value cell when "Use Default/System Value" is ticked, which would lock the
     * hint of an inherited field. The panel is checked as well because it is
     * moved to <body> while open.
     */
    Popover.prototype._keepControlsEnabled = function () {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.target.disabled) {
                    mutation.target.disabled = false;
                    mutation.target.classList.remove('disabled');
                }
            });
        });
        var options = {attributes: true, attributeFilter: ['disabled'], subtree: true};

        observer.observe(this.$root[0], options);
        observer.observe(this.$panel[0], options);
    };

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

        this.$panel.on('click', function (e) {
            e.stopPropagation();
        });

        if (this.$search.length) {
            this.$search.on('input', function () {
                self._filter($(this).val());
                if (self._portaled) {
                    self._positionPanel();
                }
            });
        }

        this._keepControlsEnabled();

        // toggle does not bubble, so listen in the capture phase for every <details>.
        this.$panel[0].addEventListener('toggle', function () {
            if (self._portaled) {
                self._positionPanel();
            }
        }, true);

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

        if (this.$root.hasClass('scopehint-popover--cell')) {
            this._portalPanel();
        }

        if (this.$search.length) {
            this.$search.trigger('focus');
        }

        this._registerOutsideClick();
        this._registerEscapeKey();
    };

    Popover.prototype._close = function (options) {
        this.$trigger.attr('aria-expanded', 'false');
        this.$panel.attr('hidden', '');
        if (this._portaled) {
            this._restorePanel();
        }
        // Focusing a trigger that scrolled out of view would scroll the page back to it.
        if (!options || options.restoreFocus !== false) {
            this.$trigger.trigger('focus');
        }
        this._unregisterOutsideClick();
        this._unregisterEscapeKey();
    };

    Popover.prototype._portalPanel = function () {
        if (this._portaled) {
            return;
        }
        this._panelOrigParent = this.$panel.parent()[0];
        this._panelOrigNext   = this.$panel[0].nextSibling;

        this.$panel.css({ position: 'fixed', right: 'auto' });
        document.body.appendChild(this.$panel[0]);
        this._portaled = true;
        this._positionPanel();
        this._registerReposition();
    };

    // The panel is position:fixed, so it has to be re-anchored whenever the page or a
    // scrollable ancestor of the grid moves its trigger.
    Popover.prototype._positionPanel = function () {
        var gap         = 8;
        var triggerRect = this.$trigger[0].getBoundingClientRect();
        var panel       = this.$panel[0];
        var spaceBelow  = window.innerHeight - triggerRect.bottom - gap;
        var spaceAbove  = triggerRect.top - gap;

        // Capping the height instead of clamping top keeps the panel off its trigger, which
        // _isTriggerVisible() would otherwise hit-test as hidden and close the panel.
        this.$panel.css('max-height', Math.max(0, Math.min(window.innerHeight * 0.6, Math.max(spaceAbove, spaceBelow))) + 'px');

        var opensAbove  = panel.offsetHeight > spaceBelow && spaceAbove > spaceBelow;
        var top         = opensAbove
            ? triggerRect.top - gap - panel.offsetHeight
            : triggerRect.bottom + gap;
        var left        = Math.max(0, Math.min(triggerRect.left, window.innerWidth - panel.offsetWidth));

        this.$panel.css({ top: top + 'px', left: left + 'px' });
    };

    Popover.prototype._registerReposition = function () {
        var self        = this;
        var frameQueued = false;

        this._repositionHandler = function () {
            if (frameQueued) {
                return;
            }
            frameQueued = true;
            window.requestAnimationFrame(function () {
                frameQueued = false;
                if (!self._portaled) {
                    return;
                }
                if (self._isTriggerVisible()) {
                    self._positionPanel();
                } else {
                    self._close({ restoreFocus: false });
                }
            });
        };
        window.addEventListener('scroll', this._repositionHandler, true);
        window.addEventListener('resize', this._repositionHandler);
    };

    // Hit-testing also catches the trigger sliding under the grid's sticky header.
    Popover.prototype._isTriggerVisible = function () {
        var rect = this.$trigger[0].getBoundingClientRect();

        if (rect.bottom <= 0 || rect.top >= window.innerHeight) {
            return false;
        }

        var topmost = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);

        return topmost !== null && this.$root[0].contains(topmost);
    };

    Popover.prototype._unregisterReposition = function () {
        if (this._repositionHandler) {
            window.removeEventListener('scroll', this._repositionHandler, true);
            window.removeEventListener('resize', this._repositionHandler);
            this._repositionHandler = null;
        }
    };

    Popover.prototype._restorePanel = function () {
        if (!this._portaled) {
            return;
        }

        this._unregisterReposition();
        this.$panel.css({ position: '', top: '', left: '', right: '', 'max-height': '' });

        if (this._panelOrigParent) {
            if (this._panelOrigNext && this._panelOrigNext.parentNode === this._panelOrigParent) {
                this._panelOrigParent.insertBefore(this.$panel[0], this._panelOrigNext);
            } else {
                this._panelOrigParent.appendChild(this.$panel[0]);
            }
        }

        this._portaled        = false;
        this._panelOrigParent = null;
        this._panelOrigNext   = null;
    };

    Popover.prototype._registerOutsideClick = function () {
        var self = this;
        this._outsideHandler = function (e) {
            var insideRoot  = self.$root[0].contains(e.target);
            var insidePanel = self.$panel[0].contains(e.target);
            if (!insideRoot && !insidePanel) {
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

    Popover.prototype._registerEscapeKey = function () {
        var self = this;
        this._keyHandler = function (e) {
            if (e.key === 'Escape') {
                self._close();
            }
        };
        document.addEventListener('keydown', this._keyHandler, true);
    };

    Popover.prototype._unregisterEscapeKey = function () {
        if (this._keyHandler) {
            document.removeEventListener('keydown', this._keyHandler, true);
            this._keyHandler = null;
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
