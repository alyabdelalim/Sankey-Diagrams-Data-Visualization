# Sankey-Diagrams-Data-Visualization
Representation of Reference Building Energy Model Performance Using Sankey Diagrams: Research and Implementation

# Conceptual Framework:

The purpose of this framework is to develop a user-graphical interface web page using Hypertext Preprocessor (PHP), JavaScript, and Hypertext Markup Language (HTML) codes to automate the process of creating Sankey diagrams from energy simulation (EnergyPlus) output files. 

This framework is tested on large, medium, and small office reference building models that complies with the National Energy Code of Canada for Buildings (NECB). 

The user at first inserts the IDF file (without output variables and meters) in the web page and selects the building type (from a drop-down menu) that corresponds to the same building type (i.e. large, medium, and small) in the IDF file. The output variables and meters will be appended to the EnergyPlus IDF file using PHP code. The user then should run the generated IDF file in EnergyPlus V8.6 on local machine and import the simulation output files in the web page. 

EnergyPlus simulation output ESO files were selected for the purpose of this study as it can contain all output variables and meters. The PHP code then analyzes the ESO file by matching certain strings and patterns in the IDF file. The PHP code then generates Comma Separated Value (CSV) files that are read by JavaScript code to generate Sankey diagrams. The user can control the displayed diagrams including number of diagrams, colors, and fonts. The user also can select spatial resolution from a drop-down menu (i.e. building-level and HVAC system) and temporal resolution (i.e. monthly, daily, and hourly) using a slide bar. 


# Deployment of the System:

This section states the environment configurations and the system requirements for deployment.  
1)	Installations
	The system is implemented using PHP language of version 5.5. Therefore, the system requires at least version 5.5 of PHP. Also, it requires a PHP library called PHPExcel which is responsible of reading and writing CSV sheets. 

2)	Browsers
The system should run on modern browsers (such as Google Chrome, Firefox, Safari, Opera, Microsoft Edge, and Internet Explorer). The system is working properly on Google Chrome and Opera browsers. However, some minor issues were observed when rendering Sankey diagrams on Safari, Firefox, Microsoft Edge, and Internet Explorer.

3)	Configurations
	The system has many challenges including: 1) uploading large files (up to 200 MB), 2) the execution time can exceed 15 minutes for a single request, and 3) a lot of data are used at runtime. Consequently, some PHP configurations must be set as follows to handle these issues.
1.	post_max_size = 250 MByte
2.	file_uploads = On
3.	upload_max_filesize = 220 MByte
4.	max_file_uploads = 1 (1 means 1 file per request)
5.	max_input_time = -1 (-1 means unlimited input time) 
6.	max_execution_time = -1 (-1 means unlimited execution time)
7.	memory_limit = -1 (-1 means unlimited memory)

4)	Files directory
The system consists of a hierarchy of folders that contain the files. Read and write permissions are required to modify files in these folders. However, the folder named “Reference” should only have read permission in order to prevent any modifications of the reference files. 

5)	Supporting multiple users access
The system is developed to handle one user at a time. However, in order to handle multiple users, authentication module should be imported into the system. Moreover, PHP, JavaScript, and HTML should be modified to adapt to these changes. The PHP code should be modified by adding logic to create diagrams per user. JavaScript codes should be modified to load diagrams per user. The HTML should include new pages for user login, user account page, and user preference page.
