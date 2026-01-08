/**
 * ACF Country Field - Input JavaScript
 *
 * Handles country/city/state selection with AJAX loading.
 * Includes caching, nonce verification, and ACF integration.
 *
 * @package AcfCountryField
 */

;(function($, acf, undefined) {
    'use strict';

    // Check if ACF is available
    if (typeof acf === 'undefined') {
        console.warn('ACF Country Field: ACF is not loaded');
        return;
    }

    // Get configuration from localized script
    var config = window.acfCountryField || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        nonceKey: 'acf_country_nonce',
        usaCountryId: 446,
        i18n: {
            chooseCountry: 'Choose a country...',
            selectCity: 'Select your city...',
            selectState: 'Select your state...'
        }
    };

    // Legacy support for old config
    if (window.acfCountry) {
        config.ajaxUrl = window.acfCountry.ajaxurl || config.ajaxUrl;
    }

    /**
     * LocalStorage cache with TTL support.
     */
    var Cache = {
        TTL: 18000, // 5 hours in seconds

        set: function(key, value, ttl) {
            ttl = ttl || this.TTL;

            try {
                var now = Math.round(Date.now() / 1000);
                localStorage.setItem(key, JSON.stringify(value));
                localStorage.setItem(key + '_expires', String(now + ttl));
            } catch (e) {
                // localStorage not available or quota exceeded
                console.warn('ACF Country Field: Cache write failed', e);
            }
        },

        get: function(key) {
            try {
                var expires = parseInt(localStorage.getItem(key + '_expires'), 10);
                var now = Math.round(Date.now() / 1000);

                if (!expires || expires < now) {
                    this.remove(key);
                    return null;
                }

                var value = localStorage.getItem(key);
                return value ? JSON.parse(value) : null;
            } catch (e) {
                console.warn('ACF Country Field: Cache read failed', e);
                return null;
            }
        },

        remove: function(key) {
            try {
                localStorage.removeItem(key);
                localStorage.removeItem(key + '_expires');
            } catch (e) {
                // Ignore errors
            }
        }
    };

    /**
     * AJAX helper with nonce support.
     */
    var Ajax = {
        request: function(action, data, callback) {
            var requestData = $.extend({}, data, {
                action: action
            });

            // Add nonce for secure endpoints
            if (config.nonce && config.nonceKey) {
                requestData[config.nonceKey] = config.nonce;
            }

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: requestData,
                success: function(response) {
                    // Handle both old format (plain object) and new format (success wrapper)
                    if (response && response.success !== undefined) {
                        callback(response.data || {});
                    } else {
                        callback(response || {});
                    }
                },
                error: function(xhr, status, error) {
                    console.error('ACF Country Field: AJAX error', status, error);
                    callback({});
                }
            });
        }
    };

    /**
     * Get cities for a country (with caching).
     */
    function getCities(countryId, callback) {
        var cacheKey = 'acf_cities_' + countryId;
        var cached = Cache.get(cacheKey);

        if (cached) {
            callback(cached);
            return;
        }

        // Try new action first, fall back to legacy
        Ajax.request('acf_get_country_cities', { country_id: countryId }, function(response) {
            if (response && Object.keys(response).length > 0) {
                Cache.set(cacheKey, response);
                callback(response);
            } else {
                // Fallback to legacy action
                Ajax.request('get_country_cities', { countryId: countryId }, function(legacyResponse) {
                    if (legacyResponse && Object.keys(legacyResponse).length > 0) {
                        Cache.set(cacheKey, legacyResponse);
                    }
                    callback(legacyResponse || {});
                });
            }
        });
    }

    /**
     * Get US states (with caching).
     */
    function getStates(callback) {
        var cacheKey = 'acf_us_states';
        var cached = Cache.get(cacheKey);

        if (cached) {
            callback(cached);
            return;
        }

        // Try new action first, fall back to legacy
        Ajax.request('acf_get_us_states', {}, function(response) {
            if (response && Object.keys(response).length > 0) {
                Cache.set(cacheKey, response);
                callback(response);
            } else {
                // Fallback to legacy action
                Ajax.request('get_us_states', {}, function(legacyResponse) {
                    if (legacyResponse && Object.keys(legacyResponse).length > 0) {
                        Cache.set(cacheKey, legacyResponse);
                    }
                    callback(legacyResponse || {});
                });
            }
        });
    }

    /**
     * Build options HTML from data object.
     */
    function buildOptions(data, placeholder) {
        var html = '<option value="">' + (placeholder || '') + '</option>';

        $.each(data, function(id, name) {
            html += '<option value="' + escapeHtml(String(id)) + '">' + escapeHtml(name) + '</option>';
        });

        return html;
    }

    /**
     * Escape HTML entities.
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Show loading indicator.
     */
    function showLoading($container) {
        $container.find('.acf-field-inner, .field-inner').css('visibility', 'hidden');
        $container.find('.acf-loading-indicator, .css3-loader').show();
    }

    /**
     * Hide loading indicator.
     */
    function hideLoading($container) {
        $container.find('.acf-field-inner, .field-inner').css('visibility', 'visible');
        $container.find('.acf-loading-indicator, .css3-loader').hide();
    }

    /**
     * Initialize Chosen on select elements.
     */
    function initChosen($selects) {
        if ($.fn.chosen) {
            $selects.each(function() {
                var $select = $(this);
                // Only apply Chosen to selects with many options
                if ($select.find('option').length > 10) {
                    $select.chosen({
                        disable_search_threshold: 10,
                        width: '100%'
                    });
                }
            });
        }
    }

    /**
     * Initialize a country field wrapper.
     */
    function initField($wrapper) {
        var $countrySelect = $wrapper.find('.acf-country-select, select[name*="country_id"]');
        var $citySelect = $wrapper.find('.acf-city-select, select[name*="city_id"]');
        var $stateSelect = $wrapper.find('.acf-state-select, select[name*="state_id"]');
        var $cityContainer = $citySelect.closest('li');
        var $stateContainer = $stateSelect.closest('li');

        var usaId = parseInt($wrapper.data('usa-id') || config.usaCountryId, 10);

        // Initialize Chosen on all selects
        initChosen($wrapper.find('select'));

        // Country change handler
        $countrySelect.on('change', function() {
            var countryId = parseInt($(this).val(), 10);

            if (!countryId) {
                // No country selected - reset city and hide state
                $citySelect.html(buildOptions({}, config.i18n.selectCity));
                $stateSelect.html(buildOptions({}, config.i18n.selectState));
                $stateContainer.hide();

                // Update Chosen
                $citySelect.trigger('chosen:updated');
                $stateSelect.trigger('chosen:updated');
                return;
            }

            // Show loading on city
            showLoading($cityContainer);

            // Fetch cities
            getCities(countryId, function(cities) {
                $citySelect.html(buildOptions(cities, config.i18n.selectCity));
                $citySelect.trigger('chosen:updated');
                hideLoading($cityContainer);
            });

            // Handle state field for USA
            if (countryId === usaId) {
                showLoading($stateContainer);
                $stateContainer.show();

                getStates(function(states) {
                    $stateSelect.html(buildOptions(states, config.i18n.selectState));
                    $stateSelect.trigger('chosen:updated');
                    hideLoading($stateContainer);
                });
            } else {
                $stateSelect.html(buildOptions({}, config.i18n.selectState));
                $stateSelect.trigger('chosen:updated');
                $stateContainer.hide();
            }
        });
    }

    /**
     * ACF field initialization hook.
     */
    if (typeof acf.add_action === 'function') {
        // ACF 5+ initialization
        acf.add_action('ready', function($el) {
            $el.find('.acf-country-field-wrapper, .country-selector-list').each(function() {
                initField($(this).closest('.acf-country-field-wrapper, ul'));
            });
        });

        acf.add_action('append', function($el) {
            $el.find('.acf-country-field-wrapper, .country-selector-list').each(function() {
                initField($(this).closest('.acf-country-field-wrapper, ul'));
            });
        });
    }

    // Fallback: Initialize on document ready
    $(function() {
        // For fields not caught by ACF hooks
        $('.acf-country-field-wrapper, .country-selector-list').each(function() {
            var $wrapper = $(this).closest('.acf-country-field-wrapper, ul');
            if (!$wrapper.data('acf-country-initialized')) {
                $wrapper.data('acf-country-initialized', true);
                initField($wrapper);
            }
        });
    });

})(jQuery, window.acf);
