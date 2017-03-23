function InitColorSelector() {
    $(".color_selector").change(function () {
        var changed_diagram_index = $(this).data("diagram");
        colors[changed_diagram_index] = $(this).val();
        Redraw();
    });
}

function AddDiagramColorElement(diagram_name, diagram_idx)
{
    $("#colors_table").append('<tr>' +
        '<td> <label for="color_'+diagram_idx+'">'+diagram_name+' </label> </td>' +
        ' <td> <input data-diagram="'+diagram_idx+'" class="color_selector" id="color_'+diagram_idx+'" type="color" /> </td>'+
        '</tr>');

    $("#color_" + diagram_idx).val(colors[diagram_idx]);
}