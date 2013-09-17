;(function($, undefined) {
    $(function() {
        var originalCountry = 0;
        var countrySelect   = $('select[name*="country_name"]');

        if (countrySelect.length) {
            countrySelect.change(function() {
                var $this              = $(this);
                var $list               = $this.parents('ul');
                var countryCity   = $list.find('select[name*="country_city"]');
                var countryState = $list.find('select[name*="country_state"]');

                if ($this.val() !== originalCountry) {
                    originalCountry = $this.val();

                    var optionsValues   = '';
                    var $countryParent  = countryCity.parents("li");

                    $countryParent.find(".field-inner").css("visibility", "hidden");
                    $countryParent.find(".css3-loader").show();

                    get_related_cities($this.val(), function(response) {
                        countryCity.empty();
                        $.each(response, function(k, v) {
                            optionsValues += '<option value="'+k+'">'+v+'</option>';
                        });
                        countryCity.html(optionsValues);
                        $countryParent.find(".field-inner").css("visibility", "visible");
                        $countryParent.find(".css3-loader").hide();
                    });

                    if ($this.val() == 446) {
                        var stateValues = '';

                        get_us_states(function(response) {
                            countryState.empty().parent("li").show();
                            $.each(response, function(k, v) {
                                stateValues += '<option value="'+k+'">'+v+'</option>';
                            });
                            countryState.html(stateValues);
                        });
                    } else {
                        if (countryState.parent("li").is(":visible")) {
                            countryState.empty().parent("li").hide();
                        }
                    }
                }
            });
        }

        function get_related_cities(countryID, callback) {
            var storageKey      = "cities"+countryId;
            var cities                 = getLocalStorage(storageKey);

            if (cities !== null)
            {
                callback(JSON.parse(cities));
            }
            else
            {
                $.ajax({
                        url              :   acfCountry.ajaxurl,
                        type           :   'post',
                        dataType  :   'json',
                        data           :   {
                            action      :   'get_country_cities',
                            countryId : countryID
                        },
                        success    : function(response) {
                            callback(response);
                            setLocalStorage(storageKey, JSON.stringify(response));
                        }
                });
            }
        }

        function setLocalStorage(key, value, expires) {
            if (expires==undefined || expires=='null') { var expires = 18000; } // default: 5h

            var date = new Date();
            var schedule = Math.round((date.setSeconds(date.getSeconds()+expires))/1000);

            localStorage.setItem(key, value);
            localStorage.setItem(key+'_time', schedule);
        }

        function getLocalStorage(key) {
            var date     = new Date();
            var current = Math.round(+date/1000);

            // Get Schedule
            var stored_time = localStorage.getItem(key+'_time');
            if (stored_time==undefined || stored_time=='null') { var stored_time = 0; }

            if (stored_time < current) {
                clearLocalStorage(key);
                return null;

            } else {
                return localStorage.getItem(key);
            }
        }

        function clearLocalStorage(key) {
            localStorage.removeItem(key);
            localStorage.removeItem(key+'_time');
        }

        function get_us_states(callback) {
            $.ajax({
                url              :   acfCountry.ajaxurl,
                type           :   'post',
                dataType  :   'json',
                data           :   {
                    action      :   'get_us_states'
                },
                success    : function(response) {
                    callback(response);
                }
            });
        }



    });
})(jQuery);
