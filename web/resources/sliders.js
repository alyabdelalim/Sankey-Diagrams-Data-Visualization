function InitSliders()
{
    $( "#hours_slider" ).slider({
        value: 1,
        min: 1,
        max: 8760,
        step: 1,
        change: function( event, ui ) {
            temp_index = ((ui.value-1) % 168) + 1;
            target_file_name =  "week_" + Math.ceil(ui.value /168) ;
            target_column_name = "hour_" + temp_index;

            hour_label = (ui.value-1)%24;
            week_label = Math.ceil((ui.value)/168);
            day_index = days_array[(Math.ceil((ui.value)/24)-1)%7];
            var day_number_in_year = Math.floor((ui.value-1)/24) + 1;
            var day_number_in_month = day_number_in_year;
            var temp_days_count = 0;
            var added_days = 0;
            var month = '';
            for(i=0 ; i < 12 ; i++)
            {
                temp_days_count += days_count[i];
                if(day_number_in_year <= temp_days_count)
                {
                    month = months_array[i];
                    day_number_in_month -= added_days;
                    break;
                }
                added_days += days_count[i];
            }

            $("#week_number_title").text(month + " " +day_number_in_month+": " + day_index + " " + hour_label + ":00");
            Redraw();
        }
    }).each(function() {
        var opt = $(this).data().uiSlider.options;
        var vals = opt.max - opt.min;
        for (var i = 0; i <= vals; i++) {

            if((i+1) % 168 == 0 )
            {
                week_label = (i+1) / 168;
                var el = $('<label>'+week_label+'</label>').css('left',(i/vals*100)+'%');

                $( "#hours_slider" ).append(el);
            }
        }
    });


    $( "#days_slider" ).slider({
        value: 1,
        min: 1,
        max: 365,
        step: 1,
        change: function( event, ui ) {
            target_file_name =  "days_" + Math.ceil(ui.value /182) ;
            target_column_name = "day_" + ui.value;
            day_name = days_array[((ui.value-1)%7)];

            var day_number_in_year = ui.value
            var day_number_in_month = day_number_in_year;
            var temp_days_count = 0;
            var added_days = 0;
            var month = '';
            for(i=0 ; i < 12 ; i++)
            {
                temp_days_count += days_count[i];
                if(day_number_in_year <= temp_days_count)
                {
                    month = months_array[i];
                    day_number_in_month -= added_days;
                    break;
                }
                added_days += days_count[i];
            }

            $("#week_number_title").text(month + " " +day_number_in_month+": " + day_name);
            //$("#hours_slider").slider('value',(ui.value-1)*24 +1);
            Redraw();
        }
    }).each(function() {
        var opt = $(this).data().uiSlider.options;
        var vals = opt.max - opt.min;
        for (var i = 0; i <= vals; i++) {
            if ((i + 1) % 7 == 0) {
                day_label = (i+1) / 7;
                var el = $('<label>' + day_label + '</label>').css('left', (i / vals * 100) + '%');

                $("#days_slider").append(el);
            }
        }
    });

    $( "#months_slider" ).slider({
        value: 1,
        min: 1,
        max: 12,
        step: 1,
        change: function( event, ui ) {
            target_file_name =  "months";
            target_column_name = "month_" + ui.value;
            $("#week_number_title").text(months_array[ui.value-1]);

            var day = 0;
            for(i=0 ; i< ui.value-1 ; i++)
            {
                day += days_count[i];
            }

            //$("#days_slider").slider('value',day+1);
            Redraw();
        }
    }).each(function() {
        var opt = $(this).data().uiSlider.options;
        var vals = opt.max - opt.min;
        for (var i = 0; i <= vals; i++) {

            month_label = months_array[i];
            var el = $('<label>' + month_label + '</label>').css('left', (i / vals * 100) + '%');

            $("#months_slider").append(el);

        }
    });


    $('#days_slider').slider ('disable');
    $('#months_slider').slider('disable');

    $('input[type=radio][name=slider_radio]').change(function() {
        if (this.value == 'hours') {
            $('#hours_slider').slider('enable');
            $('#days_slider').slider('disable');
            $('#months_slider').slider('disable');
            active_slider_id = "hours_slider";
            node_height_factor = 950.0;
        }
        else if (this.value == 'days') {
            $('#hours_slider').slider('disable');
            $('#days_slider').slider('enable');
            $('#months_slider').slider('disable');
            active_slider_id = "days_slider";
            node_height_factor = 25.0;
        }
        else if(this.value == 'months')
        {
            $('#hours_slider').slider('disable');
            $('#days_slider').slider('disable');
            $('#months_slider').slider('enable');
            active_slider_id = "months_slider";
            node_height_factor = 950.0;
        }

        var val = $("#" + active_slider_id).slider("value");
        $( "#" + active_slider_id ).slider( "option", "value", val );
    });
}