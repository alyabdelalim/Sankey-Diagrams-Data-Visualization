
function InitDiagramsDropdown() {

    $('.dropdown-menu a').on('click', function (event) {

        var $target = $(event.currentTarget),
            val = $target.attr('data-value'),
            $inp = $target.find('input'),
            idx;

        var checked = $inp.is(':checked');
        setTimeout(function () {
            $inp.prop('checked', checked)
        }, 0);

        $(event.target).blur();

        if (checked) {
            nodes_display[val] = 1;
        }
        else {
            nodes_display[val] = 0;
        }

        Redraw();
        return false;
    });
}


function AddDiagramDropdownElement(diagram_name, diagram_idx)
{
    $("#diagrams_display_dd").append('<li><a href="#" class="small" data-value="'+diagram_idx+'" tabIndex="-1"><input checked="checked" type="checkbox"/>'+diagram_name+'</a></li>');
}