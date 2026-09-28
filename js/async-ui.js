(function () {
    const cachePrefix = 'villa-eusebio-cache:';
    const syncKey = 'villa-eusebio-sync';
    const clientKey = 'villa-eusebio-client-id';
    const defaultTtl = 60000;

    function getStorage() {
        try {
            const testKey = cachePrefix + 'test';
            window.localStorage.setItem(testKey, '1');
            window.localStorage.removeItem(testKey);
            return window.localStorage;
        } catch (error) {
            return null;
        }
    }

    const storage = getStorage();
    let clientId = '';
    try {
        clientId = window.sessionStorage.getItem(clientKey) || '';
        if (!clientId) {
            clientId = String(Date.now()) + '-' + Math.random().toString(36).slice(2);
            window.sessionStorage.setItem(clientKey, clientId);
        }
    } catch (error) {
        clientId = String(Date.now()) + '-' + Math.random().toString(36).slice(2);
    }

    function normalizeUrl(url) {
        return new URL(url, window.location.href).toString();
    }

    function csrfToken() {
        if (window.VillaAdminCsrfToken) return String(window.VillaAdminCsrfToken);
        const meta = document.querySelector('meta[name="villa-admin-csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function ensureCsrf(form) {
        if (!form || String(form.method || 'GET').toUpperCase() !== 'POST') return;
        const token = csrfToken();
        if (!token) return;
        let input = form.querySelector('input[name="csrf_token"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            form.appendChild(input);
        }
        input.value = token;
    }

    function cacheKeyFor(url, options) {
        return cachePrefix + (options && options.cacheKey ? options.cacheKey : normalizeUrl(url));
    }

    function readCache(key, ttl, allowExpired) {
        if (!storage) return null;
        try {
            const raw = storage.getItem(key);
            if (!raw) return null;
            const cached = JSON.parse(raw);
            const age = Date.now() - Number(cached.savedAt || 0);
            if (!allowExpired && age > ttl) return null;
            return cached.value;
        } catch (error) {
            storage.removeItem(key);
            return null;
        }
    }

    function writeCache(key, value, url) {
        if (!storage) return;
        try {
            storage.setItem(key, JSON.stringify({
                savedAt: Date.now(),
                url: normalizeUrl(url),
                value: value
            }));
        } catch (error) {}
    }

    function fetchFreshJson(url, init, key) {
        const fetchInit = Object.assign({}, init || {});
        fetchInit.headers = Object.assign({
            'Accept': 'application/json'
        }, fetchInit.headers || {});
        fetchInit.credentials = fetchInit.credentials || 'same-origin';
        fetchInit.cache = fetchInit.cache || 'no-store';

        return fetch(url, fetchInit)
            .then(function (response) {
                if (!response.ok) throw new Error('Request failed with status ' + response.status);
                return response.json();
            })
            .then(function (data) {
                writeCache(key, data, url);
                return data;
            });
    }

    function sameJson(a, b) {
        try {
            return JSON.stringify(a) === JSON.stringify(b);
        } catch (error) {
            return false;
        }
    }

    function cachedJson(url, init, options) {
        const settings = Object.assign({
            ttl: defaultTtl,
            allowStale: true,
            force: false,
            revalidate: false,
            onUpdate: null
        }, options || {});
        const key = cacheKeyFor(url, settings);
        const cached = settings.force ? null : readCache(key, settings.ttl, false);
        if (cached !== null) {
            if (settings.revalidate) {
                fetchFreshJson(url, init, key)
                    .then(function (fresh) {
                        if (!sameJson(cached, fresh) && typeof settings.onUpdate === 'function') {
                            settings.onUpdate(fresh);
                        }
                    })
                    .catch(function () {});
            }
            return Promise.resolve(cached);
        }

        return fetchFreshJson(url, init, key)
            .catch(function (error) {
                const stale = settings.allowStale ? readCache(key, Number.MAX_SAFE_INTEGER, true) : null;
                if (stale !== null) return stale;
                throw error;
            });
    }

    function emitSync(detail) {
        window.dispatchEvent(new CustomEvent('villa-async-sync', { detail: detail }));
    }

    function broadcastSync(reason) {
        const detail = {
            source: clientId,
            external: false,
            reason: reason || 'update',
            time: Date.now()
        };
        if (storage) {
            try {
                storage.setItem(syncKey, JSON.stringify(detail));
            } catch (error) {}
        }
        emitSync(detail);
    }

    function onSync(callback, options) {
        const settings = Object.assign({ externalOnly: false }, options || {});
        const handler = function(event) {
            const detail = event.detail || {};
            if (settings.externalOnly && !detail.external) return;
            callback(detail);
        };
        window.addEventListener('villa-async-sync', handler);
        return function() {
            window.removeEventListener('villa-async-sync', handler);
        };
    }

    function invalidate(match, options) {
        if (!storage) return;
        const settings = Object.assign({ broadcast: false, reason: 'cache-invalidated' }, options || {});
        const needle = match ? String(match) : '';
        const keys = [];
        for (let i = 0; i < storage.length; i++) {
            const key = storage.key(i);
            if (key && key.indexOf(cachePrefix) === 0) keys.push(key);
        }

        keys.forEach(function (key) {
            if (!needle || key.indexOf(needle) !== -1) {
                storage.removeItem(key);
                return;
            }
            try {
                const cached = JSON.parse(storage.getItem(key) || '{}');
                if (String(cached.url || '').indexOf(needle) !== -1) {
                    storage.removeItem(key);
                }
            } catch (error) {}
        });
        if (settings.broadcast) {
            broadcastSync(settings.reason);
        }
    }

    function invalidateBookingCaches(options) {
        const settings = Object.assign({ broadcast: false, reason: 'booking-update' }, options || {});
        invalidate('get_booked_dates.php');
        invalidate('adminBadges');
        invalidate('bookedDates');
        invalidate('adminCalendarEvents');
        invalidate('rescheduleAvailability');
        if (settings.broadcast) {
            broadcastSync(settings.reason);
        }
    }

    function skeletonMarkup(count, className) {
        const total = count || 3;
        let html = '<span class="ve-skeleton-bars ' + (className || '') + '" aria-hidden="true">';
        for (let i = 0; i < total; i++) {
            html += '<span class="ve-skeleton-line"></span>';
        }
        html += '</span>';
        return html;
    }

    function renderCalendarSkeleton(target, cells) {
        if (!target) return;
        const total = cells || 42;
        target.classList.add('is-loading');
        target.innerHTML = '';
        for (let i = 0; i < total; i++) {
            const cell = document.createElement('span');
            cell.className = 've-skeleton-cell';
            target.appendChild(cell);
        }
    }

    function setSubmitting(form, isSubmitting) {
        if (!form) return;
        form.classList.toggle('ve-async-submitting', isSubmitting);
        form.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
            if (isSubmitting) {
                button.dataset.originalDisabled = button.disabled ? 'true' : 'false';
                button.disabled = true;
            } else if (button.dataset.originalDisabled !== 'true') {
                button.disabled = false;
            }
        });
    }

    function nearestOptimisticTarget(form) {
        if (!form) return null;
        return form.closest('tr, .gallery-admin-item, .announcement-admin-item, .settings-card, .modal-content');
    }

    function actionLooksDestructive(form) {
        const action = String(form.getAttribute('action') || '').toLowerCase();
        const actionInput = form.querySelector('input[name="action"]');
        const actionValue = actionInput ? String(actionInput.value || '').toLowerCase() : '';
        return action.indexOf('archive') !== -1 ||
            action.indexOf('restore') !== -1 ||
            action.indexOf('delete') !== -1 ||
            actionValue === 'archive' ||
            actionValue === 'gallery_delete';
    }

    function submitOptimisticForm(form, options) {
        const settings = Object.assign({
            redirect: true,
            effect: 'pending',
            errorMessage: 'Your change could not be saved. Please try again.'
        }, options || {});
        const target = settings.target || nearestOptimisticTarget(form);
        const previousClass = target ? target.className : '';
        const previousStyle = target ? target.getAttribute('style') : null;
        let rollback = null;

        if (settings.onMutate) {
            rollback = settings.onMutate();
        } else if (target) {
            target.classList.add('ve-optimistic-pending');
            if (settings.effect === 'remove') {
                target.classList.add('ve-optimistic-removed');
            }
        }

        setSubmitting(form, true);
        invalidateBookingCaches({ broadcast: false });

        ensureCsrf(form);
        const token = csrfToken();
        const headers = {
            'X-Requested-With': 'fetch'
        };
        if (token) headers['X-CSRF-Token'] = token;

        return fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            body: new FormData(form),
            credentials: 'same-origin',
            headers: headers,
            redirect: 'follow'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Request failed with status ' + response.status);
                invalidateBookingCaches({ broadcast: true, reason: settings.syncReason || 'booking-update' });
                if (settings.redirect !== false) {
                    window.location.href = response.url || form.action;
                }
                return response;
            })
            .catch(function (error) {
                if (typeof rollback === 'function') {
                    rollback();
                } else if (target) {
                    target.className = previousClass;
                    if (previousStyle === null) {
                        target.removeAttribute('style');
                    } else {
                        target.setAttribute('style', previousStyle);
                    }
                }
                setSubmitting(form, false);
                window.alert(settings.errorMessage);
                throw error;
            });
    }

    function postFormJson(form, options) {
        const settings = Object.assign({
            optimisticClass: 'subscribed'
        }, options || {});
        const previousHadClass = settings.optimisticClass ? form.classList.contains(settings.optimisticClass) : false;
        setSubmitting(form, true);
        if (settings.optimisticClass) {
            form.classList.add(settings.optimisticClass, 've-optimistic-pending');
        }

        ensureCsrf(form);
        const token = csrfToken();
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'fetch'
        };
        if (token) headers['X-CSRF-Token'] = token;

        return fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            body: new FormData(form),
            credentials: 'same-origin',
            headers: headers
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Request failed with status ' + response.status);
                return response.json();
            })
            .catch(function (error) {
                if (settings.optimisticClass && !previousHadClass) {
                    form.classList.remove(settings.optimisticClass);
                }
                throw error;
            })
            .finally(function () {
                form.classList.remove('ve-optimistic-pending');
                setSubmitting(form, false);
            });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        ensureCsrf(form);
        window.setTimeout(function () {
            if (event.defaultPrevented || form.dataset.veManaged === 'true') return;
            if (String(form.method || 'GET').toUpperCase() !== 'POST') return;

            setSubmitting(form, true);
            invalidateBookingCaches({ broadcast: false });

            const target = nearestOptimisticTarget(form);
            if (target) {
                target.classList.add('ve-optimistic-pending');
                if (actionLooksDestructive(form)) {
                    target.classList.add('ve-optimistic-removed');
                }
            }
        }, 0);
    });

    document.addEventListener('click', function(event) {
        const logoutLink = event.target.closest('a.logout-btn[href]');
        if (!logoutLink || !csrfToken()) return;
        event.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = logoutLink.href;
        document.body.appendChild(form);
        ensureCsrf(form);
        form.submit();
    });

    window.VillaAsync = {
        cachedJson: cachedJson,
        invalidate: invalidate,
        invalidateBookingCaches: invalidateBookingCaches,
        broadcastSync: broadcastSync,
        onSync: onSync,
        skeletonMarkup: skeletonMarkup,
        renderCalendarSkeleton: renderCalendarSkeleton,
        setSubmitting: setSubmitting,
        submitOptimisticForm: submitOptimisticForm,
        postFormJson: postFormJson,
        ensureCsrf: ensureCsrf
    };

    window.addEventListener('storage', function(event) {
        if (event.key !== syncKey || !event.newValue) return;
        try {
            const detail = JSON.parse(event.newValue);
            if (!detail || detail.source === clientId) return;
            detail.external = true;
            emitSync(detail);
        } catch (error) {}
    });
})();
