/**
 * Bootstrap for EAV form popovers injected via the `additionalInfo` slot.
 *
 * Knockout's html= binding injects raw HTML into the DOM after mage/apply has
 * already run, so data-mage-init is never processed on those nodes. This module
 * listens for `contentUpdated` (fired by Magento's UI framework after KO renders)
 * and document-ready, then manually instantiates the popover widget on any
 * [data-scopehint-popover] node that has not yet been bound.
 */
define(['jquery', 'AvS_ScopeHint/js/popover'], function ($, popoverFactory) {
    'use strict';

    // Page Builder fields render additionalInfo below the full-width stage, where the badge
    // is detached from its field and the open panel covers the label of the next field.
    function anchorToPageBuilderLabel(popover) {
        var $label = $(popover).closest('.admin__field-page-builder').find('.admin__field-label').first();

        if (!$label.length) {
            return;
        }

        $label.children('[data-scopehint-popover]').not(popover).remove();
        $label.append(popover);
    }

    function bindAll(root) {
        $(root || document).find('[data-scopehint-popover]').addBack('[data-scopehint-popover]').each(function () {
            if (this.dataset.scopehintBound === '1') {
                return;
            }
            this.dataset.scopehintBound = '1';
            anchorToPageBuilderLabel(this);
            popoverFactory['AvS_ScopeHint/js/popover']({}, this);
        });
    }

    $(document).on('contentUpdated', function (e) {
        bindAll(e.target);
    });

    $(function () {
        setTimeout(bindAll, 0);

        if (window.MutationObserver && document.body) {
            new MutationObserver(function (records) {
                records.forEach(function (record) {
                    Array.prototype.forEach.call(record.addedNodes, function (node) {
                        if (node.nodeType === 1) {
                            bindAll(node);
                        }
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        }
    });

    return bindAll;
});
