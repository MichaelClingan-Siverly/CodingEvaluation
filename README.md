# CodingEvaluation

This project uses MySql. The server will have to be started first.
After connecting to the server, run the db_init script to create the schema and tables.

After your database is up and running, all that is left is to drop the adclear_eval folder into your server.
I used WAMP for this, but any kind of AMP server should work.
	NOTE: the values in the included private/db_connect.php file may need to be changed in order to connect to your database

	
Even though I stopped using db_fill and db_clear (moved the scripts into the test file), 
I left the files so they are available if you would like them. However, they should not be used for production.


