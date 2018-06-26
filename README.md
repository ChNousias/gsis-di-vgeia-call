## GSIS and Di@vgeia call

A simple web application for advanced decision search in the repository of di@vgeia.

This repository contains a simple web application for making advanced decision search in the repository of di@vgeia. 

### Backend - SOAP call, API query and DB storing
The web app consists of two calls:

1. A SOAP call at the Web Service of GSIS of the Greek Ministry of Economics

During the first call it checks if a given VAT number (9-digit unique) is valid and involved in business activity.

2. A query at the API of the Di@vgeia service for getting all official decisions that are related to the VAT in question.

At the meantime the results of both calls are stored in two tables of a local SQL database for future reference. Backend consists of:  
.  
|-- **main.php**  
|-- **init.php**  
|-- **functions.php**  
|-- **objects.php**  
|-- **cloud_app.sql**  

Main.php keeps control of the data_flow as well as the functionality of the WS and the API.
In case of a connection problem, it points to the local Database.
Upon calling **main.php** a JSON file is returned that contains the results of the search, that is an array of **decisions** as well as
**metadata** from the search process.

### Frontend - Simple HTML with jQuery

.  
|-- **index.html**  
|  
|-- css  
|    |-- **style.css**    
|  
|-- js  
     |-- **submit_jquery.js**  
     
### Additional Notes

For communicating with the WS of GSIS personal credentials are needed (Username, Password). Those were removed from the uploaded files. 
