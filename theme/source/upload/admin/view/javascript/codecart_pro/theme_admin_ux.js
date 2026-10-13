/* CodeCart PRO Theme v8.7.7 — route-scoped admin UX */
(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(function () {
        var root = document.querySelector('.ccp-admin-v626');
        var form = root ? root.querySelector('[data-ccp-settings-form]') : null;
        if (!root || !form) {
            return;
        }

        var sticky = root.querySelector('[data-ccp-sticky-actions]');
        var stateText = sticky ? sticky.querySelector('[data-ccp-form-state-text]') : null;
        var currentSection = sticky ? sticky.querySelector('[data-ccp-current-section]') : null;
        var initialState = '';
        var submitting = false;
        var dirty = false;
        var stateTimer = 0;
        var savedText = stateText ? stateText.textContent : '';
        var dirtyText = root.getAttribute('data-ccp-unsaved-text') || '';

        function serializeForm() {
            var data = new FormData(form);
            var rows = [];
            data.forEach(function (value, key) {
                if (key === 'ccp_scroll_top' || key === 'ccp_active_tab') {
                    return;
                }
                if (value && typeof value === 'object' && 'name' in value) {
                    rows.push(key + '=file:' + value.name + ':' + value.size);
                } else {
                    rows.push(key + '=' + String(value));
                }
            });
            rows.sort();
            return rows.join('&');
        }

        function setDirty(nextDirty) {
            dirty = !!nextDirty;
            if (!sticky) {
                return;
            }
            sticky.classList.toggle('is-dirty', dirty);
            var icon = sticky.querySelector('.ccp-sticky-state > .fa');
            if (icon) {
                icon.className = dirty ? 'fa fa-exclamation-circle' : 'fa fa-check-circle';
            }
            if (stateText) {
                stateText.textContent = dirty ? (dirtyText || stateText.getAttribute('data-dirty-text') || stateText.textContent) : savedText;
            }
        }

        function checkDirty() {
            window.clearTimeout(stateTimer);
            stateTimer = window.setTimeout(function () {
                setDirty(serializeForm() !== initialState);
            }, 80);
        }

        function updateSelectState(select) {
            if (!select || select.tagName !== 'SELECT') {
                return;
            }
            var group = select.closest ? select.closest('.form-group') : null;
            if (!group) {
                return;
            }
            var binary = select.querySelector('option[value="1"]') && select.querySelector('option[value="0"]');
            if (!binary) {
                return;
            }
            group.classList.toggle('ccp-setting-enabled', String(select.value) === '1');
            group.classList.toggle('ccp-setting-disabled', String(select.value) === '0');
        }

        function updateAllSelectStates() {
            Array.prototype.forEach.call(form.querySelectorAll('select'), updateSelectState);
        }

        function updateSection(link) {
            if (!currentSection || !link) {
                return;
            }
            currentSection.textContent = (link.textContent || '').replace(/\s+/g, ' ').trim();
        }

        function getActiveMainLink() {
            return root.querySelector('.ccp-tabs > li.active > a');
        }

        function keepOneGroupOpen(clicked) {
            var group = clicked && clicked.closest ? clicked.closest('.ccp-tab-group') : null;
            if (!group || !group.classList.contains('is-open')) {
                return;
            }
            Array.prototype.forEach.call(root.querySelectorAll('.ccp-tab-group'), function (item) {
                if (item !== group) {
                    item.classList.remove('is-open');
                }
            });
        }



        function stabilizeScrollablePanels() {
            Array.prototype.forEach.call(root.querySelectorAll('.ccp-admin-search-results,.ccp-tab-groups,.ccp-link-results'), function (panel) {
                if (panel.getAttribute('data-ccp-scroll-ready') === '1') {
                    return;
                }
                panel.setAttribute('data-ccp-scroll-ready', '1');
                panel.addEventListener('wheel', function (event) {
                    var delta = event.deltaY || 0;
                    var atTop = panel.scrollTop <= 0;
                    var atBottom = Math.ceil(panel.scrollTop + panel.clientHeight) >= panel.scrollHeight;
                    if ((delta < 0 && !atTop) || (delta > 0 && !atBottom)) {
                        event.stopPropagation();
                    }
                }, {passive: true});
            });
        }

        function initDiagnosticFilters() {
            var box = root.querySelector('[data-ccp-diagnostics]');
            if (!box) {
                return;
            }
            Array.prototype.forEach.call(box.querySelectorAll('[data-ccp-diag-filter]'), function (button) {
                button.addEventListener('click', function () {
                    var filter = button.getAttribute('data-ccp-diag-filter') || 'all';
                    Array.prototype.forEach.call(box.querySelectorAll('[data-ccp-diag-filter]'), function (item) {
                        item.classList.toggle('active', item === button);
                    });
                    Array.prototype.forEach.call(box.querySelectorAll('[data-ccp-diag-status]'), function (row) {
                        row.classList.toggle('ccp-diag-hidden', filter !== 'all' && row.getAttribute('data-ccp-diag-status') !== filter);
                    });
                });
            });
        }

        dirtyText = root.querySelector('[data-ccp-sticky-actions] [data-ccp-form-state-text]')
            ? root.querySelector('[data-ccp-sticky-actions] [data-ccp-form-state-text]').getAttribute('data-dirty-text') || ''
            : '';

        initialState = serializeForm();
        updateAllSelectStates();
        updateSection(getActiveMainLink());
        initDiagnosticFilters();
        stabilizeScrollablePanels();
        setDirty(false);

        form.addEventListener('input', function (event) {
            if (event.target && event.target.tagName === 'SELECT') {
                updateSelectState(event.target);
            }
            checkDirty();
        });
        form.addEventListener('change', function (event) {
            if (event.target && event.target.tagName === 'SELECT') {
                updateSelectState(event.target);
            }
            checkDirty();
        });
        form.addEventListener('submit', function () {
            submitting = true;
        });

        root.addEventListener('click', function (event) {
            var groupTitle = event.target && event.target.closest ? event.target.closest('.ccp-tab-group-title') : null;
            if (groupTitle) {
                window.setTimeout(function () { keepOneGroupOpen(groupTitle); }, 0);
            }
            var tab = event.target && event.target.closest ? event.target.closest('.ccp-tabs a[data-toggle="tab"]') : null;
            if (tab) {
                updateSection(tab);
            }
        });

        if (window.jQuery) {
            jQuery(document).on('shown.bs.tab', '.ccp-admin-v626 .ccp-tabs a[data-toggle="tab"]', function () {
                updateSection(this);
            });
        }

        window.addEventListener('beforeunload', function (event) {
            if (!dirty || submitting) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        });

        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 's') {
                event.preventDefault();
                submitting = true;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        });
    });
})();


