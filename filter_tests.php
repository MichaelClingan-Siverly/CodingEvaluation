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
				(3, 20181020163005,5,4),
				(4, 20181020153005,1,0),
				(4, 20181020163005,5,4);";
	
	if(!mysqli_multi_query($link, $sql)){
		die("error: " . mysqli_error($link) . "<br>");
	}
	//flush multi_queries (thanks man pages)
	while(mysqli_more_results($link)){
		mysqli_next_result($link);
	}
}

function test_malformed($link){
	refresh_db($link);
	//an array is not json. So even valid keys and values should be rejected
	$arr = array(
		'customerID' => 2,
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 20181020173000,
		'remoteIP' => 12345678901
		);
	echo "testing malformed request (it should die - doesn't know who to charge): " . send_request($arr) . "<br>";
}

function test_missing_customer_id($link){
	refresh_db($link);
	$arr = array(
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 20181020173000,
		'remoteIP' => 12345678901
		);
	$json = json_encode($arr);
	echo "testing malformed request (it should die - doesn't know who to charge): " . send_request($json) . "<br>";
}

//behavior should be all the same, so I left this as one test case
function test_missing_others($link){
	refresh_db($link);
	//timestamp is within an hour, so it only updates the one 
	$arr = array(
		'tagID' => 560,
		'userID' => 'frank',
		'timestamp' => 20181020163605,
		'remoteIP' => 12345678901,
		'customerID' => 4
		);
	for($i = 0; $i < count($arr) - 1; $i++){
		reset($arr);
		$removed_key = key($arr); 
		$removed = array_shift($arr);
		$arr[$removed_key] = $removed;
		$json = json_encode($arr);
		send_request($json);
	}
	echo "testing missing fields other than customerID (expected: Euro Telecom Group has 9 requests and 8 invalid requests): ";
	$sql = "SELECT request_count, invalid_count FROM hourly_stats WHERE customer_id = 4 AND time = 20181020163005";
	$result = mysqli_query($link, $sql);
	
	if(mysqli_num_rows($result) > 0){
		$result_arr = mysqli_fetch_assoc($result);
		print_r($result_arr);
		echo "<br>";
	}
}

test_malformed($link);
test_missing_customer_id($link);
test_missing_others($link);

?>