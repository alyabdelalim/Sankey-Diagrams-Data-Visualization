
function ShiftChartUp()
{
    if(min_y < 0){
        $("#chart").css("margin-top", -min_y).css("z-index", -100);
    }
    else
    {
        $("#chart").css("margin-top", -min_y + 380).css("z-index", -100);
    }
}

function UpdateTextDisplay() {

    for(index = 0 ; index < diagrams_count ; index ++)
    {
        $(".node"+index+" text").css("display", "none");
    }

    if(Object.keys(nodes_positions).length > 0) {
        for (var key in nodes_positions) {

            for(index = diagrams_count-1 ; index >= 0 ; index --) {
                if (nodes_display[index] == 1 && $(".node" + index + "[data-name='" + key + "'] rect").last().attr("height") > 0)
                {
                    $(".node" + index + "[data-name='" + key + "'] text").last().css("display", "");
                    break;
                }
            }
        }
    }
}

function Redraw()
{
    if(diagrams_count > 0) {
        min_y = 1000000;

        diagram_index = 0;
        if (graphs_vars.length == 0) {
            svg = d3.select("#chart").append("svg")
                .attr("id", "svg0")
                .attr("width", width + margin.left + margin.right)
                .attr("height", height + margin.top + margin.bottom)
                .append("g")
                .attr("transform",
                    "translate(" + margin.left + "," + margin.top + ")")
                .attr("id", "parent_g");
            for (var j = 0; j < diagrams_count; j++) {
                graphs_vars[j] = {"nodes": [], "links": []};
                sankey_vars[j] = d3.sankey()
                    .nodeWidth(36)
                    .nodePadding(40)
                    .size([width, height]);
                links_vars.push(null);
                path_vars.push(sankey_vars[j].link());
            }
        }
        else {
            $("#parent_g g").fadeOut('slow', "linear", function () {
                $(this).remove();
            });
        }

        RunD3csv();
    }
}

function RunD3csv()
{
    // load the data (using the timelyportfolio csv method)
    var file_name = "files/diagrams/"+diagrams_dirs[diagram_index]+"/" + file_prefix + target_file_name + ".csv";

    d3.csv(file_name, function(error, data) {
        if(nodes_display[diagram_index] == 1)
        {
            //set up graph in same style as original example but empty
            graphs_vars[diagram_index] = {"nodes": [], "links": []};

            data.forEach(function (d) {
                var column = target_column_name;
                graphs_vars[diagram_index].nodes.push({"name": d.source});
                graphs_vars[diagram_index].nodes.push({"name": d.target});
                graphs_vars[diagram_index].links.push({
                    "source": d.source,
                    "target": d.target,
                    "value": +d[column]
                });
            });

            // return only the distinct / unique nodes
            graphs_vars[diagram_index].nodes = d3.keys(d3.nest()
                .key(function (d) {
                    return d.name;
                })
                .map(graphs_vars[diagram_index].nodes));

            // loop through each link replacing the text with its index from node
            graphs_vars[diagram_index].links.forEach(function (d, i) {
                graphs_vars[diagram_index].links[i].source = graphs_vars[diagram_index].nodes.indexOf(graphs_vars[diagram_index].links[i].source);
                graphs_vars[diagram_index].links[i].target = graphs_vars[diagram_index].nodes.indexOf(graphs_vars[diagram_index].links[i].target);
            });

            //now loop through each nodes to make nodes an array of objects
            // rather than an array of strings
            graphs_vars[diagram_index].nodes.forEach(function (d, i) {
                graphs_vars[diagram_index].nodes[i] = {"name": d, "diagram_index": diagram_index};
            });

            sankey_vars[diagram_index]
                .nodes(graphs_vars[diagram_index].nodes)
                .links(graphs_vars[diagram_index].links)
                .layout(32);

            // add in the links
            links_vars[diagram_index] = svg.append("g").style("display", "none").selectAll(".link")
                .data(graphs_vars[diagram_index].links)
                .enter()
                .append("path")
                .attr("class", "link" + diagram_index)
                .attr("d", path_vars[diagram_index])
                .style("stroke", colors[diagram_index])
                .style("opacity", "0.7")
                .style("stroke-width", function (d) {
                    if (d.value < 0.00001) return 0;
                    return Math.max(1, d.dy);
                })
                .sort(function (a, b) {
                    return b.dy - a.dy;
                });

            // add the link titles
            links_vars[diagram_index].append("title")
                .text(function (d) {
                    return d.source.name + " → " +
                        d.target.name + "\n" + format(d.value);
                });

            // add in the nodes
            var node = svg.append("g").style("display", "none").selectAll(".node")
                .data(graphs_vars[diagram_index].nodes)
                .enter().append("g")
                .attr("class", "node" + diagram_index)
                .attr("data-name", function (d) {
                    return d.name;
                })
                .attr("transform", function (d) {
                    return "translate(" + d.x + "," + d.y + ")";
                })
                .call(d3.behavior.drag()
                    .origin(function (d) {
                        return d;
                    })
                    .on("dragstart", function () {
                        this.parentNode.appendChild(this);
                    })
                    .on("drag", function (d) {
                        if (Math.max(0, Math.min(height - d.dy, d3.event.y) > (min_y + 20))) {
                            d3.select(this).attr("transform",
                                "translate(" + (d.x = Math.max(0, Math.min(width - d.dx, d3.event.x)))
                                + "," + (
                                    d.y = Math.max(0, Math.min(height - d.dy, d3.event.y))
                                ) + ")");
                            sankey_vars[d.diagram_index].relayout();
                            links_vars[d.diagram_index].attr("d", path_vars[d.diagram_index]);
                            MoveGroup(d.name, d.x, d.y);
                            nodes_positions[d.name] = {y:d.y,x:d.x};
                        }
                    }));


            // add the rectangles for the nodes
            node.append("rect")
                .attr("height", function (d) {
                    if (d.value < 0.00001) return 0;
                    return d.dy;
                })
                .attr("width", sankey_vars[diagram_index].nodeWidth())
                .style("fill", colors[diagram_index])
                .style("stroke", "black")
                .style("opacity", "1.0")
                .append("title")
                .text(function (d) {
                    return d.name + "\n" + format(d.value);
                });

            // add in the title for the nodes
            node.append("text")
                .attr("x", -6)
                .attr("y", function (d) {
                    return d.dy / 2;
                })
                .attr("dy", ".35em")
                .attr("text-anchor", "end")
                .attr("transform", null)
                .style("display", "block")
                .text(function (d) {
                    //if (d.value < 0.00001) return "";
                    return d.name;
                })
                .filter(function (d) {
                    return d.x < width / 2;
                })
                .attr("x", 6 + sankey_vars[diagram_index].nodeWidth())
                .attr("text-anchor", "start");

            if(Object.keys(nodes_positions).length == 0)
            {
                $(".node0").each(function (d) {
                    //var reference_node = $("#chart").find(".node0[data-name='" +d.name + "']");
                    var transform_value = $(this).attr("transform");
                    var y_value = parseInt(transform_value.substring(transform_value.indexOf(",") + 1, transform_value.indexOf(")")));
                    var x_value = parseInt(transform_value.substring(transform_value.indexOf("(") + 1, transform_value.indexOf(",")));
                    nodes_positions[$(this).data("name")] = {y:y_value,x:x_value};
                });
            }

            for (var key in nodes_positions) {
                position = nodes_positions[key];
                MoveGroup(key, position.x, position.y);
            }

            graphs_vars[diagram_index].nodes.forEach(function (d, i) {
                min_y = (graphs_vars[diagram_index].nodes[i].y < min_y) ? graphs_vars[diagram_index].nodes[i].y : min_y;
            });

            ShiftChartUp();

        }
        diagram_index++;
        if(diagram_index < diagrams_count)
        {
            RunD3csv();
        }
        else
        {
            UpdateTextDisplay();
            $("g").fadeIn("fast");
        }
    });

}


function MoveGroup(name, x,y)
{
    for(index=0 ; index < diagrams_count ; index++)
    {
        if ($(".node"+index)[0]){
            graphs_vars[index].nodes.forEach(function (d,i) {
                if (d.name == name) {
                    var current_node = $("#chart").find(".node"+index+"[data-name='" +d.name + "']");
                    current_node.attr("transform", "translate(" + x + "," + y + ")");
                    graphs_vars[index].nodes[i].y = y;
                    graphs_vars[index].nodes[i].x = x;
                }
            });

            sankey_vars[index].relayout();
            links_vars[index].attr("d", path_vars[index]);
        }

    }
}