/**
 * Created by mcguffk on 12/28/2016.
 */
var configureSettings = function(configSettings, savedSettings) {
    configSettings.forEach(function(setting){
        var setting = $.extend({}, setting);

        if(setting.type == 'project-id') {
            var saved = savedSettings[setting.key];
            if(saved){
                setting.value = saved.value;
                setting.globalValue = saved.global_value;
            }

            if(setting.value != '' && setting.value != null) {
                $('select[name="' + setting.key + '"]').removeClass('project_id_textbox');
                $.ajax({
                    url: 'ajax/get-project-list.php',
                    dataType: 'json'
                }).done(function(data) {
                    for(var key in data.results) {
                        if(data.results[key]['id'] == setting.value) {
                            selectHtml = "<option value='" + setting.value + "'>" + data.results[key]['text'] + "</option>";
                        }
                    }
                    $('select[name="' + setting.key + '"]').html(selectHtml);

                    $('select[name="' + setting.key + '"]').select2({
                        width: '100%',
                        data: data.results,
                        ajax: {
                            url: 'ajax/get-project-list.php',
                            dataType: 'json',
                            delay: 250,
                            data: function(params) { return {'parameters':params.term }; },
                            method: 'GET',
                            cache: true
                        }
                    });
                });
            }
        }
    });

    $(".project_id_textbox").select2({
        width: '100%',
        ajax: {
            url: 'ajax/get-project-list.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return {'parameters':params.term }; },
            method: 'GET',
            cache: true
        }
    });
}