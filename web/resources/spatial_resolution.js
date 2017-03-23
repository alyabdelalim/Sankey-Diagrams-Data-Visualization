$("#slct_spatial_resolution").change(function () {
    var spatial_res = $( "#slct_spatial_resolution option:selected" ).text();
    if( spatial_res == "Building Level")
    {
        hvac_nodes_positions = nodes_positions;
        nodes_positions = building_nodes_positions;

        file_prefix = "";
    }
    else if( spatial_res == "HVAC System")
    {
        building_nodes_positions = nodes_positions;
        nodes_positions = hvac_nodes_positions;
        file_prefix = "hvac_";
    }

    sankey_vars = [];
    graphs_vars = [];
    links_vars = [];
    path_vars = [];
    path_vars = [];
    $("svg").remove();
    Redraw();
});