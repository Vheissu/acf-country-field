/**
 * ACF Country Field editor script.
 *
 * Fills the state list when the United States is selected, and loads cities for the selected
 * country (and state) from admin-ajax.
 *
 * @package AcfCountryField
 */
(function ($, acf) {
    'use strict';

    const config = window.acfCountryField;

    if (!config || !acf) {
        return;
    }

    // Requests for city lists, keyed by "country:state", shared by every field on the page.
    const cityRequests = new Map();

    function fetchCities(countryId, stateId) {
        const key = countryId + ':' + stateId;

        if (!cityRequests.has(key)) {
            const body = new URLSearchParams({
                action: config.action,
                nonce: config.nonce,
                country_id: String(countryId),
                state_id: String(stateId),
            });

            const request = fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (response) {
                    return response.json();
                })
                .then(function (json) {
                    if (!json || !json.success || !Array.isArray(json.data)) {
                        throw new Error('Bad response');
                    }

                    return json.data;
                });

            // Let a failed request be tried again.
            request.catch(function () {
                cityRequests.delete(key);
            });

            cityRequests.set(key, request);
        }

        return cityRequests.get(key);
    }

    function setOptions(select, items, placeholder) {
        const options = [new Option(placeholder, '')];

        items.forEach(function (item) {
            options.push(new Option(item.name, String(item.id)));
        });

        select.replaceChildren.apply(select, options);
        select.disabled = items.length === 0;

        // Refresh Select2 without running change handlers.
        $(select).trigger('change.select2');
    }

    function enhance(select) {
        // ACF loads Select2 unless a site turns it off.
        if (typeof acf.newSelect2 === 'function' && $.fn.select2) {
            acf.newSelect2($(select));
        }
    }

    function initField($el) {
        const root = $el[0] && $el[0].querySelector('.acf-country-field');

        if (!root || root.dataset.ready || $el.closest('.acf-clone').length) {
            return;
        }

        root.dataset.ready = '1';

        const part = function (name) {
            const wrapper = root.querySelector('[data-part="' + name + '"]');

            return wrapper ? { wrapper: wrapper, select: wrapper.querySelector('select') } : null;
        };

        const country = part('country');
        const state = part('state');
        const city = part('city');
        let latest = 0;

        root.querySelectorAll('select').forEach(enhance);

        function countryId() {
            return parseInt(country.select.value, 10) || 0;
        }

        function filtersByState() {
            return state !== null && countryId() === config.usaId;
        }

        function loadCities() {
            if (!city) {
                return;
            }

            const id = countryId();
            const stateId = filtersByState() ? parseInt(state.select.value, 10) || 0 : 0;
            const request = ++latest;

            if (!id || (filtersByState() && !stateId)) {
                setOptions(city.select, [], id ? config.i18n.selectStateFirst : config.i18n.selectCity);
                return;
            }

            setOptions(city.select, [], config.i18n.loading);
            root.setAttribute('aria-busy', 'true');

            fetchCities(id, stateId)
                .then(function (cities) {
                    if (request === latest) {
                        setOptions(city.select, cities, config.i18n.selectCity);
                    }
                })
                .catch(function () {
                    if (request === latest) {
                        setOptions(city.select, [], config.i18n.loadFailed);
                    }
                })
                .finally(function () {
                    if (request === latest) {
                        root.removeAttribute('aria-busy');
                    }
                });
        }

        $(country.select).on('change', function () {
            if (state) {
                const isUsa = filtersByState();

                state.wrapper.hidden = !isUsa;
                setOptions(state.select, isUsa ? config.states : [], config.i18n.selectState);
            }

            loadCities();
        });

        if (state) {
            $(state.select).on('change', loadCities);
        }
    }

    ['country_field', 'COUNTRY_FIELD'].forEach(function (type) {
        acf.addAction('ready_field/type=' + type, function (field) {
            initField(field.$el);
        });

        acf.addAction('append_field/type=' + type, function (field) {
            initField(field.$el);
        });
    });
})(jQuery, window.acf);
