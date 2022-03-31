<?php
/**
  * Gravity Forms - Populate checkboxes (projects and who owns them) from Google Spreadsheet based on what client user selected in the previous step in th Gravity Form.
*/

// The ID of your form in Gravity
$form_id = 'XX';  
 
add_filter( 'gform_pre_render_'.$form_id, 'load_projects_from_client_new_vendor_account_function' );
add_filter( 'gform_pre_validation_'.$form_id, 'load_projects_from_client_new_vendor_account_function' );
add_filter( 'gform_pre_submission_filter_'.$form_id, 'load_projects_from_client_new_vendor_account_function' );
add_filter( 'gform_admin_pre_render_'.$form_id, 'load_projects_from_client_new_vendor_account_function' );

function load_projects_from_client_new_vendor_account_function( $form ) {
	
  // Enable error reporting
  //ini_set('display_startup_errors', 1);
  //ini_set('display_errors', 1);
  //error_reporting(-1);
	
  // Get value from $_POST - what the user selected from a drop down list before they clicked on the Next button in the form.
  $selected_project = rgpost( 'input_20', true );
	
  //echo ('An the winner is...');
  //echo ($selected_project);
	
 // If a client was selected, then proceed with loading the corresponding project names
 if (!empty($selected_project)){
		
	// Connect the Google Sheets API client. Ensure the folder is included in your /etc/php.ini file under the open_basedir='' directive.
	require_once '/usr/share/php/vendor/autoload.php';

	// Our service account access key. Ensure the folder is included in your /etc/php.ini file under the open_basedir='' directive.
	$googleAccountKeyFilePath = '/usr/share/php/service_key.json';
	putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $googleAccountKeyFilePath);

		// Create new client
		$client = new Google_Client();
		// Set credentials
		$client->useApplicationDefaultCredentials();

		// Adding access area for reading, editing, creating and deleting tables - add read.only
		$client->addScope('https://www.googleapis.com/auth/spreadsheets');

		$service = new Google_Service_Sheets($client);
	
		//Your GSheet ID
    		$spreadsheetId = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
	
		//Select field ID in your Gravity Form where you want the names to load
    		$dropdown_field_ID = 'XX';
	
		// Go through each list of active (ACT) jobs for each subsidiary
		$ranges = [ 
			'Sheet1!A2:D1000', 
			'Sheet2!A2:D1000', 
			'Sheet3!A2:D1000',
		];
	
		// Save the ranges into an array + specify that we are after rows
		$params = array(
    		'ranges' => $ranges,
			'majorDimension' => 'ROWS'
		);
	
		// Call the function to retrieve (batchGet) the ranges
		$result = $service->spreadsheets_values->batchGet($spreadsheetId, $params);
	
		// Count the number of ranges retrieved (can be compared with how many ranges were supplied)
		//printf("%d ranges retrieved.", count($result->getValueRanges()));
		//echo '<pre>'; print_r($result); echo '</pre>';

		// strip the JSON output of other stuff apart from the multi-dimensional array called 'Values'
		$values = $result->getValueRanges();
		//echo '<pre>'; print_r($values); echo '</pre>';
		
		//Go through all the fields in the form
		foreach ( $form['fields'] as $field ) { 	
			//Check if field type is a select dropdown and if its ID is correct, then...
         		if ( $field->id == $dropdown_field_ID) {
				//echo 'looping through each field..';
				// ...go through the multi-dimensional array to fileter out duplicates and empty values
					foreach($values as $value){
						foreach($value as $minivalue){
							foreach($minivalue as $indiv_value){
								//print($indiv_value);
								//Verify that the cell is not empty, then
								if (!empty($indiv_value)) {
									// If it matches the previously selected project, then
									if ($indiv_value == $selected_project) {								
										// Add to the array the next cell from the row (the project name)
										//$all_jobs[] = $indiv_value;
										$all_jobs[] = $minivalue[1]." (".$minivalue[3].")";
										//$all_jobs[] = next($minivalue);
										//printf("For project ".$minivalue[1]." the approver is:". $minivalue[3]);	
									}								
								}
							}		
						}
  			  	}
				
				// Process the filtered out jobs if any were loaded.
				if (!empty($all_jobs)) {
       		 	 	// Remove duplicates from the list of all jobs - there should be none here
					//$filtered_jobs = array_unique($all_jobs);
					$filtered_jobs = $all_jobs;
					
					# Define an empty array used specifically for checkboxes in addition to choices
					$inputs = [];
					$input_id = 1;
			
					//Sort the filtered jobs alphabetically regardless of case (upper or lower)
					sort($filtered_jobs, SORT_NATURAL | SORT_FLAG_CASE);

					//Add New customer item to the beginning of the array - NOT NEEDED IN PO REQ FORM
					//array_unshift($filtered_jobs , '-- New Customer--');
			 
					//Create a multi-dimensional array compatible with the drop-down menu in Gravity
					foreach($filtered_jobs as $filtered_job){
						//printf($filtered_job);
						$jobs_to_display[] = array('text' => $filtered_job, 'value' => $filtered_job);
						# Source: https://awhitepixel.com/blog/gravity-forms-dynamically-populate-fields/
						$inputs[] = ['label' => $filtered_job, 'id' => $field->id . '.' . $input_id];
						# Increment ID of the field by one
						$input_id++;					
					}
			 
				}
				
		 	//Add a place holder - if desired
            		//$placeholder_text = "Select project";
			//$field->placeholder = $placeholder_text;
				
            		//Add the new names to the form choices
           		$field->choices = $jobs_to_display;
			$field->inputs = $inputs;
            		// Print out the contents of the array (troubleshooting only)
           		//echo '<pre>'; print_r($jobs_to_display); echo '</pre>';
			}
		}

  }
  return $form; //return data to the form
  
}
?>
