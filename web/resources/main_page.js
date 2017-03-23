
var current_id = 1;
$("#btn_idf_generate").click(function () {
  var diagram_name = $("#diagram_name").val();
  var building_type_id = $( "#slct_building_type option:selected" ).val();
  var building_type_name = $( "#slct_building_type option:selected" ).text();
  var files = $('#idf_file').prop("files");
  var formData = new FormData();
  formData.append('idf_file', files[0]);
  formData.append('building_type_id', building_type_id);
  formData.append('diagram_name', diagram_name);
  $.ajax({
    url: "index.php/idf/generate/",
    data: formData,
    enctype: 'multipart/form-data',
    processData: false,
    contentType: false,
    cache:false,
    type: 'POST',
    success: function(response) {
      if(response.success)
      {
        var file_name = response.generated_idf_filename;
        document.location = "index.php/idf/download/" + file_name;
        var new_div = '<div style="text-align: left">' +
            "Diagram Name:" + diagram_name + "<br>" +
            "Building Type:"+building_type_name+"<br>" +
            'Upload ESO file <input class="js-upload-eso" data-diagram="'+diagram_name+'" accept=".eso" type="file" name="fileToUpload" id="idf_file">'
            + '<button data-diagram="'+diagram_name+'" class="js-remove-diagram">Remove Diagram</button>'
            + '</div>';
        $("#dv_diagrams_list").append(new_div);

        BindRemoveEvent();
        $(".js-upload-eso").on("change", function() {
          event.preventDefault();
          var form = new FormData();
          files = $(this).prop("files");
          form.append($(this).data("diagram"), files[0]);
          console.log(files);
          $("#dv_files_loading_image").show();
          $.ajax({
            url: "index.php/eso/upload/",
            data: form,
            enctype: 'multipart/form-data',
            processData: false,
            contentType: false,
            cache:false,
            type: 'POST'
          });
        });

        current_id++;
      }
      else {
      }

    },
    error: function() {

    }
  });

});



$("#btn_rmv_all").click(function () {
  $.ajax({
    url: "index.php/diagram/reset/",
    data: {},
    datatype: 'json',
    type: 'POST',
    success: function(response) {
      if(response.success)
      {
        $("#dv_diagrams_list").text("");
      }
      else {
      }

    },
    error: function() {

    }
  });
});
var diagram_counter = 0;
$("#btn_generate_csv").click(function(){
  diagram_counter = 0;
  generateCSV(diagrams_dirs[diagram_counter]);
});

function generateCSV(diagram_name)
{
  $.ajax({
    url: "index.php/csv/generate/"+diagram_name,
    data: {},
    datatype: 'json',
    type: 'GET',
    success: function(response) {
      diagram_counter++;
      if(diagram_counter < diagrams_count){
        generateCSV(diagrams_dirs[diagram_counter]);
      }
    },
    error: function() {

    }
  });
}


$(document).ajaxComplete(function (evt, XHR, settings) {
  $("#dv_files_loading_image").hide();
});


function BindRemoveEvent()
{
  $(".js-remove-diagram").unbind( "click" ).click(function () {
    var btn_parent_div = $(this).parent();
    $.ajax({
      url: "index.php/diagram/remove/",
      data: {diagram_name : $(this).data("diagram")},
      datatype: 'json',
      type: 'POST',
      success: function(response) {
        if(response.success)
        {
          btn_parent_div.remove();
        }
        else {
        }

      },
      error: function() {

      }
    });

  });
}

function AddExistingDiagram(diagram_name)
{
  var new_div = '<div style="text-align: left">' +
      "Diagram Name:" + diagram_name + "<br>" +
      '<button data-diagram="'+diagram_name+'" class="js-remove-diagram">Remove Diagram</button>'
      + '</div>';

  $("#dv_diagrams_list").append(new_div);

  BindRemoveEvent();
}

