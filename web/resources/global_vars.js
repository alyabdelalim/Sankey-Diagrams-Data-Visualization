var file_prefix = "";

var node_height_factor = 950.0;
var units = "Widgets";
var target_file_name = "week_1";
var target_column_name = "hour_1";
var active_slider_id = "hours_slider";
var slideshow_active = false;
var nodes_positions = [];
var building_nodes_positions = [];
var hvac_nodes_positions = [];
var nodes_display = [];
var days_count = [31,28,31,30,31,30,31,31,30,31,30,31];
var slideshow_timer = 3000;
var margin = {top: 10, right: 10, bottom: 10, left: 10},
    width = 1200 - margin.left - margin.right,
    height = 4500	- margin.top - margin.bottom;
var formatNumber = d3.format(",.0f"),    // zero decimal places
    format = function(d) { return formatNumber(d) + " " + units; },
    color = d3.scale.category20();
var files = [];
var colors = ["#0000ff", "#ff0000", "#00ff00", "#ffff00", "#00ffff", "#ff00ff"];
var slideshow_interval_id;
var min_y = 0;
var svg;
var sankey_vars = [];
var graphs_vars = [];
var links_vars = [];
var path_vars = [];
var path_vars = [];
var diagrams_count = 0;
var diagrams_dirs = [];
var diagram_index;
var days_array = ["Monday","Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
var months_array = ["JAN","FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];