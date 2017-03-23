<?php
require_once __DIR__.'/../vendor/autoload.php'; // Add the autoloading mechanism of Composer

use Symfony\Component\HttpFoundation\JsonResponse;
$app = new Silex\Application();


class Enumerations{
    const OTHER_SECTION = "0";
    const BRANCH_SECTION = "1";
    const COIL_HEATING_WATER_SECTION = "2";
    const BUILDING_SURFACE_DETAILED_SECTION = "3";
    const OUTDOOR_AIR_MIXER_SECTION = "4";
    const TABLE_STYLE = "5";

    const SMALL_OFFICE  = 1;
    const MEDIUM_OFFICE  = 2;
    const LARGE_OFFICE  = 3;
    const LM_OFFICE = 4;
}

// POST function lists the diagrams in the system (Note: called on page refresh)
$app->post('/diagram/list/', function ()  {

    $folders = scandir("files/diagrams/");
    $folders = array_diff($folders, array('.', '..'));

    $return_array = array();
    $return_array["diagrams"] = array();
    foreach ($folders as $diagram_name)
    {
        $return_array["diagrams"][] = array("diagram_name" => $diagram_name);
    }

    $return_array['success'] = true;
    return new JsonResponse($return_array);
});

// POST function removes a diagram specified by diagram_name parameter.
$app->post('/diagram/remove/', function (\Symfony\Component\HttpFoundation\Request $request)
{
    $request_array = $request->request->all();
    $diagram_name = $request_array['diagram_name'];
    $dirname="files/diagrams/" . $diagram_name;
    array_map('unlink', glob("$dirname/*.*"));
    rmdir($dirname);

    $return_array['success'] = true;
    return new JsonResponse($return_array);
});


// POST function removes all diagrams in the system.
$app->post('/diagram/reset/', function (\Symfony\Component\HttpFoundation\Request $request)
{
    $dirname="files/diagrams/";
    deleteDir($dirname);

    $return_array['success'] = true;
    return new JsonResponse($return_array);
});

// POST function generates the IDF file (imported IDF file + the list of output variables and meters sections based on the building type)
$app->post('/idf/generate/', function (\Symfony\Component\HttpFoundation\Request $request)
{
    $request_array = $request->request->all();
    $imported_directory = "files/imported/idf/";
    mkdir("files/diagrams/" . $request_array['diagram_name']);
    $diagram_directory = "files/diagrams/" . $request_array['diagram_name'] ."/";
    $generated_directory = "files/generated/idf/";
    $output_variables_directory = "files/reference/output_variables/";
    $current_section = Enumerations::OTHER_SECTION;


    $building_type_id = $request_array['building_type_id'];
    $fileBag = $request->files->all();
    $uploaded_file = $fileBag['idf_file'];
    $originalName = $uploaded_file->getClientOriginalName();

    $uploaded_file->move($imported_directory, $originalName);

    $file_path = $imported_directory . $originalName;
    $generated_idf = fopen($generated_directory . $originalName, 'w');
    $imported_idf = fopen($file_path, "r");
    while(!feof($imported_idf)) {
        $line = fgets($imported_idf);
        if (strpos($line, '===========') !== false) {

            if (strpos($line, 'ALL OBJECTS IN CLASS: OUTPUTCONTROL:TABLE:STYLE') !== false) {
                $current_section = Enumerations::TABLE_STYLE;
            }
            else if($current_section == Enumerations::TABLE_STYLE)
            {
                $output_variables_file_name = "";

                if($building_type_id == "3")
                {
                    $output_variables_file_name = "large_office.txt";
                }
                else if($building_type_id == "2")
                {
                    $output_variables_file_name = "medium_office.txt";
                }
                else if($building_type_id == "1")
                {
                    $output_variables_file_name = "small_office.txt";
                }

                $output_variables_file = fopen($output_variables_directory . $output_variables_file_name, "r");
                while(!feof($output_variables_file)) {
                    fwrite($generated_idf, fgets($output_variables_file));
                }
                fclose($output_variables_file);
            }
        }

        fwrite($generated_idf, $line);
    }

    fclose($generated_idf);
    fclose($imported_idf);

    copy($generated_directory . $originalName , $diagram_directory . "generated_idf.idf");

    $diagram_meta_data_file = fopen($diagram_directory . "meta.txt", 'w');
    fwrite($diagram_meta_data_file, $building_type_id);
    fclose($diagram_meta_data_file);

    $results_array["success"] = true;
    $results_array["generated_idf_filename"] = $originalName;
    return new JsonResponse($results_array);
});

// GET function downloads the generated IDF file.
$app->get('/idf/download/{file_name}', function ($file_name)
{
    $generated_directory = "files/generated/idf/";
    $content = file_get_contents ($generated_directory . $file_name);

    $response = new \Symfony\Component\HttpFoundation\Response();

    //set headers
    $response->headers->set('Content-Type', 'application/plain');
    $response->headers->set('Content-Disposition', 'attachment;filename="'.$file_name);

    $response->setContent($content);
    return $response;
});

// POST function uploads the ESO file
$app->post('eso/upload/', function (\Symfony\Component\HttpFoundation\Request $request)
{
    $fileBag = $request->files->all();

    foreach ($fileBag as $diagram_name => $file)
    {
        $diagram_directory = "files/diagrams/" . $diagram_name ."/";
        $file->move($diagram_directory, "uploaded_eso.eso");
    }

    $results_array["success"] = true;
    return new JsonResponse($results_array);
});

// Internal function to delete directory and all its contents.
function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    if($dirPath != "files/diagrams/")
    {
        rmdir($dirPath);
    }
}

// Internal function to get the physical unit (i.e. kW or J) of a given eso variable.
function GetVariableUnit($variable_type_token)
{
    preg_match('/\[(.*)\]/', $variable_type_token, $matches);
    return $matches[1];
}

// Internal function to compare strings $lookup_str and $line_token ( either exact match or the $line_token contains the $lookup_str)
function IsLookupStringMatches($lookup_str, $line_token, $exact)
{
    if ($exact) // exact
    {
        return ($lookup_str === $line_token); // compare strings
    }
    else
    {
        return (strpos($line_token, $lookup_str) !== false) ;
    }
}

// Internal function to check the given line ($line_tokens) match the a lookup record ($lookup_record)
// The main functionality of this function to check if an eso dictionary line matches some given strings in specific line tokens
// $line_tokens -> array of the eso dictionary line data ( values seperated by commas)
// $lookup_record -> array(text to search for, line token index, row #, exact match/not exact match, text to search for 1, line token index 1, exact match/not exact match)
function IsLineMatches($line_tokens, $lookup_record)
{
    // $lookup_record[1] is the index of the line token that will search inside it.
    // This condition is true when the given eso dictionary line has a value in the given index.
    // In general, eso dictionary lines vary in number of values.  
    if (isset($line_tokens[$lookup_record[1]]))
    {
	// $lookup_record[0] is the string to be searched for inside $line_tokens[$lookup_record[1]] which is the required line token.
	// $lookup_record[3] is either true or false; true means that the two given strings must match exactly, false means that the lookup string should be part of the line token
        if(IsLookupStringMatches($lookup_record[0], $line_tokens[$lookup_record[1]], $lookup_record[3]))
        {
	    // If there is no more checks on other token in the eso line, therefore, the lookup record matches the eso dictionary line.
            if(count($lookup_record) == 4)
            {
                return true;
            }

    	    // If there is one more check on other token in the eso line, perform a similar check as above but for different line token and a another lookup string.
            if(count($lookup_record) > 4 && isset($line_tokens[$lookup_record[5]]))
            {
                if(IsLookupStringMatches($lookup_record[4], $line_tokens[$lookup_record[5]], $lookup_record[6]))
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
        }
        else
        {
            return false;
        }
    }
}

// Internal function to read and parse the required data from the generated IDF for file for a given diagram $diagram_name
function getIdfData($diagram_name)
{
    $idf_path = 'files/diagrams/' . $diagram_name . '/generated_idf.idf';
    $myfile_idf = fopen($idf_path, "r") ;
    $current_section = Enumerations::OTHER_SECTION;
    $pump_variables_map = array();
    $heating_coils_map = array();
    $all_heating_coils_map = array();
    $building_surface_detailed_map = array();
    $outdoor_air_stream_nodes_map = array();
    $relief_air_stream_nodes_map = array();
    $return_air_stream_nodes_map = array();

    while(!feof($myfile_idf)) {
        $line = fgets($myfile_idf);
        if(strpos($line, '===========') !== false )
        {

            if(strpos($line, '===========  ALL OBJECTS IN CLASS: BRANCH ===========') !== false)
            {
                $current_section = Enumerations::BRANCH_SECTION;
            }
            else if(strpos($line, '===========  ALL OBJECTS IN CLASS: COIL:HEATING:WATER ===========') !== false)
            {
                $current_section = Enumerations::COIL_HEATING_WATER_SECTION;
            }
            else if(strpos($line, '===========  ALL OBJECTS IN CLASS: BUILDINGSURFACE:DETAILED ===========') !== false)
            {
                $current_section = Enumerations::BUILDING_SURFACE_DETAILED_SECTION;
            }
            else if(strpos($line, '===========  ALL OBJECTS IN CLASS: OUTDOORAIR:MIXER ===========') !== false)
            {
                $current_section = Enumerations::OUTDOOR_AIR_MIXER_SECTION;
            }
            else
            {
                $current_section = Enumerations::OTHER_SECTION;
            }

            continue;
        }

        if($current_section == Enumerations::BRANCH_SECTION)
        {
            if(strpos($line, 'Pump:ConstantSpeed') !== false && strpos($line, 'Component 1 Object Type') !== false)
            {
                $name_line = fgets($myfile_idf);
                $tokens = explode(",", $name_line);

		// $tokens[0] is the first value in the line which in this case is the component name.
		// trim is used to remove any spaces around it.
                $current_name = trim($tokens[0]);
                while($line = fgets($myfile_idf))
                {
                    if(strpos($line, 'Component 1 Object Type') !== false)
                    {

                        $tokens = explode(",", $line);

			// $tokens[0] is the branch type.
                        $next_branch_type = trim($tokens[0]);

                        if($next_branch_type == "Chiller:Electric:EIR")
                        {
                            $pump_variables_map["Chiller:Electric:EIR"] = $current_name;
                        }
                        else if($next_branch_type == "CoolingTower:SingleSpeed")
                        {
                            $pump_variables_map["CoolingTower:SingleSpeed"] = $current_name;
                        }
                        else if($next_branch_type == "Boiler:HotWater")
                        {
                            $pump_variables_map["Boiler:HotWater"] = $current_name;
                        }

                        break;
                    }
                }

            }
            else if(strpos($line, 'Component 1 Object Type') !== false && strpos($line, 'AirLoopHVAC:OutdoorAirSystem') !== false)
            {
                while($line = fgets($myfile_idf))
                {
                    if(strpos($line, 'Component 3 Object Type') !== false && strpos($line, 'Coil:Heating:Water'))
                    {
                        $name_line = fgets($myfile_idf);
                        $tokens = explode(",", $name_line);
			
			// $tokens[0] is the component name.
                        $current_name = trim($tokens[0]);
                        $heating_coils_map[] =$current_name;
                        break;
                    }
                    else if(strpos($line, 'Component 3 Object Type') !== false)
                    {
                        break;
                    }
                }
            }
        }
        else if($current_section == Enumerations::COIL_HEATING_WATER_SECTION)
        {
            $tokens = explode(",", $line);
	    
	    // $tokens[1] is the second part of the line inside Coil Heating Water section.
	    // If the second part is "!- Name", add the name of the component to the all_heating_coils_map.
            if( isset($tokens[1]) && (substr(trim($tokens[1]), -7) === "!- Name"))
            {
                $all_heating_coils_map[] = trim($tokens[0]);
            }
        }
        else if($current_section == Enumerations::BUILDING_SURFACE_DETAILED_SECTION)
        {
            $tokens = explode(",", $line);
	    // $tokens[1] is the second part of the line inside Building Surface Detailed section.
            if( isset($tokens[1]) && (substr(trim($tokens[1]), -7) === "!- Name"))
            {
		// $tokens[0] is the component name.
                $current_name = trim($tokens[0]);
                while($line = fgets($myfile_idf))
                {
                    if(strpos($line, '!- Outside Boundary Condition Object') !== false)
                    {
                        $tokens = explode(",", $line);
                        if(empty(trim($tokens[0])))
                        {
                            $building_surface_detailed_map[] = $current_name;
                            break;
                        }
                    }

                }
            }
        }
        else if($current_section == Enumerations::OUTDOOR_AIR_MIXER_SECTION)
        {
            if(strpos($line, '!- Outdoor Air Stream Node Name') !== false)
            {
                $tokens = explode(",", $line);
                $outdoor_air_stream_nodes_map[] = trim($tokens[0]);
            }
            else if(strpos($line, '!- Relief Air Stream Node Name') !== false)
            {
                $tokens = explode(",", $line);
                $relief_air_stream_nodes_map[] = trim($tokens[0]);
            }
            else if(strpos($line, '!- Return Air Stream Node Name') !== false)
            {
                $tokens = explode(";", $line);
                $return_air_stream_nodes_map[] = trim($tokens[0]);
            }

        }

    }

    fclose($myfile_idf);

    (//This step is to calculate energy consumption by VAV-Reheat coils)
	$remaining_heating_coils = array_diff($all_heating_coils_map, $heating_coils_map);

    return array("pumps" => $pump_variables_map,
        "special_heating_coils" => $heating_coils_map,
        "remaining_heating_coils" => $remaining_heating_coils,
        "building_surfaces" => $building_surface_detailed_map,
        "outdoor_nodes" => $outdoor_air_stream_nodes_map,
        "relief_nodes" => $relief_air_stream_nodes_map,
        "return_nodes" => $return_air_stream_nodes_map);
}

// GET function to start generation of CSV files for all the diagrams in the system.
$app->get('/csv/generate/{diagram_name}', function ($diagram_name)
{
    $diagram_meta_data_file = fopen("files/diagrams/" . $diagram_name .  "/meta.txt", 'r');
    $building_type_id = fgets($diagram_meta_data_file);
    fclose($diagram_meta_data_file);
    generateCSV($diagram_name, $building_type_id);

    $results_array["success"] = true;
    return new JsonResponse($results_array);
});

// Internal function to generate the CSV files for a given diagram and its type.
function generateCSV($diagram_name, $building_type)
{
    //no time limit for the script (it can be too long)
    set_time_limit(0);
    $diagram_dir = 'files/diagrams/' . $diagram_name;
    $eso_path = 'files/diagrams/' . $diagram_name . '/uploaded_eso.eso';
    $reference_csv_path = 'files/reference/csv/';
    $reference_hvac_path = 'files/reference/csv/HVAC_';
    $reference_hvac_path .= ($building_type == Enumerations::SMALL_OFFICE) ? 'S_Office.csv' : 'LM_Office.csv';
    $reference_csv_path .= ($building_type == Enumerations::SMALL_OFFICE) ? 'S_Office.csv' : 'LM_Office.csv';
    $myfile = fopen($eso_path, "r") ;

    // $direct_lookup_map is array of records  
    // Record structure: (text to search for, token index, row #, exact match/not exact match)
    // The second value in each record is the line token index where a search will be performed to match the eso dictionary line against a given text to be searched for ( first value in the record)
    // e.g. 2 means search inside the third token in the line.
    // the third value -> (4, 5, 17, 18, 56, 71, 13 means the number of rows that will be filled in the CSV files from the ESO file)
   
    $small_office_offset = ($building_type == Enumerations::SMALL_OFFICE) ? 1 : 0;
    $direct_lookup_map = array( array("Boiler:Heating:Gas", 2, 2, false),
        // For large and medium office: Row 4 (Boiler energy transfer), Row 5 (Boiler electric energy consumption), Row 13 (AHU fans electric energy), Row 17 (lighting electric energy), Row 18 (Equipment electric energy), Row 56 (Extracted energy by cooling coils), Row 71 (Chiller electric energy)
		array("Boilers:EnergyTransfer", 2, 4, false),
        array("Boiler Parasitic:Heating:Electricity", 2, 5, false),
		
		// $small_office_offset is 1, which means that the number of rows are offset by one row for small office
        array("InteriorLights:Electricity", 2, 17 - $small_office_offset, false),
        array("InteriorEquipment:Electricity", 2, 18 - $small_office_offset,false),
        array("CoolingCoils:EnergyTransfer", 2, 56 - $small_office_offset, false),
        array("Cooling:Electricity", 2, 71 - ($small_office_offset*11), false),
        array("Fans:Electricity", 2, 13 - $small_office_offset, false)
    );
    if($building_type != Enumerations::SMALL_OFFICE)
    {
		$direct_lookup_map[] = array("Cooling Tower Fan Electric Energy", 3, 72, false);
    }
    else if($building_type == Enumerations::SMALL_OFFICE)
    {
        $direct_lookup_map[] = array("HeatingCoils:EnergyTransfer", 2, 10, false);
    }

    // $indirect_lookup_map is array of records  
    // Record structure: (text to search for, token index, row #, exact match/not exact match, second string to search for, second line token index, exact/non-exact match)
    // The second value in each record is the line token index where a search will be performed to match the eso dictionary line against a given text to be searched for ( first value in the record)
    // e.g. 2 means search inside the third token in the line.
    // the third value -> (20, 29, 54, 30, 31, 32, 33, 34, 35, 36, 37, 45, 46, 47, 48, 14, 76, 77) means the number of rows that will be filled in the CSV files from the ESO file)
    // The 5th, 6th, 7th values are similar to the first 3 values in the record but with different values to perform extra check in searching for the required line. 

	// For large and medium office: Row 20 (People latent energy gain), ROw 29 (Infiltration heat gain), Row 54 (Infiltration heat loss), Row 30 (Transmitted solar radiation for North window), Row 31 (Transmitted solar radiation for South window), Row 32 (Transmitted solar radiation for East window), Row 33 (Transmitted solar radiation for West window), Row 34 (Heat gain from North window), Row 35 (Heat gain from South window), Row 36 (Heat gain from East window), Row 37 (Heat gain from West window), Row 45 (Heat loss from North window), Row 46 (Heat loss from South window), Row 47 (Heat loss from East window), Row 48 (Heat loss from West window), Row 14 (Humidifier energy consumption), Row 76 (Surface Heat Storage loss Rate) Row 77 (Surface Heat Storage Gain Rate)
	// $small_office_offset is 1, which means that the number of rows are offset by one row for small office
	// The last two records are different because the Small office is offset by 14 rows in the CSV file.

    $indirect_lookup_map = array(   array("People Sensible Heating Energy", 3, 19 - $small_office_offset, false),
        array("People Latent Gain Energy", 3, 20 - $small_office_offset, false),
        array("Zone Infiltration Total Heat Gain Energy", 3, 29 - $small_office_offset, false),
        array("Zone Infiltration Total Heat Loss Energy", 3, 54 - $small_office_offset, false),
        array("Zone Windows Total Transmitted Solar Radiation Rate", 3, 30 - $small_office_offset, false, "NORTH", 2,false),
        array("Zone Windows Total Transmitted Solar Radiation Rate", 3, 31 - $small_office_offset, false, "SOUTH", 2,false),
        array("Zone Windows Total Transmitted Solar Radiation Rate", 3, 32 - $small_office_offset, false, "EAST", 2,false),
        array("Zone Windows Total Transmitted Solar Radiation Rate", 3, 33 - $small_office_offset, false, "WEST", 2,false),
        array("Zone Windows Total Heat Gain Rate", 3, 34 - $small_office_offset, false, "NORTH", 2,false),
        array("Zone Windows Total Heat Gain Rate", 3, 35 - $small_office_offset,false, "SOUTH", 2,false),
        array("Zone Windows Total Heat Gain Rate", 3, 36 - $small_office_offset,false, "EAST", 2,false),
        array("Zone Windows Total Heat Gain Rate", 3, 37 - $small_office_offset,false, "WEST", 2,false),
        array("Zone Windows Total Heat Loss Rate", 3, 45 - $small_office_offset,false, "NORTH", 2,false),
        array("Zone Windows Total Heat Loss Rate", 3, 46 - $small_office_offset,false, "SOUTH", 2,false),
        array("Zone Windows Total Heat Loss Rate", 3, 47 - $small_office_offset,false, "EAST", 2,false),
        array("Zone Windows Total Heat Loss Rate", 3, 48 - $small_office_offset,false, "WEST", 2,false),
        array("Air System Humidifier Gas Energy", 3, 14 - $small_office_offset, false),
        array("Surface Heat Storage Loss Rate", 3, 76 - ($small_office_offset*14), false),
        array("Surface Heat Storage Gain Rate", 3, 77 - ($small_office_offset*14), false)
    );


    $idf_data = getIdfData($diagram_name);

    // Pump variables
	// For large, medium, and small office: Row 63 (Chilled water (CHW) pump electric energy), Row 64 (CHW pump frictional loss), Row 61 (Condensing water (CDW) electric energy), Row 62 (CDW pump frictional losses), Row 6 (Hot water (HW) pump electric energy), Row 7 (HW pump frictional loss)
    foreach($idf_data["pumps"] as $pump_type => $variable_name)
    {
        if($pump_type == "Chiller:Electric:EIR" && $building_type != Enumerations::SMALL_OFFICE)
        {
            $direct_lookup_map[] = array(strtoupper($variable_name), 2, 63, true, "Pump Electric Energy", 3, false);
            $direct_lookup_map[] = array(strtoupper($variable_name), 2, 64, true, "Pump Fluid Heat Gain Energy", 3, false);
        }
        else if($pump_type == "CoolingTower:SingleSpeed" && $building_type != Enumerations::SMALL_OFFICE)
        {
            $direct_lookup_map[] = array(strtoupper($variable_name), 2, 61, true, "Pump Electric Energy", 3, false);
            $direct_lookup_map[] = array(strtoupper($variable_name), 2, 62, true, "Pump Fluid Heat Gain Energy", 3, false);
        }
        else if($pump_type == "Boiler:HotWater")
        {
            $direct_lookup_map[] = array(strtoupper($variable_name), 2, 6, true, "Pump Electric Energy", 3, false);
            $direct_lookup_map[] = array(strtoupper($variable_name), 2, 7, true, "Pump Fluid Heat Gain Energy", 3, false);
        }
    }

    if(!empty($idf_data['special_heating_coils']) && $building_type != Enumerations::SMALL_OFFICE)
    {
        // For large, medium, and small office: Row 10 (Energy consumption by heating coils), Row 11 (VAV-Reheat energy consumption)
		$indirect_lookup_map[] = array($idf_data['special_heating_coils'], 2, 10, true);
    }

    if(!empty($idf_data['remaining_heating_coils']) && $building_type != Enumerations::SMALL_OFFICE)
    {
        $indirect_lookup_map[] = array($idf_data['remaining_heating_coils'], 2, 11, true);
    }


    $walls_variables = array();
    $floors_variables = array();
    $ceilings_variables = array();

    foreach ($idf_data['building_surfaces'] as $building_surface_variable)
    {
        if(stripos($building_surface_variable, "Wall") !== false)
        {
            $walls_variables[] = strtoupper($building_surface_variable);
        }
        else if(stripos($building_surface_variable, "Floor") !== false)
        {
            $floors_variables[] = strtoupper($building_surface_variable);
        }
        else if(stripos($building_surface_variable, "Ceiling") !== false)
        {
            $ceilings_variables[] = strtoupper($building_surface_variable);
        }
    }

	// For large and medium office: Row 41 (Heat gain from walls), Row 42 (Heat gain from roofs), Row 43 (Heat gain from floors), Row 50 (Heat loss from walls), Row 51 (heat loss from floors), Row 52 (heat loss from roofs)
	// $small_office_offset is 1, which means that the number of rows are offset by one row for small office
    $small_office_offset = ($building_type == Enumerations::SMALL_OFFICE) ? 1 : 0;
    $indirect_lookup_map[] = array($walls_variables, 2, 41 - $small_office_offset, true, "Surface Inside Face Conduction Heat Gain Rate", 3, false);
    $indirect_lookup_map[] = array($ceilings_variables, 2, 42 - $small_office_offset, true, "Surface Inside Face Conduction Heat Gain Rate", 3, false);
    $indirect_lookup_map[] = array($floors_variables, 2, 43 - $small_office_offset, true, "Surface Inside Face Conduction Heat Gain Rate", 3, false);
    $indirect_lookup_map[] = array($walls_variables, 2, 50 - $small_office_offset, true, "Surface Inside Face Conduction Heat Loss Rate", 3, false);
    $indirect_lookup_map[] = array($ceilings_variables, 2, 51 - $small_office_offset, true, "Surface Inside Face Conduction Heat Loss Rate", 3, false);
    $indirect_lookup_map[] = array($floors_variables, 2, 52 - $small_office_offset, true, "Surface Inside Face Conduction Heat Loss Rate", 3, false);

    $complex_lookup_map = array();
    $complex_lookup_map[] = array($idf_data['outdoor_nodes'], 2, 16 - $small_office_offset, true);
    $complex_lookup_map[] = array($idf_data['relief_nodes'], 2, 55 - $small_office_offset, true);

    $hvac_complex_lookup_map = array();
    $hvac_complex_lookup_map[] = array($idf_data['return_nodes'], 2, 16, true);

    // Record ( key: initial token, val:row#)
    $direct_relation_map = array();

    // Record ( key: row #, val: array(Initial Tokens) )
    $indirect_relation_map = array();

    // Record ( key: row #, val: (record (key: variable name, val: Initial token)))
    $complex_relation_map = array();
    $hvac_complex_relation_map = array();

    $dictionary = array();

    // read dictionary
    $count = 0;
    while(!feof($myfile)) {
        $count++;
        $line = fgets($myfile);
        if(strpos($line, 'End of Data Dictionary') !== false )
        {
            break;
        }
	
	// start processing after the 6th row in the eso file.
        if($count > 6 )
        {
            $line_tokens = explode(",", $line);
            foreach($direct_lookup_map as $lookup_record)
            {
                if(IsLineMatches($line_tokens, $lookup_record))
                { 
                    $direct_relation_map[$line_tokens[0]] = $lookup_record[2];
                    $dictionary[$line_tokens[0]] = GetVariableUnit($line);
                }
            }

            foreach($indirect_lookup_map as $lookup_record)
            {
                $matches = false;
		// $lookup_record[0] is the string to search for. Some cases, there are multiple strings to search for ( array).
		// if it is array, loop on the strings and compare against the current line
                if(is_array($lookup_record[0]))
                {
                    $strings_array = $lookup_record[0];
                    foreach ($strings_array as $lookup_string)
                    {
                        $lookup_record[0] = strtoupper($lookup_string);
                        if(IsLineMatches($line_tokens, $lookup_record))
                        {
                            $matches = true;
                            break;
                        }
                    }
                }
                // else, it is only one string, therefore compare against the current line.
                else if(IsLineMatches($line_tokens, $lookup_record))
                {
                    $matches = true;
                }
                if($matches)
                {
		    // $lookup_record[2] is the csv row #
	            // $line_tokens[0] is the ID of the phyical measurement.
                    $indirect_relation_map[$lookup_record[2]][] = $line_tokens[0];
                    $dictionary[$line_tokens[0]] = GetVariableUnit($line);
                }
            }

            foreach($complex_lookup_map as $lookup_record)
            {
		// $lookup_record[0] in complex map are multiple strings to search for ( array).
		// loop on the strings and compare against the current line
                $strings_array = $lookup_record[0];
                foreach ($strings_array as $lookup_string)
                {
                    $lookup_record[0] = strtoupper($lookup_string);
                    if(IsLineMatches($line_tokens, $lookup_record))
                    {
                        $complex_relation_map[$lookup_record[2]][$lookup_string][] = $line_tokens[0];
                        $dictionary[$line_tokens[0]] = GetVariableUnit($line);
                        break;
                    }
                }
            }

            foreach($hvac_complex_lookup_map as $lookup_record)
            {
                $strings_array = $lookup_record[0];
                foreach ($strings_array as $lookup_string)
                {
                    $lookup_record[0] = strtoupper($lookup_string);
                    if(IsLineMatches($line_tokens, $lookup_record))
                    {
                        $hvac_complex_relation_map[$lookup_record[2]][$lookup_string][] = $line_tokens[0];
                        $dictionary[$line_tokens[0]] = GetVariableUnit($line);
                        break;
                    }
                }
            }
        }
    }

    $objPHPExcel_week = null;
    $objPHPExcel_day = null;
    $objReader = PHPExcel_IOFactory::createReader("CSV");
    $objPHPExcel_month = $objReader->load($reference_csv_path);
    $objPHPExcel_month->setActiveSheetIndex(0);
    $objPHPExcel_week_hvac = null;
    $objPHPExcel_day_hvac  = null;
    $objPHPExcel_month_hvac  = $objReader->load($reference_hvac_path);
    $objPHPExcel_month_hvac ->setActiveSheetIndex(0);

    $week_index = 0;
    $month_index  = 0;
    $current_hour_values = array();
    $current_hour_column = "";
    $current_day_column = "";
    $current_month_column = "";

    $hours_count = 0;
    while(!feof($myfile)) {
        $line = fgets($myfile);
        $line_tokens = explode(",", $line);
        // new hour or reached the last line.
        if($line_tokens[0] == "2" || strpos($line, 'Number of Records Written') !== false )
        {
            // Executed after each hour
            if($hours_count > 0)
            {
                // Dump values in hour column
                $objPHPExcel_week = DumpToCSV($current_hour_values, $objPHPExcel_week, $current_hour_column,
                    $direct_relation_map, $indirect_relation_map, $complex_relation_map, $building_type);

                // Dump HVAC values in hour column
                $objPHPExcel_week_hvac = DumpToHVAC($current_hour_values, $hvac_complex_relation_map, $objPHPExcel_week, $objPHPExcel_week_hvac, $current_hour_column, $building_type);

                // Add hour value to current day and month columns
                $highest_row = $objPHPExcel_week->getActiveSheet()->getHighestRow();
                for($i = 2; $i <= $highest_row ; $i++)
                {
                    $hour_value = $objPHPExcel_week->getActiveSheet()->getCell($current_hour_column . $i)->getValue();
                    $day_value = $objPHPExcel_day->getActiveSheet()->getCell($current_day_column . $i)->getValue();

                    $day_value = ($day_value == "") ? 0 : $day_value;
					//The values are divided by 1000 to get the values in kWh
                    $day_value +=  ($hour_value/1000.0);
                    $objPHPExcel_day->getActiveSheet()->setCellValue($current_day_column . $i , $day_value);

                    $month_value = $objPHPExcel_month->getActiveSheet()->getCell($current_month_column . $i)->getValue();
                    $month_value = ($month_value == "") ? 0 : $month_value;
					//The values are divided by 1000 to get the values in kWh
                    $month_value +=  ($hour_value/1000.0);
                    $objPHPExcel_month->getActiveSheet()->setCellValue($current_month_column . $i , $month_value);
                }

                // Add hour HVAC value to current day and month columns
                $highest_row_hvac = $objPHPExcel_week_hvac->getActiveSheet()->getHighestRow();
                for($i = 2; $i <= $highest_row_hvac ; $i++)
                {
                    $hour_value = $objPHPExcel_week_hvac->getActiveSheet()->getCell($current_hour_column . $i)->getValue();
                    $day_value = $objPHPExcel_day_hvac->getActiveSheet()->getCell($current_day_column . $i)->getValue();

                    $day_value = ($day_value == "") ? 0 : $day_value;
					//The values are divided by 1000 to get the values in kWh
                    $day_value += ($hour_value / 1000.0);
                    $objPHPExcel_day_hvac->getActiveSheet()->setCellValue($current_day_column . $i, $day_value);

                    $month_value = $objPHPExcel_month_hvac->getActiveSheet()->getCell($current_month_column . $i)->getValue();
                    $month_value = ($month_value == "") ? 0 : $month_value;
					//The values are divided by 1000 to get the values in kWh
                    $month_value += ($hour_value / 1000.0);
                    $objPHPExcel_month_hvac->getActiveSheet()->setCellValue($current_month_column . $i, $month_value);
                }

                // clean zero values for the current hour column.
                $objPHPExcel_week = CleanZeros($objPHPExcel_week, $current_hour_column);
                $objPHPExcel_week_hvac = CleanZeros($objPHPExcel_week_hvac, $current_hour_column);
                if(strpos($line, 'Number of Records Written') !== false)
                {
                    continue;
                }
            }

            $hours_count++;
	    // reaches beginning of a new week. Each 168 hours.
            if (($hours_count - 1) % 168 == 0)     
            {
		// execute this for any week after the first one.
                if($week_index > 0) 
                {
			// save the current opened week file 
                    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_week, 'CSV');
                    $objWriter->save($diagram_dir . '/week_' . $week_index . '.csv');

			// open another week file
                    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_week_hvac, 'CSV');
                    $objWriter->save($diagram_dir . '/hvac_week_' . $week_index . '.csv');
                }

		// equation to calculate the current week index.
                $week_index = (($hours_count - 1) / 168) + 1;
                $objPHPExcel_week = $objReader->load($reference_csv_path);
                $objPHPExcel_week->setActiveSheetIndex(0);

                $objPHPExcel_week_hvac = $objReader->load($reference_hvac_path);
                $objPHPExcel_week_hvac->setActiveSheetIndex(0);
            }

	    //  $line_tokens[1] is the day value from eso record.
            $record_day = $line_tokens[1];

            // reaches the beginning of a new day. each 24 hours.
            if (($hours_count - 1) % 24 == 0)
            {
                // clean 0's of the current day columns
                // execute this for any day except the first day.
                if($record_day > 1)
                {
                    $objPHPExcel_day = CleanZeros($objPHPExcel_day, $current_day_column);
                    $objPHPExcel_day_hvac = CalculateHvacEnergyFlows($objPHPExcel_day_hvac, $current_day_column, $building_type, $objPHPExcel_day);
                    $objPHPExcel_day_hvac = CleanZeros($objPHPExcel_day_hvac, $current_day_column);
                }

                // open new file at the begining of the year and at the mid year.
		// There are two files for the days ( days_1, days_2). days_1 -> first 183 days in the year. days_2 -> the remaining 182 days in the year.
                if($record_day == 1 || $record_day == 183)
                {
                    // save the first days file
                    if($record_day == 183)
                    {
                        $objPHPExcel_day = CleanZeros($objPHPExcel_day, $current_day_column);
                        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_day, 'CSV');
                        $objWriter->save($diagram_dir . '/days_1.csv');

                        $objPHPExcel_day_hvac = CalculateHvacEnergyFlows($objPHPExcel_day_hvac, $current_day_column, $building_type, $objPHPExcel_day);
                        $objPHPExcel_day_hvac = CleanZeros($objPHPExcel_day_hvac, $current_day_column);
                        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_day_hvac, 'CSV');
                        $objWriter->save($diagram_dir . '/hvac_days_1.csv');
                    }

                    // open new days files
                    $objPHPExcel_day = $objReader->load($reference_csv_path);
                    $objPHPExcel_day->setActiveSheetIndex(0);

                    $objPHPExcel_day_hvac = $objReader->load($reference_hvac_path);
                    $objPHPExcel_day_hvac->setActiveSheetIndex(0);
                }

                // set the column index using relationship between day index in eso file and the column index
                $column_index = $record_day + 1;

                // for the days_2 file, subtract the offset 182 days from the day index
                if($record_day > 182)
                {
                    $column_index = $record_day - 182 + 1;
                }

                // new day column header
                $current_day_column = PHPExcel_Cell::stringFromColumnIndex($column_index);
                $objPHPExcel_day->GetSheet(0)->setCellValue($current_day_column . 1 , "day_" . $record_day);
                $objPHPExcel_day_hvac->GetSheet(0)->setCellValue($current_day_column . 1 , "day_" . $record_day);

            }

            // new hour column header
	    // equation to calculate the column index in the excel sheet in terms of the current hour in the year.
            $column_index = (($hours_count - 1) % 168) + 2;
            $hour_index = $column_index -1;
            $current_hour_column = PHPExcel_Cell::stringFromColumnIndex($column_index);
            $objPHPExcel_week->GetSheet(0)->setCellValue($current_hour_column . 1 , "hour_" . $hour_index);
            $objPHPExcel_week_hvac->GetSheet(0)->setCellValue($current_hour_column . 1 , "hour_" . $hour_index);

			
	    // $line_tokens[2] is the month vale in the eso file
            $record_month = trim($line_tokens[2]);
            if($month_index !=  $record_month)
            {
                // clean zeroes for the current month column
                if($record_month > 1)
                {
                    $objPHPExcel_month = CleanZeros($objPHPExcel_month, $current_month_column);
                    $objPHPExcel_month_hvac = CalculateHvacEnergyFlows($objPHPExcel_month_hvac, $current_month_column, $building_type, $objPHPExcel_month);
                    $objPHPExcel_month_hvac = CleanZeros($objPHPExcel_month_hvac, $current_month_column);
                }

                // new month column header
                $month_index = $record_month;
                $column_index = $month_index + 1;
                $current_month_column = PHPExcel_Cell::stringFromColumnIndex($column_index);
                $objPHPExcel_month->GetSheet(0)->setCellValue($current_month_column . 1 , "month_" . $record_month);
                $objPHPExcel_month_hvac->GetSheet(0)->setCellValue($current_month_column . 1 , "month_" . $record_month);
            }
        }
        else if(isset($dictionary[$line_tokens[0]]))
        {
            if($dictionary[$line_tokens[0]] == "J")
            {
				// The values is divided by 3600 to convert from J to W
				// $line_tokens[0] is the ID of the physical measurement.
                                // $line_tokens[1] is the measured value for this phyical measurement ID.
                $current_hour_values[$line_tokens[0]] = $line_tokens[1] / 3600.000;
            }
            else
            {
                $current_hour_values[$line_tokens[0]] = $line_tokens[1];
            }
        }

    }
    $objPHPExcel_day = CleanZeros($objPHPExcel_day, $current_day_column);
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_day, 'CSV');
    $objWriter->save($diagram_dir . '/days_2.csv');
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_week, 'CSV');
    $objWriter->save($diagram_dir . '/week_' . $week_index . '.csv');
    $objPHPExcel_month = CleanZeros($objPHPExcel_month, $current_month_column);
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_month, 'CSV');
    $objWriter->save($diagram_dir . '/months.csv');

    $objPHPExcel_day_hvac = CalculateHvacEnergyFlows($objPHPExcel_day_hvac, $current_day_column, $building_type, $objPHPExcel_day);
    $objPHPExcel_day_hvac = CleanZeros($objPHPExcel_day_hvac, $current_day_column);
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_day_hvac, 'CSV');
    $objWriter->save($diagram_dir . '/hvac_days_2.csv');
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_week_hvac, 'CSV');
    $objWriter->save($diagram_dir . '/hvac_week_' . $week_index . '.csv');

    $objPHPExcel_month_hvac = CalculateHvacEnergyFlows($objPHPExcel_month_hvac, $current_month_column, $building_type, $objPHPExcel_month);
    $objPHPExcel_month_hvac = CleanZeros($objPHPExcel_month_hvac, $current_month_column);
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel_month_hvac, 'CSV');
    $objWriter->save($diagram_dir . '/hvac_months.csv');

    fclose($myfile);
}

// Internal function to evaluate some records in the CSV files based on equations.
function EvaluateEquations(\PHPExcel $objPHPExcel, $current_column, $building_type)
{
    $column_array = array();
    $column_array[0] = 0;
    $column_array[1] = 0;
    $highest_row = $objPHPExcel->getActiveSheet()->getHighestRow();
    for($row = 2 ; $row<= $highest_row; $row++)
    {
        $cell_val = $objPHPExcel->getActiveSheet()->getCell($current_column . $row)->getValue();
        $column_array[$row] = (empty($cell_val)) ? 0 : $cell_val;
    }

    if($building_type == Enumerations::SMALL_OFFICE)
    {
		// Heat loss due to inefficiency of the boiler = Boiler natural gas consumption - Boiler energy transfer
        $column_array[3] = $column_array[2] - $column_array[4];
		// Internal heat gain from lighting = Lighting energy consumption
        $column_array[20] = $column_array[16];
		// Internal heat gain from equipment = Equipment energy consumption
        $column_array[21] = $column_array[17];
		// Energy extracted by cooling coils to DX unit = Energy extracted by cooling coils
        $column_array[61] = $column_array[55];
		// Transmitted solar radiation = Transmitted solar radiation from (North + South + East + West) windows
        $column_array[37] = $column_array[29] + $column_array[30] + $column_array[31] + $column_array[32];
		// Heat gain from windows = Heat gain from (North + South + East + West) windows
        $column_array[38] = $column_array[33] + $column_array[34] + $column_array[35] + $column_array[36];
		// Total window heat gains = Transmitted solar radiation + Heat gain from windows
        $column_array[39] = $column_array[37] + $column_array[38];
		// Heat loss from windows = Heat loss from (North + South + East + West) windows
        $column_array[48] = $column_array[44] + $column_array[45] + $column_array[46] + $column_array[47];
		// Heat loss from hot water (HW) loop pump to the surroundings = Electricity consumption by HW pump - HW Pump frictional losses
        $column_array[8] = $column_array[6] - $column_array[7];
		// Heat added by HW pump to the surroundings = Heat loss from hot water (HW) pump to the surroundings
        $column_array[24] = $column_array[8];
		// Heat added by the HW pump to the fluid = HW Pump frictional losses
        $column_array[9] = $column_array[7];
		
		// Row 10 (Energy consumption by heating coils), Row 4 (boiler energy transfer), Row 7 (HW Pump frictional losses), Row 11 (Energy consumption by radiant panels), Row 13 (energy consumption by humidifier)
        if($column_array[10]  > ($column_array[4]  + $column_array[7] ))
        {
            $column_array[10]  = $column_array[4]  + $column_array[7] ;
        }
        $column_array[11] = $column_array[4] + $column_array[7] - $column_array[10] - $column_array[13];
		
        if($column_array[11] < 0)
        {
            $column_array[11] = 0;
            $column_array[10] = $column_array[4] + $column_array[7];
        }
		// Fans electric energy consumption 
        $fans_electricity = $column_array[12];
		// Fans electric energy consumption for heating = Total fans electric energy consumption * (Energy consumption by heating coils/Energy consumption by heating coils + Energy extracted by cooling coils)
        $column_array[12] = ($column_array[10] + $column_array[55] !=0) ? $fans_electricity * ($column_array[10] / ($column_array[10] + $column_array[55])) : 0;
        // Fans electric energy consumption for cooling = Total fans electric energy consumption * (Energy extracted by cooling coils/Energy consumption by heating coils + Energy extracted by cooling coils)
		$column_array[56] = ($column_array[10] + $column_array[55] !=0) ? $fans_electricity * ($column_array[55] / ($column_array[10] + $column_array[55])) : 0;
        // The amount of heat added by heating coils to PSZ = Energy consumption by heating coils
		$column_array[14] = $column_array[10];
		// Heat gain from building envelope = Heat gain from (Walls + Roofs + Floors)
        $column_array[43] = $column_array[40] + $column_array[41] + $column_array[42];
		// Heat loss from building envelope = Heat loss from (Walls + Roofs + Floors)
        $column_array[52] = $column_array[49] + $column_array[50] + $column_array[51];
		// Internal heat gain from people = People sensible heat gain
        $column_array[22] = $column_array[18];
		// Internal latent gain from people = People latent energy gain
        $column_array[23] = $column_array[19];
		// Total internal gain = Lighting energy consumption + Equipment energy consumption + People sensible heat gain + People latent energy gain
        $column_array[27] = $column_array[20] + $column_array[21] + $column_array[22] + $column_array[23];
		// Heat gain from radiant panels = Energy consumption by radiant panels
        $column_array[26] = $column_array[11];

		// Row 55 (Heat extracted by cooling coils), Row 15 (heat gain from outdoor), Row 58 (Free cooling), Row 57 (Outdoor air loss)
        if($column_array[55] > 0)
        {
            if ($column_array[15] < 0) {
				$column_array[58] = abs($column_array[15]);
                $column_array[15] = 0;
            } else {
				$column_array[58] = 0;
            }
        }
        else (// Heat extracted by cooling coils = 0)
        {
            if ($column_array[15] < 0) {
				$column_array[57] = abs($column_array[15]);
                $column_array[15] = 0;
            } else {
			   $column_array[57] = 0;
            }
        }


		// Energy supplied by PSZ = Fans electric energy consumption for heating + Energy consumption by heating coils + heat gain from outdoor + energy consumption by humidifier
        $column_array[25] = $column_array[12] + $column_array[10] + $column_array[15] + $column_array[13];

		// Row 62 (Surface Heat Storage Loss Rate), Row 63 (Surface Heat Storage Gain Rate)  
        if ($column_array[62] > $column_array[63]) {
         	$column_array[62] = $column_array[62] - $column_array[63];
            $column_array[63] = 0;
        } else {
			$column_array[63] = $column_array[63] - $column_array[62];
            $column_array[62] = 0;
        }

        // Energy extracted by PSZ = Exhaust air + Energy extracted by cooling coils + Outdoor air loss + Free cooling - Fans electric energy consumption for cooling
		$column_array[59] = $column_array[54] + $column_array[55] + $column_array[57] + $column_array[58] - $column_array[56];

        // Energy Balance
		// Energy in = Energy added by HW pump to the surroundings + Energy supplied by PSZ + Total internal gains + Infiltration heat gain + Total window heat gains + Heat gain from building envelope + Surface Heat Storage Loss Rate
        $energy_in = $column_array[24] + $column_array[25] + $column_array[26] + $column_array[27] + $column_array[28] + $column_array[39] + $column_array[43] + $column_array[62];
        // Energy out = Heat loss from windows + Heat loss from building envelope + Infiltration heat loss + Energy extracted by PSZ + Surface Heat Storage Gain Rate
		$energy_out = $column_array[48] + $column_array[52] + $column_array[53] + $column_array[59] + $column_array[63];

        $diff = abs($energy_in - $energy_out);

        if($energy_out > $energy_in)
        {
            // Energy stored in radiant panels = energy out - energy in
			$column_array[64] = $diff;
        }
        else if ($energy_out < $energy_in)
        {
            // Exhaust air = Exhaust air + diff
			$column_array[54] = $column_array[54] + $diff;
			// Recalculate Energy extracted by PSZ
            $column_array[59] = $column_array[54] + $column_array[55] + $column_array[57] + $column_array[58] - $column_array[56];
        }

		// Row 54 (Exhaust air), Row 55 (Energy extracted by cooling coils) 
        if($column_array[54] < 0)
        {
            $column_array[54] =0;
            $column_array[55] = $column_array[55] - $diff;
			// Recalculate Energy extracted by cooling coils to DX unit
            $column_array[61] = $column_array[55];
        }

		// Recalculate Energy extracted by PSZ
        $column_array[59] = $column_array[54] + $column_array[55] + $column_array[57] + $column_array[58] - $column_array[56];
    }
    else //(i.e Large and medium office)
    {
        // Heat loss due to inefficiency of the boiler = Boiler natural gas consumption - Boiler energy transfer
		$column_array[3] = $column_array[2] - $column_array[4];
		// Internal heat gain from lighting = Lighting energy consumption
        $column_array[21] = $column_array[17];
		// Internal heat gain from equipment = Equipment energy consumption
        $column_array[22] = $column_array[18];
		// Energy extracted by cooling coils to AHU unit = Energy extracted by cooling coils
        $column_array[73] = $column_array[56];
		// Transmitted solar radiation = Transmitted solar radiation from (North + South + East + West) windows
        $column_array[38] = $column_array[30] + $column_array[31] + $column_array[32] + $column_array[33];
		// Heat gain from windows = Heat gain from (North + South + East + West) windows
        $column_array[39] = $column_array[34] + $column_array[35] + $column_array[36] + $column_array[37];
		// Total window heat gains = Transmitted solar radiation + Heat gain from windows
        $column_array[40] = $column_array[38] + $column_array[39];
		// Heat loss from windows = Heat loss from (North + South + East + West) windows
        $column_array[49] = $column_array[45] + $column_array[46] + $column_array[47] + $column_array[48];
		// Heat loss from hot water (HW) loop pump to the surroundings = Electricity consumption by HW pump - HW Pump frictional losses
        $column_array[8] = $column_array[6] - $column_array[7];
		// Heat added by HW pump to the surroundings = Heat loss from hot water (HW) pump to the surroundings
        $column_array[25] = $column_array[8];
		// Heat added by the HW pump to the fluid = HW Pump frictional losses
        $column_array[9] = $column_array[7];
		// Heat added by condensing water (CDW) pump to the surroundings = Electricity consumption by CDW pump - CDW Pump frictional losses
        $column_array[65] = $column_array[61] - $column_array[62];
		// Heat added by CDW pump to the surroundings = Heat loss from CDW pump to the surroundings
        $column_array[67] = $column_array[65];
		// Heat added by the CDW pump to the fluid = CDW Pump frictional losses
        $column_array[69] = $column_array[62];
		// Heat added by chilled water (CHW) pump to the surroundings = Electricity consumption by CHW pump - CHW Pump frictional losses
        $column_array[66] = $column_array[63] - $column_array[64];
		// Heat added by CHW pump to the surroundings = Heat loss from CHW pump to the surroundings
        $column_array[68] = $column_array[66];
		// Heat added by the CHW pump to the fluid = CHW Pump frictional losses
        $column_array[70] = $column_array[64];
		// Energy extracted from chillers to cooling towers = Heat added by the CHW pump to the fluid + electric energy consumption by chillers + Heat extracted by cooling coils 
        $column_array[74] = $column_array[70] + $column_array[71] + $column_array[73];

        // if Energy consumption by heating coils > (boiler energy transfer + HW Pump frictional losses)
		if($column_array[10] > ($column_array[4] + $column_array[7]))
        {
           // Energy consumption by heating coils = boiler energy transfer + HW Pump frictional losses
		   $column_array[10] = $column_array[4] + $column_array[7];
        }

        // Energy consumption by radiant panels =  (boiler energy transfer + HW Pump frictional losses - energy consumption by heating coils - energy consumption by VAV-Reheat coils)
		$column_array[12] = $column_array[4] + $column_array[7] - $column_array[10] - $column_array[11] - $column_array[14];
        // If Energy consumption by radiant panels < 0
		if ($column_array[12] < 0) {
            $column_array[12] = 0;
			// VAV-Reheat energy consumption = boiler energy transfer + HW Pump frictional losses - Energy consumption by radiant panels
            $column_array[11] = $column_array[4] + $column_array[7] - $column_array[10];
        }

        // row 13,57 ( Fans: electricity and perform 2 equations)
        // Fans electric energy consumption
		$fans_electricity = $column_array[13];
		// Fans electric energy consumption for heating = Total fans electric energy consumption * (Energy consumption by heating coils/Energy consumption by heating coils + Energy extracted by cooling coils)
        $column_array[13] = ($column_array[10] + $column_array[56] != 0) ? $fans_electricity * ($column_array[10] / ($column_array[10] + $column_array[56])) : 0;
        // Fans electric energy consumption for cooling = Total fans electric energy consumption * (Energy extracted by cooling coils/Energy consumption by heating coils + Energy extracted by cooling coils)
		$column_array[57] = ($column_array[10] + $column_array[56] != 0) ? $fans_electricity * ($column_array[56] / ($column_array[10] + $column_array[56])) : 0;

		// The amount of heat added by heating coils to AHU = Energy consumption by heating coils
        $column_array[15] = $column_array[10];
		// Heat gain from building envelope = Heat gain from (Walls + Roofs + Floors)
        $column_array[44] = $column_array[41] + $column_array[42] + $column_array[43];
		// Heat loss from building envelope = Heat loss from (Walls + Roofs + Floors)
        $column_array[53] = $column_array[50] + $column_array[51] + $column_array[52];
		// Internal heat gain from people = People sensible heat gain
        $column_array[23] = $column_array[19];
		// Internal latent gain from people = People latent energy gain
        $column_array[24] = $column_array[20];
		// Total internal gain = Lighting energy consumption + Equipment energy consumption + People sensible heat gain + People latent energy gain
        $column_array[28] = $column_array[21] + $column_array[22] + $column_array[23] + $column_array[24];
		// Heat gain from radiant panels = Energy consumption by radiant panels
        $column_array[27] = $column_array[12];

        // row 16 (Heat gain from outdoor) and row 58 (Outdoor air loss)
        if ($column_array[16] < 0) {
            $column_array[58] = abs($column_array[16]);
            $column_array[16] = 0;
        } else {
            $column_array[58] = 0;
        }

        // Energy supplied by AHU = Fans electric energy consumption for heating + energy consumption by humidifier + Energy consumption by heating coils + heat gain from outdoor 
		$column_array[26] = $column_array[13] + $column_array[14] + $column_array[15] + $column_array[16];
		// Heat added by VAV-Reheat = VAV-Reheat energy consumption
        $column_array[75] = $column_array[11];

        // Row 76 (Surface Heat Storage Loss Rate), Row 77 (Surface Heat Storage Gain Rate) 
		if ($column_array[76] > $column_array[77]) {
            $column_array[76] = $column_array[76] - $column_array[77];
            $column_array[77] = 0;
        } else {
            $column_array[77] = $column_array[77] - $column_array[76];
            $column_array[76] = 0;
        }

		// Row 56 (Enegry extracted by cooling coils), Row 10 (Energy consumption by heating coils)
        if ($column_array[56] > 10) {
            
			$column_array[59] = ($column_array[25] + $column_array[26] + $column_array[27] + $column_array[28] + $column_array[29] + $column_array[40] + $column_array[44] + $column_array[57] + $column_array[67] + $column_array[68]) - ($column_array[49] + $column_array[53] + $column_array[54] + $column_array[55] + $column_array[56] + $column_array[58]);
        } else {
            $column_array[59] = 0;
        }
		
		// Energy extracted by AHU = Exhaust air + Energy extracted by cooling coils + Outdoor air loss + Free cooling - Fans electric energy consumption for cooling
        $column_array[60] = ($column_array[55] + $column_array[56] + $column_array[58] + $column_array[59] - $column_array[57]);

        // Adjust Energy Balance, Row 78 (Energy stored in radiant panels)
        $column_array[78] = 0;

		// Energy in = Energy added by HW pump to the surroundings + Energy supplied by AHU + Energy consumption by radiant panel + Total internal gains + Infiltration heat gain + Total window heat gains + Heat gain from building envelope + Energy added by CDW pump to the surroundings + Energy added by CHW pump to the surroundings + VAV-Rehheat coils energy consumption + Surface Heat Storage Loss Rate
        $energy_in = $column_array[25] + $column_array[26] + $column_array[27] + $column_array[28] + $column_array[29] + $column_array[40] + $column_array[44] + $column_array[67] + $column_array[68] + $column_array[75] + $column_array[76];
        // Energy out = Heat loss from windows + Heat loss from building envelope + Infiltration heat loss + Energy extracted by AHU + Surface Heat Storage Gain Rate
		$energy_out = $column_array[49] + $column_array[53] + $column_array[54] + $column_array[60] + $column_array[77];

        $diff = abs($energy_in - $energy_out); 
        if($diff > 10)
        {
            // Row 56 (Enegry extracted by cooling coils), Row 55 (Exhaust air), Row 59 (Free cooling), Row 78 (Energy stored in radiant panels)
			if($column_array[56] == 0)
            {
                if($energy_out > $energy_in)
                {
                    $column_array[78] = $diff;
                }
                else if ($energy_out < $energy_in)
                {
                    $column_array[55] = $column_array[55] + $diff;
					// Recalculate Energy extracted by AHU = Exhaust air + Energy extracted by cooling coils + Outdoor air loss + Free cooling - Fans electric energy consumption for cooling
                    $column_array[60] = ($column_array[55] + $column_array[56] + $column_array[58] + $column_array[59] - $column_array[57]);
                }

            }
            else if ($column_array[56] > 0)
            {
                if($energy_in > $energy_out)
                {
                    $column_array[59] = $column_array[59] + $diff;
                }
                else if($energy_in < $energy_out)
                {
                    $column_array[55] = $column_array[55] - $diff;
                }

				// Recalculate Energy extracted by AHU = Exhaust air + Energy extracted by cooling coils + Outdoor air loss + Free cooling - Fans electric energy consumption for cooling
                $column_array[60] = ($column_array[55] + $column_array[56] + $column_array[58] + $column_array[59] - $column_array[57]);
            }
        }

        if($column_array[59] < 0)
        {
            $column_array[59] = 0;
        }

        if($column_array[55] < 0)
        {
            $column_array[55] = 0;
            $column_array[56] = $column_array[56] - $diff;
        }

		// Recalculate Energy extracted by AHU
        $column_array[60] = ($column_array[55] + $column_array[56] + $column_array[58] + $column_array[59] - $column_array[57]);
        
		// Row 73 (Energy extracted from cooling coils to chillers = Energy extracted by cooling coils)
		$column_array[73] = $column_array[56];

		// Energy extracted from chillers to cooling towers = Heat added by the CHW pump to the fluid + Electric energy consumption by chillers + Energy extracted by cooling coils
        $column_array[74] = $column_array[70] + $column_array[71] + $column_array[73];
    }

    // begin from row 2 in the csv 
    for($row = 2 ; $row <= $highest_row; $row++)
    {
        $objPHPExcel->getActiveSheet()->setCellValue($current_column . $row, $column_array[$row]);
    }

    return $objPHPExcel;
}

// Internal function to dump values to CSV files ( executed per column)
function DumpToCSV($current_hour_values, $objPHPExcel, $current_column, $direct_relation_map, $indirect_relation_map, $complex_relation_map, $building_type)
{
    // Direct Values
    foreach ($direct_relation_map as $initial_token => $v)
    {
        $val = floatval($current_hour_values[$initial_token]);
        $val = ($val == 0) ? "0" : number_format($val, 6, '.', '');
        $objPHPExcel->getActiveSheet()->setCellValue($current_column . $direct_relation_map[$initial_token] , $val);
    }

    // Indirect Values
    foreach ($indirect_relation_map as $row => $initial_tokens)
    {
        $total_value = 0;
        foreach($initial_tokens as $k => $initial_token)
        {
            $val = floatval($current_hour_values[$initial_token]);
	    // 6 is number of decimal points.
            $val = ($val == 0) ? "0" : number_format($val, 6, '.', '');
            $total_value += $val;
        }

        $objPHPExcel->getActiveSheet()->setCellValue($current_column . $row , $total_value);
    }

    // Complex values
    foreach ($complex_relation_map as $row => $variables_array)
    {
        $total_value = 0;
        foreach ($variables_array as $variable_name => $initial_tokens)
        {
            $temp_value = 1;
            foreach ($initial_tokens as $initial_token)
            {
                $val = floatval($current_hour_values[$initial_token]);
                $temp_value *= $val;
            }

            $total_value += $temp_value;
        }

        $objPHPExcel->getActiveSheet()->setCellValue($current_column . $row , $total_value);
    }

    // Evaluated values by equations
    $objPHPExcel = EvaluateEquations($objPHPExcel, $current_column, $building_type);


    return $objPHPExcel;
}

// Internal function to replace any zero value to 0.000001 value
function CleanZeros(\PHPExcel $objPHPExcel, $current_column)
{
    $highest_row = $objPHPExcel->getActiveSheet()->getHighestRow();
    for($i = 2; $i <= $highest_row ; $i++)
    {
        $current_value = $objPHPExcel->getActiveSheet()->getCell($current_column . $i)->getValue();
        if(floatval($current_value) == 0 )
        {
            $objPHPExcel->getActiveSheet()->setCellValue($current_column . $i, "0.000001");
        }
    }
    return $objPHPExcel;
}

// Iniernal function Dump values to HVAC files
function DumpToHVAC($current_hour_values, $hvac_complex_relation_map, $objPHPExcel, $objPHPExcel_hvac, $current_column, $building_type)
{
    // Complex values
    foreach ($hvac_complex_relation_map as $row => $variables_array)
    {
        $total_value = 0;
        foreach ($variables_array as $variable_name => $initial_tokens)
        {
            $temp_value = 1;
            foreach ($initial_tokens as $initial_token)
            {
                $val = floatval($current_hour_values[$initial_token]);
                $temp_value *= $val;
            }

            $total_value += $temp_value;
        }

        $objPHPExcel_hvac->getActiveSheet()->setCellValue($current_column . $row , $total_value);
        $column_array_hvac[$row] = $total_value;
    }

    $column_array = array();
    $column_array[0] = 0;
    $column_array[1] = 0;

    $highest_row = $objPHPExcel->getActiveSheet()->getHighestRow();
    for($row = 2 ; $row<= $highest_row; $row++)
    {
        $cell_val = $objPHPExcel->getActiveSheet()->getCell($current_column . $row)->getValue();
        $column_array[$row] = (empty($cell_val)) ? 0 : $cell_val;
    }

    $column_array_hvac = array();
    $column_array_hvac[0] = 0;
    $column_array_hvac[1] = 0;
    $highest_row_hvac = $objPHPExcel_hvac->getActiveSheet()->getHighestRow();
    for($row = 2 ; $row<= $highest_row_hvac; $row++)
    {
        $cell_val = $objPHPExcel_hvac->getActiveSheet()->getCell($current_column . $row)->getValue();
        $column_array_hvac[$row] = (empty($cell_val)) ? 0 : $cell_val;
    }

    if($building_type == Enumerations::SMALL_OFFICE)
    {
        // Energy flows on the building-level are used to dump data to HVAC csv files
		// Energy consumption by heating coils = BLDG-level (energy consumption by Heating coils)
		$column_array_hvac[10] = $column_array[10];
		// Energy consumption by fans for heating = BLDG-level (Fans electric energy consumption for heating)
        $column_array_hvac[11] = $column_array[12];
		// Energy consumption by humidifier = BLDG-level (energy consumption by humidifier)
        $column_array_hvac[12] = $column_array[13];
		// Heat added by heating coils to PSZ = Energy consumption by heating coils
        $column_array_hvac[13] = $column_array_hvac[10];
		// Heat added by the PSZ fans = Fans electric energy consumption for heating
        $column_array_hvac[14] = $column_array_hvac[11];
		// Heat added by humidifier = energy consumption by humidifier
        $column_array_hvac[15] = $column_array_hvac[12];
		
        if($column_array[10] + $column_array[11] != 0)
        {
            // $column_array (data obtained from building-level CSV): Row 2 (BLDG-level: Natural gas consumption), Row 3 (Boiler heat loss due to inefficiency), Row 4 (Boiler energy transfer), Row 5 (Electric energy consumption by boiler), Row 6 (Electric energy consumption by HW pump), Row 7 (HW frictional losses), Row 10 (BLDG-level: energy consumption by heating coil), Row 11 (energy consumption by radiant panel)
			// $column_array_hvac: Row 2 (Natural gas consumption), Row 3 (Boiler heat loss due to inefficiency), Row 4 (Boiler energy transfer), Row 5 (Electric energy consumption by boiler), Row 6 (Electric energy consumption by HW pump), Row 7 (HW frictional losses)
			$column_array_hvac[2] = $column_array[2] * ($column_array[10] / ($column_array[10] + $column_array[11]));
            $column_array_hvac[3] = $column_array[3] * ($column_array[10] / ($column_array[10] + $column_array[11]));
            $column_array_hvac[4] = $column_array[4] * ($column_array[10] / ($column_array[10] + $column_array[11]));
            $column_array_hvac[5] = $column_array[5] * ($column_array[10] / ($column_array[10] + $column_array[11]));
            $column_array_hvac[6] = $column_array[6] * ($column_array[10] / ($column_array[10] + $column_array[11]));
            $column_array_hvac[7] = $column_array[7] * ($column_array[10] / ($column_array[10] + $column_array[11]));
        }
        else
        {
            $column_array_hvac[2] = 0;
            $column_array_hvac[3] = 0;
            $column_array_hvac[4] = 0;
            $column_array_hvac[5] = 0;
            $column_array_hvac[6] = 0;
            $column_array_hvac[7] = 0;
        }

		// $column_array (data obtained from building-level CSV): Row 15 (heat gain from outdoor), Row 55 (energy exctracted by cooling coils), Row 56 (Fans electric energy consumption for cooling), Row 57 (outdoor air loss), Row 58 (free cooling)
		// $column_array_hvac: Row 6 (Electric energy consumption by HW pump), Row 7 (HW frictional losses), Row 8 (HW pump heat loss to surroundings), Row 9 (HW frictional losses), Row 20 (heat gain from outdoor), Row 21 (Outdoor air loss), Row 24 (energy extracted by cooling coils), Row 25 (Free cooling), Row 26 (Fans electric energy consumption for cooling)
        $column_array_hvac[8] = $column_array_hvac[6] - $column_array_hvac[7];
        $column_array_hvac[9] = $column_array_hvac[7];
        $column_array_hvac[20] = $column_array[15];
        $column_array_hvac[21] = $column_array[57];
        $column_array_hvac[25] = $column_array[58];
        $column_array_hvac[26] = $column_array[56];
        $column_array_hvac[24] = $column_array[55] - $column_array_hvac[26];
        if($column_array_hvac[24] != 0)
        {
            // $column_array (data obtained from building-level CSV): Row 54 (Exhaust air)
			// $column_array_hvac: Row 16 (Return air from plenum), Row 17 (Exhaust air), Row 24 (energy extracted by cooling coils), Row 27 (Fans electric energy consumption for cooling), Row 29 (Energy extracted by cooling coils)
			$column_array_hvac[16] = 0;
        }
        $column_array_hvac[29] = $column_array_hvac[24] + $column_array_hvac[27];
        $column_array_hvac[17] = ($column_array_hvac[24] == 0) ? $column_array[54] : 0;

        if($column_array_hvac[17] > $column_array_hvac[16])
        {
            $column_array_hvac[16] = $column_array_hvac[17];
        }

		// $column_array (data obtained from building-level CSV): Row 60 (Electric energy consumption by DX unit)
		// $column_array_hvac: Row 13 (Energy consumption by heating coils), Row 14 (Fans electric energy consumption for heating), Row 15 (Heat added by humidifier), Row 16 (Return air), Row 17 (Exhaust air), Row 18 (Recirculated air), Row 19 (Heat added by recirculated air to the supply air), Row 20 (heat gain from outdoor), Row 21 (Outdoor air loss), Row 22 (Heat supplied to zones), Row 23 (Heat gain from building), Row 26 (Fans electric energy consumption for cooling), Row 27 (Fans electric energy consumption for cooling), Row 28 (Electric energy consumption by DX unit)
        $column_array_hvac[18] = $column_array_hvac[16] - $column_array_hvac[17];
        $column_array_hvac[19] = $column_array_hvac[18];
        $temp_val = $column_array_hvac[13]+$column_array_hvac[14] + $column_array_hvac[15] + $column_array_hvac[19] + $column_array_hvac[20] - $column_array_hvac[21];
        $column_array_hvac[22] =  ($temp_val > $column_array_hvac[16]) ? $temp_val - $column_array_hvac[16] : 0;
        $column_array_hvac[23] = ($temp_val < $column_array_hvac[16]) ? $column_array_hvac[16] - $temp_val : 0;
        $column_array_hvac[27] = $column_array_hvac[26];
        $column_array_hvac[28] = $column_array[60];
		
		// $column_array (data obtained from building-level CSV): Row 10 (Heating coil energy consumption), Row 13 (energy consumption by humidifier), Row 54 (Exhaust air), Row 55 (Energy extracted by cooling coil), Row 58 (Free cooling)
        // $column_array_hvac: Row 13 (Energy consumption by heating coils), Row 14 (Fans electric energy consumption), Row 15 (Heat added by humidifier), Row 16 (Return air), Row 17 (Exhaust air), Row 18 (Recirculated air), Row 19 (Heat added by recirculated air to the supply air), Row 20 (heat gain from outdoor), Row 21 (Outdoor air loss), Row 22 (Heat supplied to zones), Row 23 (Heat gain from building), Row 24 (energy extracted by cooling coils), Row 25 (free cooling), Row 26 (Fans electric energy consumption), Row 30 (Heat supplied to zones), Row 31 (return air to plenum), Row 32 (Exhaust air), Row 33 (Energy added by heating coils to cooling coils), Row 34 (Fans electric energy consumption), Row 35 (heat added by humidifier to cooling coils)
		if(($column_array[10] > $column_array[55]))
        {
			$column_array_hvac[32] = 0;
            $column_array_hvac[30] = $column_array_hvac[13] +$column_array_hvac[14] + $column_array_hvac[15] + $column_array_hvac[19] + $column_array_hvac[20] - $column_array_hvac[21] - $column_array_hvac[24] - $column_array_hvac[25];
            if($column_array_hvac[30] < $column_array_hvac[16])
            {
                $column_array_hvac[23] = $column_array_hvac[16] - $column_array_hvac[30];
            }
            else if ($column_array_hvac[30] > $column_array_hvac[16])
            {
                $column_array_hvac[22] = $column_array_hvac[30] - $column_array_hvac[16];
            }
            else
            {
                $column_array_hvac[22] = 0;
                $column_array_hvac[23] = 0;
            }
        }
        else if(($column_array[10] < $column_array[55]) )
        {
            $column_array_hvac[34] = $column_array_hvac[14];
            $column_array_hvac[13] =0;
            $column_array_hvac[14] =0;
            $column_array_hvac[15] =0;
            $column_array_hvac[16] =0;
            $column_array_hvac[17] =0;
            $column_array_hvac[18] =0;
            $column_array_hvac[19] =0;
            $column_array_hvac[20] =0;
            $column_array_hvac[22] =0;
            $column_array_hvac[23] =0;
            $column_array_hvac[30] =0;
            $column_array_hvac[31] =0;
            $column_array_hvac[21] = 0;
            $column_array_hvac[25] = $column_array[58];
            $column_array_hvac[32] = $column_array[54];
            $column_array_hvac[33] = $column_array[10];
            $column_array_hvac[35] = $column_array[13];
            $column_array_hvac[24] = $column_array[55] - $column_array_hvac[26] - $column_array_hvac[33] - $column_array_hvac[34] - $column_array_hvac[35] ;
        }

        $column_array_hvac[31] = $column_array_hvac[30];
        $column_array_hvac[29] = $column_array_hvac[24] + $column_array_hvac[27] + $column_array_hvac[33] + $column_array_hvac[34] + $column_array_hvac[35];
    }
    else (//i.e. Large and medium office)
    {
        // Energy flows on the building-level are used to dump data to HVAC csv files
		// Energy consumption by heating coils = BLDG-level (energy consumption by Heating coils)
		$column_array_hvac[10] = $column_array[10];
		// Energy consumption by fans for heating = BLDG-level (Fans electric energy consumption for heating)
        $column_array_hvac[11] = $column_array[13];
		// Energy consumption by humidifier = BLDG-level (energy consumption by humidifier)
        $column_array_hvac[12] = $column_array[14];
		// Heat added by heating coils to AHU = Energy consumption by heating coils
        $column_array_hvac[13] = $column_array_hvac[10];
		// Heat added by the AHU fans = Fans electric energy consumption for heating
        $column_array_hvac[14] = $column_array_hvac[11];
		// Heat added by humidifier = energy consumption by humidifier
        $column_array_hvac[15] = $column_array_hvac[12];
		
        if($column_array[10] + $column_array[11] + $column_array[12] != 0)
        {
            // $column_array (data obtained from building-level CSV): Row 2 (BLDG-level: Natural gas consumption), Row 3 (Boiler heat loss due to inefficiency), Row 4 (Boiler energy transfer), Row 5 (Electric energy consumption by boiler), Row 6 (Electric energy consumption by HW pump), Row 7 (HW frictional losses), Row 10 (BLDG-level: energy consumption by heating coil), Row 11 (energy consumption by VAV-Reheat, Row 12 (energy consumption by radiant panel)
			// $column_array_hvac: Row 2 (Natural gas consumption), Row 3 (Boiler heat loss due to inefficiency), Row 4 (Boiler energy transfer), Row 5 (Electric energy consumption by boiler), Row 6 (Electric energy consumption by HW pump), Row 7 (HW frictional losses)
			$column_array_hvac[2] = $column_array[2] * ($column_array[10] / ($column_array[10] + $column_array[11] + $column_array[12]));
            $column_array_hvac[3] = $column_array[3] * ($column_array[10] / ($column_array[10] + $column_array[11] + $column_array[12]));
            $column_array_hvac[4] = $column_array[4] * ($column_array[10] / ($column_array[10] + $column_array[11] + $column_array[12]));
            $column_array_hvac[5] = $column_array[5] * ($column_array[10] / ($column_array[10] + $column_array[11] + $column_array[12]));
            $column_array_hvac[6] = $column_array[6] * ($column_array[10] / ($column_array[10] + $column_array[11] + $column_array[12]));
            $column_array_hvac[7] = $column_array[7] * ($column_array[10] / ($column_array[10] + $column_array[11] + $column_array[12]));
        }
        else
        {
            $column_array_hvac[2] = 0;
            $column_array_hvac[3] = 0;
            $column_array_hvac[4] = 0;
            $column_array_hvac[5] = 0;
            $column_array_hvac[6] = 0;
            $column_array_hvac[7] = 0;
        }
        // $column_array (data obtained from building-level CSV): Row 16 (Heat gain from outdoor), Row 56 (energy exctracted by cooling coils), Row 57 (Fans electric energy consumption for cooling), Row 58 (outdoor air loss), Row 59 (free cooling)
		// $column_array_hvac: Row 6 (Electric energy consumption by HW pump), Row 7 (HW frictional losses), Row 8 (HW pump heat loss to surroundings), Row 9 (HW frictional losses), Row 20 (heat gain from outdoor), Row 21 (Outdoor air loss), Row 24 (energy extracted by cooling coils), Row 25 (Free cooling), Row 26 (Fans electric energy consumption for cooling), Row 27 (Fans electric energy consumption for cooling)
		$column_array_hvac[8] = $column_array_hvac[6] - $column_array_hvac[7];
        $column_array_hvac[9] = $column_array_hvac[7];
        $column_array_hvac[20] = $column_array[16];
        $column_array_hvac[21] = $column_array[58];
        $column_array_hvac[25] = $column_array[59];
        $column_array_hvac[26] = $column_array[57];
        $column_array_hvac[27] = $column_array_hvac[26];
        $column_array_hvac[24] = $column_array[56] - $column_array_hvac[26];
        if($column_array_hvac[24] < 0)
        {
            // $column_array (data obtained from building-level CSV): Row 55 (Exhaust air)
			// $column_array_hvac: Row 16 (Return air from plenum), Row 17 (Exhaust air), Row 24 (energy extracted by cooling coils)
			$column_array_hvac[24] = 0;
        }
        $column_array_hvac[17] = ($column_array_hvac[24] == 0) ? $column_array[55] : 0;
        if($column_array_hvac[17] > $column_array_hvac[16])
        {
            $column_array_hvac[16] = $column_array_hvac[17];
        }
        // $column_array (data obtained from building-level CSV): Row 10 (Heating coil energy consumption), Row 56 (energy extracted by cooling coils), Row Row 61 (CDW pump electric energy), Row 62 (CDW pump frictional losses), Row 63 (CHW pump electric energy), Row 64 (CHW pump frictional losses), Row 65 (CDW pump heat loss to surroundings), Row 66 (CDW pump heat loss to surroundings), Row 71 (Chiller electric energy consumption), Row 72 (Cooling tower fans electric energy consumption)
		// $column_array_hvac: Row 13 (Heating coil enegry consumption), Row 14 (Heat added by AHU fans), Row 15 (Heat added by humidifier), Row 16 (Return airto AHU), Row 18 (recirculated air), Row 19 (heat added by recirculated air), Row 20 (heat gain from outdoor), Row 21 (Outdoor air loss), Row 22 (heat supplied to zones), Row 23 (heat gain from building), Row 24 (energy extracted by cooling coils), Row 25 (free cooling), Row 28 (CDW pump electric energy), Row 29 (CDW pump fricitional losses), Row 30 (CHW pump electric energy), Row 31 (CHW pump frictional losses), Row 32 (CDW pump heat loss to surroundings), Row 33 (CHW heat loss to surroundings), Row 34 (CDW pump frictional losses to cooling towers), Row 35 (CHW pump frictional losses to chilled water loop), Row 37 (energy extracted by chilled water loop to chiller), Row 38 (chiller electric energy consumption), Row 39 (Cooling tower fans electric energy consumption), Row 40 (Cooling tower fans electric energy), Row 41 (energy exctracted by chillers to cooling towers), Row 42 (Heat supplied to zones from AHU), Row 43 (Return air to plenum), Row 44 (Exhaust air), Row 45 (Energy added by heating coil to be extracted by cooling coils), Row 46 (Heat added by fans to be extracted by cooling coils), Row 47 (heat added by humidifier to be extracted by cooling coils)
		$column_array_hvac[28] = $column_array[61];
        $column_array_hvac[29] = $column_array[62];
        $column_array_hvac[30] = $column_array[63];
        $column_array_hvac[31] = $column_array[64];
        $column_array_hvac[32] = $column_array[65];
        $column_array_hvac[33] = $column_array[66];
        $column_array_hvac[34] = $column_array_hvac[29];
        $column_array_hvac[35] = $column_array_hvac[31];
        $column_array_hvac[38] = $column_array[71];
        $column_array_hvac[39] = $column_array[72];
        $column_array_hvac[40] = $column_array_hvac[39];
        $column_array_hvac[18] = $column_array_hvac[16] - $column_array_hvac[17];
        $column_array_hvac[19] = $column_array_hvac[18];
        $column_array_hvac[37] = $column_array_hvac[35] + $column_array_hvac[36];
        $column_array_hvac[41] = $column_array_hvac[37] + $column_array_hvac[38];
        $temp_val = $column_array_hvac[13]+$column_array_hvac[14] + $column_array_hvac[15] + $column_array_hvac[19] + $column_array_hvac[20] - $column_array_hvac[21];
        $column_array_hvac[22] =  ($temp_val > $column_array_hvac[16]) ? $temp_val - $column_array_hvac[16] : 0;
        $column_array_hvac[23] = ($temp_val < $column_array_hvac[16]) ? $column_array_hvac[16] - $temp_val : 0;

        if(($column_array[10] > $column_array[56]))
        {
            $column_array_hvac[44] = 0;
            $column_array_hvac[42] = $column_array_hvac[13] +$column_array_hvac[14] + $column_array_hvac[15] + $column_array_hvac[19] + $column_array_hvac[20] - $column_array_hvac[21] - $column_array_hvac[24] - $column_array_hvac[25];
            if($column_array_hvac[42] < $column_array_hvac[16])
            {
                $column_array_hvac[23] = $column_array_hvac[16] - $column_array_hvac[42];
            }
            else if ($column_array_hvac[42] > $column_array_hvac[16])
            {
                $column_array_hvac[22] = $column_array_hvac[42] - $column_array_hvac[16];
            }
            else
            {
                $column_array_hvac[22] = 0;
                $column_array_hvac[23] = 0;
            }
        }
        else if(($column_array[10] < $column_array[56]) )
        {
            $column_array_hvac[46] = $column_array_hvac[14];
            $column_array_hvac[13] =0;
            $column_array_hvac[14] =0;
            $column_array_hvac[15] =0;
            $column_array_hvac[16] =0;
            $column_array_hvac[17] =0;
            $column_array_hvac[18] =0;
            $column_array_hvac[19] =0;
            $column_array_hvac[20] =0;
            $column_array_hvac[22] =0;
            $column_array_hvac[23] =0;
            $column_array_hvac[42] =0;
            $column_array_hvac[43] =0;
            $column_array_hvac[21] = 0;
            $column_array_hvac[25] = $column_array[59];
            $column_array_hvac[44] = $column_array[55];
            $column_array_hvac[45] = $column_array[10];
            $column_array_hvac[47] = $column_array[14];
            $column_array_hvac[24] = $column_array[56] - $column_array_hvac[27] - $column_array_hvac[45] - $column_array_hvac[46] - $column_array_hvac[47] ;
        }

        $column_array_hvac[43] = $column_array_hvac[42];
        $column_array_hvac[36] = $column_array_hvac[24] + $column_array_hvac[27] + $column_array_hvac[45] + $column_array_hvac[46] + $column_array_hvac[47];
        $column_array_hvac[37] = $column_array_hvac[35] + $column_array_hvac[36];
        $column_array_hvac[41] = $column_array_hvac[37] + $column_array_hvac[38];
    }


    // Row 11 (Energy consumption by AHU fans for heating), Row 26 (Energy consumption by AHU fans for cooling)
	if($column_array_hvac[11] == 0 && $column_array_hvac[26] == 0)
    {
        for($row = 2 ; $row <= $highest_row_hvac; $row++)
        {
            $objPHPExcel_hvac->getActiveSheet()->setCellValue($current_column . $row, 0);
        }
    }
    else
    {
        for($row = 2 ; $row <= $highest_row_hvac; $row++)
        {
            $objPHPExcel_hvac->getActiveSheet()->setCellValue($current_column . $row, $column_array_hvac[$row]);
        }
    }

    return $objPHPExcel_hvac;
}

//Calculate HVAC energy flows for (small, medium, and large) days and months values
function CalculateHvacEnergyFlows($objPHPExcel_hvac, $current_column, $building_type, $objPHPExcel)
{
    $column_array_hvac = array();
    $column_array_hvac[0] = 0;
    $column_array_hvac[1] = 0;

    $highest_row = $objPHPExcel_hvac->getActiveSheet()->getHighestRow();
    for($row = 2 ; $row<= $highest_row; $row++)
    {
        $column_array_hvac[$row] = $objPHPExcel_hvac->getActiveSheet()->getCell($current_column . $row)->getValue();
    }

    if($building_type == Enumerations::SMALL_OFFICE)
    {
        // $column_array (data obtained from building-level CSV): Row 10 (Heating coil energy consumption), Row 13 (energy consumption by humidifier), Row 54 (Exhaust air), Row 55 (Energy extracted by cooling coil), Row 58 (Free cooling)
        // $column_array_hvac: Row 13 (Energy consumption by heating coils), Row 14 (Fans electric energy consumption), Row 15 (Heat added by humidifier), Row 16 (Return air), Row 17 (Exhaust air), Row 18 (Recirculated air), Row 19 (Heat added by recirculated air to the supply air), Row 20 (heat gain from outdoor), Row 21 (Outdoor air loss), Row 22 (Heat supplied to zones), Row 23 (Heat gain from building), Row 24 (energy extracted by cooling coils), Row 25 (free cooling), Row 26 (Fans electric energy consumption), Row 30 (Heat supplied to zones), Row 31 (return air to plenum), Row 32 (Exhaust air), Row 33 (Energy added by heating coils to cooling coils), Row 34 (Fans electric energy consumption), Row 35 (heat added by humidifier to cooling coils)
		if(($column_array_hvac[10] > $column_array_hvac[24]))
        {
            $column_array_hvac[32] =0;
            $column_array_hvac[30] = $column_array_hvac[13] +$column_array_hvac[14] + $column_array_hvac[15] + $column_array_hvac[19] + $column_array_hvac[20] - $column_array_hvac[21] - $column_array_hvac[24] - $column_array_hvac[25];
            if($column_array_hvac[30] < $column_array_hvac[16])
            {
                $column_array_hvac[23] = $column_array_hvac[16] - $column_array_hvac[30];
            }
            else if ($column_array_hvac[30] > $column_array_hvac[16])
            {
                $column_array_hvac[22] = $column_array_hvac[30] - $column_array_hvac[16];
            }
            else
            {
                $column_array_hvac[22] = 0;
                $column_array_hvac[23] = 0;
            }
        }
        else if(($column_array_hvac[10] < $column_array_hvac[24]) )
        {
            $column_array_hvac[34] = $column_array_hvac[14];
            $column_array_hvac[13] =0;
            $column_array_hvac[14] =0;
            $column_array_hvac[15] =0;
            $column_array_hvac[16] =0;
            $column_array_hvac[17] =0;
            $column_array_hvac[18] =0;
            $column_array_hvac[19] =0;
            $column_array_hvac[20] =0;
            $column_array_hvac[22] =0;
            $column_array_hvac[23] =0;
            $column_array_hvac[30] =0;
            $column_array_hvac[31] =0;
            $column_array_hvac[21] = 0;
            $column_array_hvac[33] = $column_array_hvac[10];
            $column_array_hvac[35] = $column_array_hvac[12];
            $row55 = $objPHPExcel->getActiveSheet()->getCell($current_column . "55")->getValue();
            $column_array_hvac[24] = $row55 - $column_array_hvac[26] - $column_array_hvac[33] - $column_array_hvac[34] - $column_array_hvac[35] ;
        }

        $column_array_hvac[31] = $column_array_hvac[30];
        $column_array_hvac[29] = $column_array_hvac[24] + $column_array_hvac[27] + $column_array_hvac[33] + $column_array_hvac[34] + $column_array_hvac[35];
    }
    else (// i.e. Large and medium office)
    {
        // $column_array (data obtained from building-level CSV): Row 10 (Heating coil energy consumption), Row 56 (energy extracted by cooling coils), Row Row 61 (CDW pump electric energy), Row 62 (CDW pump frictional losses), Row 63 (CHW pump electric energy), Row 64 (CHW pump frictional losses), Row 65 (CDW pump heat loss to surroundings), Row 66 (CDW pump heat loss to surroundings), Row 71 (Chiller electric energy consumption), Row 72 (Cooling tower fans electric energy consumption)
		// $column_array_hvac: Row 13 (Heating coil enegry consumption), Row 14 (Heat added by AHU fans), Row 15 (Heat added by humidifier), Row 16 (Return airto AHU), Row 18 (recirculated air), Row 19 (heat added by recirculated air), Row 20 (heat gain from outdoor), Row 21 (Outdoor air loss), Row 22 (heat supplied to zones), Row 23 (heat gain from building), Row 24 (energy extracted by cooling coils), Row 25 (free cooling), Row 28 (CDW pump electric energy), Row 29 (CDW pump fricitional losses), Row 30 (CHW pump electric energy), Row 31 (CHW pump frictional losses), Row 32 (CDW pump heat loss to surroundings), Row 33 (CHW heat loss to surroundings), Row 34 (CDW pump frictional losses to cooling towers), Row 35 (CHW pump frictional losses to chilled water loop), Row 37 (energy extracted by chilled water loop to chiller), Row 38 (chiller electric energy consumption), Row 39 (Cooling tower fans electric energy consumption), Row 40 (Cooling tower fans electric energy), Row 41 (energy exctracted by chillers to cooling towers), Row 42 (Heat supplied to zones from AHU), Row 43 (Return air to plenum), Row 44 (Exhaust air), Row 45 (Energy added by heating coil to be extracted by cooling coils), Row 46 (Heat added by fans to be extracted by cooling coils), Row 47 (heat added by humidifier to be extracted by cooling coils)
		if($column_array_hvac[10] > $column_array_hvac[24])
        {
            $column_array_hvac[44] = 0;
            $column_array_hvac[42] = $column_array_hvac[13] +$column_array_hvac[14] + $column_array_hvac[15] + $column_array_hvac[19] + $column_array_hvac[20] - $column_array_hvac[21] - $column_array_hvac[24] - $column_array_hvac[25];
            if($column_array_hvac[42] < $column_array_hvac[16])
            {
                $column_array_hvac[23] = $column_array_hvac[16] - $column_array_hvac[42];
            }
            else if ($column_array_hvac[42] > $column_array_hvac[16])
            {
                $column_array_hvac[22] = $column_array_hvac[42] - $column_array_hvac[16];
            }
            else
            {
                $column_array_hvac[22] = 0;
                $column_array_hvac[23] = 0;
            }
        }
        else if(($column_array_hvac[10] < $column_array_hvac[24]) )
        {
            $column_array_hvac[46] = $column_array_hvac[14];
            $column_array_hvac[13] =0;
            $column_array_hvac[14] =0;
            $column_array_hvac[15] =0;
            $column_array_hvac[16] =0;
            $column_array_hvac[17] =0;
            $column_array_hvac[18] =0;
            $column_array_hvac[19] =0;
            $column_array_hvac[20] =0;
            $column_array_hvac[22] =0;
            $column_array_hvac[23] = 0;
            $column_array_hvac[42] =0;
            $column_array_hvac[43] =0;
            $column_array_hvac[21] = 0;
            $column_array_hvac[45] = $column_array_hvac[10];
            $column_array_hvac[47] = $column_array_hvac[12];
            $row56 = $objPHPExcel->getActiveSheet()->getCell($current_column . "56")->getValue();
            $column_array_hvac[24] = $row56 - $column_array_hvac[27] - $column_array_hvac[45] - $column_array_hvac[46] - $column_array_hvac[47] ;
        }

        $column_array_hvac[43] = $column_array_hvac[42];
        $column_array_hvac[36] = $column_array_hvac[24] + $column_array_hvac[27] + $column_array_hvac[45] + $column_array_hvac[46] + $column_array_hvac[47];
        $column_array_hvac[37] = $column_array_hvac[35] + $column_array_hvac[36];
        $column_array_hvac[41] = $column_array_hvac[37] + $column_array_hvac[38];
    }

    for($row = 2 ; $row <= $highest_row; $row++)
    {
        $objPHPExcel_hvac->getActiveSheet()->setCellValue($current_column . $row, $column_array_hvac[$row]);
    }

    return $objPHPExcel_hvac;
}

$app->run();
?>
