<?php 

require_once 'objects.php';
require 'init.php';

function soapHeader($UserID, $Password){
	
	/* Function to create soap header to be included in WS CALL
	
	Takes as input required Username and Password

	OUTPUT HEADER SAMPLE OF XML_REQUEST

	<SOAP-ENV:Header>
		<ns2:Security>
			<ns2:UsernameToken>
				<ns2:Username>'Username'</ns2:Username>
				<ns2:Password>'Password'</ns2:Password>
			</ns2:UsernameToken>
		</ns2:Security>
	</SOAP-ENV:Header>
*/
	$ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

	//Create Nested SoapVar to replicate nested XML
	$node1 = new SoapVar($UserID, XSD_STRING, null, null, 'Username', $ns);
	$node2 = new SoapVar($Password, XSD_STRING, null, null, 'Password', $ns);
	$token = new SoapVar(array($node1,$node2), SOAP_ENC_OBJECT, null, null, 'UsernameToken', $ns);
	$security = new SoapVar(array($token), SOAP_ENC_OBJECT, null, null, 'Security', $ns);
	$header[] = new SOAPHeader($ns, 'Security', $security, false);
	return $header;
}
function afm_api($afm){
//Define UserID and Password Credentials
$UserID = ""; // Acquired Username from GSIS
$Password = ""; // Acquired Password from GSIS
$error = 0;
$is_valid=NULL;
try{
	$afm=$_REQUEST["afm"];
	// if afm not defined, throw an exception 
	if (!isset($afm) ||  ($afm=="")){
	   throw new Exception("<b>afm not defined.</b>");
	}
	// Create SoapHeader
	
	$header = soapHeader($UserID, $Password);
	// Initialize SoapClient from WSDL
	$client = new SoapClient("https://www1.gsis.gr/webtax2/wsgsis/RgWsPublic/RgWsPublicPort?WSDL", array('trace'=>true));							
	// Pass header
	$client->__setSoapHeaders($header);
	// Create params to be passed along SoapCall
	$params = array("afmCalledFor" => $afm,);
	// Get response from WS
	$response = $client->__soapCall("rgWsPublicAfmMethod",array('parameters' =>$params));
	// Convert response to xml, register namespaces
   

   
	$xml = simplexml_load_string($client->__getLastResponse(), NULL, NULL, "http://schemas.xmlsoap.org/soap/envelope/");
	$xml->registerXPathNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
	$xml->registerXPathNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
	$xml->registerXPathNamespace('m', 'http://gr/gsis/rgwspublic/RgWsPublic.wsdl');
	
   
	// Get associated fields of interest
	$deactivationFlag=$xml->xpath('//m:rgWsPublicAfmMethodResponse/RgWsPublicBasicRt_out/m:deactivationFlag');
	$errorDescr=$xml->xpath('//m:rgWsPublicAfmMethodResponse/pErrorRec_out/m:errorDescr');
	$errorCode=$xml->xpath('//m:rgWsPublicAfmMethodResponse/pErrorRec_out/m:errorCode');
	
  
	$e_flag = array_shift($errorCode);
	$e_descr = array_shift($errorDescr);
	$afm_flag = array_shift($deactivationFlag);
	$error=0;
	
  # echo "e_flag is: ",$e_flag, "e_descr: ",$e_descr,"afm_flag: ",$afm_flag,"<br/>\n";
   
	if (!isset($e_flag) || ($e_flag==""))
	{
      $is_valid = $afm_flag;
	} 
   else {
		
      	if ($e_flag=='RG_WS_PUBLIC_TAXPAYER_NF' || $e_flag=='RG_WS_PUBLIC_WRONG_AFM' || $e_flag=='RG_WS_PUBLIC_EPIT_NF'){
			$is_valid = 0;
		} else {
			$error = 1;
		}
	}
}
catch (Exception $e){
	$error=1;
}

return array($error,$is_valid);
}

//functions declaration
function parse_json($json_raw_text,$AFM)
{
   
$obj=json_decode($json_raw_text,TRUE);

#Saves json array decisions
$decisions=array();
$decisions=$obj["decisions"];

#Saves json decisions 
$info = $obj["info"];

#We will store the diavgeia_decisions here
$myDecisions=array();

foreach ($decisions as $decision){
$myDecision = new diavgeia_decision();
$myDecision->ada = $decision['ada'];
$myDecision->submissionTimestamp = $decision['submissionTimestamp'];
$myDecision->documentUrl= $decision['documentUrl'];
$myDecision->json_decision=json_encode($decision);
$myDecision->vat=$AFM;

array_push($myDecisions,$myDecision);

}

$total_results=$info["total"];
return array($myDecisions,$total_results);

}

function insert_search_results ($JSON,$AFM) 
{
   
}

function get_afm($AFM)
{
   require 'init.php';

$exit_code=0;
try
	{
	$sql = "SELECT VAT,EXISTS_FLAG FROM afm_check WHERE VAT=:AFM";
	$sth = $dbh->prepare($sql, array());
	$sth->execute(array(
		':AFM' => $AFM
	));
	$VAT = NULL;
	$EXISTS_FLAG = NULL;
	while ($row = $sth->fetch(PDO::FETCH_ASSOC))
		{
		$VAT = $row['VAT'];
		$EXISTS_FLAG = $row['EXISTS_FLAG'];
     
		
		}
   if ($VAT==NULL)
   {
      $exit_code=1;
     #echo "AFM ". $AFM .": does not exist in Database, plese try again later when GSIS website is available"; 
   }
   
   return  array($exit_code, $EXISTS_FLAG);
   
	
	}

catch(Exception $e)
	{
	echo "error on view_afm : " . $e;
	}
}

function update_afm($VAT,$EXISTS_FLAG)
{
   
   require 'init.php';
$exit_code=0;
try
{
	$query=$dbh -> prepare("REPLACE INTO `afm_check` (`VAT`, `EXISTS_FLAG`) VALUES ( :VAT, :EXISTS_FLAG)");
	$query->bindparam(":VAT",$VAT);
	$query->bindparam(":EXISTS_FLAG",$EXISTS_FLAG);
	$query->execute();

	#echo "AFM Update was successful";		
}
catch(PDOException $ex)
{
   $exit_code=1;
	#echo $ex->getMessage();
}

   return $exit_code;
   
}

function get_diavgeia($AFM,$PAGESIZE,$PAGENO)
{
   
   require 'init.php';

$exit_code=0;

#We will store the diavgeia_decisions here
$myDecisions=array();
try
	{

	$sql = "SELECT VAT,ADA_ID,SUBMISSION_TIMESTAMP,
      PDF_LINK FROM `diaygeia_results` WHERE VAT=:AFM 
      order by submission_timestamp desc 
      LIMIT :PAGENO ,:PAGESIZE";
	$sth = $dbh->prepare($sql, array());
	$sth->execute(array(
		':AFM' => $AFM,
      ':PAGENO'=> ($PAGENO*$PAGESIZE),
      ':PAGESIZE'=> $PAGESIZE
	));
	 
	while ($row = $sth->fetch(PDO::FETCH_ASSOC))
		{
      $myDecision = new diavgeia_decision();      
      $myDecision->ada = $row['ADA_ID'];
      $myDecision->submissionTimestamp = $row['SUBMISSION_TIMESTAMP'];
      $myDecision->documentUrl=$row['PDF_LINK'];
      $myDecision->vat=$row['VAT'];
      array_push($myDecisions,$myDecision);
		
		}
   if (count($myDecisions)==0)
   {
      
      $exit_code=1;
     #echo "AFM ". $AFM .": does not exist in Database, plese try again later when GSIS website is available"; 
   }
   	$sql = "SELECT  count(*) as volume FROM `diaygeia_results` WHERE VAT=:AFM ";
	$sth = $dbh->prepare($sql, array());
	$sth->execute(array(
		':AFM' => $AFM
      
	));
   	while ($row = $sth->fetch(PDO::FETCH_ASSOC))
		{
      $total_results = $row['volume'];      
     
		
		}
   
   return  array($exit_code,$myDecisions,$total_results);
   
	
	}

catch(Exception $e)
	{
	echo "error on fetching diaygeia results from db : " . $e;
	}
}

function update_diavgeia($decisions)
{
   require 'init.php';
   
$exit_code=0;
try
{
   foreach ( $decisions as $decision)
   {
	$query=$dbh -> prepare("REPLACE INTO `diaygeia_results` (`ADA_ID`, `JSON_DECISION`,PDF_LINK,SUBMISSION_TIMESTAMP,VAT)
   VALUES (:ADA_ID, :JSON_DECISION,:PDF_LINK , :SUBMISSION_TIMESTAMP, :VAT)");
	$query->bindparam(":ADA_ID",$decision->ada);
   $query->bindparam(":JSON_DECISION",$decision->json_decision);
   $query->bindparam(":PDF_LINK",$decision->documentUrl);
   $query->bindparam(":SUBMISSION_TIMESTAMP",$decision->submissionTimestamp);
   $query->bindparam(":VAT",$decision->vat);
	
	$query->execute();
   }
	#echo "AFM Update was successful";		
}
catch(PDOException $ex)
{
   $exit_code=1;
	#echo $ex->getMessage();
}

   return $exit_code;   
   
}
/**
* Curl send get request, support HTTPS protocol
* @param string $url The request url
* @param string $refer The request refer
* @param int $timeout The timeout seconds
* @return mixed
*/
function getRequest($url, $refer = "", $timeout = 10)
{
    $exit_status=0;
    $ssl = stripos($url,'https://') === 0 ? true : false;
    $curlObj = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_AUTOREFERER => 1,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
        CURLOPT_HTTPHEADER => ['Expect:'],
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    if ($refer) {
        $options[CURLOPT_REFERER] = $refer;
    }
    if ($ssl) {
        //support https
        $options[CURLOPT_SSL_VERIFYHOST] = false;
        $options[CURLOPT_SSL_VERIFYPEER] = false;
    }
    curl_setopt_array($curlObj, $options);
    $returnData = curl_exec($curlObj);
    if (curl_errno($curlObj)) {
        //error message
        $exit_status=1;
        $returnData = curl_error($curlObj);
    }
    curl_close($curlObj);
    return array($exit_status,$returnData);
}

/**
* Curl send post request, support HTTPS protocol
* @param string $url The request url
* @param array $data The post data
* @param string $refer The request refer
* @param int $timeout The timeout seconds
* @param array $header The other request header
* @return mixed
*/
function postRequest($url, $data, $refer = "", $timeout = 10, $header = [])
{
    $curlObj = curl_init();
    $ssl = stripos($url,'https://') === 0 ? true : false;
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_AUTOREFERER => 1,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
        CURLOPT_HTTPHEADER => ['Expect:'],
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_REFERER => $refer
    ];
    if (!empty($header)) {
        $options[CURLOPT_HTTPHEADER] = $header;
    }
    if ($refer) {
        $options[CURLOPT_REFERER] = $refer;
    }
    if ($ssl) {
        //support https
        $options[CURLOPT_SSL_VERIFYHOST] = false;
        $options[CURLOPT_SSL_VERIFYPEER] = false;
    }
    curl_setopt_array($curlObj, $options);
    $returnData = curl_exec($curlObj);
    if (curl_errno($curlObj)) {
        //error message
        $returnData = curl_error($curlObj);
    }
    curl_close($curlObj);
    return $returnData;
}
?>

