;(function($, undefined) {
    $(function() {
        var originalCountry = 0;
        var countrySelect   = $('select[name*="country_name"]');

        if (countrySelect.length) {
            countrySelect.change(function() {
                var $this            = $(this);
                var $list             = $this.parents('ul');
                var countryCity = $list.find('select[name*="country_city"]');
                var cities           = {};

                if ($this.val() !== originalCountry) {
                    originalCountry = $this.val();

                    var optionsValues = '';

                    get_related_cities($this.val(), function(response) {
                        countryCity.empty();
                        $.each(response, function(k, v) {
                            optionsValues += '<option value="'+k+'">'+v+'</option>';
                        });
                        countryCity.html(optionsValues);
                    });
                }
            });
        }

        function get_related_cities(countryID, callback) {
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
                }
            });
        }



    });
})(jQuery);
