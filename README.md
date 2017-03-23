# Sankey-Diagrams-Data-Visualization
Representation of Reference Building Energy Model Performance Using Sankey Diagrams: Research and Implementation

Conceptual Framework:

The purpose of this framework is to develop a user-graphical interface web page using Hypertext Preprocessor (PHP), JavaScript, and Hypertext Markup Language (HTML) codes to automate the process of creating Sankey diagrams from energy simulation (EnergyPlus) output files. 

This framework is tested on large, medium, and small office reference building models that complies with the National Energy Code of Canada for Buildings (NECB). 

The user at first inserts the IDF file (without output variables and meters) in the web page and selects the building type (from a drop-down menu) that corresponds to the same building type (i.e. large, medium, and small) in the IDF file. The output variables and meters will be appended to the EnergyPlus IDF file using PHP code. The user then should run the generated IDF file in EnergyPlus V8.6 on local machine and import the simulation output files in the web page. 

EnergyPlus simulation output ESO files were selected for the purpose of this study as it can contain all output variables and meters. The PHP code then analyzes the ESO file by matching certain strings and patterns in the IDF file. The PHP code then generates Comma Separated Value (CSV) files that are read by JavaScript code to generate Sankey diagrams. The user can control the displayed diagrams including number of diagrams, colors, and fonts. The user also can select spatial resolution from a drop-down menu (i.e. building-level and HVAC system) and temporal resolution (i.e. monthly, daily, and hourly) using a slide bar. 
