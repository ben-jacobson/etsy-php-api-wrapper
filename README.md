# Etsy REST API wrapper for PHP

Ben Jacobson - January 2016

For a while, I had a little dropshipping side-business running on Etsy. It was a nice little side-earner, and it was great having the ability to completely automate the order fulfillment process using the API to output data to some EC2 instances. For this, I wrote my own PHP wrapper for the Etsy REST API. I had this code running on an AWS EC2 instance for about 2 years, this was part of a bigger code-base responsible for taking an order and setting up the 3rd party supplier for fulfillment.

The purpose of this repo is purely for legacy purposes. This project isn't supported and it's unlikely there will be any documentation created for it. However, feel free to grab whatever code you want from this to use in your own work. The best place to start looking is the make_request() method, once your constructors are all set up with credentials, that function makes it really easy to probe the API for data and a lot of other methods make use of it in a very similar way - for example:

	public function get_listing($id)    // $id refers to the listing_id of the item on the shop
	{	
    //see the API documentation on what the /listings/ endpoint does.    
		$request_url = $this->base_url . "listings/" . $id;   
    // use of this is fairly generic, and output is JSON 
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);		  
	}	
  
  
  Beyond that, have a look at the Etsy API documentation, which I found really helpful.
  https://www.etsy.com/developers/documentation
 
