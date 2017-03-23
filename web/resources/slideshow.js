function InitSlideShow()
{
    SetSlideshowInterval();
}

function ToggleSlideShow()
{
    slideshow_active = !(slideshow_active);
    if(slideshow_active)
    {
        $("#span_slideshow").addClass("glyphicon-pause");
        $("#span_slideshow").removeClass("glyphicon-play");
    }
    else
    {
        $("#span_slideshow").addClass("glyphicon-play");
        $("#span_slideshow").removeClass("glyphicon-pause");
    }
}

function SpeedSlideShow()
{
    slideshow_timer -= 1000;
    if(slideshow_timer == 0)
    {
        slideshow_timer = 3000;
        $("#btn_slideshow_speed").html("1.0x");
    }

    if(slideshow_timer == 2000)
    {
        $("#btn_slideshow_speed").html("2.0x");
    }
    else if(slideshow_timer == 1000)
    {
        $("#btn_slideshow_speed").html("3.0x");
    }

    clearInterval(slideshow_interval_id);
    SetSlideshowInterval();
}

function RunSlideshow()
{
    if(slideshow_active)
    {
        var val = $("#" + active_slider_id).slider("value");
        val++;
        if( ((active_slider_id == "hours_slider") && val >8760)
            || ((active_slider_id == "days_slider") && val >365)
            || ((active_slider_id == "months_slider") && val >12))
        {
            val=1;
        }

        $( "#" + active_slider_id ).slider( "option", "value", val );
    }
}

function SetSlideshowInterval()
{
    slideshow_interval_id = window.setInterval(function () {
        RunSlideshow()
    }, slideshow_timer);
}