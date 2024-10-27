<?php 


class Click_OpenSSL
{
	var $Click_Userid;
	var $Click_AccountId;
	var $Click_Password;
	var $Click_Url;
	

	function Click_OpenSSL($Url, $UserId, $AccountId, $Password){

		error_reporting(E_ERROR);
		$this->Click_Userid = $UserId;
		$this->Click_AccountId = $AccountId;
		$this->Click_Password = $Password;
		$this->Click_Url = $Url;

	}
	

	function makeUrlRequest($request) {

		$host = $this->Click_Url;
		$uri = '/api/webpayments/paymentservice/rest/WPRequest';

		$parms = array();	

		// authentication
		$parms['username'] = $this->Click_Userid;
		$parms['account_id'] = $this->Click_AccountId;
		$parms['password'] = $this->Click_Password;

		$parms = array_merge($parms, $request);


		$fp = fsockopen('ssl://'. $host, 443, $errno, $errstr, 30);

		$content = http_build_query($parms);

		fwrite($fp, "POST $uri HTTP/1.1\r\n");
		fwrite($fp, "Host: $host\r\n");
		fwrite($fp, "Content-Type: application/x-www-form-urlencoded\r\n");
		fwrite($fp, "Content-Length: ".strlen($content)."\r\n");
		fwrite($fp, "Connection: close\r\n");
		fwrite($fp, "\r\n");

		fwrite($fp, $content);

		$result = '';

		while (!feof($fp)) {
		    $result .= fgets($fp, 1024);
		}

		list($header, $body) = explode("\r\n\r\n", $result, 2);

		return $body;
		
	}



	function validateResponse($data) {

		$host = $this->Click_Url;
		$uri = '/api/webpayments/paymentservice/rest/QueryTransactionByTxnId';

		$transacton_id = isset($data['TransactionId']) ? $data['TransactionId'] : null;
	
		if (! $transacton_id) {
			throw new Exception('Invalid Transaction ID');
		}

		// perform quick lookup
		$parms = array(	
			'username' => $this->Click_Userid,
			'account_id' => $this->Click_AccountId,
			'password' => $this->Click_Password,
			'txn_id' => $transacton_id,
		);

		$fp = fsockopen('ssl://'. $host, 443, $errno, $errstr, 30);

		$content = http_build_query($parms);
		$uri = $uri. '?' .$content; 

		$request =  "GET $uri HTTP/1.1\r\n";
		$request .= "Host: $host\r\n";
		$request .= "Connection: Close\r\n";
		$request .= "\r\n";

		fwrite($fp,$request);
		
		$result = '';
		while (!feof($fp)) {
		    $result .= fgets($fp, 1024);
		}

		list($header, $body) = explode("\r\n\r\n", $result, 2);

		return $body;

	}
	
	
}

?>