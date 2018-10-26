<?php
//fortunately, sites like this exist: https://www.w3schools.com/tags/
$has_date = array_key_exists("date",$_POST) && isset($_POST["date"]);
$has_name = array_key_exists("name",$_POST) && isset($_POST["name"]);

if(!$has_date || !$has_name){
	?>
	<html>
		<head>
			<title>View Stats</title>
		</head>
		<body>
			<form name = 'ViewStats' action = 'view_stats.php' method='post' enctype='application/json'>
				Customer Name: <input type='text' name='name'><br>
				Day: <input type='date' name='date'><br>
				<input type='submit' value = 'View Stats'/> 
			</form>
		</body>
	</html>
	<?php
}
else{
	$name = $_POST['name'];
	$date = $_POST['date'];
	require ".\private\db_connect.php";
	$sql = "SELECT time, request_count, invalid_count
			FROM hourly_stats
			WHERE customer_id IN
				(SELECT id
				 FROM customer
				 WHERE name = '".$name."')
			AND (SELECT DATEDIFF('".$date."' , time) = 0)
			ORDER BY time ASC";

	$result = mysqli_query($link, $sql);

	//This looks ugly to me. But it seems like I can either do this (append a string) or use php tags instead
	$output = "<head><style>
					table, th, td {
						border: 1px solid black;
					}
				</style></head>
				<table style='width:80%'>
					<tr>
						<th>time</th>
						<th>requests by ".$name." per hour</th>
						<th>invalid requests by ".$name." per hour</th>
					</tr>";
	
	//adds rows (stats for each hour) to the output
	$this_user_total = 0;
	$this_user_invalid = 0;
	$date;
	while($row = mysqli_fetch_assoc($result)){
		//this instead of a for loop so I can sum requests (without new query), and because I know the amount and order of elements per row
		$date = date("Y-m-d", strtotime($row['time']));
		$hour = date("H:i:s",strtotime($row['time']));
		$requests = $row['request_count'];
		$this_user_total += $requests;
		$invalid = $row['invalid_count'];
		$this_user_invalid += $invalid;
		$output .= "<tr>
						<td>".$hour."</td>
						<td>".$requests."</td>
						<td>".$invalid."</td>
					</tr>";
	}
	
	$output .= "</table><br><br><br>
				Total requests by ".$name." for ".$date.":<br>".$this_user_total."<br>
				Total invalid requests by ".$name." for ".$date.":<br>".$this_user_invalid."<br><br>";
				
	$sql = "SELECT SUM(request_count), SUM(invalid_count)
			FROM hourly_stats
			WHERE (SELECT DATEDIFF('".$date."' , time) = 0)";

	$result = mysqli_query($link, $sql);
	$row = mysqli_fetch_array($result);
	$output .= "Total requests for ".$date.":<br>".$row[0]."<br>
				Total invalid requests for ".$date.":<br>".$row[1];
	echo $output;
}
?>