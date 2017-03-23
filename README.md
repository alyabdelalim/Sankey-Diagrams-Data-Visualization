# Sankey-Diagrams-Data-Visualization
Representation of Reference Building Energy Model Performance Using Sankey Diagrams: Research and Implementation

# Conceptual Framework

The purpose of this framework is to develop a user-graphical interface web page using Hypertext Preprocessor (PHP), JavaScript, and Hypertext Markup Language (HTML) codes to automate the process of creating Sankey diagrams from energy simulation (EnergyPlus) output files. 

This framework is tested on large, medium, and small office reference building models that complies with the National Energy Code of Canada for Buildings (NECB). 

The user at first inserts the IDF file (without output variables and meters) in the web page and selects the building type (from a drop-down menu) that corresponds to the same building type (i.e. large, medium, and small) in the IDF file. The output variables and meters will be appended to the EnergyPlus IDF file using PHP code. The user then should run the generated IDF file in EnergyPlus V8.6 on local machine and import the simulation output files in the web page. 

EnergyPlus simulation output ESO files were selected for the purpose of this study as it can contain all output variables and meters. The PHP code then analyzes the ESO file by matching certain strings and patterns in the IDF file. The PHP code then generates Comma Separated Value (CSV) files that are read by JavaScript code to generate Sankey diagrams. The user can control the displayed diagrams including number of diagrams, colors, and fonts. The user also can select spatial resolution from a drop-down menu (i.e. building-level and HVAC system) and temporal resolution (i.e. monthly, daily, and hourly) using a slide bar. 

# Front-end Implementation

This section explains the front-end implementation of the user-graphical interface web page developed including Hypertext Markup Language (HTML) and JavaScript codes.

Hypertext Markup Language (HTML)

The purpose of this code is to create the required elements in the webpage. These elements are: 1) select number and name of diagrams, 2) upload IDF file, 3) select building type from a drop-down menu, 4) generate IDF file with the required list of simulation output variables and meters, 5) upload ESO file, 6) generate CSV files, 7) select spatial resolution from a drop-down menu, 8) select temporal resolution from a slide bar, 9) play/pause buttons to animate the Sankey diagrams, 10) day and time caption of the Sankey diagram displayed, 11) select color for each diagram, and 12) show/hide diagrams. 

JavaScript

The JavaScript codes developed in this study aimed to: 1) generate nodes and links between elements to create Sankey diagrams using D3 (Data-Driven Documents) JavaScript library, 2) create functions for the elements created in the HTML code (such as generate and download IDF files, upload ESO file, add/remove diagrams, selecting building type, generate CSV files, select spatial resolution, select temporal resolution using slide bar, animate the results, show/hide diagrams, and add caption of the displayed Sankey diagram), and 3) provide the controls for the elements created in the HTML code (such as load data from CSV files, construct Sankey diagram(s), changing color of diagrams, and transform nodes and links of Sankey diagrams). 

# Back-end Implementation

This section explains the back-end implementation of the user-graphical interface web page developed including Hypertext Preprocessor (PHP) code.

Hypertext Preprocessor (PHP)

The purpose of the PHP code is to perform operations on the files uploaded in the web page by the user (i.e. IDF and ESO files). The operations performed are: 1) generate IDF files with the required list of simulation output variables and meters, 2) analyze ESO files, 3) generate CSV files, and 4) checking the energy balance.


# Deployment of the System

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
