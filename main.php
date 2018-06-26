<?php
require 'init.php';
require_once 'functions.php';

$VAT=$_GET['afm'];
$results_limit = isset($_GET['size']) ? $_GET['size'] : 25;
$page_no = isset($_GET['page']) ? $_GET['page'] : 0;
$first_call_flag = isset($_GET['button_call']) ? $_GET['button_call'] : 1;
$debug=0;
$AFM=NULL;
$IS_VALID=NULL;
$exit_code=0;

$decisions=array();
$metadt= array();

$metadt['page_no']=$page_no;
$metadt['results_limit']=$results_limit;
$metadt['vat'] = $VAT;

$total_results = 0;

list($exit_code,$IS_VALID)=afm_api($VAT);

if($debug==1)
{   
echo "exit_code from afm api is", $exit_code ,"<br/>\n";
echo "IS_VALID from afm api is", $IS_VALID ,"<br/>\n";
}

if ($exit_code==0)
{
	$metadt['afm_source']='GSIS';
	$exit_code2=update_afm($VAT,$IS_VALID);
   
	if($exit_code2!=0){
		throw new Exception('Update Afm failed');  
	}
}
else
{  
	#Select from db
	list($exit_code,$IS_VALID_temp)=get_afm($VAT);
	$metadt['afm_source']='DB';
	if($debug==1)
	{ 
		echo "exit_code from afm DB is", $exit_code ,"<br/>\n";
		echo "IS_VALID_temp from db api is", $IS_VALID_temp ,"<br/>\n";
	}
	if($exit_code!=0)
	{
		throw new Exception('AFM fetch failed ');
	}
	$IS_VALID=$IS_VALID_temp;
}

if($IS_VALID==1)
{
	$metadt['valid_afm'] = 1;
	$exit_code=0;
	$response=NULL;
		try
	{
		#insert afm api call 
		list($exit_code,$response) = getRequest("https://diavgeia.gov.gr/luminapi/opendata/search.json?term=:" . $VAT . "&size=" . $results_limit . "&page=" . $page_no);
	}
	catch(Exception $e)
	{
		echo $e;
		$exit_code=1;
	}
	if($debug==1)
	{ 
		echo "exit_code from diaygeia api is", $exit_code ,"<br/>\n";

		echo "response from diaygeia api is not empty?", $response==NULL ,"<br/>\n";
	}

	if($response!=NULL && $exit_code==0)
	{
		$metadt['decision_source']='DIAYGEIA';
		list($decisions,$total_results)=parse_json($response,$VAT);
			
		$exit_code=update_diavgeia($decisions);
		if($debug==1)
		{ 
			echo "exit_code from update_diaygeia DB is", $exit_code ,"<br/>\n";
		}   
	}
	else
	{  
		list($exit_code,$decisions,$total_results)=get_diavgeia($VAT,$results_limit,$page_no);
		$metadt['decision_source']='DB';
		if($debug==1)
		{ 
			echo "exit_code from select diaygeia DB is", $exit_code ,"<br/>\n";
		}
	}  
}
else 
{
	#echo 'Not a valid afm';
	$metadt['valid_afm'] = 0;
}

#pane ta results mprosta
$var1='json_decision';
foreach ($decisions as $dec)
{
	unset($dec->$var1);
}

$metadt['total_results']=$total_results;
$json['decisions']=$decisions;
$json['metadata']=$metadt;
$final_json=json_encode($json);
echo $final_json;
?>