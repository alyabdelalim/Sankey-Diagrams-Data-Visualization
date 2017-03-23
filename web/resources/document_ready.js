
$(document).ready(function () {
    InitSlideShow();
    InitSliders();
    $.ajax({
        url: "index.php/diagram/list/",
        data: {},
        datatype: 'json',
        type: 'POST',
        success: function (response) {
            if (response.success) {
                diagrams_count = response.diagrams.length;
                for (var i = 0; i < diagrams_count; i++) {
                    var diagram_name = response.diagrams[i].diagram_name;
                    diagrams_dirs.push(diagram_name);
                    nodes_display.push(1);
                    AddDiagramColorElement(diagram_name, i);
                    AddDiagramDropdownElement(diagram_name, i);
                    AddExistingDiagram(diagram_name);
                }

                InitDiagramsDropdown();
                InitColorSelector();
                Redraw();
            }
        },
        error: function () {

        }
    });
});