<?php

require ".\private\db_connect.php";

function success_stub($raw_input){
	exit();
}

//I am to only look for missing fields(might have to add one if not there). Requests with extra fields will not be rejected
function contains_missing_fields(&$json_arr){
	//'customerID' was already checked. I don't need it here
	$necesary_keys = array("tagID", "userID", "remoteIP", "timestamp");
	
	foreach($necesary_keys as $key){
		if(!array_key_exists($key,$json_arr) || empty($json_arr[$key])){
			//assumption: I can use the current time for a customer if request has no timestamp
			if(!array_key_exists('timestamp',$json_arr) || empty($json_arr['timestamp'])){
				$json_arr['timestamp'] = time();
			}
			return TRUE;
		}
	}
	return FALSE;
}

//https://www.w3schools.com/php/php_mysql_select.asp and https://stackoverflow.com/a/11715238
function from_disabled_or_blacklisted($link, $json_arr){
	$customer_name = $json_arr["customerID"];
	$user_name = $json_arr["userID"];
	$ip = $json_arr["remoteIP"];
	//I'm sure there is a better way than running separate searches,
	//but the tables are unrelated so I'm hesitant to join them.
	$sql_1 = "SELECT ip FROM ip_blacklist WHERE ip = '".$ip."'";
	$sql_2 = "SELECT user_id FROM user_id_blacklist WHERE user_id = '".$user_name."'";
	$sql_3 = "SELECT id FROM customer WHERE id = '".$customer_name."' AND active = FALSE";
	
	$a = query_helper($link, $sql_1);
	$b = query_helper($link, $sql_2);
	$c = query_helper($link, $sql_3);
	return $a || $b || $c;
}

//just checks whether a query had any results. What the result is is ignored
function query_helper($link, $query){
	$result = mysqli_query($link, $query);
	return (mysqli_num_rows($result) > 0);
}

//some searching eventually brought me here: https://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_timestampdiff
function check_if_new_hour($link, $customer_id, $time_stamp){
	$seconds_per_hour = 3600;
	//assumption: timestamp is a Unix timestamp (seconds since epoch)
	$rounded_stamp = $time_stamp - ($time_stamp % $seconds_per_hour);
	
	$sql = "SELECT time FROM hourly_stats WHERE customer_id = '".$customer_id."' ORDER BY time DESC LIMIT 1";
	$result = mysqli_query($link, $sql);
	
	if(mysqli_num_rows($result) == 1){
		$result = mysqli_fetch_assoc($result);
		return strtotime($result['time']) != $rounded_stamp;
	}
	return TRUE;
	
	
	//a new entry is made if customer isn't in the stats yet, or if the customer has no entry for this hour (calculated in seconds)
	$condition = "EXISTS (SELECT customer_id FROM hourly_stats WHERE customer_id = '".$customer_id."')
		AND '".$seconds_per_hour."' <= (SELECT TIMESTAMPDIFF(MICROSECOND, '".$time_stamp."',
		(SELECT time FROM hourly_stats WHERE customer_id = '".$customer_id."' ORDER BY time DESC LIMIT 1)))";
		
	//I insert a new entry for this customer. This only works with Unix integer timestamps. Used https://stackoverflow.com/a/6916309
	$true_outcome = "INSERT into hourly_stats VALUES ('".$customer_id."','".$rounded_hour."' 1,0)";
	
	//update the entry for this customer
	$false_outcome = "UPDATE hourly_stats SET request_count = request_count + 1 WHERE '".$customer_id."'
		IN (SELECT * FROM hourly_stats WHERE customer_id = '".$customer_id."' ORDER BY time DESC LIMIT 1)";
	
	$sql = "IF (".$condition.",".$true_outcome.",".$false_outcome.")";
	//finish the query from AND on (if last time was not in this hour)
}

function update_hourly_stats($link, $customer_id, $time_stamp, $valid, $new_hour){
	$invalid_update = "";
	$invalid_insert = 0;
	$sql;
	if(!$valid){
		$invalid_update = ", invalid_count = invalid_count+1";
		$invalid_insert = 1;
	}
		
	if(!$new_hour){
		$sql = "UPDATE hourly_stats
				SET request_count = request_count+1 ".$invalid_update."
				WHERE customer_id = '".$customer_id."'
				ORDER BY time DESC
				LIMIT 1";
	}
	else{
		//had some fun going between MySql timestamps and Unix timestamps
		$seconds_per_hour = 3600;
		$rounded_stamp = $time_stamp - ($time_stamp % $seconds_per_hour);
		$rounded_stamp = date("Y-m-d H:i:s",$rounded_stamp);
		$sql = "INSERT INTO hourly_stats
				VALUES ('".$customer_id."','".$rounded_stamp."',1,'".$invalid_insert."')";
	}
	mysqli_query($link, $sql);
}


//first, I need to receive the request and get the key/value pairs out of it
//https://stackoverflow.com/a/15617547
$raw_input = file_get_contents("php://input");
$input = json_decode($raw_input, true);

//cases where I won't know who'se requet count to increment (should also cover malformed json, since it would be unable to be decoded)
//(simple Google searches brought me to the manual pages for these)
if(!is_array($input) || !array_key_exists("customerID",$input) || !isset($input["customerID"])){
	//sends a message as instrumentation. This way my tests don't have to check the database
	die("died (I can't find a customer)");
}

//and these are the invalid requests for customers who we CAN increment the request count for
$valid_request = !contains_missing_fields($input) && !from_disabled_or_blacklisted($link, $input);
$insert_new_hour = check_if_new_hour($link, $input['customerID'], $input['timestamp']);

update_hourly_stats($link, $input['customerID'], $input['timestamp'], $valid_request, $insert_new_hour);

if($valid_request){
	success_stub($raw_input);
}
?>