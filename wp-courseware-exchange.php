<?php
/*
 * Plugin Name: WP Courseware - Exchange Membership Add On
 * Version: 1.1
 * Plugin URI: http://flyplugins.com
 * Description: The official extension for <strong>WP Courseware</strong> to add integration for the <strong>Exchange Membership plugin by iThemes</strong> for WordPress.
 * Author: Fly Plugins
 * Author URI: http://flyplugins.com
 */



// Main parent class
include_once 'class_members.inc.php';

// Hook to load the class
add_action('init', 'WPCW_Exchange_init',1);


/**
 * Initialize the membership plugin, only loaded if WP Courseware 
 * exists and is loading correctly.
 */
function WPCW_Exchange_init()
{
	$item = new WPCW_Exchange();
	
	// Check for WP Courseware
	if (!$item->found_wpcourseware()) {
		$item->attach_showWPCWNotDetectedMessage();
		return;
	}
	
	// Not found the membership tool
	if (!$item->found_membershipTool()) {
		$item->attach_showToolNotDetectedMessage();
		return;
	}
	
	// Found the tool and WP Coursewar, attach.
	$item->attachToTools();
}


/**
 * Membership class that handles the specifics of the Exchange Membership WordPress plugin and
 * handling the data for levels for that plugin.
 */
class WPCW_Exchange extends WPCW_Members
{
	const GLUE_VERSION  	= 1.00; 
	const EXTENSION_NAME 	= 'Exchange Membership';
	const EXTENSION_ID 		= 'WPCW_Exchange';
	
	
	
	/**
	 * Main constructor for this class.
	 */
	function __construct()
	{
		// Initialise using the parent constructor 
		parent::__construct(WPCW_Exchange::EXTENSION_NAME, WPCW_Exchange::EXTENSION_ID, WPCW_Exchange::GLUE_VERSION);
	}
	
	
	
	/**
	 * Get the membership levels for this specific membership plugin.
	 */
	protected function getMembershipLevels()
	{
		//Get all published membership posts
		$levelData = it_exchange_get_products( array( 'product_type' => 'membership-product-type', 'show_hidden' => true, 'posts_per_page' => -1 ) );

		if ($levelData && count($levelData) > 0)
		{
			$levelDataStructured = array();
			
			// Format the data in a way that we expect and can process
			foreach ($levelData as $levelDatum)
			{
				$levelItem = array();
				$levelItem['name'] 	= get_the_title($levelDatum->ID) ;
				$levelItem['id'] 	= $levelDatum->ID;
				$levelDataStructured[$levelItem['id']]  = $levelItem;
			}
			
			return $levelDataStructured;
		}
		
		return false;
	}

	
	/**
	 * Function called to attach hooks for handling when a user is updated or created.
	 */	
	protected function attach_updateUserCourseAccess()
	{
        	add_action( 'it_exchange_update_transaction_status', array( $this, 'handle_updateUserCourseAccess' ));
    		add_action( 'it_exchange_add_transaction_success', array( $this, 'handle_updateUserCourseAccess'));
	}


	/**
		 * Assign selected courses to members of a paticular level.
		 * @param Level ID in which members will get courses enrollment adjusted.
		 */
	protected function retroactive_assignment($level_ID)
	{
		$page = new PageBuilder(false);
		//Get all transactions from Exchange
		$transactions = it_exchange_get_transactions_for_product( $level_ID, $type='objects', $only_cleared_for_delivery=true );

		//Check for transactions
		if ($transactions){

			        $ids_per_product_all = array();

		//Get IDs that are member of membership level
        foreach($transactions as $key => $transaction){
        	$customer_id_per_product_all[$key] = $transaction->customer_id;
        }

        //clean up duplicate IDs
        $customer_id_per_product = array_unique($customer_id_per_product_all);

        //Enroll members of level
        foreach ($customer_id_per_product as $customer_id )
        {
        	$memberLevels = it_exchange_get_customer_products( $customer_id );

        	$userLevels = array();

					foreach( $memberLevels as $key => $memberLevel ) {
						$userLevels[$key] = $memberLevel['product_id'];
					}
        	// Over to the parent class to handle the sync of data.
        	parent::handle_courseSync($customer_id, $userLevels);
        }
		
		$page->showMessage(__('All members were successfully retroactively enrolled into the selected courses.', 'wp_courseware'));
            
        return;

		}else{
			 $page->showMessage(__('No existing customers found for the specified level.', 'wp_courseware'));
		}
     	

	}
	

	/**
	 * Function just for handling the membership callback, to interpret the parameters
	 * for the class to take over.
	 * 
	 * @param Integer $id The ID if the user being changed.
	 * @param Array $levels The list of levels for the user.
	 */
	public function handle_updateUserCourseAccess($transaction_id)
	{
		// Get transaction data
        $transaction = it_exchange_get_transaction( $transaction_id );
        // Get customer ID based on transaction ID
        $customer_id = it_exchange_get_transaction_customer_id( $transaction->ID );
        // Get all membership levels for customer making current transaction
		$memberLevels = it_exchange_get_customer_products( $customer_id );

		$userLevels = array();

			foreach( $memberLevels as $key => $memberLevel ) {
				$userLevels[$key] = $memberLevel['product_id'];
			}
		// Over to the parent class to handle the sync of data.
		parent::handle_courseSync($customer_id, $userLevels);
	}
	

	/**
	 * Detect presence of the membership plugin.
	 */
	public function found_membershipTool()
	{
		return function_exists('it_exchange_register_membership_addon');
	}
	
	
}


?>