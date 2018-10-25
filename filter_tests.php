<?php
require "db_connect.php";

//Don't need PECL if I use curl. http://thisinterestsme.com/sending-json-via-post-php/
function send_request($post_this){
	//the SUT
	$url = "http://localhost/adclear_eval/request_filter.php";
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_this);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}

function validate_result($link, $query, $expect_req, $expect_invalid){
	$result = mysqli_query($link, $query);
	
	if(mysqli_num_rows($result) > 0){
		$result_arr = mysqli_fetch_assoc($result);
		$output = "SUCCESS";
		if($result_arr['request_count'] != $expect_req || $result_arr['invalid_count'] != $expect_invalid){
			$output = "FAIL";
		}
		$output .= "     (request count: " .$result_arr['request_count']. "     invalid count: "
				.$result_arr['invalid_count']. ")<br><br>";
		echo $output;
	}
	else{
		echo "error validating<br>";
	}
}

//each test should be unaffected by previous tests. so I'll run this before each one
function refresh_db($link){
	//I don't need all the quotations, so I eventually stopped doing them
	$sql = "DELETE FROM customer;
			DELETE FROM ip_blacklist;
			DELETE FROM user_id_blacklist;
			DELETE FROM hourly_stats;
			INSERT INTO customer VALUES
				(1,'Big News Media Corp',1),
				(2,'Online Mega Store',1),
				(3,'Nachoroo Delivery',0),
				(4,'Euro Telecom Group',1);
			INSERT INTO ip_blacklist VALUES
				(0),
				(2130706433),
				(4294967295);
			INSERT INTO user_id_blacklist VALUES
				('A6-Indexer'),
				('Googlebot-News'),
				('Googlebot');
			INSERT INTO hourly_stats VALUES
				(1, 19700101000001,1,1),
				(3, 20181020210000,5,4),
				(4, 20181020200000,1,0),
				(4, 20181020210000,5,4);";
	
	if(!mysqli_multi_query($link, $sql)){
		die("error: " . mysqli_error($link) . "<br>");
	}
	//flush multi_queries (thanks man pages)
	while(mysqli_more_results($link)){
		mysqli_next_result($link);
	}
}

//my update and insert tests cover the edge, so I don't have to worry about testing for failures (an update fail is an insert success)
function test_update_request($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'Michael',
		'timestamp' => 1540072799,
		'remoteIP' => 12345678901,
		'customerID' => 4
		);
		
	$req = 6;
	$inv = 4;
	echo "testing update requests (expected: Euro Telecom Group has ".$req." requests and ".$inv." invalid requests):<br>";
	$json = json_encode($arr);
	$result = send_request($json);
	
	$sql = "SELECT request_count, invalid_count FROM hourly_stats WHERE customer_id = 4 ORDER BY time DESC LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

function test_insert_request($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'Michael',
		'timestamp' => 1540072800,
		'remoteIP' => 12345678901,
		'customerID' => 4
		);
	
	$req = 1;
	$inv = 0;
	echo "testing insert requests (expected: Euro Telecom Group has ".$req." requests and ".$inv." invalid requests):<br>";
	$json = json_encode($arr);
	$result = send_request($json);
	
	$sql = "SELECT request_count, invalid_count FROM hourly_stats WHERE customer_id = 4 ORDER BY time DESC LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

function test_malformed($link){
	refresh_db($link);
	//an array is not json. So even valid keys and values should be rejected
	$arr = array(
		'customerID' => 2,
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 1540071000,
		'remoteIP' => 12345678901
		);
	$result = send_request($arr);
	
	$test_outcome = "SUCCESS";
	if(strpos($result, 'die') === FALSE)
		$test_outcome = "FAIL";
	
	echo "testing malformed request (it should die - doesn't know who to charge)<br>
		".$test_outcome." (actual: ".$result.")<br><br>";
}

//more of a corner case than previous tests
function test_empty_json($link){
	refresh_db($link);
	$arr = array();
	$json = json_encode($arr);

	$result = send_request($arr);
	
	$test_outcome = "SUCCESS";
	if(strpos($result, 'die') === FALSE)
		$test_outcome = "FAIL";
	
	echo "testing empty JSON in request (expected: it should die):<br>
		".$test_outcome." (actual: ".$result.")<br><br>";
}

function test_missing_customer_id($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 1540071000,
		'remoteIP' => 12345678901
		);
	$json = json_encode($arr);
	$result = send_request($arr);
	
	$test_outcome = "SUCCESS";
	if(strpos($result, 'die') === FALSE)
		$test_outcome = "FAIL";
	
	echo "testing malformed request (it should die - doesn't know who to charge)<br>
		".$test_outcome." (actual: " . $result . ")<br><br>";
}

function test_unknown_customer($link){
	refresh_db($link);
	$arr = array(
		'customerID' => 10,
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 1540071000,
		'remoteIP' => 12345678901
		);
	$json = json_encode($arr);
	$result = send_request($arr);
	
	$test_outcome = "SUCCESS";
	if(strpos($result, 'die') === FALSE)
		$test_outcome = "FAIL";
	
	echo "testing unknown customer (it should die - this is not a valid customer)<br>
		".$test_outcome." (actual: " . $result . ")<br><br>";
}

function test_disabled_customer($link){
	refresh_db($link);
	$arr = array(
		'customerID' => 3,
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 1540071000,
		'remoteIP' => 12345678901
		);

	$req = 6;
	$inv = 5;
	$json = json_encode($arr);
	send_request($json);
	echo "testing disabled customer (expected: Nacharoo delivery has ".$req." requests and ".$inv." invalid):<br>";
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 3
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

//not quite like the others, since I have special behavior for it
function test_missing_timestamp($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'frank',
		'remoteIP' => 12345678901,
		'customerID' => 4
		);
	$json = json_encode($arr);
	send_request($json);
	
	$req = 1;
	$inv = 1;
	echo "testing missing timestamp (expected: Euro Telecom Group has ".$req." requests and ".$inv." invalid):<br>";
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 4
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

//behavior should be all the same, so I left this as one test case
function test_missing_others($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'frank',
		'remoteIP' => 12345678901,
		'timestamp' => 1540071000,
		'customerID' => 4
		);
	//each iteration of loop sends a request with a different key missing
	for($i = 0; $i < count($arr) - 2; $i++){
		reset($arr);
		$removed_key = key($arr); 
		$removed = array_shift($arr);
		$json = json_encode($arr);
		$result = send_request($json);
		$arr[$removed_key] = $removed;
	}
	
	$req = 8;
	$inv = 7;
	echo "testing missing fields other than customerID (expected: Euro Telecom Group has ".$req." requests and ".$inv." invalid):<br>";
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 4
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

function test_ip_blacklisted($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 1540071000,
		'remoteIP' => 2130706433,
		'customerID' => 4
		);
		
	$req = 6;
	$inv = 5;
	echo "testing blacklisted IP(expected: Euro Telecom Group has ".$req." requests and ".$inv." invalid):<br>";
	$json = json_encode($arr);
	send_request($json);
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 4
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}
function test_user_blacklisted($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'A6-Indexer',
		'timestamp' => 1540071000,
		'remoteIP' => 12345678901,
		'customerID' => 4
		);
	
	$req = 6;
	$inv = 5;
	echo "testing blacklisted user (expected: Euro Telecom Group has ".$req." requests and ".$inv." invalid):<br>";
	$json = json_encode($arr);
	send_request($json);
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 4
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}
//again, kind of a corner case
function test_both_blacklisted($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'A6-Indexer',
		'timestamp' => 1540071000,
		'remoteIP' => 2130706433,
		'customerID' => 4
		);
	
	$req = 6;
	$inv = 5;
	echo "testing blacklisted user (expected: Euro Telecom Group has ".$req." requests and ".$inv." invalid):<br>";
	$json = json_encode($arr);
	send_request($json);
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 4
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

function test_ip_bl_customer_disabled($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 1540071000,
		'remoteIP' => 2130706433,
		'customerID' => 3
		);
		
	$req = 6;
	$inv = 5;
	echo "testing blacklisted IP(expected: Nachoroo has ".$req." requests and ".$inv." invalid):<br>";
	$json = json_encode($arr);
	send_request($json);
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 3
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

function test_user_bl_customer_disabled($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'A6-Indexer',
		'timestamp' => 1540071000,
		'remoteIP' => 12345678901,
		'customerID' => 3
		);
		
	$req = 6;
	$inv = 5;
	echo "testing blacklisted IP(expected: Nachoroo has ".$req." requests and ".$inv." invalid):<br>";
	$json = json_encode($arr);
	send_request($json);
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 3
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

function test_both_bl_customer_disabled($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'A6-Indexer',
		'timestamp' => 1540071000,
		'remoteIP' => 2130706433,
		'customerID' => 3
		);
		
	$req = 6;
	$inv = 5;
	echo "testing blacklisted user (expected: Nachoroo has ".$req." requests and ".$inv." invalid):<br>";
	$json = json_encode($arr);
	send_request($json);
	
	$sql = "SELECT request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id = 3
			ORDER BY time DESC
			LIMIT 1";
	validate_result($link, $sql, $req, $inv);
}

//I'm doing happy paths first because if they don't work correctly, I can't really trust my validation queries in the following tests
test_update_request($link);
test_insert_request($link);

test_malformed($link);
test_empty_json($link);
test_missing_customer_id($link);
test_unknown_customer($link);
test_disabled_customer($link);
test_missing_timestamp($link);
test_missing_others($link);
test_ip_blacklisted($link);
test_user_blacklisted($link);
test_both_blacklisted($link);
test_ip_bl_customer_disabled($link);
test_user_bl_customer_disabled($link);
test_both_bl_customer_disabled($link);
?>