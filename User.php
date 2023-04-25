<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Twilio\Rest\Client;

class User extends CI_Controller {
	public function __construct()
	{	
		parent::__construct();	
		$blockedIps = array("157.38.34.63","106.193.166.198","223.184.165.202","106.205.246.32","59.95.252.2");
        $currentUserIp = $this->input->ip_address();

        if(in_array($currentUserIp, $blockedIps)){

        //block user functionality goes here
        die();

        }	
		$this->output->set_header("Cache-Control: no-store, no-cache, must-revalidate, no-transform, max-age=0, post-check=0, pre-check=0");
		$this->output->set_header("Pragma: no-cache");
		$this->load->library(array('form_validation'));
		$this->load->library('session');
		$this->load->helper(array('url', 'language'));
		$lang_id = $this->session->userdata('site_lang');
		if($lang_id == '')
		{
			$this->lang->load('content','english');
			$this->session->set_userdata('site_lang','english');
		}
		else
		{
			$this->lang->load('content',$lang_id);	
			$this->session->set_userdata('site_lang',$lang_id);
		}
		$sitelan = $this->session->userdata('site_lang'); 
	}
	function switchLang($language = "") 
    {
       $language = ($language != "") ? $language : "english";
       $this->session->set_userdata('site_lang', $language);
       redirect($_SERVER['HTTP_REFERER'], 'refresh');
    }
	public function block()
	{
		$cip = get_client_ip();
		$match_ip = $this->common_model->getTableData('page_handling',array('ip'=>$cip))->row();
		if($match_ip > 0)
		{
		return 1;
		}
		else
		{
		return 0;
		}
	}



     function announcement(){


        $data['site_common'] = site_common();
        $data['meta_content'] = $this->common_model->getTableData('meta_content', array('link' => 'announcements'))->row();

        $data['announcement'] = $this->common_model->getTableData('announcement',array('status'=>0))->result();
        
        $this->load->view('front/user/annoucement',$data);

    }





	public function block_ip()
    {
        $this->load->view('front/common/blockips');
    }

	function force_logout(){
		$this->session->unset_userdata('user_id');
		$this->session->unset_userdata('loggedin_time');
		$this->session->unset_userdata('pass_changed');
		$tokenvalues = $this->session->userdata('tokenvalues');
		$depositvalues = $this->session->userdata('depositvalues');
		if(isset($tokenvalues) && !empty($tokenvalues))
		{
			$this->session->unset_userdata('tokenvalues');
		}
		if(isset($depositvalues) && !empty($depositvalues))
		{
			$this->session->unset_userdata('depositvalues');
		}
		front_redirect('home','refresh');
	}

	function passwordreset()
	{	
		$array['status']=0;
		$login_status = getSiteSettings('login_status');
		$user_id=$this->session->userdata('user_id');
		if($user_id!="" || $login_status==0) {  
			$this->session->set_flashdata('error', 'Sorry for the inconvenience Site is under maintenance So, Login/Registration is not possible at the moment'); 
			front_redirect('', 'refresh');
		}
		
		if(!empty($_POST))
		{ 
            $this->form_validation->set_rules('forgot_email', 'Mail Id', 'trim|required|valid_email|xss_clean');
			$this->form_validation->set_rules('forgot_otp', 'OTP', 'trim|required|xss_clean');
			$this->form_validation->set_rules('forgot_pass', 'New Password', 'trim|required|xss_clean');
			if ($this->form_validation->run())
			{ 
				$email = $this->input->post('forgot_email');                
				$otp=$this->input->post('forgot_otp'); 
				$password=validatePassword($this->input->post('forgot_pass'));  

				$str=splitEmail($email);
				$prefix=get_prefix();
				// Validate email
                if (filter_var($email, FILTER_VALIDATE_EMAIL))
                {
					$array['email']=$str[1];
					$array['OTP']=$otp;
                    $check=checkEmailOtp($str[1],$otp);
                    $type=3;

					if (!$check)
					{
						$array['msg']=$this->lang->line('Mail-Id or OTP is wrong');
					}else{
						$update = array(
							$prefix.'password'    => encryptIt($password)
						);
		
						$is_updated = $this->common_model->updateTableData('users',array('id'=>$check->id),$update);


						if($is_updated){
							$array['status']=1;
							$array['msg']=$this->lang->line('Password updated successfully');
						}else{
							$array['status']=0;
							$array['msg']=$this->lang->line('New password not updated in database');
						}
					}
                }else{
					$array['msg']=$this->lang->line('Please Enter Valid Email');
				}

                
			}else{
				$array['msg']=$this->lang->line('Validation Error');
			}
		}
		die(json_encode($array));

	}

	function login()
	{	
		$login_status = getSiteSettings('login_status');
		$user_id=$this->session->userdata('user_id');
		if($user_id!="" || $login_status==0) {  
		$this->session->set_flashdata('error', 'Sorry for the inconvenience Site is under maintenance So, Login/Registration is not possible at the moment'); 
			front_redirect('', 'refresh');
		}
		$data['site_common'] = site_common();
		$static_content  = $this->common_model->getTableData('static_content',array('english_page'=>'home'))->result();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'login'))->row();
		$data['action'] = front_url() . 'login_user';	
       $data['countries'] = $this->common_model->getTableData('countries',array('phonecode!='=>null),'','','','','','','',array('phonecode','groupby'))->result(); 	

		// $this->load->library('Googleauthenticator');
		// $ga     = new Googleauthenticator();
	   	// $result = $this->common_model->getTableData('users', array('id' => '1'))->row_array();
		// $secret = $result['secret'];
		// $code="274234";
	   	// $oneCode = $ga->verifyCode($secret,$code,$discrepancy = 3);
		// echo "Hi=>";
		// print_r($result);exit;
		$this->load->view('front/user/login', $data);
	}



	public function login_check(){

		$ip_address = get_client_ip();
        $array = array('status' => "fail", 'msg' => '');



        $this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean');
        $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
        // When Post

        // print_r($this->input->post());

		if ($this->form_validation->run()) {

			$email = lcfirst($this->input->post('email'));
			$password = $this->input->post('password');
			$prefix = get_prefix();
			// Validate email
			if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$check = checkSplitEmail($email, $password);
			}
			if (!$check) {
				//vv
				$array['msg']=$this->lang->line('Invalid Log-In Details');
			} else {
				if ($check->verified != 1) {
					$array['status'] = "fail";
					$array['msg']=$this->lang->line('Please check your email to activate the account');
					
				} else {
					$array['status'] = "success";
					$array['user_details'] = $check;
					$array['username'] = $check->elxisenergy_fname;
					$array['server_time'] = $_SERVER['REQUEST_TIME'] + 600;
					$array['user_id'] = $check->id;
					if ($check->randcode == 'enable' && $check->secret != '') { 
						$array['tfa_status'] = 1;
						// $login_tfa = $this->input->post('tfa');

						$onecodes = $this->db->escape_str($this->input->post('tfa'));
						$login_tfa =  implode('',$onecodes);
						$array['tfa_code'] = $login_tfa;



						$check1 = $this->checktfa($check->id, $login_tfa);
						if ($check1) {
							$session_data = array(
								'user_id' => $check->id,
								'loggedin_time' => $_SERVER['REQUEST_TIME'] + 600
							);
							$this->session->set_userdata($session_data);
							$_SESSION['user_id'] = $check->id;
							$this->common_model->last_activity('Login', $check->id);
							$this->session->set_flashdata('success', 'Welcome back . Logged in Successfully');
							$array['status'] = "success";
							$array['user_details'] = $check;
							$array['username'] = $check->elxisenergy_email;
							$array['user_id'] = $check->id;
							$array['auth_status']=$check->randcode; 
							$array['server_time'] = $_SERVER['REQUEST_TIME'] + 48000;
							$array['msg']=$this->lang->line('Logged in Successfully');
							if ($check->verify_level2_status == 'Completed') {
								$array['login_url'] = 'dashboard';
							}
						
						} 
						else {

							$array['status'] = "success";
							$array['msg']=$this->lang->line('Enter Valid TFA Code');
						}
					} else { 
						if($this->input->post('tfa') != '')
						{
							$array['status'] = "fail";
							$array['msg']="Please don't enter 2FA, if you haven't enable it";
						} else {
							$session_data = array(
								'user_id' => $check->id,
							);
							$this->session->set_userdata($session_data);
							$this->common_model->last_activity('Login', $check->id, "", $ip_address);
							$array['status'] = "success";
							$array['user_details'] = $check;
							$array['username'] = $check->elxisenergy_email;
							$array['user_id'] = $check->id;
							$array['auth_status']=$check->randcode; 
							$array['server_time'] = $_SERVER['REQUEST_TIME'] + 48000;
							$array['msg']=$this->lang->line('Logged in Successfully');
						}
					}
				}
			}
		} else {		
			$array['status'] = "fail";
			$array['msg'] = validation_errors();
		}
        
        die(json_encode($array));

	}


// Trading Password Login Check


	public function tradingpassword_login(){

		$ip_address = get_client_ip();
        
        // When Post
		if ($this->input->post()) {

			$phonenumber = $this->input->post('phonenumber');
			$phoneotp = $this->input->post('phoneotp');

			$onecodes = $this->db->escape_str($this->input->post('phoneotp'));
			$tradingPwd =  implode('',$onecodes);

			$check = $this->common_model->getTableData('users',array('profile_mobile'=>$phonenumber))->row();

			if (!$check) {
				//vv
				$array['msg']=$this->lang->line('Invalid Log-In Details');
			} else {
				if ($check->verified != 1) {
					$array['status'] = "fail";
					$array['msg']=$this->lang->line('Please check your email to activate the account');
					
				}
				else if($check->otp!=$phoneotp)
				{
					$array['status'] = "fail";
					$array['msg']='Your OTP is Incorrect';
				}
				else if($check->tradingpassword_verification==1 && decryptIt($check->trading_password)!=$tradingPwd)
				{
					$array['status'] = "fail";
					$array['msg']= 'Your trading password is Incorrect!';
				}

				 else {
					$array['status'] = "success";
					$array['user_details'] = $check;
					$array['username'] = $check->elxisenergy_fname;
					$array['server_time'] = $_SERVER['REQUEST_TIME'] + 600;
					$array['user_id'] = $check->id;
					if ($check->randcode == 'enable' && $check->secret != '') { 
						$array['tfa_status'] = 1;
						$login_tfa = $this->input->post('2fa');
						$check1 = $this->checktfa($check->id, $login_tfa);
						if ($check1) {
							$session_data = array(
								'user_id' => $check->id,
								'loggedin_time' => $_SERVER['REQUEST_TIME'] + 600
							);
							$this->session->set_userdata($session_data);
							$_SESSION['user_id'] = $check->id;
							$this->common_model->last_activity('Login', $check->id);
							$this->session->set_flashdata('success', 'Welcome back . Logged in Successfully');
							$array['status'] = "success";
							$array['user_details'] = $check;
							$array['username'] = $check->elxisenergy_email;
							$array['user_id'] = $check->id;
							$array['auth_status']=$check->randcode; 
							$array['server_time'] = $_SERVER['REQUEST_TIME'] + 48000;
							$array['msg']=$this->lang->line('Logged in Successfully');
							if ($check->verify_level2_status == 'Completed') {
								$array['login_url'] = 'dashboard';
							}
							$array['tfa_status'] = 0;
						} else {
							$array['status'] = "fail";
							$array['msg']=$this->lang->line('Enter Valid TFA Code');
						}
					} else { 
						if($this->input->post('2fa') != '')
						{
							$array['status'] = "fail";
							$array['msg']="Please don't enter 2FA, if you haven't enable it";
						} else {
							$session_data = array(
								'user_id' => $check->id,
							);
							$this->session->set_userdata($session_data);
							$this->common_model->last_activity('Login', $check->id, "", $ip_address);
							$array['status'] = "success";
							$array['user_details'] = $check;
							$array['username'] = $check->elxisenergy_email;
							$array['user_id'] = $check->id;
							$array['auth_status']=$check->randcode; 
							$array['server_time'] = $_SERVER['REQUEST_TIME'] + 48000;
							$array['msg']=$this->lang->line('Logged in Successfully');
						}
					}
				}
			}
		} else {		
			$array['status'] = "fail";
			$array['msg'] = 'Invalid Data.Please try again!';
		}
        
        die(json_encode($array));

	}




	

	public function update_notification(){
	   $user_id=$this->session->userdata('user_id');
       $id_group = $this->input->post('id');
       $tablename = $this->input->post('tablename');
       $group_array = explode("|", $id_group);
       $update = array(
       	 "isRead" => "2"
       );

       $group_ids = array();
       if($group_array[0] == "single"){
       	 if($tablename == "coin_order"){
       	 	$this->common_model->updateTableData($tablename,array('trade_id'=>$group_array[1]),$update);
       	 }else if($tablename == "transactions"){
       	 	
       	 	$this->common_model->updateTableData($tablename,array('trans_id'=>$group_array[1]),$update);
       	 }
       	 else if($tablename == "twofa_enable"){
       	 	$this->common_model->updateTableData("user_logs",array('id'=>$group_array[1], 'meta'=>'two_factor_enabled'),$update);
       	 }else if($tablename == "twofa_disable"){
       	 	$this->common_model->updateTableData("user_logs",array('id'=>$group_array[1], 'meta'=>'two_factor_disabled'),$update);
       	 }else if($tablename == "phone_change"){
       	 	$this->common_model->updateTableData("user_logs",array('id'=>$group_array[1], 'meta'=>'phone_number_changed'),$update);
       	 }else if($tablename == "trade_enable"){
       	 	$this->common_model->updateTableData("user_logs",array('id'=>$group_array[1], 'meta'=>'trading_password_enabled'),$update);
       	 }else if($tablename == "trade_disable"){
       	 	$this->common_model->updateTableData("user_logs",array('id'=>$group_array[1], 'meta'=>'trading_password_disabled'),$update);
       	 }else if($tablename == "trade_change"){
       	 	$this->common_model->updateTableData("user_logs",array('id'=>$group_array[1], 'meta'=>'trading_password_changed'),$update);
       	 }else if($tablename == "phone_enable"){
       	 	$this->common_model->updateTableData("user_logs",array('id'=>$group_array[1], 'meta'=>'phone_verify_enable'),$update);
       	 }else if($tablename == "phone_disable"){
       	 	$this->common_model->updateTableData("user_logs",array('id'=>$group_array[1], 'meta'=>'phone_verify_disable'),$update);
       	 }
       	 else{
       	 	$this->common_model->updateTableData($tablename,array('id'=>$group_array[1]),$update);
       	 }
       	 
       }else if($group_array[0] == "group"){
       	  if($tablename == "buyback"){
       	  	$date_frmt = date('Y-m-d',strtotime($group_array[1]));
            $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_buyback where datetime LIKE '%".$date_frmt."%' AND user_id = '$user_id'");
            $this->common_model->customQuery("Update  elxisenergy_buyback set isRead = '2' where datetime LIKE '%".$date_frmt."%' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "coin_order"){
       	  	$date_frmt = $group_array[1];
            $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_coin_order where orderDate LIKE '%".$date_frmt."%' AND userId = '$user_id'");
           
            $this->common_model->customQuery("Update  elxisenergy_coin_order set isRead = '2' where orderDate LIKE '%".$date_frmt."%' AND userId = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->trade_id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "leverage"){
       	  	$date_frmt = date('Y-m-d',strtotime($group_array[1]));
            $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_leverage where leverage_start_date LIKE '%".$date_frmt."%' AND user_id = '$user_id'");
            $this->common_model->customQuery("Update  elxisenergy_leverage set isRead = '2' where leverage_start_date LIKE '%".$date_frmt."%' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "staking"){
             $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_staking where staking_start_date LIKE '%".$date_frmt."%' AND user_id = '$user_id'");
             $this->common_model->customQuery("Update  elxisenergy_staking set isRead = '2' where staking_start_date LIKE '%".$date_frmt."%' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "transactions_single"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_transactions where datetime LIKE '%".$date_frmt."%' AND type='Withdraw' AND user_id = '$user_id'");
             $this->common_model->customQuery("Update  elxisenergy_transactions set isRead = '2' where datetime LIKE '%".$date_frmt."%' AND type='Withdraw' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "deposit"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_transactions where datetime LIKE '%".$date_frmt."%' AND type='Deposit'");
             $this->common_model->customQuery("Update  elxisenergy_transactions set isRead = '2' where datetime LIKE '%".$date_frmt."%' AND type='Deposit' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }
       	  else if($tablename == "token_request_crypto"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_token_request where date_added LIKE '%".$date_frmt."%' and mode = 'crypto' and user_id = '$user_id'");
             $this->common_model->customQuery("Update  elxisenergy_token_request set isRead = '2' where date_added LIKE '%".$date_frmt."%' and mode = 'crypto' and user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "token_request_online"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_token_request where date_added LIKE '%".$date_frmt."%' and mode = 'online' and user_id = '$user_id'");
             $this->common_model->customQuery("Update  elxisenergy_token_request set isRead = '2' where date_added LIKE '%".$date_frmt."%' and mode = 'online' and user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "two_fa"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_user_logs where value LIKE '%".$date_frmt."%' AND type ='twofa'");
             $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where value LIKE '%".$date_frmt."%' AND type ='twofa' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "phone"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_user_logs where value LIKE '%".$date_frmt."%' AND type ='phonenumber'");
             $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where value LIKE '%".$date_frmt."%' AND type ='phonenumber' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "trading_pass"){
       	  	
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_user_logs where value LIKE '%".$date_frmt."%' AND type='trading_password'");

             $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where value LIKE '%".$date_frmt."%' AND type='trading_password' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "portfolio"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_user_logs where value LIKE '%".$date_frmt."%' AND type ='pricealert'");
             $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where value LIKE '%".$date_frmt."%' AND type ='pricealert' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "pricealert"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_user_logs where value LIKE '%".$date_frmt."%' AND type ='pricevalue'");
             $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where value LIKE '%".$date_frmt."%' AND type ='pricevalue' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }else if($tablename == "trading_report"){
       	  	 $date_frmt = date('Y-m-d',strtotime($group_array[1]));
             $get_openorder_buyback = $this->common_model->customQuery("Select * from elxisenergy_user_logs where value LIKE '%".$date_frmt."%' AND type ='reports'");
             $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where value LIKE '%".$date_frmt."%' AND type ='reports' AND user_id = '$user_id'");
	          foreach($get_openorder_buyback->result() as $key=> $get_openorder){
	          	$var_id = 'single|'.$get_openorder->id;
	          	array_push($group_ids, $var_id);
	          }
       	  }

       }else if($group_array[0] == "read_all"){
       	 if($tablename == "trading"){
       	 	$this->common_model->customQuery("Update  elxisenergy_leverage set isRead = '1' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_coin_order set isRead = '1' where userId = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_buyback set isRead = '1' where user_id = '$user_id'");
       	 }else if($tablename == "assets"){
       	 	$this->common_model->customQuery("Update  elxisenergy_staking set isRead = '1' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_transactions set isRead = '1' where user_id = '$user_id'");
       	 }else if($tablename == "purchases"){
       	 	$this->common_model->customQuery("Update  elxisenergy_token_request set isRead = '1' where user_id = '$user_id'");
       	 }else if($tablename == "security"){
       	 	$this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '1' where elxisenergy_user_logs.type in ('phonenumber','twofa','trading_password') and elxisenergy_user_logs.user_id = '$user_id'");
       	 }else if($tablename == "price_portfolio"){
            $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '1' where elxisenergy_user_logs.type = 'pricealert' and user_id = '$user_id'");
            $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '1' where elxisenergy_user_logs.type = 'pricevalue' and user_id = '$user_id'");
       	 }else if($tablename == "reports"){
            $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '1' where elxisenergy_user_logs.type = 'reports' and user_id = '$user_id'");
       	 }else if($tablename == "notification"){
       	 	$this->common_model->customQuery("Update  elxisenergy_leverage set isRead = '1' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_coin_order set isRead = '1' where userId = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_buyback set isRead = '1' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_staking set isRead = '1' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_transactions set isRead = '1' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_token_request set isRead = '1' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '1' where user_id = '$user_id'");
       	 }
       }else{

       	if($tablename == "trading"){
       	 	$this->common_model->customQuery("Update  elxisenergy_leverage set isRead = '2' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_coin_order set isRead = '2' where userId = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_buyback set isRead = '2' where user_id = '$user_id'");
       	 }else if($tablename == "assets"){
       	 	$this->common_model->customQuery("Update  elxisenergy_staking set isRead = '2' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_transactions set isRead = '2' where user_id = '$user_id'");
       	 }else if($tablename == "purchases"){
       	 	$this->common_model->customQuery("Update  elxisenergy_token_request set isRead = '2' where user_id = '$user_id'");
       	 }else if($tablename == "security"){
       	 	$this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where elxisenergy_user_logs.type in ('phonenumber','twofa','trading_password') and elxisenergy_user_logs.user_id = '$user_id'");
       	 }else if($tablename == "price_portfolio"){
            $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where elxisenergy_user_logs.type = 'pricealert' and user_id = '$user_id'");
            $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where elxisenergy_user_logs.type = 'pricevalue' and user_id = '$user_id'");
       	 }else if($tablename == "reports"){
            $this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where elxisenergy_user_logs.type = 'reports' and user_id = '$user_id'");
       	 }else if($tablename == "notification"){
       	 	$this->common_model->customQuery("Update  elxisenergy_leverage set isRead = '2' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_coin_order set isRead = '2' where userId = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_buyback set isRead = '2' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_staking set isRead = '2' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_transactions set isRead = '2' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_token_request set isRead = '2' where user_id = '$user_id'");
       	 	$this->common_model->customQuery("Update  elxisenergy_user_logs set isRead = '2' where user_id = '$user_id'");
       	 }

       }
       
       die(json_encode(['status' => 'success','id_group' => $id_group ,'group_ids' => $group_ids]));
	}

   public function getportfoliovalue(){

   	 $user_id=$this->session->userdata('user_id');
   	 $get_currencies = $this->common_model->getTableData('currency',array('status' => '1'))->result();
   	 $total_balance = 0;
   	 foreach($get_currencies as $get_currency){
   	 	$balance = getBalance($user_id,$get_currency->id);
   	 	$balance_inusd = $balance * (int)$get_currency->online_usdprice;
   	 	$total_balance = $total_balance + $balance_inusd;

   	 }
   	 $user_balance = number_format($total_balance,8);
   	 $date = date('y-m-d');
   	 $get_data = $this->common_model->getTableData('user_logs',array('value' => $date,'type' => 'pricealert'))->num_rows();
   	 if($get_data == 0){
   	 	$user_log_data = array(
	   	 'user_id' => $user_id,
	   	 'meta' => 'userbalance_'.$user_balance,
	   	 'type' => 'pricealert',
	   	 'value' => date('y-m-d'),
	   	 'value_time' => date('H:i:s')
	   );
	   $user_log_data_clean = $this->security->xss_clean($user_log_data);
	   $this->common_model->insertTableData('user_logs',$user_log_data_clean);

	   echo "success";

   	 }
   	 
     
   } 

   public function getpricealert(){

   	$user_id=$this->session->userdata('user_id');
    $date = date('y-m-d');
   	$get_data = $this->common_model->getTableData('user_logs',array('value' => $date,'type' => 'pricevalue'))->num_rows();
    if($get_data < 2){
    	$user_price_data = array(
	   	 'user_id' => $user_id,
	   	 'meta' => 'pricealert_20_5000',
	   	 'type' => 'pricevalue',
	   	 'value' => date('y-m-d'),
	   	 'value_time' => date('H:i:s')
	   );
	    $user_price_data_clean = $this->security->xss_clean($user_price_data);
	    $this->common_model->insertTableData('user_logs',$user_price_data_clean);

   	echo "success";
    }
   	
   	
   }

   public function getreports(){
   	 $user_id=$this->session->userdata('user_id');
   	 
   	 $orderBy_reports=array('user_logs.value','desc');
   	 $get_report_data = $this->common_model->getTableData('user_logs',array('meta' => 'user_trading_report_'.$user_id,'user_id' => $user_id),'','','','','','',$orderBy_reports)->row();
   	 $curr_trading_date = $get_report_data->value;

   	 $today_date = date('Y-m-d');
   	 $ts1 = strtotime($curr_trading_date);
     $ts2 = strtotime($today_date);
     $month1 = date('m', $ts1);
     $month2 = date('m', $ts2);
     $months = $month2 - $month1;
     
     if($months >= 6){
         
         $start_date = date('Y-m-d');
   	 $end_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( $start_date ) ) . " -6 month" ) );
   	 $query_string = "SELECT tnscn.*
					FROM `elxisenergy_transactions` `tnscn`
					JOIN `elxisenergy_currency` cy
					ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='Withdraw' AND `tnscn`.`user_id`='$user_id' AND `tnscn`.`datetime` < '$start_date' AND `tnscn`.`datetime` > '$end_date' ORDER BY tnscn.trans_id DESC";
	 $getdata = $this->common_model->customQuery($query_string)->result();

	 error_reporting(E_ALL);
		require_once('vendor/phpoffice/phpspreadsheet/autoloader_psr.php');
require_once('vendor/phpoffice/phpspreadsheet/autoloader.php');
		// Create new Spreadsheet object
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
	  // Set document properties
		$spreadsheet->getProperties()->setCreator('miraimedia.co.th')
			->setLastModifiedBy('Cholcool')
			->setTitle('how to export data to excel use phpspreadsheet in codeigniter')
			->setSubject('Generate Excel use PhpSpreadsheet in CodeIgniter')
			->setDescription('Export data to Excel Work for me!');
			
	  // add style to the header
		$styleArray = array(
			'font' => array(
			  'bold' => true,
			),
			'alignment' => array(
			  'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			  'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			),
			'borders' => array(
				'top' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
					'color' => array('rgb' => '333333'),
				),
				'bottom' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
					'color' => array('rgb' => '333333'),
				),
				'left' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
					'color' => array('rgb' => '333333'),
				),
				'right' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
					'color' => array('rgb' => '333333'),
				),
			),
			'fill' => array(
				'type'       => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
				'rotation'   => 90,
				'startcolor' => array('rgb' => '0d0d0d'),
				'endColor'   => array('rgb' => 'f2f2f2'),
			),
		);

		 // auto fit column to content
		foreach(range('A', 'E') as $columnID) {
			$spreadsheet->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
			// $spreadsheet->getActiveSheet()->getStyle($columnID)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
			// $spreadsheet->getActiveSheet()->getStyle($columnID)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
		}

		$spreadsheet->getActiveSheet()->getStyle('A1:F1')->applyFromArray($styleArray);
			$sheet->setCellValue('A1', 'Assets');
			$sheet->setCellValue('B1', 'Time');
			$sheet->setCellValue('C1', 'Volume');
			$sheet->setCellValue('D1', 'Price');
			$sheet->setCellValue('E1', 'Transaction Id');
			$sheet->setCellValue('F1', 'Status');
			
			$x = 2;
			foreach($getdata as $get){
				
				$sheet->setCellValue('A'.$x, strtoupper(getcryptocurrency($get->currency_id)));
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('B'.$x, $get->datetime);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('C'.$x, $get->amount." ".strtoupper(getcryptocurrency($get->currency_id)));
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$usd_currency_value =getUsdValue($get->currency_id)->online_usdprice;
				$usd_amount = abs(($get->amount) * ($usd_currency_value));
				$sheet->setCellValue('D'.$x, $usd_amount);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('E'.$x, $get->transaction_id);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('F'.$x, $get->status);
				$spreadsheet->getActiveSheet()->getStyle('F'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('F'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$x++;
			}
          
          
		//Create file excel.xlsx
		$fileName_new = 'user_trading_report_'.$user_id;
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
		$fileName = $fileName_new.'.xls';
		// header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		// header('Content-Disposition: attachment; filename="'.$fileName.'"');
        $writer->save($_SERVER['DOCUMENT_ROOT'].'/elxisenergy_demo'.'/assets/front/reports/'.$fileName);

         $check = $this->common_model->getTableData('users',array('id' => $user_id))->row();
         $to = getUserEmail($check->id);
		 $email_template = 55;
		 $special_vars = array();
		 $type = 'html';
         $atch=$_SERVER['DOCUMENT_ROOT'].'/elxisenergy_demo'.'/assets/front/reports/'.$fileName;
         
         $this->email_model->sendMailAttachment($to, '', '', $email_template,$special_vars,'','',$type,$atch
     );

         $user_report_data = array(
	   	 'user_id' => $user_id,
	   	 'meta' => $fileName_new,
	   	 'type' => 'reports',
	   	 'value' => date('y-m-d'),
	   	 'value_time' => date('H:i:s')
		);
		$user_report_data_clean = $this->security->xss_clean($user_report_data);
		$this->common_model->insertTableData('user_logs',$user_report_data_clean);
		echo "success";


        
        exit;
     }
   	 
   }

function account_twofactor($id){
        // print_r($id);
        $data['userid']=$id;
 
$this->load->view('front/user/twofactor_login',$data);
    }
    function twofactor_logincheck(){
       $userid=$this->input->post('userid');
       $login_tfa=$this->input->post('twofactor');
       $check1 = $this->checktfa($userid, $login_tfa); 
   
     if($check1){
     $session_data = array('user_id' => $userid,);
     $this->session->set_userdata($session_data);    
     $array['status'] = 1;
     $array['user_id'] = $userid;
	 
	 
	 $check = $this->common_model->getTableData('users',array('id'=>$userid))->row();
	 $array['status'] = 1;
	 $array['user_details'] = $check;
	 $array['username'] = getUserEmail($check->id);
	 $array['user_id'] = $check->id;
	 $array['server_time'] = $_SERVER['REQUEST_TIME'] + 48000;


     
        $array['auth_status']=$check->randcode; 
      // $array['msg']='Logged in Successfully';

       $this->session->set_flashdata('success', 'Welcome back . Logged in Successfully');
       front_redirect('account','refresh');
           
    // echo json_encode($array);
     }else{
         $this->session->set_flashdata('error', 'Please Enter Valid TFA Code');
       front_redirect('account_twofactor/'.$userid.'','refresh');

      
     }


    
}


	public function login_checks()
    {
         

              $ip_address = get_client_ip();

        $array = array('status' => 0, 'msg' => '');

        $this->form_validation->set_rules('login_detail', 'Email', 'trim|required|xss_clean');
        $this->form_validation->set_rules('login_password', 'Password', 'trim|required|xss_clean');  

        if ($this->input->post()) {

            if ($this->form_validation->run()) {

                $email = lcfirst($this->input->post('login_detail'));
                $password = $this->input->post('login_password');

                $prefix = get_prefix();
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $check = checkSplitEmail($email, $password);               

                }

                if (!$check) {
					$array['msg']=$this->lang->line('Enter Valid Login Details');
                } else {
                    if ($check->verified != 1) {
						$array['msg']=$this->lang->line('Please check your email to activate elxisenergy account');

                    } else {

                    	
                        $array['status'] = 1;
                        $array['user_details'] = $check;
		                $array['username'] = $check->elxisenergy_username;
		                $array['user_id'] = $check->id;
						$array['server_time'] = $_SERVER['REQUEST_TIME'] + 48000;

                        if ($check->randcode == 'enable' && !empty($check->secret)) {                   

                            $array['tfa_status'] = 1;
                            $login_tfa = $this->input->post('login_tfa');

                            $check1 = $this->checktfa($check->id, $login_tfa); 

                            if ($check1) {
                              

                                $session_data = array(
                                	
                                    'user_id' => $check->id,
                                    'uid' => $check->id,
                                    'login' => 'true',
                                );

                                $this->session->set_userdata($session_data);
                                $this->common_model->last_activity('Login', $check->id, "", $ip_address);
                                $this->session->set_flashdata('success', 'Welcome back . Logged in Successfully');
                                $array['msg']=$this->lang->line('Welcome back . Logged in Successfully');
                                                              
                                if ($check->verify_level2_status == 'Completed') {
                                
                                    $array['login_url'] = 'account';
                                }
                                $array['tfa_status'] = 0;
                                
                            } else {
                                                       
                                $array['msg'] = $this->lang->line('Enter Valid TFA Code');
                            }
                        } else {
                           
                            $session_data = array(
                                'user_id' => $check->id,
                                'login' => 'true',
                            );
                            $this->session->set_userdata($session_data);
                            $this->common_model->last_activity('Login', $check->id, "", $ip_address);
                            $array['tfa_status'] = 0;                          
                            


                               $this->session->set_flashdata('success', 'Welcome back . Logged in Successfully');
							   $array['msg']=$this->lang->line('Welcome back . Logged in Successfully');

                                 $array['login_url'] = 'account';

                                          

                        } 

                        /*if($this->input->post('remember_me')==1)
                        {
                        $this->session->set_userdata('remember_me', 1);
                        $sess_data = array(
                            'login_detail' => $email,
                            'login_password' => $password,
                        );
                        $this->session->set_userdata('logged_in_user', $sess_data);
                        }
                        else
                        {
                          $this->session->unset_userdata('remember_me');
                          $this->session->unset_userdata('logged_in_user');
                        } */

                    }
                }
            } else {
                $array['msg'] = validation_errors();
            }
        } else {
			$array['msg']=$this->lang->line('Login error');
        }
        die(json_encode($array));
    

    }
    function checktfa($user_id,$code)
    {
        $this->load->library('Googleauthenticator');
        $ga     = new Googleauthenticator();
        $result = $this->common_model->getTableData('users', array('id' => $user_id))->row_array();
        if(count($result)){
			$secret = $result['secret'];
			$oneCode = $ga->verifyCode($secret,$code,$discrepancy = 3);
			if($oneCode==1)
			{
				return true;
			}
			else
			{
				return false;
			}
	   }else{
			return false;
	   }
	  
    }
   function forgot_user()
    {
        //If Already logged in
        $user_id=$this->session->userdata('user_id');
        if($user_id!="")
        {   
            front_redirect('', 'refresh');
        }
        $data['site_common'] = site_common();
        $data['meta_content'] = $this->common_model->getTableData('meta_content', array('link' => 'forgot_password'))->row();
        $data['action'] = front_url() . 'forgot_user';
		$data['countries'] = $this->common_model->getTableData('countries',array('phonecode!='=>null),'','','','','','','',array('phonecode','groupby'))->result(); 	
        $data['js_link'] = 'forgot';
        // $this->load->view('front/user/text', $data);
		$this->load->view('front/user/forgot_password', $data);
    }
    function forgot_check()
    {
        //$this->form_validation->set_rules('forgot_detail', 'Email', 'trim|required|xss_clean');
        // When Post
		$array['status']=0;
        if ($this->input->post() != '')
        { 
            if ($this->input->post('forgot_email') != '')
            {
                $email = validateEmail($this->input->post('forgot_email'));

				// check for sql injection
				if(!filter_var($email, FILTER_VALIDATE_EMAIL)) 
				{
					$array['status'] = 0;
					$array['msg']=$this->lang->line('Email is not valid');
	
					echo json_encode($array); 
					exit(); 
				}

                $prefix=get_prefix();
                // Validate email
                if (filter_var($email, FILTER_VALIDATE_EMAIL))
                {
                    $check=checkSplitEmail($email);
                    $type=1;
                }
                else
                {
                    $check=checkElseEmail($email);
                    $type=2;
                }
                if (!$check)
                {
                    $array['msg']=$this->lang->line('User does not Exists');
                }
                else
                {


                     if ($check->verified != 1) {
						$array['msg']=$this->lang->line('Please check your email to activate elxisenergy account'); 

                    }else{

                        $array['status']=1;
						$key = mt_rand(100000,888888);
                        $update = array(
                        'forgotten_password_code' => $key,
                        'forgotten_password_time' => time(),
                        'forgot_url'=>0
                        );
                        $link=front_url().'reset_pw_user/'.$key;

                        $this->common_model->last_activity('Forgot Password',$check->id,"",get_client_ip());
                        $this->common_model->updateTableData('users',array('id'=>$check->id),$update);

						$to         = getUserEmail($check->id);
						$email_template = 3;
						$username=$prefix.'username';
						$site_common      =   site_common();                        
						$fb_link = $site_common['site_settings']->facebooklink;
						$tw_link = $site_common['site_settings']->twitterlink;               
						$md_link = $site_common['site_settings']->youtube_link;
						$ld_link = $site_common['site_settings']->linkedin_link;

						$special_vars = array(                  
							'###USERNAME###' => $check->$username,
							'###LINK###' => '',
							'###FB###' => $fb_link,
							'###TW###' => $tw_link,                   
							'###LD###' => $ld_link,
							'###MD###' => $md_link,
							'Click Here'=>$key,
							'Verification Link For Reset Password'=>'OTP',
							'Reset Login Password'=>'Password Reset OTP'
						);

						$this->email_model->sendMail($to, '', '', $email_template,$special_vars);
						$array['msg']=$this->lang->line('Password reset OTP is sent to your email'); 

                	}

            	}
            }
            else
            {
				$array['msg']=$this->lang->line('Please Enter Email to reset the password'); 
            }   
        }
        else
        {
			$array['msg']=$this->lang->line('Forgot Password error'); 
        }   
        die(json_encode($array));
    }



        function reset_pw_user($code = NULL)
    {
        if (!$code)
        {
            front_redirect('', 'refresh');
        }
        $profile = $this->common_model->getTableData('users', array('forgotten_password_code' => validateTextBox($code)))->row(); 
        if($profile)
        {
            if($profile->forgot_url!=1)
            {
                $expiration=15*60;
                if (time() - $profile->forgotten_password_time < $expiration)
                {

                    
                    $this->form_validation->set_rules('reset_password', 'Password', 'trim|required|xss_clean');
                    // When Post
                    if ($this->input->post())
                    {
                        // print_r($this->input->post());exit();
                        if ($this->form_validation->run())
                        {

                            $prefix=get_prefix();
                            $password=validatePassword($this->input->post('reset_password'));
                            $data = array(
                            $prefix.'password'                => encryptIt($password),
                            'forgotten_password_code' => NULL,
                            'verified'                  => 1,
                            'forgot_url'                  => 1
                            );
                            // print_r($data);exit();
                            $this->common_model->last_activity('Password Reset',$profile->id,"",get_client_ip());
                            $this->common_model->updateTableData('users',array('forgotten_password_code'=>$code),$data);
                            $this->session->set_flashdata('success','Password reset successfully Please Login');
                            front_redirect('','refresh');
                        }
                        else
                        {
                            $this->session->set_flashdata('error', 'Enter Password and Confirm Password');
                            front_redirect('reset_pw_user/'.$code,'refresh');
                        }   
                    }
                    $data['action'] = front_url() . 'reset_pw_user/'.$code;
                    $data['site_common'] = site_common();
                    $data['meta_content'] = $this->common_model->getTableData('meta_content', array('link' => 'reset_password'))->row();
                    $data['js_link'] = 'reset_password';
                    $this->load->view('front/user/reset_pwd', $data);
                }
                else
                {
                    $this->session->set_flashdata('error', $this->lang->line('Link Expired'));
                    front_redirect('', 'refresh');
                }
            }
            else
            {
                $this->session->set_flashdata('error', $this->lang->line('Already reset password using this link'));
                front_redirect('', 'refresh');
            }
        }
        else
        {
            $this->session->set_flashdata('error', $this->lang->line('Not a valid link'));
            front_redirect('', 'refresh');
        }
    }
	
	function inorder_balance()
	{
		$user_id=$this->session->userdata('user_id');
		
        if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$currency_id = $this->input->post('curr_id');
		$digital = $this->common_model->getTableData('currency',array('status'=>1,'id'=>$currency_id))->row();
		$balance = getscrowBalance($user_id,$currency_id);
		$default_currency=$this->common_model->getTableData('users',array('id'=>$user_id),'default_currency')->row();
		$default_currency = json_decode(json_encode($default_currency->default_currency));
		if($default_currency == "USD"){
			$usd_balance = abs($balance * $digital->online_usdprice);
		}else{
			$usd_balance = abs($balance * $digital->online_eurprice);
		}

		echo json_encode(array('balance' => $balance,'usd' => $usd_balance,'default_currency' => $default_currency));die;
	}
	function mainbalance()
	{
		$user_id=$this->session->userdata('user_id');
		
        if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$currency_id = $this->input->post('curr_id');
		$digital = $this->common_model->getTableData('currency',array('status'=>1,'id'=>$currency_id))->row();
		if($digital){
			$balance = getBalance($user_id,$currency_id);
			$trading_balance = getTradingBalance($user_id,$currency_id);

			$default_currency=$this->common_model->getTableData('users',array('id'=>$user_id),'default_currency')->row();
			$default_currency = json_decode(json_encode($default_currency->default_currency));
			if($default_currency == "USD"){
				$usd_balance = abs($balance * $digital->online_usdprice);
			}else{
				$usd_balance = abs($balance * $digital->online_eurprice);
			}
	
			echo json_encode(array('status' => $digital->status,'balance' => $balance,'trading_balance' => $trading_balance,'usd' => $usd_balance,'default_currency' => $default_currency,'digital_currency' => $digital));die;
		}else{
			$digital = $this->common_model->getTableData('currency',array('id'=>$currency_id))->row();
			if($digital){
				echo json_encode(array('status' => $digital->status,'msg' => $this->lang->line('Selected currency is deactivated. Please refresh the page and try again')));die;
			}else{
				echo json_encode(array('status' => $currency_id , 'msg' => $this->lang->line('Selected currency is not in the list. Please refresh the page and try again')));die;
			}
		}
	}

	function signup()
	{	
		$data['site_common'] = site_common();
		$static_content  = $this->common_model->getTableData('static_content',array('english_page'=>'home'))->result();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'login'))->row();
		$newuser_reg_status = getSiteSettings('newuser_reg_status');
		$user_id=$this->session->userdata('user_id');
		if($user_id!="" || $newuser_reg_status==0)
		{   
			$this->session->set_flashdata('error', 'Sorry for the
						inconvenience Site is under maintenance So, Login/Registration is not possible at the moment');
			front_redirect('', 'refresh');
		}

		// REGISTER POST SUBMIT STARTS
		if(!empty($_POST))
		{ 
            $this->form_validation->set_rules('username', 'Full Name', 'trim|required|xss_clean');
            $this->form_validation->set_rules('email', 'Email Address', 'trim|required|valid_email|xss_clean');
			$this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');

			if ($this->form_validation->run())
			{ 
				
				$email = $this->db->escape_str(lcfirst($this->input->post('email')));        
				$code=mt_rand(100000,999999);              
				$username=$this->input->post('username'); 
				$password=validatePassword($this->input->post('password'));             
				
	
					$check=checkSplitEmail($email);
					$prefix=get_prefix();
					$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
					
					$refferalid=substr(str_shuffle($permitted_chars), 0, 10);
					
					if($check)
					{
						$this->session->set_flashdata('error', $this->lang->line('Entered Email Address Already Exists'));
						front_redirect('signup', 'refresh');
					}
					else
					{	
						$refferalids = $this->input->post('referral');
					    if($refferalids == ''){
					        $ref = 0;
					    }else{
					        $ref = $refferalids;
					    }			

						$Exp = explode('@', $email);
						$User_name = $Exp[0];

						$activation_code = time().rand(); 
						$str=splitEmail($email);
						$ip_address = get_client_ip();

						$unique_id = mt_rand(100000,888888);
						if(!checkUserUniqueId($unique_id))
						{
							$unique_id = mt_rand(100000,888888);
						}

						$prefix=get_prefix();
						$user_data = array(
							'usertype' => '1',
							$prefix.'email'    => $str[1],
							$prefix.'username'    => $username,
							$prefix.'password'    => encryptIt($password),		
							'activation_code'  => $activation_code,
							'email_code' => $code,
							'verified'         =>'0',
							'default_currency'         =>'USD',
							'register_from'    =>'Website',
							'ip_address'       =>$ip_address,
							'browser_name'     =>getBrowser(),
							'verification_level' =>'1',
							'created_on' =>gmdate(time()),
							'email_verified' =>'0',
							'referralid' => $refferalid,
							'parent_referralid'=>$ref,
							'login_via' =>"Email",
							'unique_id' => $unique_id
						);
						$user_data_clean = $this->security->xss_clean($user_data);
						$id=$this->common_model->insertTableData('users', $user_data_clean);
						$usertype=$prefix.'type';
						$this->common_model->insertTableData('history', array('user_id'=>$id, $usertype=>encryptIt($str[0])));
						$this->common_model->last_activity('Registration',$id,"",$ip_address);
						$a=$this->common_model->getTableData('currency','id')->result_array();
						$currency = array_column($a, 'id');
						$currency = array_flip($currency);
						$currency = array_fill_keys(array_keys($currency), 0);
						$wallet=array('Exchange AND Trading'=>$currency);
						
						// $this->common_model->insertTableData('wallet', array('user_id'=>$id, 'crypto_amount'=>serialize($wallet)));

						$this->common_model->insertTableData('wallet', array('user_id'=>$id, 'crypto_amount'=>serialize($wallet),'in_order'=>serialize($wallet),'margin_order'=>serialize($wallet),'trading_amount'=>serialize($wallet)));

						$b=$this->common_model->getTableData('currency',array('type'=>'digital'),'id')->result_array();
						$currency1 = array_column($b, 'id');
						$currency1 = array_flip($currency1);
						$currency1 = array_fill_keys(array_keys($currency1), 0);

						$this->common_model->insertTableData('crypto_address', array('user_id'=>$id,'status'=>0, 'address'=>serialize($currency1)));
						

						// check to see if we are creating the user
						$email_template = 'Registration';
						$site_common      =   site_common();
						$fb_link = $site_common['site_settings']->facebooklink;
						$tw_link = $site_common['site_settings']->twitterlink;               
						$md_link = $site_common['site_settings']->youtube_link;
						$ld_link = $site_common['site_settings']->linkedin_link;

						$special_vars = array(
							'###USERNAME###' => $username,
							'###LINK###' => front_url().'verify_user/'.$activation_code
							);
						$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
						if($id){
							$array['status'] = 'success';
							$array['msg'] = $this->lang->line('Thank you for Signing up. Please check your e-mail and click on the verification link.');
								echo json_encode($array);
								exit();                
						}
						else{ 
							$array['status'] = 'error';
							$array['msg'] = $this->lang->line('Error. Try again');
							echo json_encode($array); 
							exit();              
										
						}				
					}

			} else {
				$array['msg'] = validation_errors();
				die(json_encode($array));
			}
		}
		// REGISTER POST SUBMIT ENDS
		$data['countries'] = $this->common_model->getTableData('countries',array('phonecode!='=>null),'','','','','','','',array('phonecode','groupby'))->result(); 
			
		$data['site_common'] = site_common();
		$data['action'] = front_url() . 'signup';
				
		$this->load->view('front/user/register', $data);
	
	}


	function paypal()
	{	
		$this->load->view('front/user/paypal', $data);
	}


// public function sendSMS($data='') {


//           // $sid    = "AC31a893dac3b8ae35886e0592fac217da"; 
//           //   $token  = "5d2bba26dedf81c60b36bb05d63912fd"; 
//           //   $twilio = new Client($sid, $token); 
//           //   $message = $twilio->messages->create($data['phone'],
//           //   	array("from" => "+14235566930",
//           //   	     "body" => $data['text'] 
//           //                  ) 
//           //         ); 


//                     $site_common      =   site_common();
//                      $sid= $site_common['site_settings']->twilio_sender;
//                      $token= $site_common['site_settings']->twilio_token;
//                      $token_number= $site_common['site_settings']->twilio_number;

  
        
//             // $sid = 'AC4bdeec3797b75b09cd87fa304a4b92c0';
//             // $token = '7a861dfa5106ff9b51107a1c86c9af20';
// 			// $data['phone'] ='+919944559360';
// 			// $data['text'] = "onn";
//      $client = new Client($sid, $token);

// 	 try {
// 		return $client->messages->create(
                
// 			$data['phone'],
// 			array(
				
// 				"from" => $token_number,
			   
// 				'body' => $data['text']
// 			)
// 		);
// 	} catch (Exception $e) {
// 		//echo 'Caught exception: '. $e->getMessage() ."\n";
// 		return false;
// 	}

            
//     }


public function sendSMS() {


	if($this->input->post()){

// print_r($this->input->post());

	$country_code = trim($this->input->post('countrycode'));
	$tonumber = trim($this->input->post('phonenumber'));
	
	$userrecs =  $this->common_model->getTableData('users',array('profile_mobile'=>$tonumber))->row();


	if($country_code!='' && isset($userrecs)) {
	$usernumber = '+'.$country_code.$tonumber;

	$OTP = rand(10000,999999);


	$update = $this->common_model->updateTableData('users',array('profile_mobile'=>$tonumber),array("otp"=>$OTP));

	$token_number= '+13023052998';
    $sid = 'AC98255edb46da7fdbfa9960253602f5f0';
    $token = '03c5d9e931382e410e5532bdc1749fe1';
	$data['phone'] = $usernumber;
	$data['text'] = "Your OTP  is ".$OTP.". Please Don't Share Anyone.";
    $client = new Client($sid, $token);

	 try {
		$response = $client->messages->create(
                
			$data['phone'],
			array(
				
				"from" => $token_number,
			    'body' => $data['text']
			)
		);
		if($response)
		{
			echo "success";
		}


	} catch (Exception $e) {
		echo 'Caught exception: '. $e->getMessage() ."\n";
		return false;
	}

	}


	}
	else
	{
		return false;
	}

}



    public function referral_commission($ref=""){
        $referer_id=$this->common_model->getTableData('users',array('referralid'=>$ref))->row();
        $ref_com_ins=$this->common_model->insertTableData('referral_commission',array('user_id'=>$referer_id->id));

    }
	function oldpassword_exist()
	{
		$oldpass = $this->db->escape_str($this->input->post('currentpassword'));
		$prefix=get_prefix();
		$check=$this->common_model->getTableData('users',array($prefix.'password'=>encryptIt($oldpass)))->result();
		//echo count($check);
		if (count($check)>0)
		{
			echo "true";
		}
		else
		{
			echo "false";
		}
	}
	function email_exist()
	{
		$email = $this->db->escape_str($this->input->get_post('email'));
		$check=checkSplitEmail($email);
		if (!$check)
		{
			echo "true";
		}
		else
		{
			echo "false";
		}
	}
	function phone_exist()
	{
		$phone = $this->db->escape_str($this->input->get_post('phone'));
		$check=phone_check_verified($phone);
		if (!$check)
		{
			// echo "hi";
			echo "true";
		}
		else
		{
			echo "false";
		}
	}


	function username_exist()
	{
		$username = $this->db->escape_str($this->input->get_post('username'));
		$prefix=get_prefix();
		$check=$this->common_model->getTableData('users',array($prefix.'username'=>$username));
		if ($check->num_rows()==0)
		{
			echo "true";
		}
		else
		{
			echo "false";
		}
	}	
	function get_csrf_token()
	{
		echo $this->security->get_csrf_hash();
	}	
	function logout(){
		$user_id=$this->session->userdata('user_id');
		$updateData['last_login'] = gmdate(time());
		$this->common_model->updateTableData('users',array('id'=>$user_id),$updateData);

		$this->session->unset_userdata('user_id');
		$this->session->unset_userdata('login');
		$this->session->unset_userdata('pass_changed');
		$tokenvalues = $this->session->userdata('tokenvalues');
		$depositvalues = $this->session->userdata('depositvalues');
		if(isset($tokenvalues) && !empty($tokenvalues))
		{
			$this->session->unset_userdata('tokenvalues');
		}
		if(isset($depositvalues) && !empty($depositvalues))
		{
			$this->session->unset_userdata('depositvalues');
		}
		$this->session->set_flashdata('success', $this->lang->line('Logged Out successfully'));
		front_redirect('home','refresh');
	}
	function verify_user($activation_code){
		$activation_code=$this->db->escape_str($activation_code);
		$user_id=$this->session->userdata('user_id');
		if($user_id!="")
		{	
			front_redirect('', 'refresh');
		}
		$activation=$this->common_model->getTableData('users',array('activation_code'=>urldecode($activation_code)));
		if ($activation->num_rows()>0)
		{
			$detail=$activation->row();
			if($detail->verified==1)
			{
				$this->session->set_flashdata('error', $this->lang->line('Your Email is already verified.'));
				front_redirect('login', 'refresh');
			}
			else
			{
				$this->common_model->updateTableData('users',array('id'=>$detail->id),array('verified'=>1));
				$this->common_model->last_activity('Email Verification',$detail->id,"",get_client_ip());
				$this->session->set_flashdata('success', $this->lang->line('Your Email is verified now.'));
				front_redirect('login', 'refresh');
			}
		}
		else
		{
			$this->session->set_flashdata('error', $this->lang->line('Activation link is not valid'));
			front_redirect('login', 'refresh');
		}
	}
	 
	function staking()
	{
		$data['site_common'] = site_common();
		if($_POST){
			$user_id=$this->session->userdata('user_id');
			if($user_id=="")
			{	
				$this->session->set_flashdata('error','you are not logged in');
				redirect(base_url().'home');
			}
			
			$this->form_validation->set_rules('staking_amount', 'Staking Amount', 'trim|required|xss_clean');
			$this->form_validation->set_rules('days_selection', 'Days Selection', 'trim|required|xss_clean');
			$this->form_validation->set_rules('checkbox', 'Checkbox', 'trim|required|xss_clean');
			if ($this->form_validation->run())
			{ 
				$staking_period_id = $this->input->post('days_selection_ip');
				$staking_amount = $this->input->post('staking_amount');
				$stake_date_ip = $this->input->post('stake_date_ip');
				$value_date_ip = $this->input->post('value_date_ip');
				$interest_end_ip = $this->input->post('interest_end_ip');
				$redemption_period_ip = $this->input->post('redemption_period_ip');
				$redemption_date_ip = $this->input->post('redemption_date_ip');
				$apy_ip = $this->input->post('apy_ip');

				$LEX_details = $this->common_model->getTableData('currency',array("currency_symbol"=>"LEX"))->row();
				$cur_ids = $LEX_details->id;
				$balance = getBalance($user_id,$cur_ids,'crypto');
				if($balance >= $staking_amount){
					
					$insertData['user_id'] = $user_id;
					$insertData['staking_amount'] = $staking_amount;
					$insertData['staking_period_id'] = $staking_period_id;
					$insertData['staking_start_date'] = $stake_date_ip;
					$insertData['staking_end_date'] = $interest_end_ip;
					$insertData['redemption_period'] = $redemption_period_ip;
					$insertData['redemption_date'] = $redemption_date_ip;
					$insertData['apy'] = $apy_ip;
	
					$insert = $this->common_model->insertTableData('staking', $insertData);
					
					if($insert){
						$main_balance = abs($balance - $staking_amount);
						$update_balance = updateBalance($user_id,$cur_ids,$main_balance);
						
						$usermail = getUserEmail($user_id);
						$username = getUserDetails($user_id,'elxisenergy_username');
						$enc_email = getAdminDetails('1','email_id');
						$adminmail = decryptIt($enc_email);
						$email_template = 'Staking';
						$special_vars = array(
							'###USERNAME###' => $username,
							'###staking_amount###' =>$staking_amount,
							// '###CANCEL_LINK###' => front_url().'elxisenergy_admin/deposit/reject/'.$insert
						);
						$this->email_model->sendMail($usermail, '', '', $email_template, $special_vars);
						$this->session->set_flashdata('success', "Order placed successfully");
						front_redirect('staking', 'refresh');
					}else{
						$this->session->set_flashdata('error', "Sorry!!! Something went wrong");
						front_redirect('staking', 'refresh');
					}
				}else{
					$this->session->set_flashdata('error', "Amount you entered is higher than your balance amount");
					front_redirect('staking', 'refresh');
				}
			}else{
				$this->session->set_flashdata('error', validation_errors());
				front_redirect('staking', 'refresh');
			}
		}else{
			$user_id=$this->session->userdata('user_id');
			if($user_id=="")
			{	
				$this->session->set_flashdata('error','you are not logged in');
				redirect(base_url().'home');
			}
			$data['staking_admin'] = $this->common_model->getTableData('staking_admin')->row();
			$data['staking_periods'] = $this->common_model->getTableData('staking_period')->result();

			$where=array('st.user_id'=>$user_id);
			$orderBy=array('st.id','asc');
			$joins = array('staking_period as sp'=>'sp.staking_period_id = st.staking_period_id');
			$data['staking_records'] = $this->common_model->getJoinedTableData('staking as st',$joins,$where,'','','','','','',$orderBy)->result();
			
			// $data['staking_records'] = $this->common_model->getTableData('staking',array("user_id"=>$user_id))->result();
			// $data['current_date'] = date("Y-m-d", gmdate(time()));
			$data['current_date'] = date("Y-m-d H:i:s");
			$data['consumed_staking_amount'] = $this->db->select_sum('staking_amount')->from('staking')->get()->row()->staking_amount;
			$data['consumed_staking_amount'] = ($data['consumed_staking_amount'])?$data['consumed_staking_amount']:"0";
			$data['my_staking_amount'] = $this->db->select_sum('staking_amount')->where('user_id',$user_id)->where('is_redeemed','0')->from('staking')->get()->row()->staking_amount;
			$data['action'] = front_url() . 'staking';
			
			$LEX_details = $this->common_model->getTableData('currency',array("currency_symbol"=>"LEX"))->row();
			$cur_ids = $LEX_details->id;
			$data['lex_balance'] = getBalance($user_id,$cur_ids,'crypto');

			
			$this->load->view('front/user/staking',$data); 
		}
	}

function redeem()
{
	$current_date = date('Y-m-d',time());
	$where=array('staking_end_date <='=>$current_date,'is_redeemed'=>0);
	$orderBy=array('st.id','asc');
	$joins = array('staking_period as sp'=>'sp.staking_period_id = st.staking_period_id');
	$redeem_records = $this->common_model->getJoinedTableData('staking as st',$joins,$where,'','','','','','',$orderBy)->result();
	// echo "<pre>";
	// print_r($redeem_records);
	// exit;
	if(count($redeem_records) > 0)
	{
		foreach($redeem_records as $record)
		{
			$redeem_id = $record->id;
			$user_id = $record->user_id;
			$redeem_amount = $record->staking_amount+($record->staking_amount*$record->staking_percentage)/100;
			$balance = getBalance($user_id,8);
			$updated_balance = $balance + $redeem_amount;
			updateBalance($user_id,8,$updated_balance);
			$update_data = array('is_redeemed'=>1);
				
			$update = $this->common_model->updateTableData('staking',array('id'=>$redeem_id),$update_data);
			$usermail = getUserEmail($user_id);
			$username = getUserDetails($user_id,'elxisenergy_username');
			$email_template = 'Redeem';
			$special_vars = array(
				'###USERNAME###' => $username,
				'###redeem_amount###' =>$redeem_amount
			);
			$this->email_model->sendMail($usermail, '', '', $email_template, $special_vars);
			echo "Redeemed successfully<br/>";
		}
	} else {
		echo "No Redeem records";
	}
}

function profile()
{
	$this->load->library('session');
	$this->load->library('session','form_validation');
	$user_id=$this->session->userdata('user_id');
	$data['user'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

	//$user_id='18';
	if($user_id=="")
	{  
		$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
	redirect(base_url().'home');
	}
	$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'profile'))->row();  
	$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
	$data['site_common'] = site_common();

		if($this->input->post('submit'))
		{   
			// if($this->form_validation->run())
			// {
				if ($_FILES['photo']['name']!="") 
				{
					$imageproone = $_FILES['photo']['name'];
					if($imageproone!="" && getExtension($_FILES['photo']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["photo"],'uploads/user/'.$user_id,$this->input->post('photo'));
						if($uploadimage1)
						{
							$imageproone=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('kyc_profile', 'refresh');
						} 
							//$insertData['photo_id_1']=$imageproone;
						
					}             
					
				}
				else{
					$this->session->set_flashdata('error','Selfie is missing');
					front_redirect('kyc_profile','refresh');
					// $imageproone=$this->input->post('photohide');
					
				} 
	
				if ($_FILES['addproof1']['name']!="") 
				{
					$imageprotwo = $_FILES['addproof1']['name'];
					if($imageprotwo!="" && getExtension($_FILES['addproof1']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["addproof1"],'uploads/user/'.$user_id,$this->input->post('addproof1'));
						if($uploadimage1)
						{
							$imageprotwo=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('kyc_profile', 'refresh');
						} 
							//$insertData['photo_id_3']=$imageprotwo;
					}  
					
				}
				else{
					$this->session->set_flashdata('error','Address proof is missing');
					front_redirect('kyc_profile','refresh');
					// $imageprotwo=$this->input->post('addproofhide');
				}               		
	
				if ($_FILES['idproof']['name']!="") 
				{
					$imageprothree = $_FILES['idproof']['name'];
					if($imageprothree!="" && getExtension($_FILES['idproof']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["idproof"],'uploads/user/'.$user_id,$this->input->post('idproof'));
						if($uploadimage1)
						{
							$imageprothree=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('kyc_profile', 'refresh');
						} 
						// $insertData['photo_id_2']=$imageprothree;
					}
					
				}
				else{
					$this->session->set_flashdata('error','Identity proof is missing');
					front_redirect('kyc_profile','refresh');
					// $imageprothree=$this->input->post('idproofhide');
	
				}
	
				$insertData['photo_id_1']=str_replace('https://res.cloudinary.com/smd-ex/image/upload','',$imageproone);
				$insertData['verify_level2_date'] = gmdate(time());
				$insertData['verify_level2_status'] = 'Pending';
				$insertData['photo_1_status'] = 1;
				
				$insertData['photo_id_3']=str_replace('https://res.cloudinary.com/smd-ex/image/upload','',$imageprotwo);
				$insertData['verify_level2_date'] = gmdate(time());
				$insertData['verify_level2_status'] = 'Pending';
				$insertData['photo_3_status'] = 1;
	
				// $insertData['elxisenergy_fname'] = $this->db->escape_str($this->input->post('firstname'));
				//  $insertData['elxisenergy_lname'] = $this->db->escape_str($this->input->post('lastname'));
				//  $insertData['proof_number'] = $this->db->escape_str($this->input->post('idnumber'));
				//  $insertData['elxisenergy_username'] = $this->db->escape_str($this->input->post('firstname'));	
	
				$insertData['photo_id_2']=str_replace('https://res.cloudinary.com/smd-ex/image/upload','',$imageprothree);
				$insertData['verify_level2_date'] = gmdate(time());
				$insertData['verify_level2_status'] = 'Pending';
				$insertData['photo_2_status'] = 1;
	
				$insertData_clean = $this->security->xss_clean($insertData);
				
				$update = $this->common_model->updateTableData('users',array('id'=>$user_id),$insertData_clean);
	
				if($update)
				{
					$this->session->set_flashdata('success','KYC Uploaded Successfully Please Wait For admin Approval');
					front_redirect('kyc_profile','refresh');
				}else
				{
					$this->session->set_flashdata('error', validation_errors());
	
					$this->session->set_flashdata('error','Some datas are missing');
					front_redirect('kyc_profile', 'refresh');
				}
			// }else{
			// 	$this->session->set_flashdata('error','Some datas are missing');
			// 	front_redirect('kyc_profile', 'refresh');
			// }
			
			
		} 
		$this->load->view('front/user/kyc_upload', $data); 
}





    function kyc_verified_plus()
    {        
        $this->load->library('session');
     $this->load->library('session','form_validation');
       $user_id=$this->session->userdata('user_id');
       $data['user'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

     //$user_id='18';
    if($user_id=="")
     {  
            $this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
         redirect(base_url().'home');
        }
        $data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'profile'))->row();  
        $data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
        $data['site_common'] = site_common();

    if($this->input->post('addressproofverify'))
        {           
                if ($_FILES['addproof1']['name']!="") 
                {
                    $imagepro = $_FILES['addproof1']['name'];
                    if($imagepro!="" && getExtension($_FILES['addproof1']['type']))
                    {
                        $uploadimage1=cdn_file_upload($_FILES["addproof1"],'uploads/user/'.$user_id,$this->input->post('addproof1'));
                        if($uploadimage1)
                        {
                            $imagepro=$uploadimage1['secure_url'];
                        }
                        else
                        {
                            $this->session->set_flashdata('error', 'Problem with profile picture');
                            front_redirect('kyc_verified_plus', 'refresh');
                        } 
                    }               
                    $insertData['photo_id_3']=$imagepro;
                }

                    $insertData['photo_id_3']=str_replace('https://res.cloudinary.com/elxisenergy/image/upload/','',$imagepro);
                    $insertData['verify_level3_date'] = gmdate(time());
                    $insertData['verify_level3_status'] = 'Pending';
                    $insertData['photo_3_status'] = 1;
                    $insertData_clean = $this->security->xss_clean($insertData);
                
                $update = $this->common_model->updateTableData('users',array('id'=>$user_id),$insertData_clean);

                if($update)
                {
                $this->session->set_flashdata('success','KYC Uploaded Successfully Please Wait For admin Approval');
                front_redirect('kyc_verified_plus','refresh');
                }else
            {
                $this->session->set_flashdata('error', validation_errors());

                $this->session->set_flashdata('error','Some datas are missing');
                front_redirect('kyc_verified_plus', 'refresh');
            }
             
        }   
    


      
    


             

    
                

        $this->load->view('front/user/kyc_verified_plus', $data); 
    }

	function login_history()
	{		 
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}		
		$data['users_history'] = $this->common_model->getTableData('user_activity',array('user_id'=>$user_id))->result(); 
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'login_history'))->row();
		$data['countries'] = $this->common_model->getTableData('countries')->result();
		$data['site_common'] = site_common();
		$this->load->view('front/user/profile', $data); 
	}

    function trade_history(){

		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$history_where = "WHERE userId = '$user_id'";
		$open_where = "WHERE userId = '$user_id' AND (status = 'active' OR status = 'partially')";
		
		if(isset($_REQUEST['search'])){
			$pair = $_POST['select1']."/".$_POST['select2'];
			$type = $_POST['select3'];

			if($type != 'all')
			{
				$open_where.=" AND Type = '$type'";
				$history_where.=" AND Type = '$type'";
			}
			if($pair != '/')
			{
				$open_where.=" AND pair_symbol = '$pair'";
				$history_where.=" AND pair_symbol = '$pair'";
			}
		}
		$data['open_order'] = $this->common_model->customQuery("SELECT CO.*,SUM(CO.Amount) as TotAmount,date_format(CO.datetime,'%d-%m-%Y %H:%i') as trade_time,sum(OT.filledAmount) as totalamount FROM elxisenergy_coin_order CO LEFT JOIN elxisenergy_ordertemp OT on CO.trade_id = OT.sellorderId OR CO.trade_id = OT.buyorderId $open_where GROUP BY CO.trade_id")->result();
		$data['trade_history'] = $this->common_model->customQuery("SELECT CO.*,SUM(CO.Amount) as TotAmount,date_format(CO.datetime,'%d-%m-%Y %H:%i') as trade_time,sum(OT.filledAmount) as totalamount FROM elxisenergy_coin_order CO LEFT JOIN elxisenergy_ordertemp OT on CO.trade_id = OT.sellorderId OR CO.trade_id = OT.buyorderId $history_where GROUP BY CO.trade_id")->result();

		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'trade_history'))->row();
		$data['pairs'] = $this->common_model->getTableData('trade_pairs',array('status'=>'1'),'','','','','','', array('id', 'ASC'))->result();
		$data['countries'] = $this->common_model->getTableData('countries')->result();
		$data['site_common'] = site_common();
		$this->load->view('front/user/trade_history', $data); 

	}

  function openorder_ajax()
  {
    $user_id=$this->session->userdata('user_id');
    $names = array('active', 'partially', 'margin','stoporder');
    $where_in=array('CO.status', $names);
    $where=array('CO.userId'=>$user_id);
    $get_openorder = $this->common_model->getleftJoinedTableData('coin_order as CO','',$where,'','','','','','','','',$where_in);
    if($get_openorder->num_rows() >= 1) {
      $data['open_order'] = $get_openorder->result();
    } else {
      $data['open_order'] = 0;
    }

    $trade_pair=$this->common_model->getTableData('trade_pairs',array('status'=>'1'),'id')->result();
    if(!empty($trade_pair)) {
    	$tradeId = array_column($trade_pair,"id");
    	$data['history'] = $this->transactionhistory($tradeId, $user_id);
    	//echo "<pre>";print_r($data['history']);die;
    }
    echo json_encode($data);die;
    // $data['history'] = $this->transactionhistory(95,$user_id);
    // echo "<pre>";print_r($data['history']);die;
    // $data['trade_history'] = $this->common_model->getTableData('coin_order',array('userId'=>$user_id))->result(); 

  }
    function transactionhistory($pair_id,$user_id) 
    {
      $user_id = $user_id;
      $joins = array('coin_order as b'=>'a.sellorderId = b.trade_id','coin_order as c'=>'a.buyorderId = c.trade_id');
      // $where = array('c.pair'=>$pair_id);
      $where_in = array('c.pair',$pair_id);
      // return implode(',', $pair_id) ;die;
      $where_or = '';
      $transactionhistory = $this->common_model->getJoinedTableData1('ordertemp as a',$joins,'','a.*,
         date_format(b.datetime,"%H:%i:%s") as sellertime,b.trade_id as seller_trade_id,date_format(c.datetime,"%H:%i:%s") as buyertime,c.trade_id as buyer_trade_id,a.askPrice as sellaskPrice,c.Price as buyaskPrice,b.Fee as sellerfee,c.Fee as buyerfee,b.Total as sellertotal,c.Total as buyertotal,c.pair_symbol as pair_symbol, c.status as status','',$where_or,'','','20',array('a.tempId','desc'),'',$where_in)->result();
      
        // $newquery = $this->common_model->customQuery('select trade_id, Type, Price, Amount, Fee, Total, status, date_format(datetime,"%d-%b-%Y %h:%i %p") as tradetime, pair_symbol from elxisenergy_coin_order where userId = '.$user_id.' and pair = '.$pair_id.' and status = "cancelled"')->result();

      $newquery = $this->common_model->customQuery('select trade_id, Type, Price, Amount, Fee, Total, status, date_format(datetime,"%d-%b-%Y %h:%i %p") as tradetime, pair_symbol from elxisenergy_coin_order where userId = '.$user_id.' and pair IN ('. implode(',', $pair_id) .') and status = "cancelled"')->result();

      if((isset($transactionhistory) && !empty($transactionhistory)) || (isset($newquery) && !empty($newquery)))
      {
          $transactionhistory_1 = array_merge($transactionhistory,$newquery);
          // $transactionhistory_1 = $transactionhistory;
          $historys = $transactionhistory_1;
      }
      else
      {
          $historys='0';
      }
      // return $this->db->last_query();
      return $historys;

    }

	// function usersettings()
	// {		 
	// 	$this->load->library('session');
	// 	$user_id=$this->session->userdata('user_id');
	// 	if($user_id=="")
	// 	{	
	// 		$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
	// 		redirect(base_url().'home');
	// 	}	
	// 	if($_POST){
	// 		$array['status']=0;
	// 		$from = $this->input->post('from');
	// 		if($from == "change_pass"){
	// 			$this->form_validation->set_rules('currentpassword', 'Old Password', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('cpassword', 'Confirm Password', 'trim|required|xss_clean');
	// 			if ($this->form_validation->run())
	// 			{       
	// 				$prefix = get_prefix();
	// 				$password=validatePassword($this->input->post('password'));  
	// 				$update = array(
	// 					$prefix.'password'    => encryptIt($password)
	// 				);
	
	// 				$is_updated = $this->common_model->updateTableData('users',array('id'=>$user_id),$update);
	
	// 				if($is_updated){
	// 					$array['status']=1;
	// 					$array['msg']=$this->lang->line('Password updated successfully');
	// 				}else{
	// 					$array['status']=0;
	// 					$array['msg']=$this->lang->line('New password not updated in database');
	// 				}
	// 				$this->session->set_flashdata('success', $this->lang->line('Password updated successfully'));
	// 				front_redirect('usersettings', 'refresh');
	// 			}else{
	// 				$array['msg']=validation_errors();
	// 				echo json_encode($array); 
	// 				exit();     
	// 			}
	// 		}else if($from == "bank_details"){
	// 			$this->form_validation->set_rules('bank_name', 'Bank Name', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('bank_account_number', 'Bank Account Number', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('bank_account_name', 'Bank Account Name', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('bank_swift', 'Bank Swift/IFSC', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('bank_address', 'Bank Address', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('bank_city', 'Bank City', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('bank_country', 'Bank Country', 'trim|required|xss_clean');
	// 			$this->form_validation->set_rules('bank_postalcode', 'Bank Postal Code', 'trim|required|xss_clean');
	// 			if ($this->form_validation->run())
	// 			{  
	// 				unset($_POST['from']);
	// 				$_POST['user_id'] = $user_id;
	// 				$_POST['status'] = 'Pending';
	// 				$_POST['user_status'] = '1';
	// 				$_POST['currency'] = '7';
					
	// 				$get=$this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row();
	// 				if(empty($get)) {
	// 				$insert=$this->common_model->insertTableData('user_bank_details',$_POST);
	// 				} else {
	// 				$insert=$this->common_model->updateTableData('user_bank_details',array('id'=>$get->id),$_POST);
	// 				}
	// 				if ($insert) {
	// 					$this->session->set_flashdata('success', 'Bank details Updated Successfully');
	// 					front_redirect('usersettings', 'refresh');
	// 				} else {
	// 					$this->session->set_flashdata('error', 'Something ther is a Problem .Please try again later');
	// 					exit();   
	// 				}
	// 			}else{
	// 				$array['msg']=validation_errors();
	// 				echo json_encode($array); 
	// 				exit();     
	// 			}
				
	// 		}else if($from == "2fa"){

	// 			$this->load->library('Googleauthenticator');
	// 			$this->form_validation->set_rules('google_auth_code', 'Authenticator Code', 'trim|required|xss_clean');
	// 			if($this->form_validation->run())
	// 			{
	// 				$ga = new Googleauthenticator();
	// 				$secret_code = $this->input->post('google_secret_code');
	// 				$onecode = $this->db->escape_str($this->input->post('google_auth_code'));
	// 				$users=$this->common_model->getTableData('users',array('id'=>$user_id))->row();

	// 				$code = $ga->verifyCode($secret_code,$onecode,$discrepancy = 6);
					
					
	// 				if($users->randcode != "enable")
	// 				{			
						
	// 					if($code==1)
	// 					{
	// 						$this->db->where('id',$user_id);
	// 						$data1=array('secret'  => $secret_code,'randcode'  => "enable");
	// 						$data1_clean = $this->security->xss_clean($data1);
	// 						$this->db->update('users',$data1_clean);
	// 						$user_log_data = array(
	// 	                   	 'user_id' => $user_id,
	// 	                   	 'meta' => 'two_factor_enabled',
	// 	                   	 'type' => 'twofa',
	// 	                   	 'value' => date('y-m-d'),
	// 	                   	 'value_time' => date('H:i:s')
	// 	                    );
	// 	                     $user_log_data_clean = $this->security->xss_clean($user_log_data);
	// 	                     $this->common_model->insertTableData('user_logs',$user_log_data_clean);
	// 						$this->session->set_flashdata('success','TFA Enabled successfully');
	// 						//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
	// 						front_redirect('usersettings', 'refresh');
	// 					}
	// 					else
	// 					{
					
	// 						$this->session->set_flashdata('error','Please Enter correct code to enable TFA');
	// 						//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
	// 						front_redirect('usersettings', 'refresh');
	// 						exit();   
	// 					}
	// 				}
	// 				else
	// 				{

	// 					if($code==1)
	// 					{
	// 						$this->db->where('id',$user_id);
	// 						$data1=array('secret'  => $secret_code,'randcode'  => "disable");
	// 						$data1_clean = $this->security->xss_clean($data1);
	// 						$this->db->update('users',$data1_clean);  
	// 						$this->session->set_flashdata('success','TFA Disabled successfully');
	// 						$user_log_data = array(
	// 		                   	 'user_id' => $user_id,
	// 		                   	 'meta' => 'two_factor_disabled',
	// 		                   	 'type' => 'twofa',
	// 		                   	 'value' => date('y-m-d'),
	// 		                   	 'value_time' => date('H:i:s')
	// 		                    );
	// 		                     $user_log_data_clean = $this->security->xss_clean($user_log_data);
	// 		                     $this->common_model->insertTableData('user_logs',$user_log_data_clean);
	// 						//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
	// 						front_redirect('usersettings', 'refresh');
	// 					}
	// 					else
	// 					{
	// 						$this->session->set_flashdata('error','Please Enter correct code to disable TFA');
	// 						//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
	// 						front_redirect('usersettings', 'refresh');
	// 						exit();
	// 					}
	// 				}
	// 			}else{
	// 				$this->session->set_flashdata('error',validation_errors());
	// 				front_redirect('usersettings', 'refresh');
	// 				exit();     
	// 			}
	// 		}
			
	// 	}else{
	// 		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
	// 		$data['countrieslist'] = $this->common_model->getTableData('countries')->result();
	// 		$data['bank_details'] = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row();
	// 		$data['site_common'] = site_common();
	// 		// $data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'2fa'))->row();

	// 		$this->load->library('Googleauthenticator');
	// 		$data['meta_content'] = $this->common_model->getTableData('meta_content', array('link'=>'settings'))->row();
	// 		$data['users_history']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 5 ")->result();
	// 		$data['users_history_last']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1,2")->row();

	// 		if($data['users']->randcode=="enable" || $data['users']->secret!="")
	// 		{ 
	// 			$secret = $data['users']->secret; 
	// 			$data['secret'] = $secret;
	// 			$ga     = new Googleauthenticator();
	// 			$data['url'] = $ga->getQRCodeGoogleUrl('ElxisEnergy', $secret);
	// 		}
	// 		else
	// 		{
	// 			$ga = new Googleauthenticator();
	// 			$data['secret'] = $ga->createSecret();
	// 			$data['url'] = $ga->getQRCodeGoogleUrl('ElxisEnergy', $data['secret']);
	// 			$data['oneCode'] = $ga->getCode($data['secret']);
	// 		}

	// 		$this->load->view('front/user/settings', $data); 
	// 	}	
	// } 




// Security Measures Start



	function usersettings()
	{	

		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}	
		if($_POST){



			$array['status']=0;
			$from = $this->input->post('from');
			if($from == "change_pass"){
				$this->form_validation->set_rules('currentpassword', 'Old Password', 'trim|required|xss_clean');
				$this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
				$this->form_validation->set_rules('cpassword', 'Confirm Password', 'trim|required|xss_clean');
				if ($this->form_validation->run())
				{       
					$prefix = get_prefix();
					$password=validatePassword($this->input->post('password'));  
					$update = array(  
						$prefix.'password'    => encryptIt($password)
					);

	
					$is_updated = $this->common_model->updateTableData('users',array('id'=>$user_id),$update);
					if($is_updated){
						$array['status']=1;
						$array['msg']=$this->lang->line('Password updated successfully');
					}else{
						$array['status']=0;
						$array['msg']=$this->lang->line('New password not updated in database');
					}
					$this->session->set_flashdata('success', $this->lang->line('Password updated successfully'));
					front_redirect('usersettings', 'refresh');
				}else{
					$array['msg']=validation_errors();
					echo json_encode($array); 
					exit();     
				}
			}else if($from == "bank_details"){
				$this->form_validation->set_rules('bank_name', 'Bank Name', 'trim|required|xss_clean');
				$this->form_validation->set_rules('bank_account_number', 'Bank Account Number', 'trim|required|xss_clean');
				$this->form_validation->set_rules('bank_account_name', 'Bank Account Name', 'trim|required|xss_clean');
				$this->form_validation->set_rules('bank_swift', 'Bank Swift/IFSC', 'trim|required|xss_clean');
				$this->form_validation->set_rules('bank_address', 'Bank Address', 'trim|required|xss_clean');
				$this->form_validation->set_rules('bank_city', 'Bank City', 'trim|required|xss_clean');
				$this->form_validation->set_rules('bank_country', 'Bank Country', 'trim|required|xss_clean');
				$this->form_validation->set_rules('bank_postalcode', 'Bank Postal Code', 'trim|required|xss_clean');
				if ($this->form_validation->run())
				{  
					unset($_POST['from']);
					$_POST['user_id'] = $user_id;
					$_POST['status'] = 'Pending';
					$_POST['user_status'] = '1';
					$_POST['currency'] = '7';
					
					$get=$this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row();
					if(empty($get)) {
					$insert=$this->common_model->insertTableData('user_bank_details',$_POST);
					} else {
					$insert=$this->common_model->updateTableData('user_bank_details',array('id'=>$get->id),$_POST);
					}
					if ($insert) {
						$this->session->set_flashdata('success', 'Bank details Updated Successfully');
						front_redirect('usersettings', 'refresh');
					} else {
						$this->session->set_flashdata('error', 'Something ther is a Problem .Please try again later');
						exit();   
					}
				}else{
					$array['msg']=validation_errors();
					echo json_encode($array); 
					exit();     
				}
				
			}else if($from == "2fa"){


				$onecodes = $this->db->escape_str($this->input->post('google_auth_code'));
				$onecode =  implode('',$onecodes);

				

				
				
				// $this->form_validation->set_rules('google_auth_code', 'Authenticator Code', 'trim|required|xss_clean');
				if($onecode!='')
				{
					$this->load->library('Googleauthenticator');
					$ga = new Googleauthenticator();
					$secret_code = $this->db->escape_str($this->input->post('google_secret_code'));
					
					// echo " secret_code ".$secret_code.' -- > '.$onecode;


					$users=$this->common_model->getTableData('users',array('id'=>$user_id))->row();
					$code = $ga->verifyCode($secret_code,$onecode,$discrepancy = 6);
					$tfa_emailcode = $this->db->escape_str($this->input->post('tfa_emailcode'));
					$email_code = $users->email_code;

					// echo " Secret  ".$secret_code." One Code ".$onecode;
					// echo "<br>";
					// echo " Code ---> ".$code;
					// exit();
					if($email_code==$tfa_emailcode) {

					
					if($users->randcode != "enable")
					{			
						
						if($code==1)
						{
							$this->db->where('id',$user_id);
							$data1=array('secret'  => $secret_code,'randcode'  => "enable",'tfa_verification'=>1);
							$data1_clean = $this->security->xss_clean($data1);
							$this->db->update('users',$data1_clean);
								
							$this->session->set_flashdata('success','TFA Enabled successfully');
							front_redirect('usersettings', 'refresh');
						}
						else
						{
					
							$this->session->set_flashdata('error','Please Enter correct code to enable TFA');
							front_redirect('usersettings', 'refresh');
						}
					}
					else
					{

						if($code==1)
						{
							$this->db->where('id',$user_id);
							$data1=array('secret'  => $secret_code,'randcode'  => "disable",'tfa_verification'=>0);
							$data1_clean = $this->security->xss_clean($data1);
							$this->db->update('users',$data1_clean);  
							$this->session->set_flashdata('success','TFA Disabled successfully');
							//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
							front_redirect('usersettings', 'refresh');
						}
						else
						{
							$this->session->set_flashdata('error','Please Enter correct code to disable TFA');
							//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
							front_redirect('usersettings', 'refresh');
							exit();
						}
					}

				}
				else
				{
					$this->session->set_flashdata('error','Please Enter correct Email OTP');
					front_redirect('usersettings', 'refresh');
				}	



				}else{
					$this->session->set_flashdata('error','Please Enter correct Authenticator Code');
					front_redirect('usersettings', 'refresh');
					exit();     
				}
			}
			
		}else{
			$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
			$data['countrieslist'] = $this->common_model->getTableData('countries')->result();
			$data['country_code'] = get_countrycode($data['users']->country);
			$data['bank_details'] = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row();
			$data['site_common'] = site_common();
			// $data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'2fa'))->row();

			$this->load->library('Googleauthenticator');
			$data['meta_content'] = $this->common_model->getTableData('meta_content', array('link'=>'settings'))->row();
			$data['users_history']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 5 ")->result();
			$data['users_history_last']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1,2")->row();

			if($data['users']->randcode=="enable" || $data['users']->secret!="")
			{ 
				$secret = $data['users']->secret; 
				$data['secret'] = $secret;
				$ga     = new Googleauthenticator();
				$data['url'] = $ga->getQRCodeGoogleUrl('ElxisEnergy', $secret);
			}
			else
			{
				$ga = new Googleauthenticator();
				$data['secret'] = $ga->createSecret();
				$data['url'] = $ga->getQRCodeGoogleUrl('ElxisEnergy', $data['secret']);
				$data['oneCode'] = $ga->getCode($data['secret']);
			}

			$this->load->view('front/user/settings', $data); 
		}	
	} 



public function enable_verification()
{

		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}	
		if($_POST){

			$this->form_validation->set_rules('email_otp', 'Mail Otp', 'trim|required|xss_clean');
			if ($this->form_validation->run()){   



				$this->load->library('Googleauthenticator');	
				$ga = new Googleauthenticator();
				$user_recs=$this->common_model->getTableData('users',array('id'=>$user_id))->row();
				$phoneotp =  $this->db->escape_str($this->input->post('phoneotp'));
				$email_otp =  $this->db->escape_str($this->input->post('email_otp'));

				$onecodes = $this->db->escape_str($this->input->post('2fa_phone'));
				$tfa_code =  implode('',$onecodes);

				$secret_code = $user_recs->secret;
				$code = $ga->verifyCode($secret_code,$tfa_code,$discrepancy = 6);

				$useremail_code = $user_recs->email_code;
				$otp = $user_recs->otp;

				//Phone OTP Verification
				if($phoneotp!='' && $otp!=$phoneotp)
				{
					$this->session->set_flashdata('error','Invalid Phone OTP.Please Enter Valid Phone OTP!');
					front_redirect('usersettings', 'refresh');
				}
				// Email Verification
				else if($email_otp!='' && $useremail_code!=$email_otp)
				{
					$this->session->set_flashdata('error','Invalid Mail OTP.Please Enter Valid Mail OTP!');
					front_redirect('usersettings', 'refresh');
					
				}
				// Tfa Verification
				else if($code!=1 && $user_recs->randcode == "enable")
				{
					$this->session->set_flashdata('error','Please Enter correct code to disable TFA');
					front_redirect('usersettings', 'refresh');
				}
				else
				{
					$this->db->where('id',$user_id);
					$data1=array('phone_verification'=>1);
					$data1_clean = $this->security->xss_clean($data1);
					$this->db->update('users',$data1_clean);  
					$this->session->set_flashdata('success','Phone Verification Enabled Successfully');
					front_redirect('usersettings', 'refresh');
				}

			}
			else
			{
				$this->session->set_flashdata('error',validation_errors());
				front_redirect('usersettings', 'refresh');
			}
		}
		else
		{
			$this->session->set_flashdata('error','Invalid Fields.Please Try Again Later!');
			front_redirect('usersettings', 'refresh');
		}	
}





public function trading_password()
{

		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}	
		if($_POST){

			// $this->form_validation->set_rules('email_otp', 'Mail Otp', 'trim|required|xss_clean');
			$this->form_validation->set_rules('tradingpassword', 'Trading Password', 'trim|required|xss_clean');
			if ($this->form_validation->run()){   
				
				$this->load->library('Googleauthenticator');	
				$ga = new Googleauthenticator();
				$user_recs=$this->common_model->getTableData('users',array('id'=>$user_id))->row();

				$old_pwd = $this->input->post('old_tradingpassword');

				$oldpassword =  encryptIt($this->db->escape_str($this->input->post('old_tradingpassword')));

				$email_otp =  $this->db->escape_str($this->input->post('email_otp'));
				$trading_password = encryptIt($this->db->escape_str($this->input->post('tradingpassword')));

				$onecodes = $this->db->escape_str($this->input->post('2fa_trading'));
				$tfa_code =  implode('',$onecodes);

				$secret_code = $user_recs->secret;
				$code = $ga->verifyCode($secret_code,$tfa_code,$discrepancy = 6);

				$useremail_code = $user_recs->email_code;
				$otp = $user_recs->otp;

				if($old_pwd!='' && $user_recs->tradingpassword_verification==1 && $user_recs->trading_password!=$oldpassword)
				{
					$this->session->set_flashdata('error','Your Old Trading Password Incorrect. Please Try Again');
					front_redirect('usersettings', 'refresh');
				}

				// Email Verification
				else if($email_otp!='' && $useremail_code!=$email_otp)
				{
					$this->session->set_flashdata('error','Invalid Mail OTP.Please Enter Valid Mail OTP!');
					front_redirect('usersettings', 'refresh');
					
				}
				// Tfa Verification
				else if($code!=1 && $user_recs->randcode == "enable")
				{
					$this->session->set_flashdata('error','Please Enter correct code to disable TFA');
					front_redirect('usersettings', 'refresh');
				}
				else
				{
					
					$this->db->where('id',$user_id);
					$data1=array('trading_password'=>$trading_password,'tradingpassword_verification'=>1);
					$data1_clean = $this->security->xss_clean($data1);
					$this->db->update('users',$data1_clean);    
					$this->session->set_flashdata('success','Trading Password Updated Successfully');
					front_redirect('usersettings', 'refresh');
				}

			}
			else
			{
				$this->session->set_flashdata('error',validation_errors());
				front_redirect('usersettings', 'refresh');
			}

		}
		else
		{
			$this->session->set_flashdata('error','Invalid Fields.Please Try Again Later!');
			front_redirect('usersettings', 'refresh');
		}	

}


// Change Mobile Number

public function change_mobile_number()
{


		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}	
		if($_POST){

			$this->form_validation->set_rules('email_otp', 'Mail Otp', 'trim|required|xss_clean');
			if ($this->form_validation->run()){   



				$this->load->library('Googleauthenticator');	
				$ga = new Googleauthenticator();
				$user_recs=$this->common_model->getTableData('users',array('id'=>$user_id))->row();
				$phoneotp =  $this->db->escape_str($this->input->post('old_phone_code'));
				$email_otp =  $this->db->escape_str($this->input->post('email_otp'));

				$temp_code=$this->session->userdata('temp_code');

				$newphone =  $this->db->escape_str($this->input->post('new_phone'));
				$newphone_code = $this->db->escape_str($this->input->post('phonechange_code'));

				$onecodes = $this->db->escape_str($this->input->post('phonechange_2fa'));
				$tfa_code =  implode('',$onecodes);

				$secret_code = $user_recs->secret;
				$code = $ga->verifyCode($secret_code,$tfa_code,$discrepancy = 6);

				$useremail_code = $user_recs->email_code;
				$otp = $user_recs->otp;

				// New Phone OTP Verification
				if($newphone_code=='' || $newphone_code!=$temp_code)
				{
					$this->session->set_flashdata('error','Invalid New Phone OTP.Please Enter Valid New Phone OTP!');
					front_redirect('usersettings', 'refresh');
				}
				
				//Old Phone OTP Verification

				else if($phoneotp!='' && $otp!=$phoneotp)
				{
					$this->session->set_flashdata('error','Invalid Phone OTP! Please Enter Valid Phone OTP!');
					front_redirect('usersettings', 'refresh');
				}
				// Email Verification
				else if($email_otp=='' || $useremail_code!=$email_otp)
				{
					$this->session->set_flashdata('error','Invalid Mail OTP.Please Enter Valid Mail OTP!');
					front_redirect('usersettings', 'refresh');
					
				}
				// Tfa Verification
				else if($code!=1 && $user_recs->randcode == "enable")
				{
					$this->session->set_flashdata('error','Please Enter correct code');
					front_redirect('usersettings', 'refresh');
				}
				else
				{
					$this->db->where('id',$user_id,'profile_mobile',$user_recs->profile_mobile);
					$data1=array('profile_mobile'=>$newphone);
					$data1_clean = $this->security->xss_clean($data1);
					$this->db->update('users',$data1_clean);  
					$this->session->set_flashdata('success','Phone Number Changed Successfully');
					front_redirect('usersettings', 'refresh');
				}

			}
			else
			{
				$this->session->set_flashdata('error',validation_errors());
				front_redirect('usersettings', 'refresh');
			}
		}
		else
		{
			$this->session->set_flashdata('error','Invalid Fields.Please Try Again Later!');
			front_redirect('usersettings', 'refresh');
		}	
}

// Phone Number Verification Disable Section


public function phone_verifi_disable()
{
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}	
		if($_POST){

			// $this->form_validation->set_rules('email_otp', 'Mail Otp', 'trim|required|xss_clean');
			$this->form_validation->set_rules('phone_dis_mailcode', 'Trading Password', 'trim|required|xss_clean');
			if ($this->form_validation->run()){   
				
				$this->load->library('Googleauthenticator');	
				$ga = new Googleauthenticator();
				$user_recs=$this->common_model->getTableData('users',array('id'=>$user_id))->row();

				$email_otp =  $this->db->escape_str($this->input->post('phone_dis_mailcode'));
				$trading_password = encryptIt($this->db->escape_str($this->input->post('tradingpassword')));

				$onecodes = $this->db->escape_str($this->input->post('2fa'));
				$tfa_code =  implode('',$onecodes);

				$secret_code = $user_recs->secret;
				$code = $ga->verifyCode($secret_code,$tfa_code,$discrepancy = 6);

				$useremail_code = $user_recs->email_code;
				$otp = $user_recs->otp;

				// Trading Password Verification 
				// if($user_recs->tradingpassword_verification==1 && $trading_password!=''  && $trading_password!=$user_recs->trading_password)
				// {
				// 	$this->session->set_flashdata('error','Incorrect Trading Password. Please Try Again Later.');
				// 	front_redirect('usersettings', 'refresh');
				// }
				// Email Verification
				 if($email_otp=='' && $useremail_code!=$email_otp)
				{
					$this->session->set_flashdata('error','Invalid Mail OTP.Please Enter Valid Mail OTP!');
					front_redirect('usersettings', 'refresh');
					
				}
				// Tfa Verification
				else if($code!=1 && $user_recs->randcode == "enable")
				{
					$this->session->set_flashdata('error','Please Enter correct code to disable TFA');
					front_redirect('usersettings', 'refresh');
				}
				else
				{
					$this->db->where('id',$user_id);
					$data1=array('phone_verification'=>0);
					$data1_clean = $this->security->xss_clean($data1);
					$this->db->update('users',$data1_clean);  
					$this->session->set_flashdata('success','Phone Verification Disabled Successfully');
					front_redirect('usersettings', 'refresh');
				}

			}
			else
			{
				$this->session->set_flashdata('error',validation_errors());
				front_redirect('usersettings', 'refresh');
			}

		}
		else
		{
			$this->session->set_flashdata('error','Invalid Fields.Please Try Again Later!');
			front_redirect('usersettings', 'refresh');
		}	
}


// Trading Password Disabled

public function trading_password_dis()
{
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}	
		if($_POST){

			// $this->form_validation->set_rules('email_otp', 'Mail Otp', 'trim|required|xss_clean');
			$this->form_validation->set_rules('tradingpassword', 'Trading Password', 'trim|required|xss_clean');
			if ($this->form_validation->run()){   
				
				$this->load->library('Googleauthenticator');	
				$ga = new Googleauthenticator();
				$user_recs=$this->common_model->getTableData('users',array('id'=>$user_id))->row();

				$trading_password = encryptIt($this->db->escape_str($this->input->post('tradingpassword')));

				$email_otp = $this->db->escape_str($this->input->post('trading_emailcode'));

				$onecodes = $this->db->escape_str($this->input->post('2fa'));
				$tfa_code =  implode('',$onecodes);

				$secret_code = $user_recs->secret;
				$code = $ga->verifyCode($secret_code,$tfa_code,$discrepancy = 6);

				$useremail_code = $user_recs->email_code;
				$otp = $user_recs->otp;

				// Trading Password Verification 
				if($trading_password!=$user_recs->trading_password)
				{
					$this->session->set_flashdata('error','Incorrect Trading Password. Please Try Again Later.');
					front_redirect('usersettings', 'refresh');
				}
				// Email Verification
				else if($email_otp=='' && $useremail_code!=$email_otp)
				{
					$this->session->set_flashdata('error','Invalid Mail OTP.Please Enter Valid Mail OTP!');
					front_redirect('usersettings', 'refresh');
					
				}
				// Tfa Verification
				else if($code!=1 && $user_recs->randcode == "enable")
				{
					$this->session->set_flashdata('error','Please Enter correct code to disable TFA');
					front_redirect('usersettings', 'refresh');
				}
				else
				{
					$this->db->where('id',$user_id);
					$data1=array('tradingpassword_verification'=>0);
					$data1_clean = $this->security->xss_clean($data1);
					$this->db->update('users',$data1_clean);  
					$this->session->set_flashdata('success','Trading Password Verification Disabled Successfully');
					front_redirect('usersettings', 'refresh');
				}

			}
			else
			{
				$this->session->set_flashdata('error',validation_errors());
				front_redirect('usersettings', 'refresh');
			}

		}
		else
		{
			$this->session->set_flashdata('error','Invalid Fields.Please Try Again Later!');
			front_redirect('usersettings', 'refresh');
		}	
}


public function mailotp()
{

	$user_id=$this->session->userdata('user_id');
	if($user_id=="")
	{	
		$data['msg'] = 'You are not logged in';
		$data['status'] = 0;
	} else {

	
	$userrecs =  $this->common_model->getTableData('users',array('id'=>$user_id))->row();
	// print_r($userrecs);
	if(isset($userrecs))
		{
			$mail = getUserEmail($userrecs->id);
			$code=mt_rand(100000,999999); 
			$special_vars = array(
			'###USERNAME###' => $userrecs->elxisenergy_fname,
			'###OTP###' => $code
			);
			
			$this->email_model->sendMail($mail, '', '', 'sms_otp', $special_vars);
			$update = $this->common_model->updateTableData('users',array('id'=>$userrecs->id),array("email_code"=>$code));
			if($update) {
				$data['msg'] = 'Mail OTP Sent Successfully!';
				$data['status'] = 1;
			}



		}
	else
		{
			$data['msg'] = 'Invalid Please try Again!';
			$data['status'] = 0;
		}

	}	
		echo json_encode($data);

}




// Security Measures End



	function authentication()
	{		 
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}		
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['countries'] = $this->common_model->getTableData('countries')->result();
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'2fa'))->row();
		$this->load->view('front/user/twofa', $data); 
	} 

	function two_factor()
    {   
		
		$user_id=$this->session->userdata('user_id');

		if($user_id=="")
		{ 
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'');
		}

		$this->load->library('Googleauthenticator');
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content', array('link'=>'settings'))->row();
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['users_history']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 5 ")->result();
		$data['users_history_last']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1,2")->row();

		if($data['users']->randcode=="enable" || $data['users']->secret!="")
		{ 
			$secret = $data['users']->secret; 
			$data['secret'] = $secret;
			$ga     = new Googleauthenticator();
			$data['url'] = $ga->getQRCodeGoogleUrl('ElxisEnergy', $secret);
		}
		else
		{
			$ga = new Googleauthenticator();
			$data['secret'] = $ga->createSecret();
			$data['url'] = $ga->getQRCodeGoogleUrl('ElxisEnergy', $data['secret']);
			$data['oneCode'] = $ga->getCode($data['secret']);
		}
		
		if(isset($_POST['tfa_sub']))
		{
			$ga = new Googleauthenticator();
			$secret_code = $this->db->escape_str($this->input->post('secret'));
			$onecode = $this->db->escape_str($this->input->post('code'));

			$code = $ga->verifyCode($secret_code,$onecode,$discrepancy = 6);
		
			if($data['users']->randcode != "enable")
			{			
				if($code==1)
				{
					$this->db->where('id',$user_id);
					$data1=array('secret'  => $secret_code,'randcode'  => "enable");
					$data1_clean = $this->security->xss_clean($data1);
					$this->db->update('users',$data1_clean);
						
					$this->session->set_flashdata('success','TFA Enabled successfully');
					//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
					front_redirect('two_factor', 'refresh');
				}
				else
				{
			
					$this->session->set_flashdata('error','Please Enter correct code to enable TFA');
					//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
					front_redirect('two_factor', 'refresh');
				}
			}
			else
			{

				if($code==1)
				{
					$this->db->where('id',$user_id);
					$data1=array('secret'  => $secret_code,'randcode'  => "disable");
					$data1_clean = $this->security->xss_clean($data1);
					$this->db->update('users',$data1_clean);  
					$this->session->set_flashdata('success','TFA Disabled successfully');
					//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
					front_redirect('two_factor', 'refresh');
				}
				else
				{
					$this->session->set_flashdata('error','Please Enter correct code to disable TFA');
					//front_redirect('Front/two_factor_authentication?page=tfa', 'refresh');
					front_redirect('two_factor', 'refresh');
				}
			}
		}
		//$data['site_common'] = site_common();
		//$data['countries'] = $this->common_model->getTableData('countries')->result();
		//$data['currencies'] = $this->common_model->getTableData('currency',array('type'=>'fiat','status'=>1))->result();
       	$this->load->view('front/user/google',$data);
    }

	function editprofileView()
	{	
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}	
		$user_id = $this->session->userdata('user_id');

		$data['countries'] = $this->common_model->getTableData('countries')->result_array();
		$data['currencies'] = $this->common_model->getTableData('currency')->result_array();
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['email'] = getUserEmail($user_id);
        
		$referralid = $this->common_model->getTableData('users',array('id'=>$user_id),'referralid')->row()->referralid;
		$qr_url = base_url().'signup?referralid='.$referralid;
		// $this->load->library('Googleauthenticator');
		// $ga     = new Googleauthenticator();
		$data['url'] ="https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$qr_url&choe=UTF-8&chld=L";;
		$data['share_url']=$qr_url;
		$data['site_common'] = site_common();
		$this->load->view('front/user/editprofile',$data);
	}
	function bank_details()
	{		 
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}		
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

		$data['countries'] = $this->common_model->getTableData('countries')->result();
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'bank-details'))->row();
		$this->load->view('front/user/bank-details', $data); 
	}
	function changepassword()
	{	


		//$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('success','you are not logged in');
			redirect(base_url().'home');
		}

		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
        $old = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
       
 

		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'change_password'))->row();
		if(isset($_POST['change_pwd']))
		{

            $prefix = get_prefix();
		 	$oldpassword = encryptIt($this->input->post("currentpassword"));
            $newpassword = encryptIt($this->input->post("password"));
            $confirmpassword = encryptIt($this->input->post("cpassword"));

     
			
			// Check old pass is correct/not
			$password = $prefix.'password';

			if($oldpassword == $old->elxisenergy_password)
			{

                   //check new pass is equal to confirm pass
				if($newpassword == $confirmpassword)
				{
    
					//$this->db->where('id',$user_id);
					$data=array($prefix.'password'  => $newpassword);

                  
					$this->common_model->updateTableData('users',array('id'=>$user_id),$data);
					$this->session->set_flashdata('success','Password changed successfully');
					front_redirect('change_password', 'refresh');
				}
				else
				{
					$this->session->set_flashdata('error','Confirm password must be same as new password');
					front_redirect('change_password', 'refresh');
				}
			}
			else
			{
				$this->session->set_flashdata('error','Your old password is wrong');
				front_redirect('change_password', 'refresh');
			}			
		}		
		$data['site_common'] = site_common();
        $data['users_history']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 5 ")->result();
        $data['users_history_last']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1,2")->row();

		$this->load->view('front/user/changepassword', $data); 
	}
	function editprofile()
	{		
		
		$this->load->library('session','form_validation');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('success', 'Please Login');
			redirect(base_url().'home');
		}
		if($_POST)
		{			
			// $this->form_validation->set_rules('elxisenergy_password', 'Password', 'required|xss_clean');
			$this->form_validation->set_rules('elxisenergy_birthday', 'Birthday', 'required|xss_clean');
			$this->form_validation->set_rules('country', 'Country', 'required|xss_clean');
			$this->form_validation->set_rules('default_currency', 'Default Currency', 'required|xss_clean');
			$this->form_validation->set_rules('referralid', 'Referral Id', 'required|xss_clean');
			$this->form_validation->set_rules('profile_mobile', 'Mobile', 'required|xss_clean');

			if($this->form_validation->run())
			{
				// $email = $this->db->escape_str($this->input->post('elxisenergy_email'));
				$email = "mohamednooreee02@gmail.com";
				
				$str=splitEmail($email);
				$insertData['elxisenergy_fname'] = $this->db->escape_str($this->input->post('elxisenergy_fname'));
				$insertData['elxisenergy_lname'] = $this->db->escape_str($this->input->post('elxisenergy_lname'));
				// $insertData['elxisenergy_email'] = $str[1];
				// $insertData['elxisenergy_password'] =  encryptIt($this->db->escape_str($this->input->post('elxisenergy_password')));
				$insertData['elxisenergy_bio'] = $this->db->escape_str($this->input->post('elxisenergy_bio'));			
				$insertData['elxisenergy_birthday'] = $this->db->escape_str($this->input->post('elxisenergy_birthday'));
				$insertData['country']	 	   = $this->db->escape_str($this->input->post('country'));
				$insertData['parent_referralid']	= $this->db->escape_str($this->input->post('referralid'));
				$insertData['profile_mobile']	 	   = $this->db->escape_str($this->input->post('profile_mobile'));
				$insertData['elxisenergy_website']	= $this->db->escape_str($this->input->post('elxisenergy_website'));
				$insertData['default_currency']	= $this->db->escape_str($this->input->post('default_currency'));
			
				$condition = array('id' => $user_id);
				;
				$check = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
				if($check->profile_mobile != $this->db->escape_str($this->input->post('profile_mobile'))){
                   $user_log_data = array(
                   	 'user_id' => $user_id,
                   	 'meta' => 'phone_number_changed',
                   	 'type' => 'phonenumber',
                   	 'value' => date('y-m-d'),
                   	 'value_time' => date('H:i:s')
                   );
                   $user_log_data_clean = $this->security->xss_clean($user_log_data);
                   $this->common_model->insertTableData('user_logs',$user_log_data_clean);
				}
				
				$insertData_clean = $this->security->xss_clean($insertData);
				$insert = $this->common_model->updateTableData('users',$condition, $insertData_clean);

				
				if ($_FILES['profile-image']['name']!="") 
				{
					$imagepro = $_FILES['profile-image']['name'];
					if($imagepro!="" && getExtension($_FILES['profile-image']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["profile-image"],'uploads/user/'.$user_id,'');
						
						if($uploadimage1)
						{
							$imagepro=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('profile/edite', 'refresh');
						} 
					}				
					$insertData['profile_picture']=$imagepro;
				}
				
				$insertData_clean = $this->security->xss_clean($insertData);
				$insert = $this->common_model->updateTableData('users',$condition, $insertData_clean);
				if ($insert) {
					$profileupdate = $this->common_model->updateTableData('users',array('id' => $user_id), array('profile_status'=>1));
					$this->session->set_flashdata('success', 'Profile details Updated Successfully');
					front_redirect('profile/edit', 'refresh');
				} else {
					$this->session->set_flashdata('error', 'Something ther is a Problem .Please try again later');
					front_redirect('profile/edit', 'refresh');
				}
			}
			else
			{
				$this->session->set_flashdata('error',validation_errors());
				front_redirect('profile/edit', 'refresh');
			}
		}		
		front_redirect('profile/edit', 'refresh'); 
	}

	


	function update_profileimage()
	{
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			front_redirect('', 'refresh');
		}
		if($_FILES)
		{			
				$prefix=get_prefix();
				$imagepro = $_FILES['profile']['name'];
				if($imagepro!="" && getExtension($_FILES['profile']['type']))
				{
					$uploadimage1=cdn_file_upload($_FILES["profile"],'uploads/user/'.$user_id,$this->input->post('profile'));
					if($uploadimage1)
					{
						$imagepro=$uploadimage1['secure_url'];
					}
					else
					{
						$this->session->set_flashdata('error', $this->lang->line('Problem with yourself holding photo ID'));
						front_redirect('profile', 'refresh');
					} 
				}
				else 
				{
					$imagepro='';
				}

				$insertData = array();
				$insertData['profile_picture']=$imagepro;				
				$condition = array('id' => $user_id);
				$insert = $this->common_model->updateTableData('users',$condition, $insertData);
				if ($insert) {
					$this->session->set_flashdata('success',$this->lang->line('Profile image Updated Successfully'));
					front_redirect('profile', 'refresh');
				} else {
					$this->session->set_flashdata('error', $this->lang->line('Something ther is a Problem .Please try again later'));
					front_redirect('profile', 'refresh');
				}			
		}
    }



public function email(){
    $user_id=$this->session->userdata('user_id');

 $data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

$this->load->view('front/user/email',$data);
}

public function mail_check(){

     if ($this->input->post('email_update')) 
	 {
        $ip_address = get_client_ip();
        $email=$this->input->post('email_update');
        $user_id=$this->session->userdata('user_id');
		
       	$check_data=array('id'=>$user_id);
   		$check = $this->common_model->getTableData('users',$check_data)->row();
		//$array['user_details'] = $check;
		$array['username'] = $check->elxisenergy_username;
		$array['user_id'] = $check->id;
		$code=mt_rand(100000,999999);   

		$email_check = checkELXISENERGYSplitEmail($email);
		if($email_check)
		{
			$array['status'] = 'error';
			$array['msg'] = $this->lang->line('Email already exists.');
			echo json_encode($array); 
			exit();   
		} else {
			$str = splitEmail($email);

			$update=array(
				'email_code'=>$code,
				//'profile_email'=>$email,
				'elxisenergy_email'=>$str[1]);

			$this->common_model->updateTableData('users',array('id'=>$check->id),$update);

			$this->common_model->updateTableData('history',array('user_id'=>$check->id),array('elxisenergy_type'=>encryptIt($str[0])));

			$email_template = 'Registration OTP';
			$site_common      =   site_common();
			$fb_link = $site_common['site_settings']->facebooklink;
			$tw_link = $site_common['site_settings']->twitterlink;               
			$md_link = $site_common['site_settings']->youtube_link;
			$ld_link = $site_common['site_settings']->linkedin_link;

			$special_vars = array(
			'###USERNAME###' => $email,
			// '###LINK###' => front_url().'verify_user/'.$activation_code,
			'###CODE###' => $code,
			'###FB###' => $fb_link,
			'###TW###' => $tw_link,                   
			'###LD###' => $ld_link,
			'###MD###' => $md_link

			);
			$id=$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
			if($id){
				$array['status'] = 'success';
				$array['msg'] = $this->lang->line('OTP send Successfully');
				echo json_encode($array);
				exit();                
			}
			else{ 
				$array['status'] = 'error';
				$array['msg'] = $this->lang->line('Error. Try again');
				echo json_encode($array); 
				exit();              
							
			}
		}                    
                 
	}


 $user_id=$this->session->userdata('user_id');
     if ($this->input->post('emailotp')) {
           $emailotp=$this->input->post('emailotp');
         

      $checkdata=array(
        'email_code'=>$emailotp,
        
     );



     $check = $this->common_model->getTableData('users',$checkdata)->row();

     if($check){
 $user_id=$this->session->userdata('user_id');
     $updatedata = array('email_verified' => 1,'email_code'=>'');
$this->common_model->updateTableData('users',array('id'=>$user_id),$updatedata);
	  $array['status'] = 'success';
	  $array['msg'] = $this->lang->line('Verified Successfully');
      echo  json_encode($array);
exit();
           
    // echo json_encode($array);
     }else{
		$array['status'] = 'error';
		$array['msg'] = $this->lang->line('Invalid OTP Details');
       echo  json_encode($array);
exit();
      
     }


}


    }



    function kyc(){

      $user_id=$this->session->userdata('user_id');
      if($user_id=="")
      {   
          $this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
          redirect(base_url().'home');
      }    
      $data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();



$data['site_common'] = site_common();
$data['sta_content'] = $this->common_model->getTableData('static_content', array('slug' => 'refferal_program'))->row();
$data['country'] = $this->common_model->getTableData('countries')->result();
$data['user'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

$data['users_history']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1 ")->result();
$data['users_history_last']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1,2")->row();

$this->load->view('front/user/kyc',$data);

}


    function kyc_profile(){
        $user_id=$this->session->userdata('user_id');
        if($user_id=="")
        {   
            $this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
            redirect(base_url().'home');
        }
        $data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'profile'))->row();  
        // if($this->input->post('submitdata')){
          // $this->form_validation->set_rules('firstname', 'firstname', 'required|xss_clean');
          //  $this->form_validation->set_rules('lastname', 'lastname', 'required|xss_clean');
          //  $this->form_validation->set_rules('idnumber', 'idnumber', 'required|xss_clean');

        $data['user'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row(); 
        if($data['user']->elxisenergy_fname=='') {

			$fname=$this->db->escape_str($this->input->post('firstname'));
			$lname=$this->db->escape_str($this->input->post('lastname'));
			$proof_number=$this->db->escape_str($this->input->post('idnumber'));
			$country=$this->db->escape_str($this->input->post('country'));

			$insertUser=array('elxisenergy_fname'=>$fname,
				'elxisenergy_lname'=>$lname,
				'elxisenergy_username'=>$fname,
				'proof_number'=>$proof_number,
				'country'=>$country,
				'photo_4_status'=>1  );

			$cond = array('id'=>$user_id);
			$insert = $this->common_model->updateTableData('users',$cond, $insertUser);
			$this->session->set_flashdata('success', 'Updated Kyc Successfully');


           //redirect('account/#kyc', 'refresh');
        }
		// }
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['users_history']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 5 ")->result();
		$data['users_history_last']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1,2")->row();  

     	$data['site_common'] = site_common();
      	$data['sta_content'] = $this->common_model->getTableData('static_content', array('slug' => 'refferal_program'))->row();
    
		$this->load->view('front/user/kyc_upload',$data);

    }



    function kyc_verification()
	{		 
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', 'you are not logged in');
			redirect(base_url().'home');
		}
		if($_POST)
		{
			$this->form_validation->set_rules('proof_number', 'Proof Number', 'required|xss_clean');
			$this->form_validation->set_rules('pan_number', 'Pan Number', 'required|xss_clean');
			if($this->form_validation->run())
			{
				$insertData['id_type'] = $this->db->escape_str($this->input->post('id_type'));
				$insertData['proof_number'] = $this->db->escape_str($this->input->post('proof_number'));
				$insertData['pan_number'] = $this->db->escape_str($this->input->post('pan_number'));
				$insertData['elxisenergy_phone'] = $this->db->escape_str($this->input->post('mobile'));
				//$insertData['reference_currency'] = $this->db->escape_str($this->input->post('ref_currency'));
				//$insertData['street_address'] = $this->db->escape_str($this->input->post('address'));
				//$insertData['city'] = $this->db->escape_str($this->input->post('city'));
				//$insertData['state'] = $this->db->escape_str($this->input->post('state'));
				//$insertData['postal_code'] = $this->db->escape_str($this->input->post('postal_code'));

				//$paypal_email = $this->input->post('paypal_email');
				//if(isset($paypal_email) && !empty($paypal_email)){
				//$insertData['paypal_email'] = $this->db->escape_str($paypal_email);
			//}				
				//$insertData['verification_level'] = '2';
				//$insertData['verify_level2_date'] = gmdate(time());
				//$insertData['country']	 	   = $this->db->escape_str($this->input->post('country'));
				// $insertData['elxisenergy_phone']	= $this->db->escape_str($this->input->post('phone'));
				$condition = array('id' => $user_id);
				$insertData_clean = $this->security->xss_clean($insertData);
				$insert = $this->common_model->updateTableData('users',$condition, $insertData_clean);

				if ($_FILES['photo_id_1']['name']!="") 
				{
					$imagepro = $_FILES['photo_id_1']['name'];
					if($imagepro!="" && getExtension($_FILES['photo_id_1']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["photo_id_1"],'uploads/user/'.$user_id,$this->input->post('photo_id_1'));
						if($uploadimage1)
						{
							$imagepro=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('profile', 'refresh');
						} 
					}				
					$insertData['photo_id_1']=str_replace('https://res.cloudinary.com/elxisenergy/image/upload/','',$imagepro);								
					$insertData['verify_level2_date'] = gmdate(time());
					$insertData['verify_level2_status'] = 'Pending';
					$insertData['photo_1_status'] = 1;
				}
				if ($_FILES['photo_id_2']['name']!="") 
				{
					$imagepro1 = $_FILES['photo_id_2']['name'];
					if($imagepro1!="" && getExtension($_FILES['photo_id_2']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["photo_id_2"],'uploads/user/'.$user_id,$this->input->post('photo_id_2'));
						if($uploadimage1)
						{
							$imagepro1=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('profile', 'refresh');
						} 
					}				
					$insertData['photo_id_2']=str_replace('https://res.cloudinary.com/elxisenergy/image/upload/','',$imagepro1);
					$insertData['verify_level2_date'] = gmdate(time());
					$insertData['verify_level2_status'] = 'Pending';
					$insertData['photo_2_status'] = 1;
				}
				if ($_FILES['photo_id_3']['name']!="") 
				{
					$imagepro2 = $_FILES['photo_id_3']['name'];
					if($imagepro2!="" && getExtension($_FILES['photo_id_3']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["photo_id_3"],'uploads/user/'.$user_id,$this->input->post('photo_id_3'));
						if($uploadimage1)
						{
							$imagepro2=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('profile', 'refresh');
						} 
					}				
					$insertData['photo_id_3']=str_replace('https://res.cloudinary.com/elxisenergy/image/upload/','',$imagepro2);
					$insertData['verify_level2_date'] = gmdate(time());
					$insertData['verify_level2_status'] = 'Pending';
					$insertData['photo_3_status'] = 1;
				}
				if ($_FILES['photo_id_4']['name']!="") 
				{
					$imagepro3 = $_FILES['photo_id_4']['name'];
					if($imagepro3!="" && getExtension($_FILES['photo_id_4']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["photo_id_4"],'uploads/user/'.$user_id,$this->input->post('photo_id_4'));
						if($uploadimage1)
						{
							$imagepro3=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('profile', 'refresh');
						} 
					}				
					$insertData['photo_id_4']=str_replace('https://res.cloudinary.com/elxisenergy/image/upload/','',$imagepro3);
					$insertData['verify_level2_date'] = gmdate(time());
					$insertData['verify_level2_status'] = 'Pending';
					$insertData['photo_4_status'] = 1;
				}
				$insertData_clean = $this->security->xss_clean($insertData);
				$insert = $this->common_model->updateTableData('users',$condition, $insertData_clean);
				if ($insert) {
					$profileupdate = $this->common_model->updateTableData('users',array('id' => $user_id), array('kyc_status'=>1));
					$this->session->set_flashdata('success', 'Your details have been sent to our team for verification');
					front_redirect('profile', 'refresh');
				} else {
					$this->session->set_flashdata('error', 'Something ther is a Problem .Please try again later');
					front_redirect('profile', 'refresh');
				}
			}
			else
			{
				$this->session->set_flashdata('error','Some datas are missing');
				front_redirect('profile', 'refresh');
			}
		}
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'kyc_verification'))->row();		
		$this->load->view('front/user/profile', $data); 
	}
	function address_verification()	{
		$user_id=$this->session->userdata('user_id');
			if($user_id=="")
			{	
				front_redirect('', 'refresh');
			}
			if($_FILES)	{				
				$prefix=get_prefix();
				$image = $_FILES['photo_id_1']['name'];
					if($image!="" && getExtension($_FILES['photo_id_1']['type']))
					{		
						$uploadimage=cdn_file_upload($_FILES["photo_id_1"],'uploads/user/'.$user_id,$this->db->escape_str($this->input->post('photo_id_1')));
						if($uploadimage)
						{
							$image=$uploadimage['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error','Problem with your scan of photo id');
							front_redirect('settings', 'refresh');
						}
					} 
					elseif($this->input->post('photo_ids_1')=='')
					{
						$image = $this->db->escape_str($this->input->post('photo_ids_1'));
					}
					else 
					{ 
						$image='';
					}
					$insertData = array();
					$insertData['photo_id_1'] = str_replace('https://res.cloudinary.com/elxisenergy/image/upload/','',$image);					
					$insertData['verify_level2_date'] = gmdate(time());
					$insertData['verify_level2_status'] = 'Pending';
					$insertData['photo_1_status'] = 1;	                
					$condition = array('id' => $user_id);
					$insertData_clean = $this->security->xss_clean($insertData);
					$insert = $this->common_model->updateTableData('users',$condition, $insertData_clean);
					if($insert !='' && $_FILES["photo_id_1"]['name'] !='') {
						$this->session->set_flashdata('success','Your details have been sent to our team for verification');
						front_redirect('settings', 'refresh');
					} 
	                elseif($insert !='' && $_FILES["photo_id_1"]['name'] =='') {
						$this->session->set_flashdata('success', 'Your Address proof cancelled successfully');
						front_redirect('settings', 'refresh');
					}
					else {
						$this->session->set_flashdata('error','Unable to send your details to our team for verification. Please try again later!');
						front_redirect('settings', 'refresh');
					}
			}
	}
	function id_verification()	{
		$user_id=$this->session->userdata('user_id');
			if($user_id=="")
			{	
				front_redirect('', 'refresh');
			}
			if($_FILES)
			{
				$image = $_FILES['photo_id_2']['name'];
					if($image!="" && getExtension($_FILES['photo_id_2']['type']))
					{		
						$uploadimage=cdn_file_upload($_FILES["photo_id_2"],'uploads/user/'.$user_id,$this->db->escape_str($this->input->post('photo_id_2')));
						if($uploadimage)
						{
							$image=$uploadimage['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error','Problem with your scan of photo id');
							front_redirect('settings', 'refresh');
						}
					} 
					elseif($this->input->post('photo_ids_2')=='')
					{
						$image = $this->db->escape_str($this->input->post('photo_ids_2'));
					}
					else 
					{ 
						$image='';
					}
					$insertData = array();
					$insertData['photo_id_2'] = str_replace('https://res.cloudinary.com/elxisenergy/image/upload/','',$image);
					$insertData['verify_level2_date'] = gmdate(time());
					$insertData['verify_level2_status'] = 'Pending';
					$insertData['photo_2_status'] = 1;
					$condition = array('id' => $user_id);
					$insertData_clean = $this->security->xss_clean($insertData);
					$insert = $this->common_model->updateTableData('users',$condition, $insertData_clean);
					if($insert !='' && $_FILES["photo_id_2"]['name'] !='') {
						$this->session->set_flashdata('success','Your details have been sent to our team for verification');
						front_redirect('settings', 'refresh');
					} 
	                elseif($insert !='' && $_FILES["photo_id_2"]['name'] =='') {
						$this->session->set_flashdata('success', 'Your ID proof cancelled successfully');
						front_redirect('settings', 'refresh');
					}
					else {
						$this->session->set_flashdata('error','Unable to send your details to our team for verification. Please try again later!');
						front_redirect('settings', 'refresh');
					}
			}
	}
	// function photo_verification(){
	// 	$user_id=$this->session->userdata('user_id');
	// 		if($user_id=="")
	// 		{	
	// 			front_redirect('', 'refresh');
	// 		}
	// 		if($_FILES)
	// 		{
	// 			$image = $_FILES['photo_id_3']['name'];
	// 				if($image!="" && getExtension($_FILES['photo_id_3']['type']))
	// 				{		
	// 					$uploadimage=cdn_file_upload($_FILES["photo_id_3"],'uploads/user/'.$user_id,$this->db->escape_str($this->input->post('photo_id_3')));
	// 					if($uploadimage)
	// 					{
	// 						$image=$uploadimage['secure_url'];
	// 					}
	// 					else
	// 					{
	// 						$this->session->set_flashdata('error', 'Problem with your scan of photo id');
	// 						front_redirect('settings', 'refresh');
	// 					}
	// 				} 
	// 				elseif($this->input->post('photo_ids_3')=='')
	// 				{
	// 					$image = $this->db->escape_str($this->input->post('photo_ids_3'));
	// 				}
	// 				else 
	// 				{ 
	// 					$image='';
	// 				}
	// 				$insertData['photo_id_3'] = $image;
	// 				$insertData['verify_level2_date'] = gmdate(time());
	// 				$insertData['verify_level2_status'] = 'Pending';
	// 				$insertData['photo_3_status'] = 1;
	// 				$condition = array('id' => $user_id);
	// 				$insertData_clean = $this->security->xss_clean($insertData);
	// 				$insert = $this->common_model->updateTableData('users',$condition, $insertData_clean);
	// 				if($insert !='' && $_FILES["photo_id_3"]['name'] !='') {
	// 					$this->session->set_flashdata('success','Your details have been sent to our team for verification');
	// 					front_redirect('settings', 'refresh');
	// 				} 
	//                 elseif($insert !='' && $_FILES["photo_id_3"]['name'] =='') {
	// 					$this->session->set_flashdata('success', 'Your Photo cancelled successfully');
	// 					front_redirect('settings', 'refresh');
	// 				}
	// 				else {
	// 					$this->session->set_flashdata('error','Unable to send your details to our team for verification. Please try again later!');
	// 					front_redirect('settings', 'refresh');
	// 				}
	// 		}
	// }
	function pwcheck(){
        $pwd = $_POST['oldpass'];
        $epwd = encryptIt($pwd);
        $Cnt_Row = $this->common_model->getTableData('users', array('elxisenergy_password' => $epwd,'id'=>$this->session->userdata('user_id')))->num_rows();    
        if($Cnt_Row > 0){
            echo '0';
        }
        else{
            echo '1';
        }
    }
	function settings()
	{
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('success', 'Please Login');
			redirect(base_url().'home');
		}
		$this->load->library('Googleauthenticator');
		$data['meta_content'] = $this->common_model->getTableData('meta_content', array('link'=>'settings'))->row();
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['user_bank'] = $this->common_model->getTableData('user_bank_details', array('user_id'=>$user_id))->row();
		if($data['users']->randcode=="enable" || $data['users']->secret!="")
		{	
			$secret = $data['users']->secret; 
			$data['secret'] = $secret;
        	$ga     = new Googleauthenticator();
			$data['url'] = $ga->getQRCodeGoogleUrl('elxisenergy', $secret);
		}
		else
		{
			$ga = new Googleauthenticator();
			$data['secret'] = $ga->createSecret();
			$data['url'] = $ga->getQRCodeGoogleUrl('elxisenergy', $data['secret']);
			$data['oneCode'] = $ga->getCode($data['secret']);
		}
		if(isset($_POST['chngpass']))
		{
			$prefix = get_prefix();
			$oldpassword = encryptIt($this->input->post("oldpass"));
			$newpassword = encryptIt($this->input->post("newpass"));
			$confirmpassword = encryptIt($this->input->post("confirmpass"));
			
			// Check old pass is correct/not
			$password = $prefix.'password';
			if($oldpassword == $data['users']->$password)
			{
				//check new pass is equal to confirm pass
				if($newpassword==$confirmpassword)
				{
					$this->db->where('id',$user_id);
					$data=array($prefix.'password'  => $newpassword);
					$this->db->update('users',$data);
					$this->session->set_flashdata('success',$this->lang->line('Password changed successfully'));
					front_redirect('settings', 'refresh');
				}
				else
				{
					$this->session->set_flashdata('error',$this->lang->line('Confirm password must be same as new password'));
					front_redirect('settings', 'refresh');
				}
			}
			else
			{
				$this->session->set_flashdata('error',$this->lang->line('Your old password is wrong'));
				front_redirect('settings', 'refresh');
			}			
		}
		
		$data['site_common'] = site_common();

		$data['countries'] = $this->common_model->getTableData('countries')->result();
		$data['currencies'] = $this->common_model->getTableData('currency',array('type'=>'fiat','status'=>1))->result();
		$this->load->view('front/user/settings', $data);
	}

	function elxis_support()
	{
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'support'))->row();
		if(isset($_POST['submit_tick']))
		{
			$image = $_FILES['image']['name'];
			if($image!="") {
				if(getExtension($_FILES['image']['type']))
				{			
					$uploadimage1=cdn_file_upload($_FILES["image"],'uploads/user/'.$user_id);
					if($uploadimage1)
					{
						$image=$uploadimage1['secure_url'];
					}
					else
					{
						$this->session->set_flashdata('error',$this->lang->line('Error occur!! Please try again'));
						front_redirect('elxis_support', 'refresh');
					}
					$image=$image;
				}
				else
				{
					$this->session->set_flashdata('error',$this->lang->line('Please upload proper image format'));
					front_redirect('elxis_support', 'refresh');	
				}
			} 
			else 
			{ 
				$image = "";
			}
			$insertData['user_id'] = $user_id;
			$insertData['subject'] = $this->input->post('subject');
			$insertData['message'] = $this->input->post('message');
			$insertData['category'] = $this->input->post('category');
			$insertData['image'] = $image;
			$insertData['created_on'] = gmdate(time());
			$insertData['ticket_id'] = 'TIC-'.encryptIt(gmdate(time()));
			$insertData['status'] = '1';
			$insert = $this->common_model->insertTableData('support', $insertData);
			if ($insert) {

				$email_template   	= 'Support_admin';
				$email_template_user   	= 'Support_user';
				$site_common      	=   site_common();

                $enc_email = getAdminDetails('1','email_id');
                $adminmail = decryptIt($enc_email);
                $usermail = getUserEmail($user_id);
                $username = getUserDetails($user_id,'elxisenergy_username');
                $message = $this->input->post('message');
				$special_vars 		= array(
						'###SITELINK###' 		=> front_url(),
						'###SITENAME###' 		=> $site_common['site_settings']->site_name,
						'###USERNAME###' 		=> $username,
						'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>",
						'###LINK###' 			=> admin_url().'support/reply/'.$insert
				);
				
				$special_vars_user 		= array(
						'###SITELINK###' 		=> front_url(),
						'###SITENAME###' 		=> $site_common['site_settings']->site_name,
						'###USERNAME###' 		=> $username,
						'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>"
				);


				$this->email_model->sendMail($adminmail, '', '', $email_template, $special_vars);
				$this->email_model->sendMail($usermail, '', '', $email_template_user, $special_vars_user);

				$this->session->set_flashdata('success',$this->lang->line('Your message successfully sent to our team'));
				front_redirect('elxis_support', 'refresh');
			} else {
				$this->session->set_flashdata('error',$this->lang->line('Error occur!! Please try again'));
				front_redirect('elxis_support', 'refresh');
			}
		}

		$data['site_common'] = site_common();		
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['action'] = front_url() . 'elxis_support';

		$data['category'] = $this->common_model->getTableData('support_category', array('status' => '1'))->result();
		$data['support'] = $this->common_model->getTableData('support', array('user_id' => $user_id, 'parent_id'=>0))->result();
		$data['supports'] = $this->common_model->getTableData('static_content',array('slug'=>'supports'))->row();
		$data['services'] = $this->common_model->getTableData('static_content',array('slug'=>'services'))->row();
	
		$data['contact'] = $this->common_model->getTableData('static_content',array('slug'=>'contact'))->row();

		$data['prefix'] = get_prefix();
		$this->load->view('front/user/elxis_support', $data);
	}

	function support()
	{
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'support'))->row();
		if(isset($_POST['submit_tick']))
		{
			$image = $_FILES['image']['name'];
			if($image!="") {
				if(getExtension($_FILES['image']['type']))
				{			
					$uploadimage1=cdn_file_upload($_FILES["image"],'uploads/user/'.$user_id);
					if($uploadimage1)
					{
						$image=$uploadimage1['secure_url'];
					}
					else
					{
						$this->session->set_flashdata('error',$this->lang->line('Error occur!! Please try again'));
						front_redirect('support', 'refresh');
					}
					$image=$image;
				}
				else
				{
					$this->session->set_flashdata('error',$this->lang->line('Please upload proper image format'));
					front_redirect('support', 'refresh');	
				}
			} 
			else 
			{ 
				$image = "";
			}
			$insertData['user_id'] = $user_id;
			$insertData['subject'] = $this->input->post('subject');
			$insertData['message'] = $this->input->post('message');
			$insertData['category'] = $this->input->post('category');
			$insertData['image'] = $image;
			$insertData['created_on'] = gmdate(time());
			$insertData['ticket_id'] = 'TIC-'.encryptIt(gmdate(time()));
			$insertData['status'] = '1';
			$insert = $this->common_model->insertTableData('support', $insertData);
			if ($insert) {

				$email_template   	= 'Support_admin';
				$email_template_user   	= 'Support_user';
				$site_common      	=   site_common();

                $enc_email = getAdminDetails('1','email_id');
                $adminmail = decryptIt($enc_email);
                $usermail = getUserEmail($user_id);
                $username = getUserDetails($user_id,'elxisenergy_username');
                $message = $this->input->post('message');
				$special_vars 		= array(
						'###SITELINK###' 		=> front_url(),
						'###SITENAME###' 		=> $site_common['site_settings']->site_name,
						'###USERNAME###' 		=> $username,
						'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>",
						'###LINK###' 			=> admin_url().'support/reply/'.$insert
				);
				
				$special_vars_user 		= array(
						'###SITELINK###' 		=> front_url(),
						'###SITENAME###' 		=> $site_common['site_settings']->site_name,
						'###USERNAME###' 		=> $username,
						'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>"
				);

				$this->email_model->sendMail($adminmail, '', '', $email_template, $special_vars);
				$this->email_model->sendMail($usermail, '', '', $email_template_user, $special_vars_user);

				$this->session->set_flashdata('success',$this->lang->line('Your message successfully sent to our team'));
				front_redirect('support', 'refresh');
			} else {
				$this->session->set_flashdata('error',$this->lang->line('Error occur!! Please try again'));
				front_redirect('support', 'refresh');
			}
		}

		$data['site_common'] = site_common();		
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['action'] = front_url() . 'support';

		$data['category'] = $this->common_model->getTableData('support_category', array('status' => '1'))->result();
		$data['support'] = $this->common_model->getTableData('support', array('user_id' => $user_id, 'parent_id'=>0))->result();
		$data['supports'] = $this->common_model->getTableData('static_content',array('slug'=>'supports'))->row();
		$data['services'] = $this->common_model->getTableData('static_content',array('slug'=>'services'))->row();
	
		$data['contact'] = $this->common_model->getTableData('static_content',array('slug'=>'contact'))->row();

		$data['prefix'] = get_prefix();
		$this->load->view('front/user/support', $data);
	}

	function support_old()
	{
		// $user_id=$this->session->userdata('user_id');
		// if($user_id=="")
		// {	
		// 	$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
		// 	redirect(base_url().'home');
		// }
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'support'))->row();
		if(isset($_POST['submit_tick']))
		{
			$image = $_FILES['image']['name'];
			if($image!="") {
				if(getExtension($_FILES['image']['type']))
				{			
					$uploadimage1=cdn_file_upload($_FILES["image"],'uploads/user/'.$user_id);
					if($uploadimage1)
					{
						$image=$uploadimage1['secure_url'];
					}
					else
					{
						$this->session->set_flashdata('error', 'Error occur!! Please try again');
						front_redirect('support', 'refresh');
					}
					$image=$image;
				}
				else
				{
					$this->session->set_flashdata('error','Please upload proper image format');
					front_redirect('support', 'refresh');	
				}
			} 
			else 
			{ 
				$image = "";
			}
			// $insertData['user_id'] = $user_id;
			$user_name = $this->input->post('name');
			$user_email = $this->input->post('email');
			$insertData['name'] = $user_name;
			$insertData['email'] = $user_email;
			$insertData['subject'] = $this->input->post('subject');
			$insertData['message'] = $this->input->post('message');
			// $insertData['category'] = $this->input->post('category');
			$insertData['image'] = $image;
			$insertData['created_on'] = gmdate(time());
			$insertData['ticket_id'] = 'TIC-'.encryptIt(gmdate(time()));
			$insertData['status'] = '1';
			$insert = $this->common_model->insertTableData('support', $insertData);
			if ($insert) {

				$email_template   	= 'Support_admin';
				$email_template_user   	= 'Support_user';
				$site_common      	=   site_common();

                $enc_email = getAdminDetails('1','email_id');
                $adminmail = decryptIt($enc_email);
                // $usermail = getUserEmail($user_id);
                $usermail = $user_email;
                // $username = getUserDetails($user_id,'elxisenergy_username');
                $username = $user_name;
                $message = $this->input->post('message');
				$special_vars 		= array(
						'###SITELINK###' 		=> front_url(),
						'###SITENAME###' 		=> $site_common['site_settings']->site_name,
						'###USERNAME###' 		=> $username,
						'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>",
						'###LINK###' 			=> admin_url().'support/reply/'.$insert
				);
				
				$special_vars_user 		= array(
						'###SITELINK###' 		=> front_url(),
						'###SITENAME###' 		=> $site_common['site_settings']->site_name,
						'###USERNAME###' 		=> $username,
						'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>"
				);

				$this->email_model->sendMail($adminmail, '', '', $email_template, $special_vars);
				$this->email_model->sendMail($usermail, '', '', $email_template_user, $special_vars_user);

				$this->session->set_flashdata('success', 'Your message successfully sent to our team');
				front_redirect('support', 'refresh');
			} else {
				$this->session->set_flashdata('error', 'Error occur!! Please try again');
				front_redirect('support', 'refresh');
			}
		}

		$data['site_common'] = site_common();		
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['action'] = front_url() . 'support';

		$data['category'] = $this->common_model->getTableData('support_category', array('status' => '1'))->result();
		$data['support'] = $this->common_model->getTableData('support', array('user_id' => $user_id, 'parent_id'=>0))->result();

		$data['prefix'] = get_prefix();

		$this->load->view('front/user/support', $data);

	}

	function elxis_support_reply($code='')
	{
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'support'))->row();
		$data['prefix'] = get_prefix();
		$data['support'] = $this->common_model->getTableData('support', array('user_id' => $user_id, 'ticket_id'=>$code))->row();
		$id = $data['support']->id;
		//$data['support_reply'] = $this->common_model->getTableData('support', array('parent_id'=>$data['support']->id,'id'=>$id))->result();
		$data['support_reply'] = $this->db->query("SELECT * FROM `elxisenergy_support` WHERE `parent_id` = '".$id."'")->result();
		$data['support_replies'] = $this->db->query("SELECT * FROM `elxisenergy_support` WHERE ticket_id = '".$code."'")->result();
		if($_POST)
		{
			$image = $_FILES['image']['name'];
			if($image!="") {
				if(getExtension($_FILES['image']['type']))
				{			
					$uploadimage1=cdn_file_upload($_FILES["image"],'uploads/user/'.$user_id);
					if($uploadimage1)
					{
						$image=$uploadimage1['secure_url'];
					}
					else
					{
						$this->session->set_flashdata('error',$this->lang->line('Please upload proper image format'));
						front_redirect('elxis_support_reply/'.$code, 'refresh');
					}
					$image=$image;
				}
				else
				{
					$this->session->set_flashdata('error',$this->lang->line('Please upload proper image format'));
					front_redirect('elxis_support_reply/'.$code, 'refresh');	
				}
			} 
			else 
			{ 
				$image = "";
			}
			$insertsData['status'] = '1';
			$update = $this->common_model->updateTableData('support',array('ticket_id'=>$code),$insertsData);
			if($update){
				$insertData['parent_id'] = $data['support']->id;
				$insertData['user_id'] = $user_id;
				$insertData['message'] = $this->input->post('message');
				$insertData['image'] = $image;
				$insertData['created_on'] = gmdate(time());
				$insert = $this->common_model->insertTableData('support', $insertData);
				if ($insert) {

					$email_template   	= 'Support_admin';
					$email_template_user   	= 'Support_user';
					$site_common      	=   site_common();
	                $enc_email = getAdminDetails('1','email_id');
	                $adminmail = decryptIt($enc_email);
	                $usermail = getUserEmail($user_id);
	                $username = getUserDetails($user_id,'elxisenergy_username');
	                $message = $this->input->post('message');
					$special_vars 		= array(
							'###SITELINK###' 		=> front_url(),
							'###SITENAME###' 		=> $site_common['site_settings']->site_name,
							'###USERNAME###' 		=> $username,
							'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>",
							'###LINK###' 			=> admin_url().'support/reply/'.$data['support']->id
					);
					
					$special_vars_user 		= array(
							'###SITELINK###' 		=> front_url(),
							'###SITENAME###' 		=> $site_common['site_settings']->site_name,
							'###USERNAME###' 		=> $username,
							'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>"
					);

					$this->email_model->sendMail($adminmail, '', '', $email_template, $special_vars);
					$this->email_model->sendMail($usermail, '', '', $email_template_user, $special_vars_user);

					$this->session->set_flashdata('success', $this->lang->line('Your message successfully sent to our team'));
					front_redirect('elxis_support_reply/'.$code, 'refresh');
				} else {
					$this->session->set_flashdata('error', $this->lang->line('Error occur!! Please try again'));
					front_redirect('elxis_support_reply/'.$code, 'refresh');
				}
			}
			else
			{
				$this->session->set_flashdata('error', $this->lang->line('Error occur!! Please try again'));
				front_redirect('elxis_support_reply/'.$code, 'refresh');
			}
		}
		$data['code'] = $code;
		$data['supports'] = $this->common_model->getTableData('static_content',array('slug'=>'supports'))->row();
		$data['services'] = $this->common_model->getTableData('static_content',array('slug'=>'services'))->row();
	
		$data['contact'] = $this->common_model->getTableData('static_content',array('slug'=>'contact'))->row();
		$data['user_detail'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
        $data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['action'] = front_url() . 'elxis_support_reply/'.$code;
		$this->load->view('front/user/elxis_support_reply', $data);
	}

	function support_reply($code='')
	{
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'support'))->row();
		$data['prefix'] = get_prefix();
		$data['support'] = $this->common_model->getTableData('support', array('user_id' => $user_id, 'ticket_id'=>$code))->row();
		$id = $data['support']->id;
		//$data['support_reply'] = $this->common_model->getTableData('support', array('parent_id'=>$data['support']->id,'id'=>$id))->result();
		$data['support_reply'] = $this->db->query("SELECT * FROM `elxisenergy_support` WHERE `parent_id` = '".$id."'")->result();
		$data['support_replies'] = $this->db->query("SELECT * FROM `elxisenergy_support` WHERE ticket_id = '".$code."'")->result();
		if($_POST)
		{
			$image = $_FILES['image']['name'];
			if($image!="") {
				if(getExtension($_FILES['image']['type']))
				{			
					$uploadimage1=cdn_file_upload($_FILES["image"],'uploads/user/'.$user_id);
					if($uploadimage1)
					{
						$image=$uploadimage1['secure_url'];
					}
					else
					{
						$this->session->set_flashdata('error',$this->lang->line('Please upload proper image format'));
						front_redirect('support_reply/'.$code, 'refresh');
					}
					$image=$image;
				}
				else
				{
					$this->session->set_flashdata('error',$this->lang->line('Please upload proper image format'));
					front_redirect('support_reply/'.$code, 'refresh');	
				}
			} 
			else 
			{ 
				$image = "";
			}
			$insertsData['status'] = '1';
			$update = $this->common_model->updateTableData('support',array('ticket_id'=>$code),$insertsData);
			if($update){
				$insertData['parent_id'] = $data['support']->id;
				$insertData['user_id'] = $user_id;
				$insertData['message'] = $this->input->post('message');
				$insertData['image'] = $image;
				$insertData['created_on'] = gmdate(time());
				$insert = $this->common_model->insertTableData('support', $insertData);
				if ($insert) {

					$email_template   	= 'Support_admin';
					$email_template_user   	= 'Support_user';
					$site_common      	=   site_common();
	                $enc_email = getAdminDetails('1','email_id');
	                $adminmail = decryptIt($enc_email);
	                $usermail = getUserEmail($user_id);
	                $username = getUserDetails($user_id,'elxisenergy_username');
	                $message = $this->input->post('message');
					$special_vars 		= array(
							'###SITELINK###' 		=> front_url(),
							'###SITENAME###' 		=> $site_common['site_settings']->site_name,
							'###USERNAME###' 		=> $username,
							'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>",
							'###LINK###' 			=> admin_url().'support/reply/'.$data['support']->id
					);
					
					$special_vars_user 		= array(
							'###SITELINK###' 		=> front_url(),
							'###SITENAME###' 		=> $site_common['site_settings']->site_name,
							'###USERNAME###' 		=> $username,
							'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>"
					);

					$this->email_model->sendMail($adminmail, '', '', $email_template, $special_vars);
					$this->email_model->sendMail($usermail, '', '', $email_template_user, $special_vars_user);

					$this->session->set_flashdata('success', $this->lang->line('Your message successfully sent to our team'));
					front_redirect('support_reply/'.$code, 'refresh');
				} else {
					$this->session->set_flashdata('error', $this->lang->line('Error occur!! Please try again'));
					front_redirect('support_reply/'.$code, 'refresh');
				}
			}
			else
			{
				$this->session->set_flashdata('error', $this->lang->line('Error occur!! Please try again'));
				front_redirect('support_reply/'.$code, 'refresh');
			}
		}
		$data['code'] = $code;
		$data['supports'] = $this->common_model->getTableData('static_content',array('slug'=>'supports'))->row();
		$data['services'] = $this->common_model->getTableData('static_content',array('slug'=>'services'))->row();
	
		$data['contact'] = $this->common_model->getTableData('static_content',array('slug'=>'contact'))->row();
		$data['user_detail'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
        $data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['action'] = front_url() . 'support_reply/'.$code;
		$this->load->view('front/user/support_reply', $data);
	}

	
	function support_reply_old($code='')
	{
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'support'))->row();
		$data['prefix'] = get_prefix();
		$data['support'] = $this->common_model->getTableData('support', array('user_id' => $user_id, 'ticket_id'=>$code))->row();
		$id = $data['support']->id;
		//$data['support_reply'] = $this->common_model->getTableData('support', array('parent_id'=>$data['support']->id,'id'=>$id))->result();
		$data['support_reply'] = $this->db->query("SELECT * FROM `elxisenergy_support` WHERE `parent_id` = '".$id."'")->result();
		if($_POST)
		{
			$image = $_FILES['image']['name'];
			if($image!="") {
				if(getExtension($_FILES['image']['type']))
				{			
					$uploadimage1=cdn_file_upload($_FILES["image"],'uploads/user/'.$user_id);
					if($uploadimage1)
					{
						$image=$uploadimage1['secure_url'];
					}
					else
					{
						$this->session->set_flashdata('error', 'Please upload proper image format');
						front_redirect('support_reply/'.$code, 'refresh');
					}
					$image=$image;
				}
				else
				{
					$this->session->set_flashdata('error','Please upload proper image format');
					front_redirect('support_reply/'.$code, 'refresh');	
				}
			} 
			else 
			{ 
				$image = "";
			}
			$insertsData['status'] = '1';
			$update = $this->common_model->updateTableData('support',array('ticket_id'=>$code),$insertsData);
			if($update){
				$insertData['parent_id'] = $data['support']->id;
				$insertData['user_id'] = $user_id;
				$insertData['message'] = $this->input->post('message');
				$insertData['image'] = $image;
				$insertData['created_on'] = gmdate(time());
				$insert = $this->common_model->insertTableData('support', $insertData);
				if ($insert) {

					$email_template   	= 'Support_admin';
					$email_template_user   	= 'Support_user';
					$site_common      	=   site_common();
	                $enc_email = getAdminDetails('1','email_id');
	                $adminmail = decryptIt($enc_email);
	                $usermail = getUserEmail($user_id);
	                $username = getUserDetails($user_id,'elxisenergy_username');
	                $message = $this->input->post('message');
					$special_vars 		= array(
							'###SITELINK###' 		=> front_url(),
							'###SITENAME###' 		=> $site_common['site_settings']->site_name,
							'###USERNAME###' 		=> $username,
							'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>",
							'###LINK###' 			=> admin_url().'support/reply/'.$data['support']->id
					);
					
					$special_vars_user 		= array(
							'###SITELINK###' 		=> front_url(),
							'###SITENAME###' 		=> $site_common['site_settings']->site_name,
							'###USERNAME###' 		=> $username,
							'###MESSAGE###'  		=> "<span style='color: #500050;'>".$message . "</span><br>"
					);

					$this->email_model->sendMail($adminmail, '', '', $email_template, $special_vars);
					$this->email_model->sendMail($usermail, '', '', $email_template_user, $special_vars_user);

					$this->session->set_flashdata('success', 'Your message successfully sent to our team');
					front_redirect('support_reply/'.$code, 'refresh');
				} else {
					$this->session->set_flashdata('error', 'Error occur!! Please try again');
					front_redirect('support_reply/'.$code, 'refresh');
				}
			}
			else
			{
				$this->session->set_flashdata('error', 'Error occur!! Please try again');
				front_redirect('support_reply/'.$code, 'refresh');
			}
		}
		$data['code'] = $code;
		$data['user_detail'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
        $data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['action'] = front_url() . 'support_reply/'.$code;
		$this->load->view('front/user/support_reply', $data);
	}
	function dashboard()
	{ 	 
		$user_id = $this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url('home'));
		}
		
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content', array('link'=>'dashboard'))->row();
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

		$data['login_history'] = $this->common_model->getTableData('user_activity',array('activity' => 'Login','user_id'=>$user_id),'','','','','','',array('act_id','DESC'))->result();

		$data['wallet'] = unserialize($this->common_model->getTableData('wallet',array('user_id'=>$user_id),'crypto_amount')->row('crypto_amount'));

		$data['dig_currency'] = $this->common_model->getTableData('currency',array('status'=>1),'','','','','','',array('sort_order','ASC'))->result();
		/*$data['banners'] = $this->common_model->getTableData('banners',array('status'=>1),'','','','','','', array('id', 'ASC'))->result();*/

		$today = date("Y-m-d");

		$data['banners'] = $this->common_model->getTableData('banners',array('status'=>1,'position'=>'dashboard','expiry_date>='=>$today),'','','','','','', array('id', 'ASC'))->row();

		$data['trans_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id),'','','','','','',array('trans_id','DESC'))->result();
		
		$this->load->view('front/user/dashboard', $data);
	}   
	function change_address()
	{
		$user_id=$this->session->userdata('user_id');
		$currency_id = $this->input->post('currency_id');

		$coin_address = getAddress($user_id,$currency_id);

		$data['img'] =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$coin_address&choe=UTF-8&chld=L";
		$data['address'] = $coin_address;
		
		$currency_det = $this->common_model->getTableData("currency",array('id'=>$currency_id))->row();
		$data['coin_symbol'] = $currency_det->currency_symbol;
		if($data['coin_symbol']=="INR")
		{
			$format = 2;
		}
		else
		{
			$format = 8;
		}
		if($currency_id==8){
			$data['destination_tag'] = '';
		}
		$coin_balance = number_format(getBalance($user_id,$currency_id),$format);
		$data['coin_name'] = ucfirst($currency_det->currency_name);
		$data['coin_balance'] = $coin_balance;
		$data['withdraw_fees'] = $currency_det->withdraw_fees;
		$data['withdraw_limit'] = $currency_det->max_withdraw_limit;
		echo json_encode($data);
    }

    function update_user_address()
    {
		ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
    	$Fetch_coin_list = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1','id'=>'2'))->result(); 
		//print_r($Fetch_coin_list); exit;



		foreach($Fetch_coin_list as $coin_address)
		{
    		$userdetails = $this->common_model->getTableData('crypto_address',array($coin_address->currency_symbol.'_status'=>'0','user_id'=>'1'),'','','','','','',array('id','DESC'))->result();

	    	foreach($userdetails as $user_details) 
	    	{
	    		$User_Address = getAddress($user_details->user_id,$coin_address->id);
	    		if(empty($User_Address) || $User_Address==0)
	    		{
	    			echo $coin_address->coin_type;
	    			echo "<br>";
					$parameter = '';


	                if($coin_address->coin_type=="coin")
	                {
	                	echo "<pre>";
print_r($coin_address->currency_symbol);
echo "<pre>"; 


	                	if($coin_address->currency_symbol=='ETH')
						{
                           

							$parameter='create_eth_account';

							$Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account',getUserEmail($user_details->user_id));
							if(!empty($Get_First_address) || $Get_First_address!=0)
							{
								print_r($Get_First_address);
								updateEtherAddress($user_details->user_id,$coin_address->id,$Get_First_address);
								echo $coin_address->currency_symbol.' Success1 <br/>';
							}
							else{
								$Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
								if($Get_First_address){
									updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
									echo $coin_address->currency_symbol.' Success2 <br/>';
								}
							}
						}
				   
                    elseif($coin_address->currency_symbol=='TRX')
                    {
                        $parameter='create_tron_account';

                        $Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_tron_account',$user_details->user_id);
						// echo "<pre>";
						// print_r($Get_First_address); exit;

                        $tron_private_key = $Get_First_address['privateKey'];
                        $tron_public_key = $Get_First_address['publicKey'];
                        $tron_address = $Get_First_address['address']['base58'];
                        $tron_hex = $Get_First_address['address']['hex'];
                        if(!empty($Get_First_address) || $Get_First_address!=0)
                        {
                            updatetronAddress($user_details->user_id,$coin_address->id,$tron_address,$tron_hex,$tron_private_key,$tron_public_key);
                            echo "success TRON";
                        }
                        else{
                            $Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
                            if($Get_First_address){
                                updatetronAddress($user_details->user_id,$coin_address->id,$tron_address,$tron_hex);
                                echo "success TRON";
                            }
                        }
                    }
                         else if($coin_address->currency_symbol=='BNB')
						{



							$parameter='create_eth_account';

                        $Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account',getUserEmail($user_details->user_id));

                      
                        if(!empty($Get_First_address) || $Get_First_address!=0)
                        {
                            updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
                        }
                        else{
                            $Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
                            if($Get_First_address){
                                updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
                            }
                        }


                         }
						else
						{
							echo $coin_address->currency_symbol.'<br/>';

								$parameter = 'getaccountaddress';

							$Get_First_address1 = $this->local_model->access_wallet($coin_address->id,$parameter,getUserEmail($user_details->user_id));



							if(!empty($Get_First_address1) || $Get_First_address1!=0){
								
								$Get_First_address = $Get_First_address1;
								
								updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
								echo $coin_address->currency_symbol.' Success1 <br/>';
							}
							else{
								if($Get_First_address1){
									$Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);

									updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
									echo $coin_address->currency_symbol.' Success2 <br/>';
								}
							}
						}
		            }

		            else
		            {
		            	if($coin_address->crypto_type=='eth'){
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'ETH'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}
					elseif($coin_address->crypto_type=='tron'){
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'TRX'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}
					else{
						$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'BNB'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}


						updateAddress($user_details->user_id,$coin_address->id,$eth_address);
		            }
		            /*else
		            {
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'ETH'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
						updateAddress($user_details->user_id,$coin_address->id,$eth_address);
		            }*/
				}
			}
		}		
    } 

	// Update user missed address - to generate all the address
	function update_user_missed_address()
    {
		$currency_symbol = $this->input->post('currency_symbol');
		$user_id = $this->session->userdata('user_id');
		ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
		$curr = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1','currency_symbol'=>$currency_symbol))->row(); 
		if($curr->crypto_type_other != '')
		{
			$where = "(type = 'digital' AND status = 1)";
			if($curr->crypto_type_other == 'eth|tron|bsc')
				$where.=" AND (currency_symbol = 'TRX' OR currency_symbol = 'BNB' OR currency_symbol = 'ETH' OR currency_symbol = 'USDT') ";
			else if($curr->crypto_type_other == 'tron')
				$where.=" AND (crypto_type = 'tron' OR crypto_type_other like '%tron%') ";
			else if($curr->crypto_type_other == 'bsc')
				$where.=" AND (crypto_type = 'bsc' OR crypto_type_other like '%bsc%') ";
			else if($curr->crypto_type_other == 'eth')
				$where.=" AND (crypto_type = 'eth' OR crypto_type_other like '%eth%') ";
			$Fetch_coin_list   =  $this->db->query("select * from elxisenergy_currency where $where")->result();
		} else {
			$Fetch_coin_list = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1','currency_symbol'=>$currency_symbol))->result(); 
		}
		//print_r($Fetch_coin_list); exit;



		foreach($Fetch_coin_list as $coin_address)
		{
    		$userdetails = $this->common_model->getTableData('crypto_address',array('user_id'=>$user_id),'','','','','','',array('id','DESC'))->result();
			//print_r($userdetails); exit;

	    	foreach($userdetails as $user_details) 
	    	{
	    		$User_Address = getAddress($user_details->user_id,$coin_address->id);
	    		if($User_Address == '0')
	    		{
					$parameter = '';


	                if($coin_address->coin_type=="coin")
	                {


	                	if($coin_address->currency_symbol=='ETH')
						{
                           

							$parameter='create_eth_account';

							$Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account',getUserEmail($user_details->user_id));
							if(!empty($Get_First_address) || $Get_First_address!=0)
							{
								updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
								//echo $coin_address->currency_symbol.' Success1 <br/>';
							}
							else{
								$Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
								if($Get_First_address){
									updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
									//echo $coin_address->currency_symbol.' Success2 <br/>';
								}
							}
						}
				   
                    elseif($coin_address->currency_symbol=='TRX')
                    {
                        $parameter='create_tron_account';

                        $Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_tron_account',$user_details->user_id);
						// echo "<pre>";
						// print_r($Get_First_address); exit;

                        $tron_private_key = $Get_First_address['privateKey'];
                        $tron_public_key = $Get_First_address['publicKey'];
                        $tron_address = $Get_First_address['address']['base58'];
                        $tron_hex = $Get_First_address['address']['hex'];
                        if(!empty($Get_First_address) || $Get_First_address!=0)
                        {
                            updatetronAddress($user_details->user_id,$coin_address->id,$tron_address,$tron_hex,$tron_private_key,$tron_public_key);
                            //echo "success TRON";
                        }
                        else{
                            $Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
                            if($Get_First_address){
                                updatetronAddress($user_details->user_id,$coin_address->id,$tron_address,$tron_hex);
                                //echo "success TRON";
                            }
                        }
                    }
                         else if($coin_address->currency_symbol=='BNB')
						{



							$parameter='create_eth_account';

                        $Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account',getUserEmail($user_details->user_id));

                      
                        if(!empty($Get_First_address) || $Get_First_address!=0)
                        {
                            updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
                        }
                        else{
                            $Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
                            if($Get_First_address){
                                updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
                            }
                        }


                         }
						else
						{
							//echo $coin_address->currency_symbol.'<br/>';

								$parameter = 'getaccountaddress';

							$Get_First_address1 = $this->local_model->access_wallet($coin_address->id,$parameter,getUserEmail($user_details->user_id));



							if(!empty($Get_First_address1) || $Get_First_address1!=0){
								
								$Get_First_address = $Get_First_address1;
								
								updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
								//echo $coin_address->currency_symbol.' Success1 <br/>';
							}
							else{
								if($Get_First_address1){
									$Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);

									updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
									//echo $coin_address->currency_symbol.' Success2 <br/>';
								}
							}
						}
		            }

		            else
		            {
		            	if($coin_address->crypto_type=='eth'){
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'ETH'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}
					elseif($coin_address->crypto_type=='tron'){
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'TRX'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}
					else{
						$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'BNB'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}


						updateAddress($user_details->user_id,$coin_address->id,$eth_address);
		            }
		            /*else
		            {
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'ETH'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
						updateAddress($user_details->user_id,$coin_address->id,$eth_address);
		            }*/
					// $result = array('status'=>'success','msg'=>'Address generated successfully');
					// echo json_encode($result);
				} else {
					// $result = array('status'=>'failed','msg'=>'Already generated');
					// echo json_encode($result);
				}
			}
		}
		$this->checkaddress($currency_symbol);		
    }

	function checkaddress($currency_symbol)
	{
		$user_id = $this->session->userdata('user_id');
		$currency = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1','currency_symbol'=>$currency_symbol))->row(); 
		$address = getAddress($user_id,$currency->id);
		if($address != '0')
		{
			// $this->session->set_flashdata('success', 'Address generated successfully');
       		// front_redirect('wallet','refresh');
			if($currency_symbol == 'USDT')
			{
				$address_ETH = getAddress($user_id,2);
				$address_TRX = getAddress($user_id,5);
				$address_BNB = getAddress($user_id,4);
				$img_ETH =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$address_ETH&choe=UTF-8&chld=L";
				$img_TRX =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$address_TRX&choe=UTF-8&chld=L";
				$img_BNB =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$address_BNB&choe=UTF-8&chld=L";
				$result = array('status'=>'success','msg'=>'Address generated successfully','address'=>$address,'address_ETH'=>$address_ETH,'address_TRX'=>$address_TRX,'address_BNB'=>$address_BNB,'img_ETH'=>$img_ETH,'img_TRX'=>$img_TRX,'img_BNB'=>$img_BNB);
			} else {
				$img =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$address&choe=UTF-8&chld=L";
				$result = array('status'=>'success','msg'=>'Address generated successfully','address'=>$address,'img'=>$img);
			}
			echo json_encode($result);
		} else {
			// $this->session->set_flashdata('error', 'Issue in Address generation. Please try again later.');
       		// front_redirect('wallet','refresh');
			$result = array('status'=>'failed','msg'=>'Issue in Address generation. Please try again later.');
			echo json_encode($result);
		}
	}

	function update_user_address_app()
    {
		$currency_symbol = $this->input->post('currency_symbol');
		$user_id = $this->input->post('user_id');
		if($currency_symbol == '' || $user_id == '')
		{
			$result = array('status'=>'failed','msg'=>'User Id and Currency Symbol are required','paramaters'=>'user_id & currency_symbol');
			echo json_encode($result);
			exit;
		}
		ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
		$curr = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1','currency_symbol'=>$currency_symbol))->row(); 
		if($curr->crypto_type_other != '')
		{
			$where = "(type = 'digital' AND status = 1)";
			if($curr->crypto_type_other == 'eth|tron|bsc')
				$where.=" AND (currency_symbol = 'TRX' OR currency_symbol = 'BNB' OR currency_symbol = 'ETH' OR currency_symbol = 'USDT') ";
			else if($curr->crypto_type_other == 'tron')
				$where.=" AND (crypto_type = 'tron' OR crypto_type_other like '%tron%') ";
			else if($curr->crypto_type_other == 'bsc')
				$where.=" AND (crypto_type = 'bsc' OR crypto_type_other like '%bsc%') ";
			else if($curr->crypto_type_other == 'eth')
				$where.=" AND (crypto_type = 'eth' OR crypto_type_other like '%eth%') ";
			$Fetch_coin_list   =  $this->db->query("select * from elxisenergy_currency where $where")->result();
		} else {
			$Fetch_coin_list = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1','currency_symbol'=>$currency_symbol))->result(); 
		}
		//print_r($Fetch_coin_list); exit;



		foreach($Fetch_coin_list as $coin_address)
		{
    		$userdetails = $this->common_model->getTableData('crypto_address',array('user_id'=>$user_id),'','','','','','',array('id','DESC'))->result();
			//print_r($userdetails); exit;

	    	foreach($userdetails as $user_details) 
	    	{
	    		$User_Address = getAddress($user_details->user_id,$coin_address->id);
	    		if($User_Address == '0')
	    		{
					$parameter = '';


	                if($coin_address->coin_type=="coin")
	                {


	                	if($coin_address->currency_symbol=='ETH')
						{
                           

							$parameter='create_eth_account';

							$Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account',getUserEmail($user_details->user_id));
							if(!empty($Get_First_address) || $Get_First_address!=0)
							{
								updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
								//echo $coin_address->currency_symbol.' Success1 <br/>';
							}
							else{
								$Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
								if($Get_First_address){
									updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
									//echo $coin_address->currency_symbol.' Success2 <br/>';
								}
							}
						}
				   
                    elseif($coin_address->currency_symbol=='TRX')
                    {
                        $parameter='create_tron_account';

                        $Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_tron_account',$user_details->user_id);
						// echo "<pre>";
						// print_r($Get_First_address); exit;

                        $tron_private_key = $Get_First_address['privateKey'];
                        $tron_public_key = $Get_First_address['publicKey'];
                        $tron_address = $Get_First_address['address']['base58'];
                        $tron_hex = $Get_First_address['address']['hex'];
                        if(!empty($Get_First_address) || $Get_First_address!=0)
                        {
                            updatetronAddress($user_details->user_id,$coin_address->id,$tron_address,$tron_hex,$tron_private_key,$tron_public_key);
                            //echo "success TRON";
                        }
                        else{
                            $Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
                            if($Get_First_address){
                                updatetronAddress($user_details->user_id,$coin_address->id,$tron_address,$tron_hex);
                                //echo "success TRON";
                            }
                        }
                    }
                         else if($coin_address->currency_symbol=='BNB')
						{



							$parameter='create_eth_account';

                        $Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account',getUserEmail($user_details->user_id));

                      
                        if(!empty($Get_First_address) || $Get_First_address!=0)
                        {
                            updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
                        }
                        else{
                            $Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
                            if($Get_First_address){
                                updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
                            }
                        }


                         }
						else
						{
							//echo $coin_address->currency_symbol.'<br/>';

								$parameter = 'getaccountaddress';

							$Get_First_address1 = $this->local_model->access_wallet($coin_address->id,$parameter,getUserEmail($user_details->user_id));



							if(!empty($Get_First_address1) || $Get_First_address1!=0){
								
								$Get_First_address = $Get_First_address1;
								
								updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
								//echo $coin_address->currency_symbol.' Success1 <br/>';
							}
							else{
								if($Get_First_address1){
									$Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);

									updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
									//echo $coin_address->currency_symbol.' Success2 <br/>';
								}
							}
						}
		            }

		            else
		            {
		            	if($coin_address->crypto_type=='eth'){
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'ETH'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}
					elseif($coin_address->crypto_type=='tron'){
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'TRX'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}
					else{
						$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'BNB'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}


						updateAddress($user_details->user_id,$coin_address->id,$eth_address);
		            }
		            /*else
		            {
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'ETH'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
						updateAddress($user_details->user_id,$coin_address->id,$eth_address);
		            }*/
					// $result = array('status'=>'success','msg'=>'Address generated successfully');
					// echo json_encode($result);
				} else {
					// $result = array('status'=>'failed','msg'=>'Already generated');
					// echo json_encode($result);
				}
			}
		}
		$this->checkaddressApi($currency_symbol,$user_id);		
    }

	function checkaddressApi($currency_symbol,$user_id)
	{
		$currency = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1','currency_symbol'=>$currency_symbol))->row(); 
		$address = getAddress($user_id,$currency->id);
		if($address != '0')
		{
			// $this->session->set_flashdata('success', 'Address generated successfully');
       		// front_redirect('wallet','refresh');
			if($currency_symbol == 'USDT')
			{
				$address_ETH = getAddress($user_id,2);
				$address_TRX = getAddress($user_id,5);
				$address_BNB = getAddress($user_id,4);
				$img_ETH =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$address_ETH&choe=UTF-8&chld=L";
				$img_TRX =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$address_TRX&choe=UTF-8&chld=L";
				$img_BNB =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$address_BNB&choe=UTF-8&chld=L";
				$result = array('status'=>'success','msg'=>'Address generated successfully','address'=>$address,'address_ETH'=>$address_ETH,'address_TRX'=>$address_TRX,'address_BNB'=>$address_BNB,'img_ETH'=>$img_ETH,'img_TRX'=>$img_TRX,'img_BNB'=>$img_BNB);
			} else {
				$img =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$address&choe=UTF-8&chld=L";
				$result = array('status'=>'success','msg'=>'Address generated successfully','address'=>$address,'img'=>$img);
			}
			echo json_encode($result);
		} else {
			// $this->session->set_flashdata('error', 'Issue in Address generation. Please try again later.');
       		// front_redirect('wallet','refresh');
			$result = array('status'=>'failed','msg'=>'Issue in Address generation. Please try again later.');
			echo json_encode($result);
		}
	}

	// Update user missed address - to generate all the address
	function update_user_missed_currency($currency='BTC',$range1='1',$range2='1000')
    {
		ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
    	$Fetch_coin_list = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1','currency_symbol'=>$currency))->result(); 
		//print_r($Fetch_coin_list); exit;



		foreach($Fetch_coin_list as $coin_address)
		{
    		//$userdetails = $this->common_model->getTableData('crypto_address',array($currency.'_status'=>1),'','','','','','',array('id','DESC'))->result();
			$cur = $currency.'_status';
			$userdetails   =  $this->db->query("select * from elxisenergy_crypto_address where $cur = '1' AND user_id BETWEEN $range1 AND $range2")->result();
			// echo "<pre>";
			// print_r($userdetails);exit;

	    	foreach($userdetails as $user_details) 
	    	{
	    		$User_Address = getAddress($user_details->user_id,$coin_address->id);
				//echo $User_Address; 
				//exit;
	    		if($User_Address == '0')
	    		{
					echo "generate address";
					echo "<br/>";
	    			echo $coin_address->coin_type;
	    			echo "<br>";
					$parameter = '';


	                if($coin_address->coin_type=="coin")
	                {
	                	echo "<pre>";
						print_r($coin_address->currency_symbol);
						echo "<pre>"; 


	                	if($coin_address->currency_symbol=='ETH')
						{
                           

							$parameter='create_eth_account';

							$Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account',getUserEmail($user_details->user_id));
							if(!empty($Get_First_address) || $Get_First_address!=0)
							{
								updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
								echo $coin_address->currency_symbol.' Success1 <br/>';
							}
							else{
								$Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
								if($Get_First_address){
									updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
									echo $coin_address->currency_symbol.' Success2 <br/>';
								}
							}
						}
				   
                    elseif($coin_address->currency_symbol=='TRX')
                    {
                        $parameter='create_tron_account';

                        $Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_tron_account',$user_details->user_id);
						// echo "<pre>";
						// print_r($Get_First_address); exit;

                        $tron_private_key = $Get_First_address['privateKey'];
                        $tron_public_key = $Get_First_address['publicKey'];
                        $tron_address = $Get_First_address['address']['base58'];
                        $tron_hex = $Get_First_address['address']['hex'];
                        if(!empty($Get_First_address) || $Get_First_address!=0)
                        {
                            updatetronAddress($user_details->user_id,$coin_address->id,$tron_address,$tron_hex,$tron_private_key,$tron_public_key);
                            echo "success TRON";
                        }
                        else{
                            $Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
                            if($Get_First_address){
                                updatetronAddress($user_details->user_id,$coin_address->id,$tron_address,$tron_hex);
                                echo "success TRON";
                            }
                        }
                    }
                         else if($coin_address->currency_symbol=='BNB')
						{



							$parameter='create_eth_account';

                        $Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account',getUserEmail($user_details->user_id));

                      
                        if(!empty($Get_First_address) || $Get_First_address!=0)
                        {
                            updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
                        }
                        else{
                            $Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);
                            if($Get_First_address){
                                updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
                            }
                        }


                         }
						else
						{
							echo $coin_address->currency_symbol.'<br/>';

								$parameter = 'getaccountaddress';

							$Get_First_address1 = $this->local_model->access_wallet($coin_address->id,$parameter,getUserEmail($user_details->user_id));



							if(!empty($Get_First_address1) || $Get_First_address1!=0){
								
								$Get_First_address = $Get_First_address1;
								
								updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
								echo $coin_address->currency_symbol.' Success1 <br/>';
							}
							else{
								if($Get_First_address1){
									$Get_First_address = $this->common_model->update_address_again($user_details->user_id,$coin_address->id,$parameter);

									updateAddress($user_details->user_id,$coin_address->id,$Get_First_address);
									echo $coin_address->currency_symbol.' Success2 <br/>';
								}
							}
						}
		            }

		            else
		            {
		            	if($coin_address->crypto_type=='eth'){
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'ETH'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}
					elseif($coin_address->crypto_type=='tron'){
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'TRX'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}
					else{
						$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'BNB'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
					}


						updateAddress($user_details->user_id,$coin_address->id,$eth_address);
		            }
		            /*else
		            {
		            	$eth_id = $this->common_model->getTableData('currency',array('currency_symbol'=>'ETH'))->row('id');
						$eth_address = getAddress($user_details->user_id,$eth_id);
						updateAddress($user_details->user_id,$coin_address->id,$eth_address);
		            }*/
				}
			}
		}		
    }

    function get_user_list_coin($curr_id,$crypto_type)
	{


	
		$currency=$this->common_model->getTableData('currency',array('status'=>1, 'type'=>'digital','id'=>$curr_id),'','','','','',1)->row();
		$curr_symbol = $currency->currency_symbol;
    $selectFields='US.id as id,CA.address as address,HI.elxisenergy_type as elxisenergy_type,US.elxisenergy_email as email';
  $where=array('US.verified'=>1,$curr_symbol.'_status'=>1);
  //$where=array('US.verified'=>1,'US.id'=>1030731);
  $orderBy=array('US.id','asc');
  $joins = array('crypto_address as CA'=>'CA.user_id = US.id','history as HI'=>'HI.user_id = US.id');
  $users = $this->common_model->getJoinedTableData('users as US',$joins,$where,$selectFields,'','','','','',$orderBy)->result();

		$rude = array();

        //Binance Usd

		if($crypto_type == 'bsc' || $crypto_type == 'tron'|| $crypto_type == 'eth') {
			// for eth,trx and bsc
			echo "get_user_list_coin_final bsc tron and eth<br/>";
			echo $crypto_type."<br/>";
			print_r($users);
			foreach($users as $user)
			{	
				echo "USER".$user->id."<br/>";
				/*$wallet = unserialize($this->common_model->getTableData('crypto_address',array('user_id'=>$user->id),'address','','','','',1)->row('address'));	
				
				$email = getUserEmail($user->id);*/
        $wallet = unserialize($user->address);

        $email = decryptIt($user->elxisenergy_type).$user->email;

				//$currency=$this->common_model->getTableData('currency',array('status'=>1, 'type'=>'digital','id'=>$curr_id))->result();			

				/*$i = 0;
				foreach($currency as $cu)
				{*/

						$count = strlen($wallet[$currency->id]);
						//echo $count."<br>";

						
						
						if(!empty($wallet[$currency->id]) && $count!=1)
						{
							//echo $wallet[$currency->id]; exit;
							//echo "here";
							/*echo $count."<br>";
							echo "here";
							echo $wallet[$cu->id]."<br>";*/
							//echo $currency->crypto_type_other; exit;

							if($currency->crypto_type_other != '')
							{
								$crypto_other_type_arr = explode('|',$currency->crypto_type_other);
								foreach($crypto_other_type_arr as $val)
								{
									$Wallet_balance = 0;
									if($val == $crypto_type)
									{
										echo $val;
										if($currency->coin_type=="token" && $val=='tron')
										{
											$spec_address_count = strlen($wallet[5]);
											if($spec_address_count!=1)
											{
												$tron_private = gettronPrivate($user->id);
												$crypto_type_other = array('crypto'=>$val,'tron_private'=>$tron_private);
												$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet[5],$crypto_type_other);
												echo "<br/>".$wallet[5]."<br/>".$Wallet_balance."<br/>";

												if($Wallet_balance>0){
													$balance[$user->id] = array('currency_symbol'=>$currency->currency_symbol, 
														'currency_name'=>$currency->currency_name,
														'currency_id'=>$curr_id,
														'address'=>$wallet[5],
														'user_id'=>$user->id,
														'user_email'=>$email);
													array_push($rude, $balance[$user->id]); 
												}
											}
										} 
										else if($currency->coin_type=="token" && $val=='bsc')
										{
											$crypto_type_other = array('crypto'=>$val);
											$spec_address_count = strlen($wallet[4]);
											if($spec_address_count!=1)
											{
												$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet[4],$crypto_type_other);
												echo "<br/>".$wallet[4]."<br/>".$Wallet_balance."<br/>";

												if($Wallet_balance>0){
													$balance[$user->id] = array('currency_symbol'=>$currency->currency_symbol, 
														'currency_name'=>$currency->currency_name,
														'currency_id'=>$curr_id,
														'address'=>$wallet[4],
														'user_id'=>$user->id,
														'user_email'=>$email);
													array_push($rude, $balance[$user->id]); 
												}
											}
										}
										else
										{
											$crypto_type_other = array('crypto'=>$val);
											$spec_address_count = strlen($wallet[2]);
											if($spec_address_count!=1)
											{
												$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet[2],$crypto_type_other);
												echo "<br/>Address".$wallet[2]."<br/>".$Wallet_balance."<br/>";

												if($Wallet_balance>0){
													$balance[$user->id] = array('currency_symbol'=>$currency->currency_symbol, 
														'currency_name'=>$currency->currency_name,
														'currency_id'=>$currency->id,
														'address'=>$wallet[2],
														'user_id'=>$user->id,
														'user_email'=>$email);
													array_push($rude, $balance[$user->id]); 
												}
											}
										}
									}
								}
								//exit;
							} else {
								echo "Normal CRYPTO Type";
								echo "<br/>";
								if($currency->coin_type=="token" && $crypto_type=='tron')
								{

									
									$tron_private = gettronPrivate($user->id);
									$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet[$currency->id],$tron_private);
									echo $wallet[$currency->id]."<br/>".$Wallet_balance."<br/>";

									if($Wallet_balance>0){
										$balance[$user->id] = array('currency_symbol'=>$currency->currency_symbol, 
											'currency_name'=>$currency->currency_name,
											'currency_id'=>$currency->id,
											'address'=>$wallet[$currency->id],
											'user_id'=>$user->id,
											'user_email'=>$email);
										array_push($rude, $balance[$user->id]); 
									}
								}
								else
								{
									$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet[$currency->id]);
									echo $wallet[$currency->id]."<br/>".$Wallet_balance."<br/>";

									if($Wallet_balance>0){
										$balance[$user->id] = array('currency_symbol'=>$currency->currency_symbol, 
											'currency_name'=>$currency->currency_name,
											'currency_id'=>$currency->id,
											'address'=>$wallet[$currency->id],
											'user_id'=>$user->id,
											'user_email'=>$email);
										array_push($rude, $balance[$user->id]); 
									}
								}
							}

							//exit;
								
							//echo $Wallet_balance."#".$currency->currency_symbol."<br/>";

							
						}
						/*if($currency->currency_symbol=='XRP'){
							break;
						}*/		
					/*$i++;
				}*/
			}
			//print_r($rude); exit;

        } else {

			// for other
            foreach($users as $user)
			{	
				echo "USER".$user->id."<br/>";
				$wallet = unserialize($this->common_model->getTableData('crypto_address',array('user_id'=>$user->id),'address')->row('address'));

				//echo "<pre>"; print_r($wallet); echo "</pre>";
				
				$email = getUserEmail($user->id);
				$currency=$this->common_model->getTableData('currency',array('status'=>1, 'type'=>'digital','id'=>$curr_id))->result();

				//echo "<pre>"; print_r($currency); echo "</pre>";
				$i = 0;
				foreach($currency as $cu)
				{
						if(($wallet[$cu->id]!='') || ($wallet[$cu->id]!=0))
						{
							$balance[$user->id][$i] = array('currency_symbol'=>$cu->currency_symbol, 
								'currency_name'=>$cu->currency_name,
								'currency_id'=>$cu->id,
								'address'=>$wallet[$cu->id],
								'user_id'=>$user->id,
								'user_email'=>$email);
							array_push($rude, $balance[$user->id][$i]); 
						}		
					$i++;
				}
			}
 

        }


		return $rude;	
	}

	public function get_user_with_dep_det($curr_id,$crypto_type)
	{
       

		$users 	= $this->get_user_list_coin($curr_id,$crypto_type);


		$currencydet = $this->common_model->getTableData('currency', array('id'=>$curr_id))->row();

		//$currencydet = $this->common_model->getTableData('currency', array('id'=>$curr_id),'','','','','',1)->row();

		$orders = $this->common_model->getTableData('transactions', array('type'=>'Deposit', 'user_status'=>'Completed','currency_type'=>'crypto','currency_id'=>$curr_id))->result_array();


		$address_list = $transactionIds = array();


		if(count($users)){


			foreach($users as $user){
				if( $user['address'] != '')
				{
					$address_list[(string)$user['address']] = $user;
				}
			}
		}
		
		if(count($orders)){
			foreach($orders as $order){
				if(trim($order['wallet_txid']) != '')
				$transactionIds[$order['wallet_txid']] = $order;
			}
		}
		// echo "CRYPTO Type".$crypto_type;
		// echo "<br/>";
		// echo "USERSSS";
		// print_r($users);
		// echo "ORDERS";
		// print_r($orders);
		// echo "<br/>";
		//print_r($address_list);
		$currency_decimal = $currencydet->currency_decimal;
		if($crypto_type == 'tron' && $currencydet->trx_currency_decimal != '')
		{
			$currency_decimal = $currencydet->trx_currency_decimal;
		} else if($crypto_type == 'bsc' && $currencydet->bsc_currency_decimal != '')
		{
			$currency_decimal = $currencydet->bsc_currency_decimal;
		}
		
		return array('address_list'=>$address_list,'transactionIds'=>$transactionIds,'currency_decimal'=>$currency_decimal);
	

	}
	
	// cronjob for deposit -  new method
	public function update_crypto_deposits($coin='BTC') // cronjob for deposit
	{
		// Modified by Ram Nivas
		// Modified this method to accomodate dynamic USDT deposits(erc20,trc20 and beb20) for single token
		// modified in get_user_with_dep_det method with crypto_type_other field

		//$currencies = $this->common_model->getTableData('currency',array('status'=>1),'','','','','','')->row();
		$currencies   =  $this->db->query("select * from elxisenergy_currency where currency_symbol='$coin' AND status = 1")->result(); // get user addresses
		// echo "<pre>";
		// print_r($currencies); exit;

		if(count($currencies) > 0)
		{
			echo "Process begins<br/>";
			foreach($currencies as $curr)
			{
				echo "<pre>";
				echo $curr->currency_name;
				echo "<br/>";
				// dynamic call for currencies
				// echo "<pre>";
				// print_r($curr); exit;
				
				$crypto_type = $curr->crypto_type_other;
				if($crypto_type != '')
				{
					echo $crypto_type;
					echo "<br/>";
					// ERC, TRX and BSC Tokens
					$crypto_type_arr = explode("|",$crypto_type);
					foreach($crypto_type_arr as $val)
					{
						echo "crypto type other<br/>";
						print_r($crypto_type_arr);
						echo "<br/>";
						echo "In That, checking".$val;
						echo "<br/>";
						$crypto_type = $curr->crypto_type_other;
						$this->crypto_deposit($curr,$val);
					}

				} else {
					// Other coin
					$crypto_type = $curr->crypto_type;
					$this->crypto_deposit($curr,$crypto_type);

				}

			}
		}

		
	}

	public function crypto_deposit($curr,$crypto_type)
	{
		$curr_id = $curr->id;
		$coin_name =  $curr->deposit_currency;
		$curr_symbol = $curr->currency_symbol;
		$coin_type = $curr->coin_type;
		
		$Deposit_Fees_type = $curr->deposit_fees_type;
		$Deposit_Fees = $curr->deposit_fees;
		$Deposit_Fees_Update = 0;
		$coin_name1 =  $this->common_model->getTableData('currency',array('deposit_currency'=>$coin_name),'','','','','',1)->row('currency_name');


		//Db Call based on coin - retrieve
			// crypto_type_other -


		/*echo $curr_id.'<br>';
		echo $curr_symbol.'<br>';
		echo $coin_type.'<br>';
		echo $crypto_type.'<br>';
		exit;*/
		$user_trans_res   = $this->get_user_with_dep_det($curr_id,$crypto_type);


		$address_list     = $user_trans_res['address_list'];
		$transactionIds   = $user_trans_res['transactionIds'];
		$tot_transactions = array();

		//$valid_server = $this->local_model->get_valid_server();
		$valid_server=1;

		/*$coin_type = $this->common_model->getTableData('currency',array('currency_name'=>$coin_name1),'','','','','',1)->row('coin_type');

		$crypto_type = $this->common_model->getTableData('currency',array('currency_name'=>$coin_name1))->row('crypto_type');*/
		


		if($valid_server)
		{


			if($coin_type=="coin")
			{
			
			switch ($coin_name) 
			{
				case 'Bitcoin':
					$transactions   = $this->local_model->get_transactions('Bitcoin');
					break;

				case 'BNB':
					$transactions 	 = $this->local_model->get_transactions('BNB',$user_trans_res);
					break;

				case 'Tron':
					$transactions 	 = $this->local_model->get_transactions('Tron',$user_trans_res);
					break;

				case 'Ethereum':
					$transactions 	 = $this->local_model->get_transactions('Ethereum',$user_trans_res);
					break;

				case 'Ripple':
					$transactions   = $this->local_model->get_transactions('Ripple',$user_trans_res);
					break;

				case 'Doge':
					$transactions   = $this->local_model->get_transactions('Doge');
					break;

				case 'Litecoin':
					$transactions   = $this->local_model->get_transactions('Litecoin');
					break;

				case 'Dash':
					$transactions   = $this->local_model->get_transactions('Dash');
					break;

				case 'Monero':
					$transactions   = $this->local_model->get_transactions('Monero');
					break;
				
				default:
					show_error('No directory access');
					break;
			}
		}
		else
		{ 
			// Token Logic                  

				$transactions 	 = $this->local_model->get_transactions($coin_name1,$user_trans_res,$crypto_type);
		}
			// echo $coin_name1;
			echo "<pre>mm"; print_r($transactions); echo "</pre>"; //exit();
			//exit();

			if(count($transactions)>0 || $transactions!='')
			{
				$i=0;
				foreach ($transactions as $key => $value) 
				{
					/*26-6-18*/
					$i++;
					$index = $value['address'].'-'.$value['confirmations'].'-'.$i;
					/*26-6-18*/
					
					$tot_transactions[$index] = $value;
				}
			}
			//print_r($tot_transactions); exit;



			if(!empty($tot_transactions) && count($tot_transactions)>0)
			{
				echo "<pre>";
				print_r($tot_transactions);
				
				$a=0;
				foreach($tot_transactions as $row) 
				{
					$a++;
					// $account       = $row['account'];		
					$address       = $row['address'];
					$confirmations = $row['confirmations'];	
					//$txid          = $row['txid'];
					$txid          = $row['txid'].'#'.$row['time'];

					$time_st       = $row['time'];			
					$amount1        = $row['amount'];
					echo "Deposit Type".$Deposit_Fees_type;
					echo "<br/>";
					echo "Deposit fees".$Deposit_Fees;
					echo "<br/>";
					if(isset($Deposit_Fees_type) && !empty($Deposit_Fees_type) && $Deposit_Fees!=0){

						if($Deposit_Fees_type=='Percent'){
							$Deposit_Fee = ($amount1 * ($Deposit_Fees/100));
							$amount = $amount1 - $Deposit_Fee;
							$Deposit_Fees_Update = $Deposit_Fee;
						}
						else{
							$amount = $amount1 - $Deposit_Fees;
							$Deposit_Fees_Update = $Deposit_Fees;
						}

					}else{
						$amount = $amount1;
						$Deposit_Fees_Update = 0;
					}
					echo "Amount".$amount;
					echo "<br/>";
					echo "Deposit Fees Update".$Deposit_Fees_Update;
					echo "<br/>";
					$category      = $row['category'];		
					$blockhash 	   = (isset($row['blockhash']))?$row['blockhash']:'';
					$ind_val 	   = $address.'-'.$confirmations.'-'.$a;
					$from_address = $row['from'];
					
					
						$admin_address = getadminAddress(1,$curr_symbol);
					
				//echo $admin_address."<br/>";
					// echo $row['blockhash'];
					// echo "<br/>";
					// echo $txid; 
					// echo "<br/>";
					// echo $curr_id;
					// echo "<br/>";
					
			
					$counts_tx = $this->db->query('select * from elxisenergy_transactions where information="'.$row['blockhash'].'" and wallet_txid="'.$txid.'" limit 1')->row();
					/*echo count($counts_tx);
					echo "<br>";*/

					// echo $counts_tx;
					//exit;
					
					/*echo $row['blockhash'];
					echo "<br>";
					echo $counts_tx;
					echo "<br>";*/
					if($category == 'receive' && $confirmations > 0 && count($counts_tx) == 0 && $amount>0)
					{
	
						if(isset($address_list[$address]))
						{
							if($coin_name=='Ripple'){

							$user_id = $row['user_id'];
						}
						else{
							
							$user_id   = $address_list[$address]['user_id'];
						}
							
							$coin_name = $address_list[$address]['currency_name'];
							$cur_sym   = $address_list[$address]['currency_symbol'];
							$cur_ids   = $address_list[$address]['currency_id'];
							$email 	   = $address_list[$address]['user_email'];
						}
						else
						{
							foreach ($address_list as $key => $value) 
							{
							
								if(($value['currency_symbol'] == 'ETH') && strtolower($address) ==  strtolower($value['address']))	
								{
									$user_id   = $value['user_id'];
									$coin_name = "else ".$value['currency_name'];
									$cur_sym   = $value['currency_symbol'];
									$cur_ids   = $value['currency_id'];
									$email 	   = $value['user_email'];
								}
							}
						}
						

						if($coin_type=="coin")
						{
							if(trim($from_address)!= trim($admin_address))
							{
								if($coin_name=='Tron'){
									$TRX_hexaddress = admin_trx_hex('1');
									if(trim($from_address)==trim(strtolower($TRX_hexaddress))){
										$user_id='41';
									}
									echo $from_address." =#= Pila".trim(strtolower($TRX_hexaddress))."<br/>";
								}
								
								if(isset($user_id) && !empty($user_id)){
									if(($coin_name=='Tron' && ($amount==0.000001 || $amount==0.000007 || $amount==2 || $amount==5 || $amount==9 || $amount==10 || $amount==0.000003 || $amount==0.000045))){
										echo "TRON Min Amount 0.000001 and 2 Not Inserting<br/>";
									}
									else{
									$balance = getBalance($user_id,$cur_ids,'crypto'); // get user bal
									$finalbalance = $balance+$amount; // bal + dep amount
									//echo "Final".$finalbalance;
									$updatebalance = updateBalance($user_id,$cur_ids,$finalbalance,'crypto'); // Update balance

									// Add to reserve amount
									$reserve_amount = getcryptocurrencydetail($cur_ids);
									$final_reserve_amount = (float)$amount + (float)$reserve_amount->reserve_Amount;
									$new_reserve_amount = updatecryptoreserveamount($final_reserve_amount, $cur_ids);

									// insert the data for deposit details
									$dep_data = array(
										'user_id'    		=> $user_id,
										'currency_id'   	=> $cur_ids,
										'type'       		=> "Deposit",
										'currency_type'		=> "crypto",
										'description'		=> $coin_name." Payment",
										'amount'     		=> $amount,
										'transfer_amount'	=> $amount,
										'fee'				=> $Deposit_Fees_Update,
										'information'		=> $blockhash,
										'wallet_txid'       => $txid,
										'crypto_address'	=> $address,
										'status'     		=> "Completed",
										'datetime' 			=> $time_st,
										'user_status'		=> "Completed",
										'crypto_type'       => $crypto_type,
										'transaction_id'	=> rand(100000000,10000000000),
										'datetime' 		=> (empty($txid))?$time_st:time()
									);
									//print_r($dep_data); exit;
									$ins_id = $this->common_model->insertTableData('transactions',$dep_data);

									$prefix = get_prefix();
									$userr = getUserDetails($user_id);
									$usernames = $prefix.'username';
									$username = $userr->$usernames;
									$sitename = getSiteSettings('site_name');
									// check to see if we are creating the user
									$site_common      =   site_common();
									$email_template = 'Deposit_Complete';
									$special_vars	=	array(
										'###SITENAME###'  =>  $sitename,
										'###USERNAME###'    => $username,
										'###AMOUNT###' 	  	=> $amount,
										'###CURRENCY###'    => $cur_sym,
										'###HASH###'        => $blockhash,
										'###TIME###'        => date('Y-m-d H:i:s',$time_st),
										'###TRANSID###' 	=> $txid,
									);
									
									$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
									
									
									}
								
								}
							}
						}
						else
						{
							if(isset($user_id) && !empty($user_id)){
									$balance = getBalance($user_id,$cur_ids,'crypto'); // get user bal
									$finalbalance = $balance+$amount; // bal + dep amount
									//echo "Final".$finalbalance;
									$updatebalance = updateBalance($user_id,$cur_ids,$finalbalance,'crypto'); // Update balance

									// Add to reserve amount
									$reserve_amount = getcryptocurrencydetail($cur_ids);
									$final_reserve_amount = (float)$amount + (float)$reserve_amount->reserve_Amount;
									$new_reserve_amount = updatecryptoreserveamount($final_reserve_amount, $cur_ids);

									// insert the data for deposit details
									$dep_data = array(
										'user_id'    		=> $user_id,
										'currency_id'   	=> $cur_ids,
										'type'       		=> "Deposit",
										'currency_type'		=> "crypto",
										'description'		=> $coin_name." Payment",
										'amount'     		=> $amount,
										'transfer_amount'	=> $amount,
										'information'		=> $blockhash,
										'wallet_txid'       => $txid,
										'crypto_address'	=> $address,
										'status'     		=> "Completed",
										'datetime' 			=> $time_st,
										'user_status'		=> "Completed",
										'crypto_type'       => $crypto_type,
										'transaction_id'	=> rand(100000000,10000000000),
										'datetime' 		=> (empty($txid))?$time_st:time()
									);
									// echo "DEP DATA2";
									// echo $address; echo "<br/>";
									// print_r($dep_data);
									$ins_id = $this->common_model->insertTableData('transactions',$dep_data);

									$prefix = get_prefix();
									$userr = getUserDetails($user_id);
									$usernames = $prefix.'username';
									$username = $userr->$usernames;
									$sitename = getSiteSettings('site_name');
									// check to see if we are creating the user
									$site_common      =   site_common();
									$email_template = 'Deposit_Complete';
									$special_vars	=	array(
										'###SITENAME###'  =>  $sitename,
										'###USERNAME###'    => $username,
										'###AMOUNT###' 	  	=> $amount,
										'###CURRENCY###'    => $cur_sym,
										'###HASH###'        => $blockhash,
										'###TIME###'        => date('Y-m-d H:i:s',$time_st),
										'###TRANSID###' 	=> $txid,
									);
									
									$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
								}

						}
						
						
					}
					

					if($crypto_type=='eth' || $crypto_type=='bsc' || $crypto_type=='tron'){
									
						//$this->move_to_admin_wallet($coin_name1,$crypto_type);
					}
					
				/*}*/
				}
				/*26-6-18*/
				$result = array('status'=>'success','message'=>'update deposit successed');
				/*26-6-18*/
			}
			else
			{
				/*26-6-18*/
				$result = array('status'=>'success','message'=>'update failed1');
			}
		}
		else
		{
			$result = array('status'=>'error','message'=>'update failed');
		}
		echo json_encode($result);

	}

	// cronjob for deposit -  new method
	public function update_crypto_deposits_admin() // cronjob for deposit
	{
		// Modified by Ram Nivas
		// Modified this method to accomodate dynamic USDT deposits(erc20,trc20 and beb20) for single token
		// modified in get_user_with_dep_det method with crypto_type_other field

		//$currencies = $this->common_model->getTableData('currency',array('status'=>1),'','','','','','')->row();
		$currencies   =  $this->db->query("select * from elxisenergy_currency where status = 1 AND type='digital'")->result(); // get user addresses
		// echo "<pre>";
		// print_r($currencies); exit;

		if(count($currencies) > 0)
		{
			echo "Process begins<br/>";
			foreach($currencies as $curr)
			{
				echo "<pre>";
				echo "CURRENCYNAME";
				echo "<br/>";
				echo $curr->currency_name;
				echo "<br/>";
				// dynamic call for currencies
				// echo "<pre>";
				// print_r($curr); exit;
				
				$crypto_type = $curr->crypto_type_other;
				if($crypto_type != '')
				{
					echo $crypto_type;
					echo "<br/>";
					// ERC, TRX and BSC Tokens
					$crypto_type_arr = explode("|",$crypto_type);
					foreach($crypto_type_arr as $val)
					{
						echo "crypto type other<br/>";
						print_r($crypto_type_arr);
						echo "<br/>";
						echo "In That, checking".$val;
						echo "<br/>";
						$crypto_type = $curr->crypto_type_other;
						$this->crypto_deposit_admin($curr,$val);
					}

				} else {
					// Other coin
					$crypto_type = $curr->crypto_type;
					$this->crypto_deposit_admin($curr,$crypto_type);

				}

			}
		}

		
	}

	public function crypto_deposit_admin($curr,$crypto_type)
	{
		$curr_id = $curr->id;
		$coin_name =  $curr->deposit_currency;
		$curr_symbol = $curr->currency_symbol;
		$coin_type = $curr->coin_type;
		
		$Deposit_Fees_type = $curr->deposit_fees_type;
		$Deposit_Fees = $curr->deposit_fees;
		$Deposit_Fees_Update = 0;
		$coin_name1 =  $this->common_model->getTableData('currency',array('deposit_currency'=>$coin_name),'','','','','',1)->row('currency_name');


		//Db Call based on coin - retrieve
			// crypto_type_other -


		/*echo $curr_id.'<br>';
		echo $curr_symbol.'<br>';
		echo $coin_type.'<br>';
		echo $crypto_type.'<br>';
		exit;*/
		$user_trans_res   = $this->get_admin_with_dep_det($curr_id,$crypto_type);
		echo "USER TRANS REs";
		print_r($user_trans_res);


		$address_list     = $user_trans_res['address_list'];
		$transactionIds   = $user_trans_res['transactionIds'];
		$tot_transactions = array();

		//$valid_server = $this->local_model->get_valid_server();
		$valid_server=1;

		/*$coin_type = $this->common_model->getTableData('currency',array('currency_name'=>$coin_name1),'','','','','',1)->row('coin_type');

		$crypto_type = $this->common_model->getTableData('currency',array('currency_name'=>$coin_name1))->row('crypto_type');*/
		


		if($valid_server)
		{


			if($coin_type=="coin")
			{
			
			switch ($coin_name) 
			{
				case 'Bitcoin':
					$transactions   = $this->local_model->get_transactions('Bitcoin');
					break;

				case 'BNB':
					$transactions 	 = $this->local_model->get_transactions('BNB',$user_trans_res);
					break;

				case 'Tron':
					$transactions 	 = $this->local_model->get_transactions('Tron',$user_trans_res);
					break;

				case 'Ethereum':
					$transactions 	 = $this->local_model->get_transactions('Ethereum',$user_trans_res);
					break;

				case 'Ripple':
					$transactions   = $this->local_model->get_transactions('Ripple',$user_trans_res);
					break;

				case 'Doge':
					$transactions   = $this->local_model->get_transactions('Doge');
					break;

				case 'Litecoin':
					$transactions   = $this->local_model->get_transactions('Litecoin');
					break;

				case 'Dash':
					$transactions   = $this->local_model->get_transactions('Dash');
					break;

				case 'Monero':
					$transactions   = $this->local_model->get_transactions('Monero');
					break;
				
				default:
					show_error('No directory access');
					break;
			}
		}
		else
		{ 
			// Token Logic                  

				$transactions 	 = $this->local_model->get_transactions($coin_name1,$user_trans_res,$crypto_type);
		}
			// echo $coin_name1;
			echo "<pre>mm"; print_r($transactions); echo "</pre>"; //exit();
			//exit();

			if(count($transactions)>0 || $transactions!='')
			{
				$i=0;
				foreach ($transactions as $key => $value) 
				{
					/*26-6-18*/
					$i++;
					$index = $value['address'].'-'.$value['confirmations'].'-'.$i;
					/*26-6-18*/
					
					$tot_transactions[$index] = $value;
				}
			}
			//print_r($tot_transactions); exit;



			if(!empty($tot_transactions) && count($tot_transactions)>0)
			{
				echo "<pre>";
				print_r($tot_transactions);
				
				$a=0;
				foreach($tot_transactions as $row) 
				{
					$a++;
					// $account       = $row['account'];		
					$address       = $row['address'];
					$confirmations = $row['confirmations'];	
					//$txid          = $row['txid'];
					$txid          = $row['txid'].'#'.$row['time'];

					$time_st       = $row['time'];			
					$amount1        = $row['amount'];
					$amount = $amount1;
					$category      = $row['category'];		
					$blockhash 	   = (isset($row['blockhash']))?$row['blockhash']:'';
					$ind_val 	   = $address.'-'.$confirmations.'-'.$a;
					$from_address = $row['from'];
					
					
						$admin_address = getadminAddress(1,$curr_symbol);
					
				//echo $admin_address."<br/>";
					// echo $row['blockhash'];
					// echo "<br/>";
					// echo $txid; 
					// echo "<br/>";
					// echo $curr_id;
					// echo "<br/>";
					
			
					$counts_tx = $this->db->query('select * from elxisenergy_admin_transactions where hash_txid="'.$row['blockhash'].'" limit 1')->row();
					/*echo count($counts_tx);
					echo "<br>";*/

					// echo $counts_tx;
					//exit;
					
					/*echo $row['blockhash'];
					echo "<br>";
					echo $counts_tx;
					echo "<br>";*/
					if($category == 'receive' && $confirmations > 0 && count($counts_tx) == 0 && $amount>0)
					{
						echo "Address LISTTT".$admin_address;
						print_r($address_list);
						if(isset($address_list[$admin_address]))
						{
							if($coin_name=='Ripple'){

							$user_id = $row['user_id'];
						}
						else{
							
							$user_id   = $address_list[$admin_address]['user_id'];
						}
							
							$coin_name = $address_list[$admin_address]['currency_name'];
							$cur_sym   = $address_list[$admin_address]['currency_symbol'];
							$cur_ids   = $address_list[$admin_address]['currency_id'];
							$email 	   = $address_list[$admin_address]['user_email'];
						}
						else
						{
							// print_r($address_list);
							// echo $admin_address; exit;
							foreach ($address_list as $key => $value) 
							{
								$user_id   = $value['user_id'];
								$coin_name = "else ".$value['currency_name'];
								$cur_sym   = $value['currency_symbol'];
								$cur_ids   = $value['currency_id'];
								$email 	   = $value['user_email'];
							}
						}
						

						// insert the data for deposit details
						$dep_data = array(
							'user_id'    		=> 	1,
							'currency_id'   	=> $cur_ids,
							'type'       		=> "Deposit",
							'description'		=> $coin_name." Payment",
							'amount'     		=> $amount,
							'hash_txid'			=> $blockhash,
							'crypto_address'	=> $admin_address,
							'status'     		=> "Completed",
							'datetime' 			=> $time_st,
							'crypto_type'       => $crypto_type,
							'datetime' 		=> (empty($txid))?$time_st:time()
						);
						//print_r($dep_data); exit;
						$ins_id = $this->common_model->insertTableData('admin_transactions',$dep_data);

						$prefix = get_prefix();
						$userr = getUserDetails($user_id);
						$usernames = $prefix.'username';
						$username = $userr->$usernames;
						$sitename = getSiteSettings('site_name');
						// check to see if we are creating the user
						$site_common      =   site_common();
						$email_template = 'Deposit_Complete';
						$special_vars	=	array(
							'###SITENAME###'  =>  $sitename,
							'###USERNAME###'    => $username,
							'###AMOUNT###' 	  	=> $amount,
							'###CURRENCY###'    => $cur_sym,
							'###HASH###'        => $blockhash,
							'###TIME###'        => date('Y-m-d H:i:s',$time_st),
							'###TRANSID###' 	=> $blockhash,
						);
						
						//$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
						
						
					}
					

					
					
				/*}*/
				}
				/*26-6-18*/
				$result = array('status'=>'success','message'=>'update deposit successed');
				/*26-6-18*/
			}
			else
			{
				/*26-6-18*/
				$result = array('status'=>'success','message'=>'update failed1');
			}
		}
		else
		{
			$result = array('status'=>'error','message'=>'update failed');
		}
		echo json_encode($result);

	}

	function get_admin_list_coin($curr_id,$crypto_type)
	{


		$users = $this->common_model->getTableData('admin_wallet',array('user_id'=>1), '','','','')->result();
		
		$currency=$this->common_model->getTableData('currency',array('status'=>1, 'type'=>'digital','id'=>$curr_id),'','','','','',1)->row();

		$rude = array();

        //Binance Usd
		echo "CTYPE".$crypto_type;

		if($crypto_type == 'bsc' || $crypto_type == 'tron'|| $crypto_type == 'eth') {
			// for eth,trx and bsc
			echo "get_user_list_coin_final bsc tron and eth<br/>";
			echo $crypto_type."<br/>";
			foreach($users as $user)
			{	
				$balance = json_decode($user->wallet_balance, true);
				$wallet = json_decode($user->addresses, true);	
				
				//$email = getUserEmail($user->id);

				//$currency=$this->common_model->getTableData('currency',array('status'=>1, 'type'=>'digital','id'=>$curr_id))->result();			

				/*$i = 0;
				foreach($currency as $cu)
				{*/

						$count = strlen($wallet[$currency->currency_symbol]);
						//echo $count."<br>";
						
						if(!empty($wallet[$currency->currency_symbol]) && $count!=1)
						{
							//echo "here";
							/*echo $count."<br>";
							echo "here";
							echo $wallet[$cu->id]."<br>";*/

							if($currency->crypto_type_other != '')
							{
								$crypto_other_type_arr = explode('|',$currency->crypto_type_other);
								foreach($crypto_other_type_arr as $val)
								{
									$Wallet_balance = 0;
									if($val == $crypto_type)
									{
										echo $val;
										if($currency->coin_type=="token" && $val=='tron')
										{
											$private_key = getadmintronPrivate(1);
											$crypto_type_other = array('crypto'=>$val,'tron_private'=>$private_key);
											$admin_address   =   $wallet['TRX'];
											$wallet_balance= $this->local_model->wallet_balance($currency->currency_name, $admin_address,$crypto_type_other);
											echo "<br/>".$wallet['TRX']."<br/>".$wallet_balance."<br/>";

											if($Wallet_balance>0){
												$balance[$user->id] = array('currency_symbol'=>$currency->currency_symbol, 
													'currency_name'=>$currency->currency_name,
													'currency_id'=>$curr_id,
													'address'=>$wallet['TRX'],
													'user_id'=>$user->user_id,
													'user_email'=>'info@elxisenergy.io');
												array_push($rude, $balance[$user->user_id]); 
											}
										} 
										else if($currency->coin_type=="token" && $val=='bsc')
										{
											$crypto_type_other = array('crypto'=>$val);
											$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet['BNB'],$crypto_type_other);
											echo "<br/>".$wallet['BNB']."<br/>".$Wallet_balance."<br/>";

											if($Wallet_balance>0){
												$balance[$user->user_id] = array('currency_symbol'=>$currency->currency_symbol, 
													'currency_name'=>$currency->currency_name,
													'currency_id'=>$curr_id,
													'address'=>$wallet['BNB'],
													'user_id'=>$user->user_id,
													'user_email'=>'info@elxisenergy.io');
												array_push($rude, $balance[$user->user_id]); 
											}
										}
										else
										{
											$crypto_type_other = array('crypto'=>$val);
											$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet['ETH'],$crypto_type_other);
											echo "<br/>".$wallet['ETH']."<br/>".$Wallet_balance."<br/>";

											if($Wallet_balance>0){
												$balance[$user->user_id] = array('currency_symbol'=>$currency->currency_symbol, 
													'currency_name'=>$currency->currency_name,
													'currency_id'=>$currency->id,
													'address'=>$wallet['ETH'],
													'user_id'=>$user->user_id,
													'user_email'=>'info@elxisenergy.io');
												array_push($rude, $balance[$user->user_id]); 
											}
										}
									}
								}
								//exit;
							} else {
								echo "Normal CRYPTO Type";
								echo "<br/>";
								if($currency->coin_type=="token" && $crypto_type=='tron')
								{

									
									$private_key = getadmintronPrivate(1);
									$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet[$currency->id],$private_key);
									echo $wallet[$currency->currency_symbol]."<br/>".$Wallet_balance."<br/>";

									if($Wallet_balance>0){
										$balance[$user->id] = array('currency_symbol'=>$currency->currency_symbol, 
											'currency_name'=>$currency->currency_name,
											'currency_id'=>$currency->id,
											'address'=>$wallet[$currency->currency_symbol],
											'user_id'=>$user->user_id,
											'user_email'=>'info@elxisenergy.io');
										array_push($rude, $balance[$user->user_id]); 
									}
								}
								else
								{
									//echo $wallet[$currency->currency_symbol]; exit;
									$Wallet_balance = $this->local_model->wallet_balance($currency->currency_name,$wallet[$currency->currency_symbol]);
									echo $wallet[$currency->currency_symbol]."<br/>".$Wallet_balance."<br/>";

									if($Wallet_balance>0){
										$balance[$user->id] = array('currency_symbol'=>$currency->currency_symbol, 
											'currency_name'=>$currency->currency_name,
											'currency_id'=>$currency->id,
											'address'=>$wallet[$currency->currency_symbol],
											'user_id'=>$user->user_id,
											'user_email'=>'info@elxisenergy.io');
										array_push($rude, $balance[$user->user_id]); 
									}
								}
							}

						//exit;
							
						//echo $Wallet_balance."#".$currency->currency_symbol."<br/>";

							
						}
						/*if($currency->currency_symbol=='XRP'){
							break;
						}*/		
					/*$i++;
				}*/
			}
			//print_r($rude); exit;

        } else {

			// for other
			//print_r($users); exit;
            foreach($users as $user)
			{	
				$balance = json_decode($user->wallet_balance, true);
				$wallet = json_decode($user->addresses, true);	

				//echo "<pre>"; print_r($wallet); echo "</pre>";
				
				//$email = getUserEmail($user->id);
				$currency=$this->common_model->getTableData('currency',array('status'=>1, 'type'=>'digital','id'=>$curr_id))->result();

				//echo "<pre>"; print_r($currency); echo "</pre>";
				$i = 0;
				foreach($currency as $cu)
				{
					print_r($wallet);
					echo $wallet[$cu->currency_symbol];
					echo "BREAKKK";
					echo "<br/>";
						if(($wallet[$cu->currency_symbol]!='') || ($wallet[$cu->currency_symbol]!=0))
						{
							$balance[$user->id][$i] = array('currency_symbol'=>$cu->currency_symbol, 
								'currency_name'=>$cu->currency_name,
								'currency_id'=>$cu->id,
								'address'=>$wallet[$cu->currency_symbol],
								'user_id'=>$user->user_id,
								'user_email'=>'info@elxisenergy.io');
							array_push($rude, $balance[$user->user_id][$i]); 
						}		
					$i++;
				}
			}
 

        }
		echo "RUDEEEE";
		print_r($rude);

		return $rude;	
	}

	public function get_admin_with_dep_det($curr_id,$crypto_type)
	{
       

		$users 	= $this->get_admin_list_coin($curr_id,$crypto_type);


		$currencydet = $this->common_model->getTableData('currency', array('id'=>$curr_id))->row();

		//$currencydet = $this->common_model->getTableData('currency', array('id'=>$curr_id),'','','','','',1)->row();

		$orders = $this->common_model->getTableData('transactions', array('type'=>'Deposit', 'user_status'=>'Completed','currency_type'=>'crypto','currency_id'=>$curr_id))->result_array();


		$address_list = $transactionIds = array();


		if(count($users)){


			foreach($users as $user){
				if( $user['address'] != '')
				{
					$address_list[(string)$user['address']] = $user;
				}
			}
		}
		
		if(count($orders)){
			foreach($orders as $order){
				if(trim($order['wallet_txid']) != '')
				$transactionIds[$order['wallet_txid']] = $order;
			}
		}
		echo "CRYPTO Type".$crypto_type;
		echo "<br/>";
		echo "USERSSS";
		print_r($users);
		echo "ORDERS";
		print_r($orders);
		echo "<br/>";
		print_r($address_list);
		$currency_decimal = $currencydet->currency_decimal;
		if($crypto_type == 'tron' && $currencydet->trx_currency_decimal != '')
		{
			$currency_decimal = $currencydet->trx_currency_decimal;
		} else if($crypto_type == 'bsc' && $currencydet->bsc_currency_decimal != '')
		{
			$currency_decimal = $currencydet->bsc_currency_decimal;
		}
		
		return array('address_list'=>$address_list,'transactionIds'=>$transactionIds,'currency_decimal'=>$currency_decimal);
	

	}

	public function transfer_to_admin_wallet($coinname)
	{
	    $coinname = str_replace("%20"," ",$coinname);
	    $currency_det =   $this->db->query("select * from elxisenergy_currency where currency_name = '".$coinname."' ")->row(); // get currency detail
	    $currency_status = $currency_det->currency_symbol.'_status';
	    $address_list   =  $this->db->query("select * from elxisenergy_crypto_address where ".$currency_status." = 1")->result(); // get user addresses
	    $fetch          =  $this->db->query("select * from elxisenergy_admin_wallet where id='1'")->row(); // get admin wallet
	    $get_addr       =  json_decode($fetch->addresses,true);
	    $toaddress      =  $get_addr[$currency_det->currency_symbol]; // get admin address

	    if($coinname!="")
	    {
	        $i =1;

	        foreach ($address_list as $key => $value) {

	                $arr       = unserialize($value->address);
	                $from      = $arr[$currency_det->id];
	                echo 'from'.$from.'<br>';

	                $amount    = $this->local_model->wallet_balance($coinname,$from); // get balance 
					echo 'amount'.$amount.'<br>';
	                $minamt       = $currency_det->min_withdraw_limit; // get minimum withdraw limit
	                $from_address = trim($from); // get user address- from address
	                $to = trim($toaddress); // get admin address - to address
                   
                   echo 'to'.$to.'<br>';

	                if($from_address!='0') { // check user address to be valid

	                if($amount>0) // check transfer amount with min withdraw limit and to be valid
	                {
	                    switch ($coinname) 
	                    {
	                        case 'Ethereum': // get transcation details for eth
	                        $GasLimit = 21000;
	                        $Gas_calc = $this->check_ethereum_functions('eth_gasPrice','Ethereum');
	                        $Gwei = $Gas_calc;
	                        $GasPrice = $Gwei;
	                        $Gas_res = $Gas_calc / 1000000000;
	                        $Gas_txn = $Gas_res / 1000000000;
	                        $txn_fee = $GasLimit * $Gas_txn;
	                        $amount_send = $amount - $txn_fee;
	                        $amounts = $amount_send * 1000000000000000000;
	                        $amount1 = rtrim(sprintf("%u", $amounts), ".");
	                        $nonce = $this->get_transactioncount($from_address);
	                        $trans_det      = array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice,'nonce'=>$nonce);
	                        break;

	                        case 'Tether': // get transcation details for usdt
	                        $GasLimit = 30000;
	                        $Gas_calc = $this->check_ethereum_functions('eth_gasPrice','Ethereum');
	                        $Gwei = $Gas_calc;
	                        $GasPrice = $Gwei;
	                        $Gas_res = $Gas_calc / 1000000000;
	                        $Gas_txn = $Gas_res / 1000000000;
	                        $txn_fee = $GasLimit * $Gas_txn;
	                        $amount_send = $amount;
	                        $amounts = $amount_send * 1000000;
	                        $amount1 = rtrim(sprintf("%u", $amounts), ".");
	                        $nonce = $this->get_transactioncount($from_address);
	                        $contract_address = $currency_det->contract_address;
	                        $trans_det      = array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice,'nonce'=>$nonce);
	                        break;

	                        
	                    } 

	                    //print_r($trans_det); exit;

                        if($coinname=="Tether") // check eth balance for usdt transfer
		                {
		                	$eth_balance = $this->local_model->wallet_balance("ethereum",$from_address); // get balance from blockchain
		                	//$eth_balance = getBalance($value->user_id,3); // get balance from db
		                	if($eth_balance >= "0.001")
		                	{
                                $send_money_res = $this->local_model->make_transfer($coinname,$trans_det); // transfer to admin
		                		//$send_money_res = "test";
		                	}
		                	else
		                	{
                                $eth_amount = 0.001;
                                $GasLimit1 = 21000;
                                $Gas_calc1 = $this->check_ethereum_functions('eth_gasPrice','Ethereum');
		                        $Gwei1 = $Gas_calc1;
		                        $GasPrice1 = $Gwei1;
		                        $Gas_res1 = $Gas_calc1 / 1000000000;
		                        $Gas_txn1 = $Gas_res1 / 1000000000;
                                $txn_fee = $GasLimit1 * $Gas_txn1;
                                $send_amount = $eth_amount + $txn_fee;
		                		$eth_amounts = $send_amount * 1000000000000000000;
		                        $eth_amount1 =  rtrim(sprintf("%u", $eth_amounts), ".");
		                        $nonce1 = $this->get_transactioncount($to);
		                        $eth_trans = array('from'=>$to,'to'=>$from_address,'value'=>(float)$eth_amount1,'gas'=>(float)$GasLimit1,'gasPrice'=>(float)$GasPrice1,'nonce'=>$nonce1);
                                $send_money_res1 = $this->local_model->make_transfer("ethereum",$eth_trans); 
                               /* updateBalance($value->user_id,2,$eth_amount);
                                $admin_ethbalance = getadminBalance(1,2); // get admin eth balance
				                $eth_bal = $admin_ethbalance - $eth_amount; // calculate remaining eth amount in admin wallet
				                updateadminBalance(1,2,$eth_bal); // update eth balance in admin wallet*/
		                	}
		                }
		                else if($coinname=="Ripple") // check eth balance for usdt transfer
		                {
		                	echo "Ripple";
		                }

		                else
		                {
		                	$send_money_res = $this->local_model->make_transfer($coinname,$trans_det); // transfer to admin
		                	//$send_money_res = "test";
		                }

	                    // add to admin wallet logs
                        if($send_money_res!="" || $send_money_res!="error")
                        {
	                    $trans_data = array(
	                                        'userid'=>$value->user_id,
	                                        'crypto_address' => $from_address,
	                                        'type'=>'deposit',
	                                        'amount'=>(float)$amount,
	                                        'currency_symbol'=>$currency_det->currency_symbol,
	                                        'status'=>'Completed',
	                                        'date_created'=>date('Y-m-d H:i:s'),
	                                        'currency_id'=>$currency_det->id,
	                                        'txn_id'=>$send_money_res
	                                    );
	                    $insert = $this->common_model->insertTableData('admin_wallet_logs',$trans_data);
	                    $result = array('status'=>'success','message'=>'update deposit success');
	                    }

	                }
	                else
	                {
                       $result = array('status'=>'failed','message'=>'update deposit failed insufficient balance');
	                }

	            }
	            else
	            {
	                  $result = array('status'=>'failed','message'=>'invalid address');	
	            }

	        $i++;}

	    }
	    die(json_encode($result));

	}

	function get_transactioncount($address)
	{
       $coin_name = 'Ethereum';
       $model_name = strtolower($coin_name).'_wallet_model';
	   $model_location = 'wallets/'.strtolower($coin_name).'_wallet_model';
	   $this->load->model($model_location,$model_name);
	   $getcount = $this->$model_name->eth_getTransactionCount($address);
	   //echo "Get TransactionCount ===========> ".$getcount;
	   return $getcount;
	}

	function check_ethereum_functions($value,$coin)
	{
		$coin_name = $coin;
		$model_name = strtolower($coin_name).'_wallet_model';
		$model_location = 'wallets/'.strtolower($coin_name).'_wallet_model';
		$this->load->model($model_location,$model_name);
		if($value=='eth_accounts')
		{
		$parameter = "";
		$get_account = $this->$model_name->eth_accounts($parameter);
		echo "Get Account ===========> ".$get_account;
		}
		else if($value=='eth_blockNumber')
		{
		$parameter = "";
		$get_blockNumber = $this->$model_name->eth_blockNumber($parameter);
		echo "Get Block Number ===========> ".$get_blockNumber;
		}
		else if($value=='eth_getLogs')
		{
		$parameter = "";
		$getLogs = $this->$model_name->eth_getLogs($parameter);
		echo "Get Logs ===========> ".$getLogs;
		}
		else if($value=='eth_getBalance')
		{
		$parameter = "0x8936c1af634e0a1c3c6ac6bf4af7f1e37a565d14";
		$getBalance = $this->$model_name->eth_getBalance($parameter);
		echo "Get Balance ===========> ".$getBalance;
		}
		else if($value=='eth_getTransactionCount')
		{
		$parameter = "0x8936c1af634e0a1c3c6ac6bf4af7f1e37a565d14";
		$getcount = $this->$model_name->eth_getTransactionCount($parameter);
		echo "Get TransactionCount ===========> ".$getcount;
		}
		else if($value=='eth_gasPrice')
		{
			$parameter = "";
			$gas_price = $this->$model_name->eth_gasPrice($parameter);
			return $gas_price;
		}
		else if($value=='eth_pending')
		{
			//$txn_count = $this->$model_name->eth_getTransactionCount("0x2f460786e12e7720bed76ffcf1f31eb2ad303e49","pending");
			$txn_count = $this->$model_name->eth_pendingTransactions();
			return $txn_count;
		}

	}


    // function check_ethereum_functions($value)
	// {
	// 	echo $coin_name = 'Ethereum';
	// 	$model_name = strtolower($coin_name).'_wallet_model';
	// 	$model_location = 'wallets/'.strtolower($coin_name).'_wallet_model';
	// 	$this->load->model($model_location,$model_name);
		
	// 	if($value=='eth_gasPrice')
	// 	{
	// 		$parameter = "";
	// 		$gas_price = $this->$model_name->eth_gasPrice($parameter);
	// 		return $gas_price;
	// 	}
	// 	else
	// 	{
	// 		return '1';
	// 	}

	// }

	function cancel_withdraw_auto()
	{
		// Cancel the withdraw automatically after 10 mins cron
		$withdraws = $this->common_model->getTableData('transactions', array('type' =>'withdraw', 'status'=>'Pending', 'user_status'=>'Pending'))->result();
		if(count($withdraws))
		{
			foreach($withdraws as $withdraw)
			{
				$datetime =  $withdraw->datetime;
				$time = strtotime($datetime);
				$withdraw_timestamp = strtotime(date("Y-m-d H:i:s", strtotime("+10 minutes", $time))); // Check whether the time exceeds 10 mins
				$current_time = date('Y-m-d H:i:s', time());
				$current_date_timestamp = strtotime($current_time);
				if($withdraw_timestamp < $current_date_timestamp)
				{
					$currency = $withdraw->currency_id;
					$amount = $withdraw->amount;
					$balance = getBalance($withdraw->user_id,$currency,'crypto');
					$finalbalance = $balance+$amount;
					$updatebalance = updateBalance($withdraw->user_id,$currency,$finalbalance,'crypto');
					$updateData['user_status'] = 'Cancelled';
					$updateData['status'] = 'Cancelled';
					$condition = array('trans_id' => $withdraw->trans_id,'type' => 'withdraw','currency_type'=>'crypto');
					$update = $this->common_model->updateTableData('transactions', $condition, $updateData);
					echo "Withraw Cancelled - ".$withdraw->trans_id."<br/>";
				}
			}
		}
	}

	function withdraw_coin_user_confirm($id)
	{
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			front_redirect('', 'refresh');
		}
		$id = decryptIt($id);

		$isValids = $this->common_model->getTableData('transactions', array('trans_id' => $id, 'type' =>'withdraw', 'user_status'=>'Pending','status'=>'Pending'));
		$isValid = $isValids->num_rows();
		$withdraw = $isValids->row();

		//print_r($isValid);
		//exit();
		if($isValid > 0)
		{
			$fromid 	= $withdraw->user_id;
			 $fromuser  = $this->common_model->getTableData('users',array('id'=>$fromid))->row();
			 $fromacc   = getUserEmail($fromid);

			if($withdraw->user_status=='Completed')
			{
				$this->session->set_flashdata('error','Your withdraw request already confirmed');
				front_redirect('wallet', 'refresh');
			}
			else if($withdraw->user_status=='Cancelled')
			{
				$this->session->set_flashdata('error','Your withdraw request already cancelled');
				front_redirect('wallet', 'refresh');
			}
			elseif($withdraw->user_id != $user_id)
			{
				$this->session->set_flashdata('error','Your are not the owner of this withdraw request');
				front_redirect('wallet', 'refresh');
			}
			else {
				if($withdraw->currency_type!='fiat'){
					$eth_id = $this->common_model->getTableData("currency",array("currency_symbol"=>"ETH"))->row('id');
					$eth_admin_balance = getadminbalance(1,$eth_id);
					

					$bnb_id = $this->common_model->getTableData("currency",array("currency_symbol"=>"BNB"))->row('id');
					$bnb_admin_balance = getadminbalance(1,$bnb_id);

					$tron_id = $this->common_model->getTableData("currency",array("currency_symbol"=>"TRX"))->row('id');
					$tron_admin_balance = getadminbalance(1,$tron_id);

					$amount 		= $withdraw->transfer_amount;
					$address 		= $withdraw->crypto_address;
					$currency 		= $withdraw->currency_id;
					$tagid = $withdraw->destination_tag;
					$coin_name  	= getcryptocurrencys($currency);
					$coin_symbol  	= getcryptocurrency($currency);
					// $coin_name = strtolower($coin_name);
					// $coin_name = str_replace(" ","",$coin_name);
					
					/*New code 26-6-18*/
					//$from_address1 = getAddress($withdraw->user_id,$withdraw->currency_id);
					$currency_det = getcryptocurrencydetail($currency);

					if($currency_det->crypto_type_other != '')
					{
						$crypto_other_type_arr = explode('|',$currency_det->crypto_type_other);
						foreach($crypto_other_type_arr as $val)
						{
							if($val=='eth' && $withdraw->crypto_type == $val) // TOKEN
							{   
								$mini_balance = "0.005";
								if($eth_admin_balance <= $mini_balance)
								{
									$this->session->set_flashdata('error','Your Ethereum Balance is low so you did not able to withdraw');
									front_redirect('withdraw/'.$coin_symbol, 'refresh');
								}
								else
								{

								}
							}


							if($val=='bsc' && $withdraw->crypto_type == $val) // TOKEN
							{
								$mini_balance = "0.005";
								if($bnb_admin_balance <= $mini_balance)
								{
									$this->session->set_flashdata('error','Your BNB Balance is low so you did not able to withdraw');
									front_redirect('withdraw/'.$coin_symbol, 'refresh');
								}
								else
								{

								}
							}

							if($val=='tron' && $withdraw->crypto_type == $val) // TOKEN
							{
								$mini_balance = "2";
								if($tron_admin_balance <= $mini_balance)
								{
									$this->session->set_flashdata('error','Your TRX Balance is low so you did not able to withdraw');
									front_redirect('withdraw/'.$coin_symbol, 'refresh');
								}
								else
								{

								}
							}
						}
					} else {
						if($currency_det->crypto_type=='eth') // TOKEN
						{   
							$mini_balance = "0.005";
							if($eth_admin_balance <= $mini_balance)
							{
								$this->session->set_flashdata('error','Your Ethereum Balance is low so you did not able to withdraw');
								front_redirect('withdraw/'.$coin_symbol, 'refresh');
							}
							else
							{

							}
						}


						if($currency_det->crypto_type=='bsc') // TOKEN
						{
							$mini_balance = "0.005";
							if($bnb_admin_balance <= $mini_balance)
							{
								$this->session->set_flashdata('error','Your BNB Balance is low so you did not able to withdraw');
								front_redirect('withdraw/'.$coin_symbol, 'refresh');
							}
							else
							{

							}
						}

						if($currency_det->crypto_type=='tron') // TOKEN
						{
							$mini_balance = "2";
							if($tron_admin_balance <= $mini_balance)
							{
								$this->session->set_flashdata('error','Your TRX Balance is low so you did not able to withdraw');
								front_redirect('withdraw/'.$coin_symbol, 'refresh');
							}
							else
							{

							}
						}
					}


                   echo $coin_symbol;
                    $from_address1 = getadminAddress(1,$coin_symbol);
                    
					

					$user_address = getAddress($withdraw->user_id,$withdraw->currency_id);
					
					/*End 26-6-18*/
					// echo "<br>";
					// echo "Coin Name : - ".$coin_name;
					// echo "<br>";
					// echo "From Address : - ".$from_address1;
					// echo "<br>";
					// echo "To Address : - ".$address;
					// echo "<br>";
					//exit;
					if($currency_det->crypto_type_other != '')
					{
						$crypto_other_type_arr = explode('|',$currency_det->crypto_type_other);
						foreach($crypto_other_type_arr as $val)
						{
							if($val=="tron" && $withdraw->crypto_type == $val)
							{
								echo "tron";
								$from_address1 = getadminAddress(1, 'TRX');
								$private_key = getadmintronPrivate(1);
								$crypto_type_other = array('crypto'=>$val,'tron_private'=>$private_key);
								$wallet_bal 	= $this->local_model->wallet_balance($coin_name, $from_address1,$crypto_type_other);
							}
							else if($val=="bsc" && $withdraw->crypto_type == $val)
							{
								echo "bsc";
								$from_address1 = getadminAddress(1, 'BNB');
								$crypto_type_other = array('crypto'=>$val);
								$wallet_bal 	= $this->local_model->wallet_balance($coin_name, $from_address1,$crypto_type_other);
							}
							else if($val=="eth" && $withdraw->crypto_type == $val)
							{
								echo "eth";
								$from_address1 = getadminAddress(1, 'ETH');
								$crypto_type_other = array('crypto'=>$val);
								$wallet_bal 	= $this->local_model->wallet_balance($coin_name, $from_address1,$crypto_type_other);
							}
						}
					} else {
						if($currency_det->crypto_type=="tron")
						{
							$private_key = getadmintronPrivate(1);
							$crypto_type_other = array('crypto'=>$currency_det->crypto_type,'tron_private'=>$private_key);
							$wallet_bal 	= $this->local_model->wallet_balance($coin_name, $from_address1,$crypto_type_other);
						}
						else
						{
							$crypto_type_other = array('crypto'=>$currency_det->crypto_type);
							$wallet_bal 	= $this->local_model->wallet_balance($coin_name, $from_address1,$crypto_type_other);
						}
					}

					//$wallet_bal = 100;
                    
                    $coin_type = $currency_det->coin_type;
                    $coin_decimal = $currency_det->currency_decimal;
                    $decimal_places = coin_decimal($coin_decimal);
                    
					//print_r($wallet_bal);  die();
					//$address
					$wallet_bal = number_format((float)$wallet_bal,8,'.','');
					$amount = number_format($amount,8,'.','');
                    //$wallet_bal = getBalance($withdraw->user_id,$currency,'crypto');

					// echo "From Balance : ".$wallet_bal;
					// echo "<br>";
					// echo "Withdraw Amount :".$amount; 
					// exit;
                 
					if($wallet_bal >= $amount)
					{
						if($coin_type=="coin")
						{
							switch ($coin_name) 
							{
								case 'Ethereum':
									$from_address = trim($from_address1);
									$to = trim($address);	
									$amount1 = $amount * 1000000000000000000;
								// $amount1 =  rtrim(sprintf("%u", $amounts), ".");
									$GasLimit = 21000;
									$Gwei = $this->check_ethereum_functions('eth_gasPrice',"Ethereum");
									$GasPrice = $Gwei;
									$privateKey = getadminetherPrivate(1);
									$trans_det 		= array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice,"privateKey"=>$privateKey);
									//$trans_det 		= array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1);
									/*$trans_det 		= array('address'=>$address,'amount'=>(float)$amount,'comment'=>'Admin Confirms Withdraw');*/
									break;

									case 'BNB':
									$from_address = trim($from_address1);
									$to = trim($address);	
									$amount1 = $amount * 1000000000000000000;
									$GasLimit = 120000;
									/*$Gwei = $this->check_ethereum_functions('eth_gasPrice',"BNB");
									$GasPrice = $Gwei;*/
									$GasPrice = 30000000000;
									
									$trans_det 		= array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice);
									
									break;

									case 'Tron':
									$from_address = trim($from_address1);
									$to = trim($address);	
									$amount1 = $amount * 1000000;
									$privateKey = getadmintronPrivate(1);
									$trans_det 		= array('fromAddress'=>$from_address,'toAddress'=>$to,'amount'=>rtrim(sprintf("%.0f", $amount1), "."),"privateKey"=>$privateKey);
								//print_r($trans_det); exit;

									break;

									case "Ripple":
									$xrp_tag_det = $this->common_model->getTableData('crypto_address', array('user_id' => $fromid))->row();
									$from_address = trim($from_address1);
										$to = trim($address);
									$trans_det = array('fromacc' => $fromacc, 'toaddress' => $to, 'amount' => (float) $amount, 'tagid' => $xrp_tag_det->payment_id, 'destag' => $tagid, 'secret' => $xrp_tag_det->auto_gen, 'comment' => 'Admin Confirms Withdraw', 'comment_to' => 'Completed');

									break;	
															
								default:
									$trans_det 		= array('address'=>$address,'amount'=>(float)$amount,'comment'=>'Admin Confirms Withdraw');

									//$trans_det 		= array('fromacc'=>$fromacc,'toaddress'=>$address,'amount'=>(float)$amount,'minconf'=>1,'comment'=>'Admin Confirms Withdraw','comment_to'=>'Completed');
									break;
							}
							// echo $amount;
							// echo "<br>";
							// echo $decimal_places;
							// echo "<br>";
							// print_r($trans_det);
							$send_money_res = $this->local_model->make_transfer($coin_name,$trans_det);
					    }
					    else
					    {
							if($currency_det->crypto_type_other != '')
							{
								$crypto_other_type_arr = explode('|',$currency_det->crypto_type_other);
								foreach($crypto_other_type_arr as $val)
								{
									if($val=='eth' && $withdraw->crypto_type == $val){
										$from_address = trim($from_address1);
										$to = trim($address);	
										$amount1 = $amount * $decimal_places;
										//$amount1 =  rtrim(sprintf("%u", $amounts), ".");
										$GasLimit = 70000;
										//$Gwei = 30 * 1000000000;
										$GasPrice = $this->check_ethereum_functions('eth_gasPrice',"Ethereum");
										$privateKey = getadminetherPrivate(1);
										$trans_det 		= array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice,'privateKey'=>$privateKey);
									}
									elseif($val=='bsc' && $withdraw->crypto_type == $val){
										$coin_decimal = $currency_det->bsc_currency_decimal;
										$decimal_places = coin_decimal($coin_decimal);
										$from_address = trim($from_address1);
										$to = trim($address);	
										$amount1 = $amount * $decimal_places;
										//$amount1 =  rtrim(sprintf("%u", $amounts), ".");
										$GasLimit = 120000;
										$GasPrice = 30 * 1000000000;
										//$GasPrice = $this->check_ethereum_functions('eth_gasPrice',"BNB");
										$trans_det 		= array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice);
									}
									elseif($val=='tron' && $withdraw->crypto_type == $val){
										$coin_decimal = $currency_det->trx_currency_decimal;
										$decimal_places = coin_decimal($coin_decimal);
										$amount1 = $amount * $decimal_places;
										$from_address = trim($from_address1);
										$to = trim($address);
										$privateKey = getadmintronPrivate(1);
										$trans_det 	= array('owner_address'=>$from_address,'to_address'=>$to,'amount'=>rtrim(sprintf("%.0f", $amount1), "."),'privateKey'=>$privateKey);
										//print_r($trans_det); exit;
		
										/*$from_address = trim($from_address1);
										$to = trim($address);	
										$amount1 = $amount * $decimal_places;
										$trans_det 		= array('owner_address'=>$from_address,'to_address'=>$to,'amount'=>(float)$amount1);*/
									}
								}
								// echo $amount;
								// echo "<br>";
								// echo $decimal_places;
								// echo "<br>";
								// print_r($trans_det);
								// echo "<br/>";
								// //echo $val;
								// exit;
								$send_money_res = $this->local_model->make_transfer($coin_name,$trans_det,$withdraw->crypto_type);
							} else {
								if($currency_det->crypto_type=='eth'){
									$from_address = trim($from_address1);
									$to = trim($address);	
									$amount1 = $amount * $decimal_places;
									//$amount1 =  rtrim(sprintf("%u", $amounts), ".");
									$GasLimit = 70000;
									//$Gwei = 30 * 1000000000;
									$GasPrice = $this->check_ethereum_functions('eth_gasPrice',"Ethereum");
									$privateKey = getadminetherPrivate(1);
									$trans_det 		= array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice,'privateKey'=>$privateKey);
								}
								elseif($currency_det->crypto_type=='bsc'){
									$from_address = trim($from_address1);
									$to = trim($address);	
									$amount1 = $amount * $decimal_places;
									//$amount1 =  rtrim(sprintf("%u", $amounts), ".");
									$GasLimit = 120000;
									$GasPrice = 30 * 1000000000;
									//$GasPrice = $this->check_ethereum_functions('eth_gasPrice',"BNB");
									$trans_det 		= array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice);
								}
								elseif($currency_det->crypto_type=='tron'){
	
									$amount1 = $amount * $decimal_places;
									$from_address = trim($from_address1);
									$to = trim($address);
									$privateKey = getadmintronPrivate(1);
									$trans_det 	= array('owner_address'=>$from_address,'to_address'=>$to,'amount'=>rtrim(sprintf("%.0f", $amount1), "."),'privateKey'=>$privateKey);
									//print_r($trans_det); exit;
	
									/*$from_address = trim($from_address1);
									$to = trim($address);	
									$amount1 = $amount * $decimal_places;
									$trans_det 		= array('owner_address'=>$from_address,'to_address'=>$to,'amount'=>(float)$amount1);*/
								}
								// echo $amount;
								// echo "<br>";
								// echo $decimal_places;
								// echo "<br>";
								// print_r($trans_det);
								// echo "<br/>";
								// echo $val;
								$send_money_res = $this->local_model->make_transfer($coin_name,$trans_det,$currency_det->crypto_type);
							}

					    }

						

						$updateData  = array('status'=>"Completed",'wallet_txid'=>$send_money_res);
						$condition = array('trans_id' => $id,'type' => 'withdraw','currency_type'=>'crypto');
						$update = $this->common_model->updateTableData('transactions', $condition, $updateData);
						
						////////////////////SEND EMAIL
						$ua=$this->getBrowser();
						$yourbrowser= $ua['name'] . " " . $ua['version'] . " on " .$ua['platform'];
						$to      	= getUserEmail($fromid);
						$email_template = 34;
						$username=get_prefix().'username';
						$site_common      =   site_common();
						$currency_name = getcryptocurrency($withdraw->currency_id);
						$fb_link = $site_common['site_settings']->facebooklink;
						$tw_link = $site_common['site_settings']->twitterlink;
						$tw_link = $site_common['site_settings']->coinmarket;
						$tg_link = $site_common['site_settings']->telegramlink;
						$md_link = $site_common['site_settings']->youtubelink;
						//$md_link = $site_common['site_settings']->mediumlink;
						$ld_link = $site_common['site_settings']->linkedin_link;

						$special_vars = array(
						'###AMOUNT###' => $withdraw->transfer_amount,
						'###CURRENCY###' => $currency_name,
						'###ADDRESS###' => $withdraw->crypto_address,
						'###TX###' => $withdraw->wallet_txid
						);
						$this->email_model->sendMail($to, '', '', $email_template,$special_vars);
						/////////////////////////END SEND EMAIL
						
						// Reserve amount
						$reserve_amount = getcryptocurrencydetail($withdraw->currency_id);
						$final_reserve_amount = (float)$reserve_amount->reserve_Amount + (float)$amount;
						$new_reserve_amount = updatecryptoreserveamount($final_reserve_amount, $withdraw->currency_id);


						if($coin_name !="bitcoin")
                        {
		                $admin_balance = getadminBalance(1,$withdraw->currency_id); // get admin balance
		                $admin_bal = $admin_balance - $withdraw->transfer_amount;
		                updateadminBalance(1,$withdraw->currency_id,$admin_bal); // update balance in admin wallet
		                }
                        
						if($currency_det->crypto_type_other != '')
						{
							$crypto_other_type_arr = explode('|',$currency_det->crypto_type_other);
							foreach($crypto_other_type_arr as $val)
							{
								if($coin_type=="token" && $val=='eth')
								{
									$admin_bal = getadminBalance(1,2); // get admin balance
									$admin_up = $admin_bal + $withdraw->fee;
									updateadminBalance(1,2,$admin_up);
								}
								elseif($coin_type=="token" && $val=='bsc')
								{
									$admin_bal = getadminBalance(1,4); // get admin balance
									$admin_up = $admin_bal + $withdraw->fee;
									updateadminBalance(1,4,$admin_up);
								}
								elseif($coin_type=="token" && $val=='tron')
								{
									$admin_bal = getadminBalance(1,5); // get admin balance
									$admin_up = $admin_bal + $withdraw->fee;
									updateadminBalance(1,5,$admin_up);
								}
							}
						} else {
							if($coin_type=="token" && $currency_det->crypto_type=='eth')
							{
								$admin_bal = getadminBalance(1,2); // get admin balance
								$admin_up = $admin_bal + $withdraw->fee;
								updateadminBalance(1,2,$admin_up);
							}
							elseif($coin_type=="token" && $currency_det->crypto_type=='bsc')
							{
								$admin_bal = getadminBalance(1,4); // get admin balance
								$admin_up = $admin_bal + $withdraw->fee;
								updateadminBalance(1,4,$admin_up);
							}
							elseif($coin_type=="token" && $currency_det->crypto_type=='tron')
							{
								$admin_bal = getadminBalance(1,5); // get admin balance
								$admin_up = $admin_bal + $withdraw->fee;
								updateadminBalance(1,5,$admin_up);
							}
							else
							{
								$admin_bal = getadminBalance(1,$withdraw->currency_id); // get admin balance
								$admin_up = $admin_bal + $withdraw->fee;
								updateadminBalance(1,$withdraw->currency_id,$admin_up);

							}
						}


						// add to transaction history
						$trans_data = array(
							'userId'=>$withdraw->user_id,
							'type'=>'Withdraw',
							'currency'=>$withdraw->currency_id,
							'amount'=>$withdraw->amount,
							'profit_amount'=>$withdraw->fee,
							'comment'=>'Withdraw #'.$withdraw->trans_id,
							'datetime'=>date('Y-m-d h:i:s'),
							'currency_type'=>'crypto',
						);
						$update_trans = $this->common_model->insertTableData('transaction_history',$trans_data);


						$trans_datas = array(
				                'userid'=>$withdraw->user_id,
				                'crypto_address'=>$user_address,
				                'type'=>'userwithdraw',
				                'amount'=>(float)$withdraw->transfer_amount,
				                'currency_symbol'=>$coin_symbol,
				                'status'=>'Completed',
				                'date_created'=>date('Y-m-d H:i:s'),
				                'currency_id'=>$withdraw->currency_id,
				                'txn_id'=>$withdraw->trans_id
				                );
				        $insert = $this->common_model->insertTableData('admin_wallet_logs',$trans_datas);
						//exit;

						if($update){
						$this->session->set_flashdata('success', 'Successfully confirmed the withdraw request');
						front_redirect('wallet', 'refresh');
						}
						else
						{
							$this->session->set_flashdata('error', 'Some error occured please try again later !!');
							front_redirect('wallet', 'refresh');
						}
					}
					else
					{
						$this->session->set_flashdata('error', 'Not enough balance to proceed the withdraw request amount');
						front_redirect('wallet', 'refresh');
					}
				}
				else{

					$PayPeaks = array();
					$PayPeaks['Ref'] = $withdraw->transaction_id;
					$PayPeaks['Msisdn'] = $withdraw->msisdn;
					$PayPeaks['Name'] = $withdraw->pay_name;
					$PayPeaks['Narration'] = $withdraw->description;
					$PayPeaks['Product'] = $withdraw->product_code;
					$PayPeaks['Amount'] = $withdraw->transfer_amount;
					$PayPeaks['Currency'] = 'GHS';
					$PayPeaks['MerchantId'] = getUserDetails($user_id,'merchant_id');
					$PayPeaks['Channel'] = 'WA';

					/*echo "<pre>";
					print_r(json_encode($PayPeaks));*/
					$PayPeaks_Response = paypeaks_send_money($PayPeaks);
					/*echo "<pre>";
					print_r(json_encode($PayPeaks_Response));*/


					if(isset($PayPeaks_Response) && $PayPeaks_Response!='0'){
						$Response_Code = $PayPeaks_Response->ResponseCode;
						if($Response_Code=='00'){

						$updateData['user_status'] = 'Completed';
						$updateData['status'] = 'Pending';
						$condition = array('trans_id' => $id,'type' => 'withdraw','currency_type'=>'fiat');
						$update = $this->common_model->updateTableData('transactions', $condition, $updateData);

						$this->session->set_flashdata('success','Your withdraw request has been placed successfully.');
						front_redirect('wallet', 'refresh');

					
				}else {
					if($Response_Code=='02'){
						$Error_Message = 'Duplicate transaction';
					}
					elseif($Response_Code=='07'){
						$Error_Message = 'Error processing transaction';
					}
					elseif($Response_Code=='09'){
						$Error_Message = 'Transaction/entry failed';
					}
					elseif($Response_Code=='10'){
						$Error_Message = 'Insufficient Account Balance';
					}
					else{
						$Error_Message = 'Invalid Request';
					}
						$this->session->set_flashdata('error', 'Unable to Process your Withdraw.'.$Error_Message);
						front_redirect('withdraw/GHS', 'refresh');
					}

					}
				}
		}
		
	
	}
	else
		{
			$this->session->set_flashdata('error','Invalid withdraw confirmation');
			front_redirect('wallet', 'refresh');
		}
}

	function withdraw_coin_user_cancel($id)
	{
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			front_redirect('', 'refresh');
		}
		$id = base64_decode($id);
		$isValids = $this->common_model->getTableData('transactions', array('trans_id' => $id, 'type' =>'withdraw', 'status'=>'Pending','currency_type'=>'crypto'));
		$isValid = $isValids->num_rows();
		$withdraw = $isValids->row();
		$withdraw_entries = $this->common_model->getTableData('transactions', array('multiwithdraw_id' => $withdraw->multiwithdraw_id));
		$isValid = $withdraw_entries->num_rows();
		$withdraw_rows = $withdraw_entries->result();
		if($isValid > 0)
		{
			// echo "<pre>";
			// echo $isValid;
			// print_r($withdraw_rows);
			// exit;
			foreach($withdraw_rows as $withdraw)
			{
				if($withdraw->user_status=='Completed')
				{
					$this->session->set_flashdata('error','Your withdraw request already confirmed');
					front_redirect('wallet', 'refresh');
				}
				else if($withdraw->user_status=='Cancelled')
				{
					$this->session->set_flashdata('error','Your withdraw request already cancelled');
					front_redirect('wallet', 'refresh');
				}
				elseif($withdraw->user_id != $user_id)
				{
					$this->session->set_flashdata('error','Your are not the owner of this withdraw request');
					front_redirect('wallet', 'refresh');
				}
				else {
					$currency = $withdraw->currency_id;
					$amount = $withdraw->amount;
					$balance = getBalance($user_id,$currency,'crypto');
					$finalbalance = $balance+$amount;
					$updatebalance = updateBalance($user_id,$currency,$finalbalance,'crypto');
					$updateData['user_status'] = 'Cancelled';
					$updateData['status'] = 'Cancelled';
					$condition = array('trans_id' => $withdraw->trans_id,'type' => 'withdraw','currency_type'=>'crypto');
					$update = $this->common_model->updateTableData('transactions', $condition, $updateData);
				}
			}
			$this->session->set_flashdata('success','Successfully cancelled your withdraw request');
			front_redirect('wallet', 'refresh');
		}
		else
		{
			$this->session->set_flashdata('error','Invalid withdraw confirmation');
			front_redirect('wallet', 'refresh');
		}
	}
	function getValue()
	{
        $currency_id = $_POST['currency_id'];
        $currency_det = $this->common_model->getTableData('currency', array('id' => $currency_id))->row();    
        if(count($currency_det) > 0){
           $response = array('usd_value'=>$currency_det->online_usdprice,'status'=>'success');
        }
        else{
            $response = array('status'=>'failed');
        }
        echo json_encode($response);

    }	
	function transaction()
	{
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url());
		}
		

		if(isset($_POST))
	    {

			$this->form_validation->set_rules('ids', 'ids', 'trim|required|xss_clean|numeric');
			$this->form_validation->set_rules('amount', 'Amount', 'trim|required|xss_clean');

			$id = $this->db->escape_str($this->input->post('ids'));

			if($id!=7 && $id!=9){
			$this->form_validation->set_rules('address', 'Address', 'trim|required|xss_clean');
		}

			if ($this->form_validation->run())
			{

				$id = $this->db->escape_str($this->input->post('ids'));
				$amount = $this->db->escape_str($this->input->post('amount'));
				if($id!=7 && $id!=9){
				$address = $this->db->escape_str($this->input->post('address'));
				$Payment_Method = 'crypto';
				$Currency_Type = 'crypto';
				$Bank_id = '';
			}
			else{
				$address = '';
				$Payment_Method = 'bank';
				$Currency_Type = 'fiat';
				$Bank_id = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id,'status'=>'Verified'))->row('id');
			}
	 			$balance = getBalance($user_id,$id,'crypto');
				$currency = getcryptocurrencydetail($id);
				$w_isValids   = $this->common_model->getTableData('transactions', array('user_id' => $user_id, 'type' =>'Withdraw', 'status'=>'Pending','user_status'=>'Pending','currency_id'=>$id));
				 $count        = $w_isValids->num_rows();
	             $withdraw_rec = $w_isValids->row();
                $final = 1;
                $Validate_Address = 1;
				if($Validate_Address==1)
				{	
					if($count>0)
					{
							
						$this->session->set_flashdata('error', $this->lang->line('Sorry!!! Your previous ') . $currency->currency_symbol . $this->lang->line('withdrawal is waiting for admin approval. Please use other wallet or be patience'));
						front_redirect('withdraw', 'refresh');	
					}
					else
					{
						if($amount>$balance)
						{
							$this->session->set_flashdata('error', $this->lang->line('Amount you have entered is more than your current balance'));
							front_redirect('withdraw', 'refresh');	
						}
						if($amount < $currency->min_withdraw_limit)
						{
							$this->session->set_flashdata('error',$this->lang->line('Amount you have entered is less than minimum withdrawl limit'));
							front_redirect('withdraw', 'refresh');	
						}
						elseif($amount>$currency->max_withdraw_limit)
						{
							$this->session->set_flashdata('error', $this->lang->line('Amount you have entered is more than maximum withdrawl limit'));
							front_redirect('withdraw', 'refresh');	
						}
						elseif($final!=1)
						{
							$this->session->set_flashdata('error',$this->lang->line('Invalid address'));
							front_redirect('withdraw', 'refresh');	
						}
						else
						{
							$withdraw_fees_type = $currency->withdraw_fees_type;
					        $withdraw_fees = $currency->withdraw_fees;

					        if($withdraw_fees_type=='Percent') { $fees = (($amount*$withdraw_fees)/100); }
					        else { $fees = $withdraw_fees; }
					        $total = $amount-$fees;
							$user_status = 'Pending';
							$insertData = array(
								'user_id'=>$user_id,
								'payment_method'=>$Payment_Method,
								'currency_id'=>$id,
								'amount'=>$amount,
								'fee'=>$fees,
								'crypto_address'=>$address,
								'transfer_amount'=>$total,
								'datetime'=>gmdate(time()),
								'type'=>'Withdraw',
								'status'=>'Pending',
								'currency_type'=>$Currency_Type,
								'user_status'=>$user_status
								);
							$finalbalance = $balance - $amount;
							$updatebalance = updateBalance($user_id,$id,$finalbalance,'crypto');
							$insertData_clean = $this->security->xss_clean($insertData);
							$insert = $this->common_model->insertTableData('transactions', $insertData_clean);
							if($insert) 
							{
								$prefix = get_prefix();
								$user = getUserDetails($user_id);
								$usernames = $prefix.'username';
								$username = $user->$usernames;
								$email = getUserEmail($user_id);
								$currency_name = getcryptocurrency($id);
								$link_ids = base64_encode($insert);
								$sitename = getSiteSettings('site_name');
								$site_common      =   site_common();		                    

								if($id!=7 && $id!=9){
								$email_template = 'Withdraw_User_Complete';
									$special_vars = array(
									'###SITENAME###' => $sitename,
									'###USERNAME###' => $username,
									'###AMOUNT###'   => (float)$amount,
									'###CURRENCY###' => $currency_name,
									'###FEES###' => $fees,
									'###CRYPTOADDRESS###' => $address,
									'###CONFIRM_LINK###' => base_url().'withdraw_coin_user_confirm/'.$link_ids,
									'###CANCEL_LINK###' => base_url().'withdraw_coin_user_cancel/'.$link_ids
									);
								}
								else{
	                               $email_template = 'Withdraw_User_Complete_fiat';
									$special_vars = array(
									'###SITENAME###' => $sitename,
									'###USERNAME###' => $username,
									'###AMOUNT###'   => (float)$amount,
									'###CURRENCY###' => $currency_name,
									'###FEES###' => $fees,
									'###CONFIRM_LINK###' => base_url().'withdraw_coin_user_confirm/'.$link_ids,
									'###CANCEL_LINK###' => base_url().'withdraw_coin_user_cancel/'.$link_ids
									);
								}
							    $this->email_model->sendMail($email, '', '', $email_template, $special_vars);								
								$this->session->set_flashdata('success',$this->lang->line('Your withdraw request placed successfully. Please make confirm from the mail you received in your registered mail!'));
								front_redirect('account', 'refresh');
							} 
							else 
							{
								$this->session->set_flashdata('error',$this->lang->line('Unable to submit your withdraw request. Please try again'));
								front_redirect('account', 'refresh');
							}
						}
					}
				}
				else
				{

					$this->session->set_flashdata('error', 'Please check the address');
					front_redirect('account', 'refresh');
				}	
			}
			else
			{
				$this->session->set_flashdata('error', validation_errors());
				front_redirect('account', 'refresh');
			}
	    }

	    else{
	    	front_redirect('account', 'refresh');
	    }
	}
	function wallet()
	{		 
    	$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}

		if($_POST){
			$currency_id = $this->input->post('coin_id');
			$currency_value = $this->input->post('coin_value');
			$transfer_from = $this->input->post('transfer_from');

			if($transfer_from == "trading account"){
				$trading_balance = getTradingBalance($user_id,$currency_id);
			
				if($trading_balance >= $currency_value){
					unset($_POST['transfer_from']);
					$trading_main_balance = abs($trading_balance - $currency_value);
					$update_trade_balance = updateTradingBalance($user_id,$currency_id,$trading_main_balance);
					$balance = getBalance($user_id,$currency_id);
					$update_balance = $balance + $currency_value;
					$update_balance = updateBalance($user_id,$currency_id,$update_balance);
					$this->session->set_flashdata('success','Amount transferred successfully');
					front_redirect('wallet', 'refresh');
				}else{
					$this->session->set_flashdata('failure','Amount should be less than or equal to balance');
					front_redirect('wallet', 'refresh');
				}
			}else{
				$balance = getBalance($user_id,$currency_id);
			
				if($balance >= $currency_value){
					unset($_POST['transfer_from']);
					$main_balance = abs($balance - $currency_value);
					$update_balance = updateBalance($user_id,$currency_id,$main_balance);
					$trading_balance = getTradingBalance($user_id,$currency_id);
					$update_trade_balance = $trading_balance + $currency_value;
					$update_trading_balance = updateTradingBalance($user_id,$currency_id,$update_trade_balance);
					$this->session->set_flashdata('success','Amount transferred successfully');
					front_redirect('wallet', 'refresh');
				}else{
					$this->session->set_flashdata('failure','Amount should be less than ');
					front_redirect('wallet', 'refresh');
				}
			}
			
		}
		$data['site_common'] = site_common();
		$data['wallet'] = unserialize($this->common_model->getTableData('wallet',array('user_id'=>$user_id),'crypto_amount')->row('crypto_amount'));
		$data['wallet_main'] = unserialize($this->common_model->getTableData('wallet',array('user_id'=>$user_id),'crypto_amount')->row('crypto_amount'));
		$data['wallet_trading'] = unserialize($this->common_model->getTableData('wallet',array('user_id'=>$user_id),'trading_amount')->row('trading_amount'));

		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$data['dig_currency'] = $this->common_model->getTableData('currency', array('status' => 1), '', '', '', '', '', '', array('sort_order', 'ASC'))->result();
    	$data['site_details'] = $this->common_model->getTableData('site_settings', array('id' => '1'))->row();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'wallet'))->row();
		$data['user_id'] = $user_id;
		$default_currency=$this->common_model->getTableData('users',array('id'=>$user_id),'default_currency')->row();
    
		$default_currency = json_decode(json_encode($default_currency->default_currency));
		$data['default_currency'] = $default_currency;

		$LEX_details = $this->common_model->getTableData('currency',array("currency_symbol"=>"LEX"))->row();
		$cur_ids = $LEX_details->id;
		$data['lex_balance'] = getBalance($user_id,$cur_ids,'crypto');
		$data['lex_trading_balance'] = getTradingBalance($user_id,$cur_ids);
		if($default_currency == "USD"){
			$lex_usd_value = $LEX_details->online_usdprice;
			$lex_def_cur_balance = $data['lex_balance'] * $lex_usd_value;
			$lex_trading_def_cur_balance = $data['lex_trading_balance'] * $lex_usd_value;
		}else{
			$lex_eur_value = $LEX_details->online_eurprice;
			$lex_def_cur_balance = $data['lex_balance'] * $lex_eur_value;
			$lex_trading_def_cur_balance = $data['lex_trading_balance'] * $lex_eur_value;
		}
		$data['lex_def_cur_balance'] = $lex_def_cur_balance ;
		$data['lex_trading_def_cur_balance'] = $lex_trading_def_cur_balance ;
		
    // echo "<pre>";print_r($data['dig_currency']);die;
		// $this->load->view('front/user/text', $data);
		$this->load->view('front/user/wallet', $data);
	}

	function ajax_wallet(){

		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		// if($user_id=="")
		// {	
		// 	$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
		// 	redirect(base_url().'home');
		// }
		$search =$this->db->escape_str($_POST['search_asset_input']);
		$data['isChecked'] =$this->db->escape_str($_POST['isChecked']);
		//$like = "AND currency_symbol LIKE '%".$search."%'";
		// $where_not = array('id', array('7'));
		$data['dig_currency'] = $this->common_model->getTableData('currency', array('status' => 1), '', array('currency_symbol'=>$search), '', '', '', '', array('sort_order', 'ASC'),'',$where_not)->result();
		$default_currency=$this->common_model->getTableData('users',array('id'=>$user_id),'default_currency')->row();
		$data['default_currency'] = json_decode(json_encode($default_currency->default_currency));
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
	
       	$this->load->view('front/user/search', $data);
     
	}

	function transfer_click(){

		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		// if($user_id=="")
		// {	
		// 	$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
		// 	redirect(base_url().'home');
		// }
		$search =$this->db->escape_str($_POST['search_asset_input']);
		$data['isChecked'] =$this->db->escape_str($_POST['isChecked']);
		//$like = "AND currency_symbol LIKE '%".$search."%'";
		// $where_not = array('id', array('7'));
		$data['dig_currency'] = $this->common_model->getTableData('currency', array('status' => 1), '', array('currency_symbol'=>$search), '', '', '', '', array('sort_order', 'ASC'),'',$where_not)->result();
		$default_currency=$this->common_model->getTableData('users',array('id'=>$user_id),'default_currency')->row();
		$data['default_currency'] = json_decode(json_encode($default_currency->default_currency));
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
	
		$post_data_assets = array ('status' => true,'error' => 'Incorrect symbol',);
		echo json_encode($post_data_assets,true);
     
	}

	   function history()
    {
        $user_id=$this->session->userdata('user_id');
        if($user_id=="")
        {   
            $this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
            redirect(base_url().'home');
        }
        $data['site_common'] = site_common();
        $data['user_id'] = $user_id;        

        $data['login_history'] = $this->common_model->getTableData('user_activity',array('user_id'=>$user_id),'','','','','','',array('act_id','DESC'))->result();
                

        $data['deposit_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id,'type'=>'Deposit'),'','','','','','',array('trans_id','DESC'))->result();

        $data['withdraw_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id,'type'=>'Withdraw'),'','','','','','',array('trans_id','DESC'))->result();
        $data['transfer_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id,'type'=>'Withdraw','currency_type'=>'fiat'),'','','','','','',array('trans_id','DESC'))->result();

        $data['buycrypto_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id,'type'=>'buy_crypto'),'','','','','','',array('trans_id','DESC'))->result();

        $data['trade_history'] = $this->common_model->getTableData('coin_order',array('userId'=>$user_id),'','','','','','',array('trade_id','DESC'))->result();

        $data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
        $data['action'] = front_url() . 'history';
        $data['js_link'] = '';
        $meta = $this->common_model->getTableData('meta_content', array('link' => 'coin_request'))->row();
        $data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'history'))->row();
        $this->load->view('front/user/history', $data); 
    }
    
	 function update_adminaddress($coin_symbol)
    {

        $Fetch_coin_list = $this->common_model->getTableData('currency',array('currency_symbol'=>$coin_symbol,'status'=>'1'))->result();

        $whers_con = "id='1'";

        // $get_admin  =   $this->common_model->getrow("bluerico_admin", $whers_con);
        // print_r($get_admin); exit();

        $admin_id = "1";

        $enc_email = getAdminDetails($admin_id, 'email_id');

		$email = decryptIt($enc_email);


        $get_admin = $this->common_model->getrow("elxisenergy_admin_wallet", $whers_con);

        if(!empty($get_admin)) 
        {
            $get_admin_det = json_decode($get_admin->addresses, true);

			foreach($Fetch_coin_list as $coin_address)
			{			
				//$currency_exit =  array_key_exists($coin_address->currency_symbol, $get_admin_det)?true:false;
				
				if(array_key_exists($coin_address->currency_symbol, $get_admin_det))
				{
					//$currency_address_checker = (!empty($get_admin_det[$coin_address->currency_symbol]))?true:false;

		    		if(empty($get_admin_det[$coin_address->currency_symbol]))
		    		{
						$parameter = '';

						switch ($coin_address->coin_type) {
							case 'coin':
								
								switch ($coin_address->currency_symbol) {
									case 'ETH':
										$parameter='create_eth_account';
								
										$Get_First_address = $this->local_model->access_wallet($coin_address->id,'create_eth_account', $email);
										
											$get_admin_det[$coin_address->currency_symbol] = $Get_First_address;

											$update['addresses'] = json_encode($get_admin_det);

				        					$this->common_model->updateTableData("admin_wallet",array('user_id' => $admin_id),$update);
										
										

										break;
									
									default:
										$parameter='getnewaddress';

										$Get_First_address = $this->local_model->access_wallet($coin_address->id,'getnewaddress', $email);
							

											$get_admin_det[$coin_address->currency_symbol] = $Get_First_address;

											$update['addresses'] = json_encode($get_admin_det);

				        					$this->common_model->updateTableData("admin_wallet",array('user_id'=>$admin_id),$update);
										
									
										break;
								}

								break;
							case 'token':

								$get_admin_det[$coin_address->currency_symbol] = $get_admin_det['ETH'];

								$update['addresses'] = json_encode($get_admin_det);

								$this->common_model->updateTableData("admin_wallet",array('user_id'=>$admin_id),$update);

								break;
							default:
								break;
						}	               
					}
				}
			}
		}
    }


    function add_coin()
	{
		if($this->block() == 1)
{ 
front_redirect('block_ip');
}
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			front_redirect('login', 'refresh');
		}
		if($this->input->post())
		{
			$image = $_FILES['coin_logo']['name'];
			if($image!="") {
			$uploadimage=cdn_file_upload($_FILES["coin_logo"],'uploads/coin_request');
			if($uploadimage)
			{
				$image=$uploadimage['secure_url'];
			}
			else
			{
				$this->session->set_flashdata('error','Problem with your coin image');
				front_redirect('add_coin', 'refresh');
			}
			} 
			else 
			{ 
				$image=""; 
			}
			$insertData['user_id'] = $user_id;
			$insertData['coin_type'] = $this->input->post('coin_type');
			$insertData['coin_name'] = $this->input->post('coin_name');
			$insertData['coin_symbol'] = $this->input->post('coin_symbol');
			$insertData['coin_logo'] = $image;
			$insertData['max_supply'] = $this->input->post('max_supply');
			$insertData['coin_price'] = $this->input->post('coin_price');
			$insertData['priority'] = $this->input->post('priority');
			if($this->input->post('crypto_type') !='')
			{
			$insertData['crypto_type'] = $this->input->post('crypto_type');
		    }
		    if($this->input->post('token_type') !='')
		    {
            $insertData['token_type'] = $this->input->post('token_type');
            }
            $insertData['marketcap_link'] = $this->input->post('marketcap_link');
            $insertData['coin_link'] = $this->input->post('coin_link');
            $insertData['twitter_link'] = $this->input->post('twitter_link');
            $insertData['username'] = $this->input->post('username');
            $insertData['email'] = $this->input->post('email');
			$insertData['status'] = '0';
			$insertData['added_by'] = 'user';
			$insertData['added_date'] = date('Y-m-d H:i:s');
            /*$insertData['type'] = 'digital';
            $insertData['verify_request'] = 0;*/
            $username = $this->input->post('username');
			$user_mail = $this->input->post('email');
			$coin_name = $this->input->post('coin_name');
			$insert = $this->common_model->insertTableData('add_coin', $insertData);
			$email_template = 'Coin_request';
			$special_vars = array(
			'###USERNAME###' => $username,
			'###COIN###' => $coin_name
			);
			//-----------------
			$this->email_model->sendMail($user_mail, '', '', $email_template, $special_vars);
			if ($insert) {

				$this->session->set_flashdata('success', 'Your add coin request successfully sent to our team');
				front_redirect('add_coin', 'refresh');
			} else {
				$this->session->set_flashdata('error', 'Error occur!! Please try again');
				front_redirect('add_coin', 'refresh');
			}
		}
		$data['site_common'] = site_common();
		$meta = $this->common_model->getTableData('meta_content', array('link' => 'coin_request'))->row();
		$data['action'] = front_url() . 'add_coin';
		$data['heading'] = $meta->heading;
		$data['title'] = $meta->title;
		$data['meta_keywords'] = $meta->meta_keywords;
		$data['meta_description'] = $meta->meta_description;
		$this->load->view('front/user/add_coin', $data);
	}

	public function account(){

        if($this->block() == 1)
                { 
                front_redirect('block_ip');
                }
        $user_id=$this->session->userdata('user_id');


        if($user_id=="")
        {   
            $this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
            redirect(base_url().'home');
        }

    	$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

        $data['bank_details'] = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row();

        $data['site_common'] = site_common();
        $data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'profile-edit'))->row();
        $data['countries'] = $this->common_model->getTableData('countries')->result();
      

		$data['users_history']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 5 ")->result();
		$data['users_history_last']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1,2")->row();     



        $this->load->view('front/user/account', $data); 
    }
    function activity_delete($id,$devicename){

      $data=array(
       'is_invalid' =>1,

        );

        $browser = getBrowser();

        $devicename=$this->uri->segment(3);
            
        $this->common_model->updateTableData('user_activity',array('act_id'=>$id),$data);

        if($devicename==$browser){

        $this->session->unset_userdata('user_id');
        $this->session->unset_userdata('login');

        $this->session->set_flashdata('success', 'Please login first');

        front_redirect('home','refresh');

    } else {

       $this->session->set_flashdata('success', 'Deleted Successfully');

        front_redirect('account','refresh');

    }
    

    }
	function update_bank_details()
	{		 
		$this->load->library('session','form_validation');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('success', 'Please Login');
			redirect(base_url().'home');
		}
		if($_POST)
		{
			$this->form_validation->set_rules('account_number', 'Bank Account number', 'required|xss_clean');
			$this->form_validation->set_rules('account_name', 'Bank Account Name', 'required|xss_clean');
			$this->form_validation->set_rules('ifsc', 'IFSC Code', 'required|xss_clean');
			$this->form_validation->set_rules('bank_branch', 'Bank Branch Location', 'required|xss_clean');
			$this->form_validation->set_rules('bank_name', 'Bank Name', 'required|xss_clean');
			$this->form_validation->set_rules('account_type', 'Account Type', 'required|xss_clean');
			if($this->form_validation->run())
			{
				$insertData['user_id'] = $user_id;
				//$insertData['currency'] = $this->db->escape_str($this->input->post('currency'));
				$insertData['bank_account_name'] = $this->db->escape_str($this->input->post('account_name'));
				$insertData['bank_account_number'] = $this->db->escape_str($this->input->post('account_number'));
				$insertData['bank_swift'] = $this->db->escape_str($this->input->post('ifsc'));
				$insertData['bank_name'] = $this->db->escape_str($this->input->post('bank_name'));
				$insertData['bank_address'] = $this->db->escape_str($this->input->post('bank_branch'));
				$insertData['account_type'] = $this->db->escape_str($this->input->post('account_type'));
				//$insertData['bank_city'] = $this->db->escape_str($this->input->post('bank_city'));
				//$insertData['bank_country'] = $this->db->escape_str($this->input->post('bank_country'));
				//$insertData['bank_postalcode'] = $this->db->escape_str($this->input->post('bank_postalcode'));
				$insertData['added_date'] = date("Y-m-d H:i:s");				
				$insertData['status'] = 'Pending';
				$insertData['user_status'] = '1';
				if ($_FILES['bank_statement']['name']!="") 
				{
					$imagepro = $_FILES['bank_statement']['name'];
					if($imagepro!="" && getExtension($_FILES['bank_statement']['type']))
					{
						$uploadimage1=cdn_file_upload($_FILES["bank_statement"],'uploads/user/'.$user_id,$this->input->post('bank_statement'));
						if($uploadimage1)
						{
							$imagepro=$uploadimage1['secure_url'];
						}
						else
						{
							$this->session->set_flashdata('error', 'Problem with profile picture');
							front_redirect('profile', 'refresh');
						} 
					}				
					$insertData['bank_statement']=$imagepro;
				}
				$insertData_clean = $this->security->xss_clean($insertData);

				$insert=$this->common_model->insertTableData('user_bank_details', $insertData_clean);
				if ($insert) {
					//$profileupdate = $this->common_model->updateTableData('users',array('id' => $user_id), array('profile_status'=>1));
					$this->session->set_flashdata('success', 'Bank details Updated Successfully');

					front_redirect('profile', 'refresh');
				} else {
					$this->session->set_flashdata('error', 'Something ther is a Problem .Please try again later');
					front_redirect('profile', 'refresh');
				}
			}
			else
			{
				$this->session->set_flashdata('error','Some datas are missing');
				front_redirect('profile', 'refresh');
			}
		}		
		front_redirect('profile', 'refresh');
	}

	

	function deposit($cur='BTC')
	{
		if($this->block() == 1) { 
		front_redirect('block_ip');
		}
		
		$user_id=$this->session->userdata('user_id');
		if($user_id=="") {	
			front_redirect('', 'refresh');
		}

		$data['user'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

		$data['fiat_currency'] = $this->common_model->getTableData('currency',array('status'=>1,'type'=>'fiat'))->row();

		$data['admin_bankdetails'] = $this->common_model->getTableData('admin_bank_details', array('currency'=>$data['fiat_currency']->id))->row();

		$data['user_bank'] = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id,'status'=>'1'))->row();
		

		$data['dig_currency'] = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>1),'','','','','','',array('id','ASC'))->result();
		$data['sel_currency'] = $this->common_model->getTableData('currency',array('currency_symbol'=>$cur),'','','','','','',array('id','ASC'))->row();
		if($data['sel_currency']->deposit_status == 0)
		{
			$this->session->set_flashdata('error', 'Deposit for this currency is currently not allowed');
			front_redirect('wallet', 'refresh');	
		}
		
		$cur_id = $data['sel_currency']->id;

		if($data['sel_currency']->currency_symbol=='XRP')
		{
			$data['destination_tag'] = secret($user_id);
		}

		$data['all_currency'] = $this->common_model->getTableData('currency',array('status'=>1),'','','','','','',array('id','ASC'))->result();

		$data['wallet'] = unserialize($this->common_model->getTableData('wallet',array('user_id'=>$user_id),'crypto_amount')->row('crypto_amount'));

		$data['balance_in_usd'] = to_decimal(Overall_USD_Balance($user_id),2);

		 $data['deposit_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id,'type'=>'Deposit'),'','','','','','',array('trans_id','DESC'))->result();
		 
		 if(isset($_POST['payment_types']) && $_POST['payment_types'] != 'stripe')
		{
			$ref_no 	   = $this->db->escape_str($this->input->post('ref_no'));
			$data['slct_fiat_currency'] = $this->common_model->getTableData('currency',array('status'=>1, 'id'=>$this->input->post('currency')))->row();
			$slct_fiat_currency = $data['slct_fiat_currency'];
			
			$value = $this->db->escape_str($this->input->post('amount'));
				
			if($value < $slct_fiat_currency->min_deposit_limit)
			{
				$this->session->set_flashdata('error', $this->lang->line('Amount you have entered is less than the minimum deposit limit'));
				front_redirect('deposit', 'refresh');	
			}
			elseif($value>$slct_fiat_currency->max_deposit_limit)
			{
				$this->session->set_flashdata('error', $this->lang->line('Amount you have entered is more than the maximum deposit limit'));
				front_redirect('deposit', 'refresh');	
			}
			$deposit_max_fees = $data['slct_fiat_currency']->deposit_max_fees;
			$deposit_fees_type = $data['slct_fiat_currency']->deposit_fees_type;
			$deposit_fees = $data['slct_fiat_currency']->deposit_fees;
			if($deposit_fees_type=='Percent') { $fees = (($value*$deposit_fees)/100); }
			else { $fees = $deposit_fees; }
			if($fees>$deposit_max_fees) { $final_fees = $deposit_max_fees; }
			else { $final_fees = $fees; }
			//$final_fees = apply_referral_fees_deduction($user_id,$final_fees);
			$total = $value-$final_fees;
			
			// Added to reserve amount
			$reserve_amount = getcryptocurrencydetail($this->input->post('currency'));
			$final_reserve_amount = (float)$this->input->post('amount') + (float)$reserve_amount->reserve_Amount;
			$new_reserve_amount = updatefiatreserveamount($final_reserve_amount, $this->input->post('currency'));

			$ref_no 	   = $this->db->escape_str($this->input->post('ref_no'));
			$bank_no 	   = $this->db->escape_str($this->input->post('bank'));
			$payment_types = $this->db->escape_str($this->input->post('payment_types'));
			$description = $this->db->escape_str($this->input->post('description'));

			if($ref_no == '' && $description == '')
			{
				$ref_no 	 = '-';
				$description = '-';
			}
				
			$insertData = array(
				'user_id'=>$user_id,
				'payment_method'=>$payment_types,
				'currency_id'=>$this->db->escape_str($this->input->post('currency')),
				'amount'=>$this->db->escape_str($this->input->post('amount')),
				'transaction_id'=>$ref_no,
				'description'=>$description,
				'bank_id'=>$bank_no,
				'fee'=>$final_fees,
				'transfer_amount'=>$total,
				'datetime'=>gmdate(time()),
				'type'=>'Deposit',
				'status'=>'Pending',
				'currency_type'=>'fiat',
			);

			$insert = $this->common_model->insertTableData('transactions', $insertData);
			
			
			if ($insert) {
				$this->session->set_flashdata('success', 'Your deposit request placed successfully');

				if($payment_types == 'paypal')
				{
					// Change not required 
					front_redirect('deposit', 'refresh');
					// front_redirect('pay/'.$insert, 'refresh');
				}
				//front_redirect('deposit', 'refresh');
			} else {
				$this->session->set_flashdata('error', 'Unable to submit your deposit request. Please try again');
				front_redirect('deposit', 'refresh');
			}

		}else {
			$site_settings = site_common();

			$stripe_private_key = $site_settings['site_settings']->stripeapi_private_key;
			
			try {
				$send_currency = $slct_fiat_currency->currency_name;
				$receive_currency = $slct_fiat_currency->currency_name;
				$fiat_amount = $_POST['amount'];
				$description = 'deposit';

				// This is your test secret API key.
				// \Stripe\Stripe::setApiKey('sk_test_51Hbjz8Jnc0WvIodpA5vhmF13gZxIpmNixbrXMBRrzaIFLf0SvZ0MAJGtKKQCXI21oGvbdv2IC7j7puyadVm3KLrq00mGP0G0Hq');

				// header('Content-Type: application/json');

				// try {
				// 	// retrieve JSON from POST body
				// 	$jsonStr = file_get_contents('php://input');
				// 	$jsonObj = json_decode($jsonStr);

				// 	// Create a PaymentIntent with amount and currency
				// 	$paymentIntent = \Stripe\PaymentIntent::create([
				// 		'amount' => $this->calculateOrderAmount(array()),
				// 		'currency' => 'eur',
				// 		'automatic_payment_methods' => [
				// 			'enabled' => true,
				// 		],
				// 	]);

				// 	$output = [
				// 		'clientSecret' => $paymentIntent->client_secret,
				// 	];

				// 	echo json_encode($output);
				// } catch (Error $e) {
				// 	http_response_code(500);
				// 	echo json_encode(['error' => $e->getMessage()]);
				// }
				
				// $stripe = new \Stripe\StripeClient(
				// 	'sk_test_tR3PYbcVNZZ796tH88S4VQ2u'
				//   );
				//   $stripe->checkout->sessions->create([
				// 	'payment_method_types' => ['card'],
				// 	'line_items' => [
				// 	  [
				// 		'price_data' => [
				// 			# To accept `sepa_debit`, all line items must have currency: eur
				// 			'currency' => 'EUR',
				// 			'unit_amount' => 2000,
				// 		],
				// 		'quantity' => 1,
				// 	  ],
				// 	],
				// 	'mode' => 'payment',
				// 	'success_url' => 'https://example.com/success',
				// 	'cancel_url' => 'https://example.com/cancel'
				//   ]);
				


				// Stripe::setApiKey("sk_test_51Hbjz8Jnc0WvIodpA5vhmF13gZxIpmNixbrXMBRrzaIFLf0SvZ0MAJGtKKQCXI21oGvbdv2IC7j7puyadVm3KLrq00mGP0G0Hq"); //Replace with your Secret Key
				// // Stripe::setApiKey($stripe_private_key); //Replace with your Secret Key
				// $charge = Stripe_Charge::create(array(
				// 	"amount" => $fiat_amount * 100,
				// 	"currency" => $send_currency,
				// 	"card" => $_POST['stripeToken'],
				// 	"description" => $description,
				// ));

				// $response = $charge->__toArray(TRUE);

				// echo "response=>".$response;exit;
				// $txn_status = $charge['status'];    	
				
				// if($txn_status=="succeeded")
				// {

				// 	$currency_id 	= $charge['item_number']; 
				// 	$userId 		= $_POST['userId'];
				// 	$txn_id 		= $charge["id"];
				// 	$payment_amt 	= $charge["amount"];
				// 	$transaction_id 	= $charge["balance_transaction"];
				// 	$currency_code 	= $charge["mc_currency"];
				// 	// $currency_name 	= $charge["currency"];
				// 	$status 		= $txn_status;
				// 	$payment_date 	= $charge["payment_date"];
				// 	$payer_email 	= $charge["payer_email"];
				// 	$payment_mode 	= $charge['calculated_statement_descriptor'];
				// 	$txn_name 		= $charge['item_name'];
				// 	$payment_type   = $charge['description'];

				// 	$currency_name   = strtoupper($charge["currency"]);

				// // echo "jhjfhdj".$_POST['receive_currency'];die;

				// 	$ddddd = getcoindetail($receive_currency);

				// 	$currencyId = $ddddd->id;

				// 	$update_amnt = $crypto_amnt;

				// 	$userbalance = getBalance($userid,$currencyId);

				// 	$finalbalance = $update_amnt+$userbalance;


				// 	$updatebalance = updateBalance($userid,$currencyId,$finalbalance,'crypto'); // Update balance		        

				// 	$curny = $this->common_model->getTableData('fiat_currency',array('currency_symbol'=>$currency_name))->row();


				// 	$userbalance = getBalance($userId,$usd_id);

					
				// 	$dataInsert = array(
				// 	'user_id' => $userId,
				// 	'currency_id' => $currencyId,
				// 	'currency_name' => $ddddd->currency_symbol,
				// 	'amount' => $fiat_amount,
				// 	'type' => 'instant_buy',
				// 	'transfer_amount' => $crypto_amnt,
				// 	'instant_tot_amount' => $crypto_amnt,
				// 	'transfer_currency'=>$receive_currency,
				// 	'transaction_id'=>$transaction_id,
				// 	'status' => "completed",
				// 	'datetime' => date("Y-m-d h:i:s")
				// 	);
					
				// 	$ins_id = $this->common_model->insertTableData('transactions', $dataInsert);

				// 	if ($ins_id) {
				// 	// Mail Function
				// 	$prefix = get_prefix();
				// 	$user = getUserDetails($userId);
				// 	$usernames = $prefix.'username';
				// 	$username = $user->$usernames;
				// 	$email = getUserEmail($userId);
				// 	//$currency_name = $curny->currency_symbol;
				// 	$link_ids = base64_encode($ins_id);
				// 	$sitename = getSiteSettings('site_name');
				// 	$site_common      =   site_common();
				// 	$email_template = 'InstantBuy_Complete';		
				// 		$special_vars = array(
				// 		'###SITENAME###' => $sitename,			
				// 		'###USERNAME###' => $username,
				// 		'###AMOUNT###'   => $crypto_amnt,
				// 		'###CURRENCY###' => $receive_currency
					
				// 		);
				// 	$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
				// 	/* echo $this->email->print_debugger();
				// 	exit;*/
				// 	$this->session->set_flashdata('success','Your Crypto Amount successfully sent to your crypto wallet');
				// 	front_redirect('instant_buy', 'refresh');
				// } else {
				// 	$this->session->set_flashdata('error', 'Unable to submit your withdraw request. Please try again');
				// 	front_redirect('instant_buy', 'refresh');
				// }

					
					
				// }
			}
			catch(Stripe_CardError $e) {
				/*echo "err1";
				print_r($e);die;*/
				print_r($e);die;
			}
			catch (Stripe_InvalidRequestError $e) {
				/*echo "err2";
				echo "<pre>";
				print_r($e);die;*/
			} catch (Stripe_AuthenticationError $e) {
				/*echo "err3";
				print_r($e);die;*/
			} catch (Stripe_ApiConnectionError $e) {
				/*echo "err4";
				print_r($e);die;*/
			} catch (Stripe_Error $e) {
				/*echo "err5";
				print_r($e);die;*/
			} catch (Exception $e) {
				/*echo "err6";
				print_r($e);die;*/
				print_r($e);die;
			}
		}
		$userId = $this->session->userdata('user_id');
		$getuser_details = $this->common_model->getTableData('users',array('id'=>$userId))->row();
		$sitesettings = $this->common_model->getTableData('site_settings',array('id'=>1))->row(); 
		
		if($cur=='')
		{
			
			$Fetch_coin_list = $this->common_model->getTableData('currency',array('type'=>'digital','status'=>'1'),'id')->row();
			
				$coin_address = getAddress($user_id,$Fetch_coin_list->id);
		}
		else
		{
			if($cur_id != '3' && $cur_id != '4')
			{
				$coin_address = getAddress($user_id,$cur_id);
			} else {
				$coin_address_trc = getAddress($user_id,5);
				$coin_address_erc = getAddress($user_id,2);
				$coin_address = getAddress($user_id,5);
				$data['coin_address_trc'] = $coin_address_trc;
				$data['coin_address_erc'] = $coin_address_erc;
			}
		}
		$data['First_coin_image'] =	"https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=$coin_address&choe=UTF-8&chld=L";
		$data['crypto_address'] = $coin_address;
		$data['site_common'] = site_common();
		$data['action'] = front_url() . 'deposit';
		$data['js_link'] = 'deposit';
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'deposit'))->row();
		$data['balance'] = getBalance($user_id,$cur_id,'crypto');
		$data['trade_balance'] = getTradingBalance($user_id,$cur_id,'crypto');
		/*$meta = $this->common_model->getTableData('meta_content', array('link' => 'deposit'))->row();
		$data['heading'] = $meta->heading;
		$data['title'] = $meta->title;
		$data['meta_keywords'] = $meta->meta_keywords;
		$data['meta_description'] = $meta->meta_description;*/
		$this->load->view('front/user/deposit', $data); 
	}

	function stripe_create(){

		// This is your test secret API key.
		\Stripe\Stripe::setApiKey('sk_test_51Hbjz8Jnc0WvIodpA5vhmF13gZxIpmNixbrXMBRrzaIFLf0SvZ0MAJGtKKQCXI21oGvbdv2IC7j7puyadVm3KLrq00mGP0G0Hq');

		// $stripe = new \Stripe\StripeClient('sk_test_51Hbjz8Jnc0WvIodpA5vhmF13gZxIpmNixbrXMBRrzaIFLf0SvZ0MAJGtKKQCXI21oGvbdv2IC7j7puyadVm3KLrq00mGP0G0Hq');
		header('Content-Type: application/json');

		try {
			// retrieve JSON from POST body
			$jsonStr = file_get_contents('php://input');
			$jsonObj = json_decode($jsonStr);
		
			// Create a PaymentIntent with amount and currency
			$paymentIntent = \Stripe\PaymentIntent::create([
				'amount' => '2',
				'currency' => 'eur',
				'automatic_payment_methods' => [
					'enabled' => true,
				],
			]);
		
			$output = [
				'clientSecret' => $paymentIntent->client_secret,
			];
		
			echo json_encode($output);
		} catch (Error $e) {
			http_response_code(500);
			echo json_encode(['error' => $e->getMessage()]);
		}
	}

	function calculateOrderAmount(array $items): int {
		// Replace this constant with a calculation of the order's amount
		// Calculate the order total on the server to prevent
		// people from directly manipulating the amount on the client
		return 1400;
	}

	function change_network_type()
	{
		$currency = $this->input->post('currency');
		$network = $this->input->post('network_type');
		$currency = $this->common_model->getTableData('currency',array('currency_name'=>$currency),'','','','','','',array('id','ASC'))->row();
		if($network == 'eth')
		{
			$data['fees_type'] =  $currency->withdraw_fees_type;
			$data['fees'] =  $currency->withdraw_fees;
			$data['min_withdraw_limit'] = $currency->min_withdraw_limit;
			$data['max_withdraw_limit'] = $currency->max_withdraw_limit;
		} 
		else if($network == 'tron')
		{
			$data['fees_type'] =  $currency->withdraw_trx_fees_type;
			$data['fees'] =  $currency->withdraw_trx_fees;
			$data['min_withdraw_limit'] = $currency->min_trx_withdraw_limit;
			$data['max_withdraw_limit'] = $currency->max_trx_withdraw_limit;
		} 
		else if($network == 'bsc')
		{
			$data['fees_type'] =  $currency->withdraw_bnb_fees_type;
			$data['fees'] =  $currency->withdraw_bnb_fees;
			$data['min_withdraw_limit'] = $currency->min_bnb_withdraw_limit;
			$data['max_withdraw_limit'] = $currency->max_bnb_withdraw_limit;
		}
		echo json_encode($data);
	}

	function withdraw($cur='BTC')
	{	 
    error_reporting(0);
    $this->load->library(array('form_validation','session'));
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}

		/*$kyc = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		if(($kyc->photo_2_status != 3 && $kyc->photo_2_status != 2) || ($kyc->photo_3_status != 3 && $kyc->photo_3_status != 2))
		{
			$this->session->set_flashdata('error', "Please verify your kyc");
			redirect(base_url().'settings?page=kyc');
		}
		else if(($kyc->photo_2_status != 3 && $kyc->photo_2_status == 2) || ($kyc->photo_3_status != 3 && $kyc->photo_3_status == 2))
		{
			$this->session->set_flashdata('error', "Your kyc rejected by our team, please update kyc");
			redirect(base_url().'settings?page=kyc');
		}
		else if(($kyc->photo_2_status != 3 && $kyc->photo_2_status == 1) || ($kyc->photo_3_status != 3 && $kyc->photo_3_status == 1))
		{
			$this->session->set_flashdata('error', "Your kyc not verified");
			redirect(base_url().'settings?page=kyc');
		}
		else
		{

		}*/

		$data['user'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		/*if($data['user']->randcode!='enable')
		{
			$this->session->set_flashdata('error', 'Please Enable 2 Step Verification.');
			front_redirect('settings', 'refresh');
		}*/
    $bankwire = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row();
    if(!empty($bankwire)) {
      $data['bankwire'] = $bankwire;
    }
		$data['site_common'] = site_common();	
		$data['currency'] = $this->common_model->getTableData('currency',array('status'=>1),'','','','','','',array('id','ASC'))->result();	
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		if(isset($cur) && !empty($cur)){
			$data['sel_currency'] = $this->common_model->getTableData('currency',array('currency_symbol'=>$cur),'','','','','','',array('id','ASC'))->row();

      if($data['sel_currency']->withdraw_status==0) {   
        $this->session->set_flashdata('error', 'Withdraw for this currency is currently not allowed');
		front_redirect('wallet', 'refresh');
      }

	//   if($data['users']->phoneverified == '0' || $data['users']->email_verified == '0')
	//   {
	// 	$this->session->set_flashdata('success', $this->lang->line('Enable both Email and Phone Verification to continue Withdraw.'));
	// 	redirect(base_url().'wallet');
	//   }

			$data['selcsym'] = $cur;
			if($data['sel_currency']->crypto_type_other != '')
			{
				$crypto_type_other_arr =explode('|',$data['sel_currency']->crypto_type_other);
				if($crypto_type_other_arr[0] == 'eth')
				{
					$data['fees_type'] = $data['sel_currency']->withdraw_fees_type;
					$data['fees'] = $data['sel_currency']->withdraw_fees;
					$data['min_withdraw_limit'] = $data['sel_currency']->min_withdraw_limit;
					$data['max_withdraw_limit'] = $data['sel_currency']->max_withdraw_limit;
				} 
				else if($crypto_type_other_arr[0] == 'bsc')
				{
					$data['fees_type'] = $data['sel_currency']->withdraw_bnb_fees_type;
					$data['fees'] = $data['sel_currency']->withdraw_bnb_fees;
					$data['min_withdraw_limit'] = $data['sel_currency']->min_bnb_withdraw_limit;
					$data['max_withdraw_limit'] = $data['sel_currency']->max_bnb_withdraw_limit;
				} else {
					$data['fees_type'] = $data['sel_currency']->withdraw_trx_fees_type;
					$data['fees'] = $data['sel_currency']->withdraw_trx_fees;
					$data['min_withdraw_limit'] = $data['sel_currency']->min_trx_withdraw_limit;
					$data['max_withdraw_limit'] = $data['sel_currency']->max_trx_withdraw_limit;
				}
			} else {
				$data['fees_type'] = $data['sel_currency']->withdraw_fees_type;
				$data['fees'] = $data['sel_currency']->withdraw_fees;
				$data['min_withdraw_limit'] = $data['sel_currency']->min_withdraw_limit;
				$data['max_withdraw_limit'] = $data['sel_currency']->max_withdraw_limit;
			}
            //$data['fees'] = apply_referral_fees_deduction($user_id,$data['sel_currency']->withdraw_fees);
		}
		else{
			$data['sel_currency'] = $this->common_model->getTableData('currency',array('status' => 1),'','','','','','',array('id','ASC'))->row();
			$data['selcsym'] = $data['sel_currency']->currency_symbol;
			
			$data['fees_type'] = $data['sel_currency']->withdraw_fees_type;
			$data['fees'] = $data['sel_currency']->withdraw_fees;
            //$data['fees'] = apply_referral_fees_deduction($user_id,$data['sel_currency']->withdraw_fees);
		}
		
		$data['user_id'] = $user_id;
		
		$data['selcur_id'] = $data['sel_currency']->id;
		
		$data['currency_balance'] = getBalance($user_id,$data['selcur_id'])+getTradingBalance($user_id,$data['selcur_id']);
		$data['available_balance'] = getBalance($user_id,$data['selcur_id']);
		$data['wallet'] = unserialize($this->common_model->getTableData('wallet',array('user_id'=>$user_id),'crypto_amount')->row('crypto_amount'));

		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'withdraw'))->row();
		$data['withdraw_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id,'type'=>'Withdraw'),'','','','','','',array('trans_id','DESC'))->result();


		if(isset($_POST['withdrawcoin']))
	    {
	    	//2FA Check
	  //   	$this->load->library('Googleauthenticator');
	  //   	$ga = new Googleauthenticator();
			// $secret_code = $this->input->post('google_secret_code');
			// $onecode = $this->db->escape_str($this->input->post('google_auth_code'));
			// $users=$this->common_model->getTableData('users',array('id'=>$user_id))->row();

			// $code = $ga->verifyCode($secret_code,$onecode,$discrepancy = 6);

			// if($users->randcode != "enable")
			// {
			// 	$this->session->set_flashdata('error', "Please enable the 2FA in Settings to continue the withdraw process");
			// 	front_redirect('withdraw/'.$cur, 'refresh');
			// } else {
			// 	if($code != 1)
			// 	{
			// 		$this->session->set_flashdata('error', "Please Enter correct Google Authenticator code");
			// 		front_redirect('withdraw/'.$cur, 'refresh');
			// 	}
			// }

			$no_of_withdraw = $this->input->post('no_of_withdraw');
			if($no_of_withdraw > 0)
			{
				// generate multi withdraw token - which will be used to take all the multi withdraw records in backend or automated process
				$common_multi_withdraw_token =mt_rand(100000,888888);  
				for($i = 1; $i<=$no_of_withdraw;$i++)
				{
					$amount_field = "amount_".$i;
					$address_field = "address_".$i;
					if($this->input->post($amount_field) > 0 && $this->input->post($address_field) != '')
					{
						//$this->form_validation->set_rules('ids', 'ids', 'trim|required|xss_clean|numeric');
						//$this->form_validation->set_rules($amount_field, 'Amount', 'trim|required|xss_clean');
						$passinp = $this->db->escape_str($this->input->post('ids'));
						$myval = explode('_',$passinp);
						$id = $myval[0]; 
						$name = $myval[1];
						$bal = $myval[2];

						if($id!=7 && $id!=9)
						{ 
						   //$this->form_validation->set_rules($address_field, 'Address', 'trim|required|xss_clean');
					    }
					    else
					    { 
					    	$user_bank = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row(); 
							if(count($user_bank) == 0) 
					        { 
					        	$this->session->set_flashdata('error', "Please Fill your Bank Details");
								front_redirect('withdraw/'.$cur, 'refresh');
					        }	        
					        else 
					        {
					        	if($user_bank->status =='Pending'){
						        	$this->session->set_flashdata('error', "Please Wait for verification by our team");
									front_redirect('withdraw/'.$cur, 'refresh');
						        }
						        else if($user_bank->status =='Rejected'){
						        	$this->session->set_flashdata('error', "Your Bank details rejected by our team, Please contact support");
									front_redirect('withdraw/'.$cur, 'refresh');
						        }
						        else{
						        	$Bank = $user_bank->id; 
						        }	
					        	
					        }
					    }
					   
						/*if ($this->form_validation->run()!= FALSE)
						{ echo 'dddd'; exit;*/
							$amount = $this->db->escape_str($this->input->post($amount_field));
							if($id!=7 && $id!=9)
							{
								$address = $this->db->escape_str($this->input->post($address_field));
								$Payment_Method = 'crypto';
								$Currency_Type = 'crypto';
								$Bank_id = '';
							}
							else
							{
								$address = '';
								$Payment_Method = 'bank';
								$Currency_Type = 'fiat';
								$Bank_id = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id,'status'=>'Verified'))->row('id');
							}
				 			$balance = getBalance($user_id,$id,'crypto');
							$currency = getcryptocurrencydetail($id);
							$w_isValids   = $this->common_model->getTableData('transactions', array('user_id' => $user_id, 'type' =>'Withdraw', 'status'=>'Pending','user_status'=>'Pending','currency_id'=>$id));
							//$count        = $w_isValids->num_rows();
							$count = 0;
				            $withdraw_rec = $w_isValids->row();
			                $final = 1;
			                $Validate_Address = 1;
							if($Validate_Address==1)
							{	
								if($count>0)
								{							
									$this->session->set_flashdata('error', 'Sorry!!! Your previous ') . $currency->currency_symbol . $this->lang->line('withdrawal is waiting for admin approval. Please use other wallet or be patience');
									front_redirect('withdraw/'.$cur, 'refresh');	
								}
								else
								{
									// Min and Max withdraw fees limit set
									if($currency->crypto_type_other != '')
									{
										$crypto_type_other_arr =explode('|',$currency->crypto_type_other);
										if($crypto_type_other_arr[0] == 'eth')
										{
											$min_withdraw_limit = $currency->min_withdraw_limit;
											$max_withdraw_limit = $currency->max_withdraw_limit;
										} 
										else if($crypto_type_other_arr[0] == 'bsc')
										{
											$min_withdraw_limit = $currency->min_bnb_withdraw_limit;
											$max_withdraw_limit = $currency->max_bnb_withdraw_limit;
										} else {
											$min_withdraw_limit = $currency->min_trx_withdraw_limit;
											$max_withdraw_limit = $currency->max_trx_withdraw_limit;
										}
									} else {
										$min_withdraw_limit = $currency->min_withdraw_limit;
										$max_withdraw_limit = $currency->max_withdraw_limit;
									}

									if($amount>$balance)
									{ 
										$this->session->set_flashdata('error', 'Amount you have entered is more than your current balance');
										front_redirect('withdraw/'.$cur, 'refresh');
									}
									if($amount < $min_withdraw_limit)
									{
										$this->session->set_flashdata('error','Amount you have entered is less than minimum withdrawl limit');
										front_redirect('withdraw/'.$cur, 'refresh');
									}
									elseif($amount > $max_withdraw_limit)
									{
										$this->session->set_flashdata('error', 'Amount you have entered is more than maximum withdrawl limit');
										front_redirect('withdraw/'.$cur, 'refresh');	
									}
									elseif($final!=1)
									{
										$this->session->set_flashdata('error','Invalid address');
										front_redirect('withdraw/'.$cur, 'refresh');
									}
									else
									{
										if($currency->crypto_type_other != '')
										{
											if($this->input->post('network_type') == 'tron')
											{
												$withdraw_fees_type = $currency->withdraw_trx_fees_type;
								        		$withdraw_fees = $currency->withdraw_trx_fees;
											} else if($this->input->post('network_type') == 'bsc') {
												$withdraw_fees_type = $currency->withdraw_bnb_fees_type;
								        		$withdraw_fees = $currency->withdraw_bnb_fees;
											} else {
												$withdraw_fees_type = $currency->withdraw_fees_type;
								        		$withdraw_fees = $currency->withdraw_fees;
											}
										} else {
											$withdraw_fees_type = $currency->withdraw_fees_type;
								        	$withdraw_fees = $currency->withdraw_fees;
										}

								        if($withdraw_fees_type=='Percent') { $fees = (($amount*$withdraw_fees)/100); }
								        else { $fees = $withdraw_fees; }
										//$fees = apply_referral_fees_deduction($user_id,$fees);
								        $total = $amount-$fees;
										$user_status = 'Pending';
										$ip_address = get_client_ip();
										$insertData = array(
											'user_id'=>$user_id,
											'ip_address'=>$ip_address,
											'login_through'=>'Web',
											'payment_method'=>$Payment_Method,
											'currency_id'=>$id,
											'amount'=>$amount,
											'fee'=>$fees,
											'bank_id'=>$Bank_id,
											'crypto_address'=>$address,
											'transfer_amount'=>$total,
											'datetime'=>date("Y-m-d H:i:s"),
											'type'=>'Withdraw',
											'status'=>'Pending',
											'currency_type'=>$Currency_Type,
											'user_status'=>$user_status,
											'multiwithdraw_id'=>$common_multi_withdraw_token,
											'crypto_type'=>($this->input->post('network_type') != '')?$this->input->post('network_type'):$currency->currency_symbol
											);
										$finalbalance = $balance - $amount;
										$updatebalance = updateBalance($user_id,$id,$finalbalance,'crypto');
										$insertData_clean = $this->security->xss_clean($insertData);
										$insert = $this->common_model->insertTableData('transactions', $insertData_clean);
										if($insert) 
										{
											$prefix = get_prefix();
											$user = getUserDetails($user_id);
											$usernames = $prefix.'username';
											$username = $user->$usernames;
											$email = getUserEmail($user_id);
											$currency_name = getcryptocurrency($id);
											$link_ids = base64_encode($insert);
											$sitename = getSiteSettings('english_english_site_name');
											$site_common      =   site_common();		                    

											if($id!=7 && $id!=9)
											{
											    $email_template = 'Withdraw_User_Complete';
												$special_vars = array(
												'###SITENAME###' => $sitename,
												'###USERNAME###' => $username,
												'###AMOUNT###'   => (float)$amount,
												'###CURRENCY###' => $currency_name,
												'###FEES###' => $fees,
												'###CRYPTOADDRESS###' => $address,
												'###CONFIRM_LINK###' => base_url().'withdraw_coin_user_confirm/'.$link_ids,
												'###CANCEL_LINK###' => base_url().'withdraw_coin_user_cancel/'.$link_ids
												);
											}
											else
											{
				                                $email_template = 'Withdraw_Fiat_Complete';
												$special_vars = array(
												'###SITENAME###' => $sitename,
												'###USERNAME###' => $username,
												'###AMOUNT###'   => (float)$amount,
												'###CURRENCY###' => $currency_name,
												'###FEES###' => $fees,
												'###CONFIRM_LINK###' => base_url().'withdraw_confirm/'.$link_ids,
												'###CANCEL_LINK###' => base_url().'withdraw_cancel/'.$link_ids,
												);
											}

											// Email OTP
											// $email = $this->input->post('email');

											// $update=array(
											// 	'email_code'=>$code);

											// $this->common_model->updateTableData('users',array('id'=>$user_id),$update);

											// $email_template = 'Registration OTP';
											// $site_common      =   site_common();
											// $fb_link = $site_common['site_settings']->facebooklink;
											// $tw_link = $site_common['site_settings']->twitterlink;               
											// $md_link = $site_common['site_settings']->youtube_link;
											// $ld_link = $site_common['site_settings']->linkedin_link;

											// $special_vars = array(
											// '###USERNAME###' => $email,
											// // '###LINK###' => front_url().'verify_user/'.$activation_code,
											// '###CODE###' => $code,
											// '###FB###' => $fb_link,
											// '###TW###' => $tw_link,                   
											// '###LD###' => $ld_link,
											// '###MD###' => $md_link

											// );
											// $id=$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
											// if($id){
											// 	$this->session->set_flashdata('success', "Please provide the OTP to complete the withdraw process");
											// 	front_redirect('withdraw/'.$cur, 'refresh');               
											// }

										    
										} 
										else 
										{
											$this->session->set_flashdata('error','Unable to submit your withdraw request. Please try again');
											front_redirect('withdraw/'.$cur, 'refresh');
										}
									}
								}
							}
							else
							{
								$this->session->set_flashdata('error', 'Please check the address');
								front_redirect('withdraw/'.$cur, 'refresh');
							}
					}
						
					/*}
					else
					{ 
						$this->session->set_flashdata('error', 'Please fill the correct values');
						front_redirect('withdraw/'.$cur, 'refresh');
					}*/
				}
				$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
				$this->session->set_flashdata('success','Your withdraw request placed successfully. Please make confirm from the mail you received in your registered mail!');
				front_redirect('wallet', 'refresh');
			}



			
	    }
    if(isset($_POST['withdraw_bank']))
    {

        $this->form_validation->set_rules('currency', 'Currency', 'trim|required|xss_clean');
        $this->form_validation->set_rules('amount2', 'Amount', 'trim|required|xss_clean');


        // echo "<pre>"; print_r($_POST);die;
      if($this->form_validation->run()) {
        $Payment_Method = 'Bankwire';
        $Currency_Type = 'fiat';

		$Currency_Id = $this->db->escape_str($this->input->post('currency'));
		$account_number = $this->db->escape_str($this->input->post('account_number'));
		$account_name = $this->db->escape_str($this->input->post('account_name'));
		$bank_name = $this->db->escape_str($this->input->post('bank_name'));
		$bank_swift = $this->db->escape_str($this->input->post('bank_swift'));
		$bank_country = $this->db->escape_str($this->input->post('bank_country'));
		$payment_types = $this->db->escape_str($this->input->post('payment_types'));
		$amount = $this->db->escape_str($this->input->post('amount2'));
		$bank_city = $this->db->escape_str($this->input->post('bank_city'));
		$bank_address = $this->db->escape_str($this->input->post('bank_address'));
		$bank_postalcode = $this->db->escape_str($this->input->post('bank_postalcode'));

		$balance = getBalance($user_id,$Currency_Id,'fiat');
		$currency = getcryptocurrencydetail($Currency_Id);
		$w_isValids   = $this->common_model->getTableData('transactions', array('user_id' => $user_id, 'type' =>'Withdraw', 'status'=>'Pending','user_status'=>'Completed','currency_id'=>$Currency_Id));
        $count        = $w_isValids->num_rows();
              $withdraw_rec = $w_isValids->row();
                $final = 1;
                
           if($count>0)
      { 
        $this->session->set_flashdata('error', $this->lang->line('Sorry!!! Your previous '). $currency->currency_symbol .' withdrawal is Pending. Please use other wallet or be patience');
        front_redirect('withdraw/'.$cur, 'refresh');  
      }
      else{
        if($amount>$balance)
        { 
          $this->session->set_flashdata('error', $this->lang->line('Amount you have entered is more than your current balance'));
          front_redirect('withdraw/'.$cur, 'refresh');
        }
        if($amount < $currency->min_withdraw_limit)
        {
          $this->session->set_flashdata('error',$this->lang->line('Amount you have entered is less than minimum withdrawl limit'));
          front_redirect('withdraw/'.$cur, 'refresh');
        }
        elseif($amount>$currency->max_withdraw_limit)
        {
          $this->session->set_flashdata('error', $this->lang->line('Amount you have entered is more than maximum withdrawl limit'));
          front_redirect('withdraw/'.$cur, 'refresh');  
        }
        elseif($final!=1)
        {
          $this->session->set_flashdata('error',$this->lang->line('Invalid address'));
          front_redirect('withdraw/'.$cur, 'refresh');
        }
        else{
          $withdraw_fees_type = $currency->withdraw_fees_type;
              $withdraw_fees = $currency->withdraw_fees;

              if($withdraw_fees_type=='Percent') { $fees = (($amount*$withdraw_fees)/100); }
              else { $fees = $withdraw_fees; }
              $total = $amount-$fees;
          $user_status = 'Pending';

      $Ref = $user_id.'#'.strtotime(date('d-m-Y h:i:s'));   
      $insertData = array(
        'user_id'=>$user_id,
        'payment_method'=>$Payment_Method,
        'currency_id'=>$Currency_Id,
        'amount'=>$amount,
        'transaction_id'=>$Ref,
        'fee'=>$fees,
        'transfer_amount'=>$total,
        'datetime'=>gmdate(time()),
        'type'=>'Withdraw',
        'status'=>'Pending',
        'user_status'=>'Completed',
        'currency_type'=>'fiat',
        'payment_mode'=>'1',
        'account_number'=>$account_number,
        'account_name'=>$account_name,
        'bank_name'=>$bank_name,
        'bank_swift_code'=>$bank_swift,
        'bank_country'=>$bank_country,
        'bank_city'=>$bank_city,
        'bank_address'=>$bank_address,
        'bank_postalcode'=>$bank_postalcode,
        );

        
      $insertData_clean = $this->security->xss_clean($insertData);
      $insert = $this->common_model->insertTableData('transactions', $insertData_clean);
      if ($insert) {
        $finalbalance = $balance - $amount;
        $updatebalance = updateBalance($user_id,$Currency_Id,$finalbalance,'fiat');
        $insertData_clean = $this->security->xss_clean($insertData);
        
        $enc_email = getAdminDetails('1','email_id');
        $adminmail = decryptIt($enc_email);
        $prefix = get_prefix();
        $user = getUserDetails($user_id);
        $usernames = $prefix.'username';
        $username = $user->$usernames;
        // $email = getUserEmail($user_id);
        $currency_name = getcryptocurrency($Currency_Id);
        // $link_ids = encryptIt($insert);
        // $sitename = getSiteSettings('site_name');
        // $site_common      =   site_common();

        $email_template = 'Withdraw_request_fiat';
        $special_vars = array(
        '###USERNAME###' => $username,
        '###AMOUNT###'   => (float)$amount,
        '###CURRENCY###' => $currency_name,
        '###CONFIRM_LINK###' => front_url().'Th3D6rkKni8ht_2O22/withdraw/view/'.$insert,
        );
        $this->email_model->sendMail($adminmail, '', '', $email_template, $special_vars); 

        $this->session->set_flashdata('success', 'Bank Wire withdrawl request has been received. Will Process your Payment within few Minutes');
        front_redirect('withdraw/'.$cur, 'refresh');
      }
      else {
        $this->session->set_flashdata('error', 'Unable to Process your Withdraw. Please contact Admin.');
        front_redirect('withdraw/'.$cur, 'refresh');
      }

      }

      }
    }
    



    }
    	$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
    	$this->load->library('Googleauthenticator');
    	if($data['users']->randcode=="enable" || $data['users']->secret!="")
		{ 
			$secret = $data['users']->secret; 
			$data['secret'] = $secret;
			$ga     = new Googleauthenticator();
			$data['url'] = $ga->getQRCodeGoogleUrl('ElxisEnergy', $secret);
		}
		else
		{
			$ga = new Googleauthenticator();
			$data['secret'] = $ga->createSecret();
			$data['url'] = $ga->getQRCodeGoogleUrl('ElxisEnergy', $data['secret']);
			$data['oneCode'] = $ga->getCode($data['secret']);
		}


	    
		$this->load->view('front/user/withdraw', $data);
	
	}

	function change_address_withdraw(){
	$user_id=$this->session->userdata('user_id');
	$currency_id = $this->input->post('currency_id');

	$Currency_detail = getcurrencydetail($currency_id);
	$data['balance']	=	getBalance($user_id,$currency_id);
	$data['symbol']		=	currency($currency_id);
	$data['transaction_fee']	=	(float)$Currency_detail->withdraw_fees;
	$data['minimum_withdrawal']	=	(float)$Currency_detail->min_withdraw_limit;


					
	
		echo json_encode($data);
}

    function buy_crypto()
	{ 	 
		$user_id = $this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url('home'));
		}
		
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content', array('link'=>'dashboard'))->row();
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

		$data['dig_currency'] = $this->common_model->getTableData('currency',array('wyre_currency'=>1,'status'=>1),'','','','','','',array('sort_order','ASC'))->result();

		$this->load->view('front/user/buy_crypto', $data);
	} 

	function buycrpy()
	{
		$user_id = $this->session->userdata('user_id');
		$currency_id = $this->input->post("currency");
		$amount = $this->input->post("amount");
		$currency_symbol	=	currency($currency_id);
		$Currency_detail = getcurrencydetail($currency_id);
		$currency_name = strtolower($Currency_detail->currency_name);
		$wyre_settings = $this->common_model->getTableData('wyre_settings',array('id'=>1))->row();
		$address = $currency_symbol."_address"; 
		$admincoin_address = $wyre_settings->$address;
		$userinfo = getUserDetails($user_id); 
		$country_id = $userinfo->country;
		$user_countries = $this->common_model->getTableData('countries',array('id'=>$country_id))->row();
		
		$useremal = getUserEmail($user_id);
		$user_countries->country_code;

		 $secert_key = decryptIt($wyre_settings->secret_key);
		 $referrerAccountId = decryptIt($wyre_settings->account_id);

			$postg = '{
    "amount":'.$amount.',
    "sourceCurrency":"USD",
    "destCurrency":"'.$currency_symbol.'",
    "referrerAccountId":"'.$referrerAccountId.'",
    "email":"'.$useremal.'",
    "dest":"'.$currency_name.':'.$admincoin_address.'",
    "firstName":"'.$userinfo->elxisenergy_fname.'",
    "city":"'.$userinfo->city.'",
    "phone":"+'.$user_countries->phone_number.$userinfo->elxisenergy_phone.'",
    "street1":"'.$userinfo->street_address.'",
    "country":"'.$user_countries->country_code.'",
    "redirectUrl":"'.$wyre_settings->redirect_url.'/'.base64_encode($user_id).'",
    "failureRedirectUrl":"'.$wyre_settings->failure_url.'/'.base64_encode($user_id).'",
    "paymentMethod":"debit-card",
    "state":"'.$userinfo->state.'",
    "postalCode":"'.$userinfo->postal_code.'",
    "lastName":"'.$userinfo->elxisenergy_lname.'",
    "lockFields":[]
}';

$url = ($wyre_settings->mode==0)?'https://api.testwyre.com/v3/orders/reserve':'https://api.sendwyre.com/v3/orders/reserve';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postg);

			$headers = array();
			$headers[] = 'Authorization:Bearer '.$secert_key;
			$headers[] = 'Content-Type:application/json';
			//$headers[] = 'Postman-Token:7ad1cd47-a7bc-4126-9333-4983f4c6da5d';
			$headers[] = 'Cache-Control:no-cache';
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$result = curl_exec($ch); 
			//$resp = json_decode($result); 			
			curl_close($ch);
			print_r($result);exit;
	} 


	function getresponse_wyre($userid)
	{
		$myarray = $_REQUEST;
		if(empty($myarray))
		{
			$this->session->set_flashdata('error','Something Went Wrong. Please try again.');
			front_redirect('buy_crypto', 'refresh');
		}
		$status = $_REQUEST['status'];
		$user_id = base64_decode($userid);
		$userId = $user_id;
		$wyre_settings = $this->common_model->getTableData('wyre_settings',array('id'=>1))->row();
		if(strtoupper($status)=='COMPLETE' || strtoupper($status)=='PROCESSING')
        {
        	$amount = $_REQUEST['purchaseAmount'];
        	$source_amount = $_REQUEST['sourceAmount'];
        	$destination_currency = $_REQUEST['destCurrency'];
        	$source_currency = $_REQUEST['sourceCurrency'];
        	$transaction_id = $_REQUEST['transferId'];
        	$date_occur = $_REQUEST['createdAt'];
        	$payment_method = 'Wyre';
        	$description = $_REQUEST['dest'];
        	$pay_status = 'Completed';
	        $payment_status = 'Paid';

	        if($transaction_id!='')
	        { 
	        	$ch = curl_init();
	        	$url = ($wyre_settings->mode==0)?'https://api.testwyre.com/v2/transfer/':'https://sendwyre.com/v2/transfer/';

				curl_setopt($ch, CURLOPT_URL, $url.$transaction_id.'/track');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$headers = array();
				$headers[] = 'Content-Type:application/json';
				$headers[] = 'Cache-Control:no-cache';
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				$result = curl_exec($ch); 
				$resp = json_decode($result); 			
				curl_close($ch);
				//echo '<pre>';print_r($resp);
				if($resp->transferId == $transaction_id)
				{
					$fee = $resp->fee;
					$crypto_amount = $resp->destAmount;
					$rate = $resp->rate;

					$currency = $this->common_model->getTableData('currency',array('currency_symbol'=>$destination_currency))->row();
			        $userbalance = getBalance($userId,$currency->id);
				    $finalbalance = $crypto_amount+$userbalance;
				    // Update user balance	
				    $updatebalance = updateBalance($userId,$currency->id,$finalbalance,'');

				    $dataInsert = array(
					'user_id' => $user_id,
					'currency_id' => $currency->id,
					'currency_name' => $destination_currency,
					'amount' => $amount,
					'description' => 'Paid for '.$source_amount.' '.$source_currency,
					'type' => 'buy_crypto',
					'payment_method' => 'Wyre',
					'transfer_amount' => $crypto_amount,
					'transfer_fee' => $rate,
					'paid_amount' => $crypto_amount,
					'transaction_id'=>$transaction_id,
					'status' => $pay_status,
					'payment_status' => $payment_status,
					'currency_type' => 'crypto',
					'payment_type' => 'fiat',
					'datetime' => date("Y-m-d H:i:s")
					);				 
					$ins_id = $this->common_model->insertTableData('transactions', $dataInsert); 
					if($ins_id) 
					{
						$prefix = get_prefix();
						$user = getUserDetails($userId);
						$usernames = $prefix.'username';
						$username = $user->$usernames;
						$email = getUserEmail($userId);
						$link_ids = base64_encode($ins_id);
						$sitename = getSiteSettings('site_name');
						$site_common      =   site_common();
						$email_template   = 'Deposit_Complete';		
							$special_vars = array(
							'###SITENAME###' => $sitename,			
							'###USERNAME###' => $username,
							'###AMOUNT###'   => number_format($crypto_amount,8),
							'###CURRENCY###' => $destination_currency,
							'###MSG###' => $msg,
							'###STATUS###'	 =>	ucfirst($pay_status)
							);
						// USER NOTIFICATION
						$email = 'manimegalai@spiegeltechnologies.com';
						$this->email_model->sendMail($email, '', '', $email_template, $special_vars);
						if($pay_status=='Pending')
						{
							$this->session->set_flashdata('error','Your Crypto Deposit Failed. Please try again.');
						}
						
						else
						{
							$this->session->set_flashdata('success','Your Crypto Deposit successfully completed');
						}
						front_redirect('buy_crypto', 'refresh');
					} 
					else 
					{
						$this->session->set_flashdata('error', 'Unable to submit your Fiat Deposit request. Please try again');
						front_redirect('buy_crypto', 'refresh');
					}
				}
	        }
        }
        else
        {
        	$this->session->set_flashdata('error','Something Went Wrong. Please try again.');
			front_redirect('buy_crypto', 'refresh');
        }
	} 

	function getfailureresponse_wyre($userid)
	{		
		$this->session->set_flashdata('error','Something Went Wrong. Please try again.');
		front_redirect('buy_crypto', 'refresh');
	}  

function close_ticket($code='')
	{
		$this->load->library('session');
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}

		$support= $this->common_model->getTableData('support', array('user_id' => $user_id, 'ticket_id'=>$code))->row();
		$id = $support->id;

		$updateData['close'] = '1';
		$condition = array('id' => $id);
		$update = $this->common_model->updateTableData('support', $condition, $updateData);
		if($update){
			$this->session->set_flashdata('success','Ticket Closed');
			front_redirect('support', 'refresh');
		}
		else{
			$this->session->set_flashdata('error','Something Went Wrong. Please try again.');
			front_redirect('support_reply/'.$code, 'refresh');
		}

	}


function apply_to_list(){

if($this->block() == 1)
{ 
front_redirect('block_ip');
}
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			front_redirect('login', 'refresh');
		}
		if($this->input->post())
		{
			$image = $_FILES['coin_logo']['name'];
			if($image!="") {
			$uploadimage=cdn_file_upload($_FILES["coin_logo"],'uploads/coin_request');
			if($uploadimage)
			{
				$image=$uploadimage['secure_url'];
			}
			else
			{
				$this->session->set_flashdata('error','Problem with your coin image');
				front_redirect('add_coin', 'refresh');
			}
			} 
			else 
			{ 
				$image=""; 
			}
			$insertData['user_id'] = $user_id;
			$insertData['coin_type'] = $this->input->post('coin_type');
			$insertData['coin_name'] = $this->input->post('coin_name');
			$insertData['coin_symbol'] = $this->input->post('coin_symbol');
			$insertData['coin_logo'] = $image;
			$insertData['max_supply'] = $this->input->post('max_supply');
			$insertData['coin_price'] = $this->input->post('coin_price');
			$insertData['priority'] = $this->input->post('priority');
			if($this->input->post('crypto_type') !='')
			{
			$insertData['crypto_type'] = $this->input->post('crypto_type');
			
		    }
		    if($this->input->post('coin_type') == 0)
		    {
            // $insertData['token_type'] = $this->input->post('token_type');
            $template = 'Token_request';
            } else{
            	$template = 'Coin_request';
            }
            $insertData['marketcap_link'] = $this->input->post('marketcap_link');
            $insertData['coin_link'] = $this->input->post('coin_link');
            $insertData['twitter_link'] = $this->input->post('twitter_link');
            $insertData['username'] = $this->input->post('username');
            $insertData['email'] = $this->input->post('email');
			$insertData['status'] = '0';
			$insertData['added_by'] = 'user';
			$insertData['added_date'] = date('Y-m-d H:i:s');
            /*$insertData['type'] = 'digital';
            $insertData['verify_request'] = 0;*/
            $username = $this->input->post('username');
			$user_mail = $this->input->post('email');
			$coin_name = $this->input->post('coin_name');
			$insert = $this->common_model->insertTableData('add_coin', $insertData);

			
			$email_template = $template;
			$special_vars = array(
			'###USERNAME###' => $username,
			'###COIN###' => $coin_name
			);
			//-----------------
			$this->email_model->sendMail($user_mail, '', '', $email_template, $special_vars);
			if ($insert) {

				$this->session->set_flashdata('success', 'Your add coin request successfully sent to our team');
				front_redirect('applytolist', 'refresh');
			} else {
				$this->session->set_flashdata('error', 'Error occur!! Please try again');
				front_redirect('applytolist', 'refresh');
			}
		}
		$data['site_common'] = site_common();
		$meta = $this->common_model->getTableData('meta_content', array('link' => 'coin_request'))->row();
		$data['action'] = front_url() . 'applytolist';
		$data['heading'] = $meta->heading;
		$data['title'] = $meta->title;
		$data['meta_keywords'] = $meta->meta_keywords;
		$data['meta_description'] = $meta->meta_description;
$this->load->view('front/user/apply_to_list', $data);	
}

function phone_verification(){

	$user_id=$this->session->userdata('user_id');


	$data['site_common'] = site_common();
		$meta = $this->common_model->getTableData('meta_content', array('link' => 'coin_request'))->row();
		$data['heading'] = $meta->heading;
		$data['title'] = $meta->title;
		$data['meta_keywords'] = $meta->meta_keywords;
		$data['meta_description'] = $meta->meta_description;
		 //$data['countries'] = $this->common_model->getTableData('countries',array('phone_number!='=>null),'','','','','','','',array('phone_number','groupby'))->result(); 

		 // $data['countries'] = $this->common_model->getTableData('countries',array('phone_number!='=>null),'','','','','','','',array('country_name','orderby ASC'))->result(); 

        if(isset($_REQUEST['submitphone'])){



        	$otp=$this->input->post('otpcode');
        	$number=$this->input->post('phonenumber');
        	$country=$this->input->post('country');

        	$userst=$this->common_model->getTableData('countries',array('id'=>$country))->row();

        	$userdet=$this->common_model->getTableData('users',array('id'=>$user_id))->row();


            if($otp==$userdet->phone_verifycode){ 

             $data2=array('phoneverified'=>"verified",
            'elxisenergy_phone'=>$userst->phone_number."-".$number,'phone_verifycode'=>'');
           
            $result=$this->common_model->updateTableData('users',array('id'=>$user_id),$data2);
            
            $this->session->set_flashdata('success', 'Your Mobile Number has been bound');
				front_redirect('account', 'refresh');
           
         } else {

           $this->session->set_flashdata('error', 'Wrongly entered the code,Your phone Number Unverfied');
				//front_redirect('account', 'refresh');


         }

       }  



$this->load->view('front/user/phone_verification', $data);

}



    function phonecheck() 
    {
        $response['status'] = 'success';
        $phcode=$this->input->post('country');
        $number=$this->input->post('phone');
        if($number != '')
        {
            $userdet=$this->common_model->getTableData('users',array('elxisenergy_phone'=>$number,'country'=>'91'))->row();
            if(count($userdet) > 0)
            {
                $response['msg'] = 'This Phone number was already selected, please try some other';
                $response['status'] = 'error';
                //$this->session->set_flashdata('error', 'Try Again');
            }
        }
        echo json_encode($response);
    }

    function repay()
    {
    	$id = $this->input->post('id');
    	$user_id=$this->session->userdata('user_id');
    	$amount = $this->input->post('amount');
    	$leverage_detail = $this->common_model->getTableData("leverage",array('id'=>$id))->row();
    	$collateral_id = $leverage_detail->collateral_coin_id;
    	$collateral_amount = $leverage_detail->collateral_amount;
    	$borrow_coin_id = $leverage_detail->borrow_coin_id;
    	$user_collateral_balance = getTradingBalance($user_id,$collateral_id);
    	$user_borrow_balance = getTradingBalance($user_id,$borrow_coin_id);
    	if($user_borrow_balance > $amount)
    	{
    		//update borrow balance
	    	$update_borrow_balance = $user_borrow_balance - $amount;
	    	updateTradingBalance($user_id,$borrow_coin_id,$update_borrow_balance);
	    	//update collateral balance
	    	$update_collateral_balance = $user_collateral_balance + $collateral_amount;
	    	updateTradingBalance($user_id,$collateral_id,$update_collateral_balance);
	    	$update_data = array('is_repaid'=>'1');
					
			$update = $this->common_model->updateTableData('leverage',array('id'=>$id),$update_data);
			$response['msg'] = 'Repaid successfully';
            $response['status'] = 'success';
    	} else {
    		$response['msg'] = 'Your Balance is low to proceed repay';
            $response['status'] = 'error';
    	}
    	echo json_encode($response);
    }

    function leverage_detail()
    {
    	$id = $this->input->post('id');
    	$leverage_detail = $this->common_model->getTableData("leverage",array('id'=>$id))->row();
    	$currency = $this->common_model->getTableData("currency",array('id'=>$leverage_detail->borrow_coin_id))->row();
    	$currency2 = $this->common_model->getTableData("currency",array('id'=>$leverage_detail->collateral_coin_id))->row();
    	$response['status'] = 'success';
    	$response['data'] = $leverage_detail;
    	$response['borrow_coin'] = $currency;
    	$response['collateral_coin'] = $currency2;
    	echo json_encode($response);
    }

	function leverage(){
		
		$data['site_common'] = site_common();
		$user_id=$this->session->userdata('user_id');
        if($user_id=="")
        {   
            $this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
            redirect(base_url().'home');
        }
		
		if($_POST){
			$this->form_validation->set_rules('borrow_amount', 'Borrow amount', 'trim|required|xss_clean');
			$this->form_validation->set_rules('borrow_coin_id', 'Borrow coin', 'trim|required|xss_clean');
			$this->form_validation->set_rules('collateral_amount', 'Collateral Amount', 'trim|required|xss_clean');
			$this->form_validation->set_rules('collateral_coin_id', 'Collateral Coin', 'trim|required|xss_clean');
			$this->form_validation->set_rules('period_in_days', 'Leverage Period Days', 'trim|required|xss_clean');
			$this->form_validation->set_rules('leverage_start_date', 'Leverage Start Date', 'trim|required|xss_clean');
			$this->form_validation->set_rules('leverage_end_date', 'Leverage End Date', 'trim|required|xss_clean');
			$this->form_validation->set_rules('total_interest_amount', 'Total Interest Amount', 'trim|required|xss_clean');
			$this->form_validation->set_rules('repayment_amount', 'Repayment Amount', 'trim|required|xss_clean');

			if ($this->form_validation->run())
			{ 
				$borrow_coin_det = json_decode(json_encode($this->common_model->getTableData("currency",array('id'=>$_POST['borrow_coin_id']))->row()));
				$collateral_coin_det = json_decode(json_encode($this->common_model->getTableData("currency",array('id'=>$_POST['collateral_coin_id']))->row()));
				$user_details = json_decode(json_encode($this->common_model->getTableData("users",array('id'=>$user_id))->row()));
				
				// echo "borrow_min==>";
				// print_r($borrow_coin_det->borrow_min);
				// echo " and borrow_max==>";
				// print_r($borrow_coin_det->borrow_max);
				// echo " and collateral_min==>";
				// print_r($collateral_coin_det->collateral_min);
				// echo " and collateral_max==>";
				// print_r($collateral_coin_det->collateral_max);exit;
				// if($user_details->verification_level && $user_details->verify_level2_status && $user_details->verify_level3_status){
				// 	if(strtolower($user_details->verification_level) != "pending" && strtolower($user_details->verify_level2_status) != "pending" && strtolower($user_details->verify_level3_status) != "pending"){
				// 		if(strtolower($user_details->verification_level) != "rejected" && strtolower($user_details->verify_level2_status) != "rejected" && strtolower($user_details->verify_level3_status) != "rejected"){
							if ($borrow_coin_det->status==1)
							{ 
								if ($collateral_coin_det->status==1)
								{ 
									
									$_POST['user_id'] = $user_id;
									unset($_POST['borrow_coin_sym']);
									unset($_POST['collateral_coin_sym']);
									$period_in_days = $_POST['period_in_days'];
									$leverage_period = $this->common_model->getTableData('leverage_period',array('id'=>$period_in_days))->row();
									$_POST['period_in_days'] = $leverage_period->leverage_period;
									$insert=$this->common_model->insertTableData('leverage',$_POST);
									
									if ($insert) {
										$borrow_trading_balance = getTradingBalance($user_id,$_POST['borrow_coin_id']);
										$borrow_trading_balance  = abs($borrow_trading_balance +$_POST['borrow_amount']);
										$borrow_update_trade_balance = updateTradingBalance($user_id,$_POST['borrow_coin_id'],$borrow_trading_balance);

										$collateral_trading_balance = getTradingBalance($user_id,$_POST['collateral_coin_id']);
										$collateral_trading_balance  = abs($collateral_trading_balance -$_POST['collateral_amount']);
										$collateral_update_trade_balance = updateTradingBalance($user_id,$_POST['collateral_coin_id'],$collateral_trading_balance);
										$this->session->set_flashdata('success', 'Leverage details updated successfully');
										front_redirect('leverage', 'refresh');
									} else {
										$this->session->set_flashdata('error', 'Something there is a Problem .Please try again later');
										front_redirect('leverage', 'refresh');
									}
								}else{
									$this->session->set_flashdata('error', 'Selected collateral coin is deactivated. Please refresh the page and try again');
									front_redirect('leverage', 'refresh');
								}
							}else{
								$this->session->set_flashdata('error', 'Selected borrow coin is deactivated. Please refresh the page and try again');
								front_redirect('leverage', 'refresh');
							}
				// 		}else{
				// 			$this->session->set_flashdata('error', 'Please complete your KYC verification');
				// 			front_redirect('leverage', 'refresh');
				// 		}
				// 	}else{
				// 		$this->session->set_flashdata('error', 'Please complete your KYC verification');
				// 		front_redirect('leverage', 'refresh');
				// 	}		
				// }else{
				// 	$this->session->set_flashdata('error', 'Please complete your KYC verification');
				// 	front_redirect('leverage', 'refresh');
				// }	
				
			}else{
				$this->session->set_flashdata('error', validation_errors());
				front_redirect('leverage', 'refresh');
			}
		}
		$data['current_date'] = date("Y-m-d H:i:s");
		// $data['leverage_records'] = $this->common_model->getTableData('leverage',array('user_id'=>$user_id))->result();

		$data['leverage_records'] = $this->common_model->customQuery("SELECT lg.*, `cybw`.currency_symbol AS borrow_currency_symbol,`cycl`.`currency_symbol` AS collateral_currency_symbol,
			`cybw`.`image` AS borrow_currency_image,`cycl`.`image` AS collateral_currency_image
			FROM `elxisenergy_leverage` `lg`
			JOIN `elxisenergy_currency` cybw
			JOIN `elxisenergy_currency` cycl
			ON `lg`.`borrow_coin_id` = `cybw`.`id` AND `lg`.`collateral_coin_id` = `cycl`.`id` where `lg`.user_id='$user_id'")->result();

		// $this->db->select('*');  
		// $this->db->from('leverage AS lg');
		// $this->db->where("lg.user_id",$user_id);// I use aliasing make joins easier  
		// $this->db->join('currency AS cybw', 'lg.borrow_coin_id = cybw.id');  
		// $this->db->join('currency AS cycl', 'lg.collateral_coin_id = cycl.id', 'INNER');  
		// $result = $this->db->get()->result();  
		// $where=array('lg.user_id'=>$user_id);
		// $orderBy=array('lg.id','asc');
		// $joins = array('currency as cy'=>'cy.id = lg.borrow_coin_id','currency as cy'=>'cy.id = lg.collateral_coin_id');
		// $data['leverage_records'] = $this->common_model->getJoinedTableData('leverage as lg',$joins,$where,'','','','','','',$orderBy)->result();
		
		$data['leverage_admin'] = $this->common_model->getTableData("leverage_admin")->row();
		$data['leverage_periods'] = $this->common_model->getTableData("leverage_period")->result();
		$data['currencies'] = $this->common_model->getTableData("currency",array('status'=>1))->result();
		$data['user_id'] = $user_id;
		
		$this->load->view('front/user/leverage',$data);
	}

function phoneupdate(){

	$user_id=$this->session->userdata('user_id');


        if($user_id=="")
        {   
            $this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
            redirect(base_url().'home');
        }

    //$user_id="18";
      if($this->input->post('country')){
             //  $user_id="18";
                   $code=mt_rand(100000,999999);
                   $phcode=$this->input->post('country');
                   $number=$this->input->post('phone');
                   $name=$this->input->post('name');
                   $codesession=array('code'=>$code,
                   'phcode'=>$phcode,
                   'number'=>$number);
                   $this->session->set_userdata($codesession);

                    $data = ['phone' => '+'.$phcode.$number, 'text' => "elxisenergy:Your phone number verification code " .$code];
      
               $opt=$this->sendSMS($data);

               if($opt){
                 $data1=array('phone_verifycode'=>$code,
                              'elxisenergy_phone'=>$number,
                              'country'=> $phcode, );
                $result=$this->common_model->updateTableData('users',array('id'=>$user_id),$data1);
                $this->session->set_flashdata('success', 'OTP send Successfully');

               }else{
               $this->session->set_flashdata('error', 'Try Again');

               }

      }


         if($this->input->post('otp')){
$phcode=$this->input->post('otp');
$otp = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
 $check=$otp->phone_verifycode;

if($phcode == $check){



 $update_phonestatus=array('phoneverified'=>1,'phone_verifycode'=>'');
 $result=$this->common_model->updateTableData('users',array('id'=>$user_id),$update_phonestatus);


                   if($result){
                 

                    //  $this->session->set_flashdata('success', 'OTP Verified Successfully');
//echo 'Mobile Number Verified  successfully';

    $msg='success';

        echo json_encode($msg);

exit();
                      
                  }
       

}else{


//echo 'Wrong OTP Please Re Enter';

    $msg='error';

        echo json_encode($msg);

exit();
}

      }
       
     $data['country'] = $this->common_model->getTableData('countries')->result();
     $data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
     $data['users_history']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 5 ")->result();
    $data['users_history_last']=$this->common_model->customQuery("SELECT * FROM elxisenergy_user_activity WHERE user_id = '$user_id' AND activity = 'Login' AND is_invalid = '0'  order by act_id desc limit 1,2")->row();
    $data['site_common'] = site_common();

    $this->load->view('front/user/phone',$data); 
}

function referral(){
	//front_redirect('','refresh');
$user_id=$this->session->userdata('user_id');

$data['site_common'] = site_common();

$data['content_1'] = $this->common_model->getTableData('static_content', array('slug' => 'refferal'))->row();
$data['content_2'] = $this->common_model->getTableData('static_content', array('slug' => 'referral-1'))->row();
$data['content_3'] = $this->common_model->getTableData('static_content', array('slug' => 'referral-2'))->row();
$data['content_4'] = $this->common_model->getTableData('static_content', array('slug' => 'referral-3'))->row();
$data['content_5'] = $this->common_model->getTableData('static_content', array('slug' => 'referral-4'))->row();
$data['step_1'] = $this->common_model->getTableData('static_content', array('slug' => 'referral_step-1'))->row();
$data['step_2'] = $this->common_model->getTableData('static_content', array('slug' => 'referral_step-2'))->row();
$data['step_3'] = $this->common_model->getTableData('static_content', array('slug' => 'referral_step-3'))->row();

$data['cms'] = $this->common_model->getTableData('cms', array('status' => 1, 'id'=>6))->row();

$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();

$data['ref'] = $this->db->select('COUNT(parent_referralid) as total')->from('users')->where('parent_referralid',$data['users']->referralid)->get()->row();

$data['commission'] = $this->common_model->getTableData('referral_commission')->row();
$data['faq'] = $this->common_model->getTableData('faq', array('status' => 1))->result();

        if($user_id!="")
        {   
            $this->load->view('front/user/referral_link',$data);
        } else{
            $this->load->view('front/user/referral',$data);
        }

}

	function get_pendingtransaction($address,$coin_name)
	{
      $ctype = $this->db->select('*')->where(array('currency_name'=>$coin_name,'status'=>'1'))->get('currency')->row();
      if($ctype->coin_type=="coin")
      {
         $model_currency = $coin_name;
      }
      else
      {
      	if($ctype->crypto_type=='eth'){
			$model_currency = "token";
			
		}
		else{
			$model_currency = "token_bnb";
		}

      } 
      
       
       $model_name = strtolower($model_currency).'_wallet_model';
	   $model_location = 'wallets/'.strtolower($model_currency).'_wallet_model';
	   
	   $this->load->model($model_location,$model_name);
	   $pending = $this->$model_name->eth_pendingTransactions();
	   $txn_count = 0;
	   if(count($pending) >0)
	   {
	   	foreach($pending as $txn)
	   	{
	   		if($address==$txn->from)
	   		{
              $txn_count++;
	   		}
	   	}
	   }
	   return $txn_count;
	}

	function test(){
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			front_redirect('login', 'refresh');
		}
		$this->load->library('session');
		$data['site_common'] = site_common();
		$data['meta_content'] = $this->common_model->getTableData('meta_content',array('link'=>'markets'))->row();
		$data['pairs'] = $this->common_model->getTableData('trade_pairs',array('status'=>'1'),'','','','','','', array('id', 'ASC'))->result();
		$data['usdt_pair'] = $this->common_model->getTableData('trade_pairs',array('status'=>'1','to_symbol_id'=>3),'','','','','','', array('id', 'ASC'))->result();
		// $data['currency_pair'] = $this->common_model->getTableData('trade_pairs',array('status'=>'1'),'','','','','','', array('id', 'ASC'))->result();
		$data['currency_pair'] = $this->common_model->getTableData('trade_pairs',array('status'=>'1','from_symbol_id'=>1),'','',array('to_symbol_id'=>1),'','','', array('id', 'ASC'))->result();
		
		$data['favourites_pairs'] = $this->get_favourite($user_id);
		$data['users'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$where_not = array('currency_symbol', array('INR','SMD'));
		$data['currency_symbol'] = $this->common_model->getTableData('currency',array('status'=>'1'),'','','','','','', array('id', 'ASC'),'',$where_not)->result();
		$data['currency_info'] = $this->common_model->getTableData('currency',array('status'=>'1'),'','','','','','', array('id', 'ASC'))->result();
		
		$data['market_overview_datas']  = json_decode(file_get_contents("https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=10&page=1&sparkline=false"));
		$data['user_id']  = $user_id;
		// print_r($data['scurrency_pair']);exit;
		// $iter_count = (count($data['market_overview_datas'])+2)/6;
		// $inserted = json_encode(json_decode ("{'data' : 'kk'}"));
		// $threshold = 6;
		// $empty_position=2;
		// $iter_count = ($iter_count)/$threshold;
		// for($i=0;$i<2;$i++){
		// 	if($i>0) {
		// 		$empty_position= $empty_position+6;
		// 	}
			// echo " ; i=>".$empty_position;
		// }
		// exit;
		$this->load->view('front/user/test', $data);

	}
	

	function get_favourite($user_id=''){
		if($user_id!=0){
			$result = $this->common_model->getTableData('favourite_pairs', array('user_id' => $user_id))->result();
		}else{
			$result = $this->common_model->getTableData('favourite_pairs', array('user_ip' => $_SERVER['REMOTE_ADDR']))->result();
		}
	
		if($result):
			foreach($result as $res){
			$pair_currency = $this->common_model->customQuery("select id,from_symbol_id,to_symbol_id,lastPrice,priceChangePercent,volume from elxisenergy_trade_pairs where id=".$res->pair_id." order by id DESC")->result();
		
			
			if(isset($pair_currency) && !empty($pair_currency))
			{
				$Pairs_List = array();
				foreach($pair_currency as $Pair_Currency)
				{
					$from_currency_det = getcryptocurrencydetail($Pair_Currency->from_symbol_id);
					$to_currency_det = getcryptocurrencydetail($Pair_Currency->to_symbol_id);
					$pair_from_image = $from_currency_det->image;
					$pair_to_image = $to_currency_det->image;
					$pairname = $from_currency_det->currency_symbol."/".$to_currency_det->currency_symbol;
					$pairurl = $from_currency_det->currency_symbol."_".$to_currency_det->currency_symbol;
					$change = ($Pair_Currency->priceChangePercent!='')?$Pair_Currency->priceChangePercent:'0.00';
					if($change>=0)
					{
					$class= "green";
				}
				else
				{
					$class= "red";
				}
					$Site_Pairs[$Pair_Currency->id] = array(
						"id"=>$res->pair_id,
						"currency_pair"	=> $pairname,
						"pair_from_image"	=> $pair_from_image,
						"pair" => $from_currency_det->currency_symbol.' / '.$to_currency_det->currency_symbol,
						"price"	=>	($Pair_Currency->lastPrice!='')?$Pair_Currency->lastPrice:'0.00',
						"volume"	=>	($Pair_Currency->volume!='')?$Pair_Currency->volume:'0.00',
						"change"	=> ($Pair_Currency->priceChangePercent!='')?number_format(rtrim($Pair_Currency->priceChangePercent,'.'),2):'0.00',
						"pairurl"	=> $pairurl,
						"class" => $class
					);
				}
				$sitepairs = array_reverse($Site_Pairs);
			}
		}
		endif;
		return ($sitepairs);
			// echo json_encode($result);
	}

	function add_favourite($pair='',$user_id=''){
		if($user_id){
			$user_id = $user_id;
		}else{
			$user_id = $this->session->userdata('user_id');
		}
		if($user_id){
			$insert_data['user_id'] = $user_id;
			$insert_data['pair_id'] = $pair;
			$insert_data['user_ip'] = $_SERVER['REMOTE_ADDR'];
			$get_favourites = $this->common_model->getTableData('favourite_pairs', array('user_id' => $user_id,'pair_id'=>$pair))->row();
	
	
			
			if(count($get_favourites) == 0){
				$this->common_model->insertTableData('favourite_pairs',$insert_data);
				$data['status'] ='insert';		
			}else{
				$this->common_model->deleteTableData('favourite_pairs',array('id'=>$get_favourites->id));
				$data['status'] ='delete';	
			}
			$data['result']= $this->common_model->getTableData('favourite_pairs', array('user_id' => $user_id))->result();	
		}else{
			$insert_data['user_id'] = 0;
			$insert_data['pair_id'] = $pair;
			$insert_data['user_ip'] = $_SERVER['REMOTE_ADDR'];
			$get_favourites = $this->common_model->getTableData('favourite_pairs', array('user_ip' => $_SERVER['REMOTE_ADDR'],'pair_id'=>$pair))->row();
	
	
			if(count($get_favourites) == 0){
				$this->common_model->insertTableData('favourite_pairs',$insert_data);
				$data['status'] ='insert';	
			}else{
				$this->common_model->deleteTableData('favourite_pairs',array('id'=>$get_favourites->id));
				$data['status'] ='delete';	
			}	
		 $data['result']= $this->common_model->getTableData('favourite_pairs', array('user_ip' => $_SERVER['REMOTE_ADDR']))->result();
		}
		echo json_encode($data);
	}

	function test2(){
		$url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
		// $url = 'https://sandbox-api.coinmarketcap.com/v1/cryptocurrency/market-pairs/latest';

		$parameters = [
		'start' => '1',
		'limit' => '1',
		'convert' => 'USD'
		];

		// $parameters = [
		// 	'id' => '1'
		// 	];

		$headers = [
		'Accepts: application/json',
		// 'X-CMC_PRO_API_KEY: b54bcf4d-1bca-4e8e-9a24-22ff2c3d462c' // test api key
		'X-CMC_PRO_API_KEY: fcb0d13b-806d-4fe4-898a-e74fd01d0ac1'
		];
		$qs = http_build_query($parameters); // query string encode the parameters
		$request = "{$url}?{$qs}"; // create the request URL


		$curl = curl_init(); // Get cURL resource
		// Set cURL options
		curl_setopt_array($curl, array(
		CURLOPT_URL => $request,            // set the request URL
		CURLOPT_HTTPHEADER => $headers,     // set the headers 
		CURLOPT_RETURNTRANSFER => 1         // ask for raw response instead of bool
		));

		$response = curl_exec($curl); // Send the request, save the response
		print_r(json_decode($response)); // print json decoded response
		curl_close($curl); 

	}

function user_bank_details(){
  	$user_id=$this->session->userdata('user_id');
	if($user_id=="") {  
		front_redirect('login', 'refresh');
	}
  	if($this->input->post()) {

		$_POST['user_id'] = $user_id;
		$_POST['status'] = 'Pending';
		$_POST['user_status'] = '1';
		
		$get=$this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row();
		if(empty($get)) {
		$insert=$this->common_model->insertTableData('user_bank_details',$_POST);
		} else {
		$insert=$this->common_model->updateTableData('user_bank_details',array('id'=>$get->id),$_POST);
		}
		if ($insert) {
		$this->session->set_flashdata('success', 'Bank details Updated Successfully');
		} else {
		$this->session->set_flashdata('error', 'Something ther is a Problem .Please try again later');
		}
  	}
	$data['site_common'] = site_common();
	$data['sta_content'] = $this->common_model->getTableData('static_content', array('slug' => 'refferal_program'))->row();
	$data['user'] = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
	$data['currency']=$this->common_model->getTableData('currency',array('type'=>'fiat'))->result();
	$data['user_bank'] = $this->common_model->getTableData('user_bank_details', array('user_id'=>$user_id))->row();
	$data['countries'] = $this->common_model->getTableData('countries')->result();

  	$this->load->view('front/user/user_bank_details',$data);
}

  function fiat_deposit()
  {
    $user_id=$this->session->userdata('user_id');
    $value = $this->db->escape_str($this->input->post('amount'));     
    $currency = $this->input->post('currency');
    $slct_fiat_currency = $this->common_model->getTableData('currency',array('status'=>1, 'id'=>$currency))->row();

    if($value < $slct_fiat_currency->min_deposit_limit)
    {
      $data['status']='minimum';
      echo json_encode($data);
    }
    elseif($value>$slct_fiat_currency->max_deposit_limit)
    {
      $data['status']='maximum';
      echo json_encode($data); 
    } else {

    $deposit_max_fees = $slct_fiat_currency->deposit_max_fees;
    $deposit_fees_type = $slct_fiat_currency->deposit_fees_type;
    $deposit_fees = $slct_fiat_currency->deposit_fees;
    if($deposit_fees_type=='Percent') { $fees = (($value*$deposit_fees)/100); }
    else { $fees = $deposit_fees; }
    if($fees>$deposit_max_fees) { $final_fees = $deposit_max_fees; }
    else { $final_fees = $fees; }
    $total = $value-$final_fees;

    $bankInfo = $this->common_model->getTableData('user_bank_details',array('user_id'=>$user_id))->row();
    $bank_no = $bankInfo->id;
    $payment_types = $bankInfo->payment_types;
    $description = $bankInfo->description;
    $account_number = $bankInfo->bank_account_number;
    $account_name = $bankInfo->bank_account_name;
    $bank_name = $bankInfo->bank_name;
    $bank_swift = $bankInfo->bank_swift;
    $bank_country = $bankInfo->bank_country;
    $bank_city = $bankInfo->bank_city;
    $bank_address = $bankInfo->bank_address;
    $bank_postalcode = $bankInfo->bank_postalcode;
        // echo json_encode($bankInfo);die;
    $Ref = $user_id.'#'.strtotime(date('d-m-Y h:i:s')); 
    $insertData = array(
        'user_id'=>$user_id,
        'payment_method'=>'Bankwire',
        'currency_id'=>$this->db->escape_str($this->input->post('currency')),
        'amount'=>$this->db->escape_str($this->input->post('amount')),
        'transaction_id'=>$Ref,
        'bank_id'=>$bank_no,
        'fee'=>$final_fees,
        'transfer_amount'=>$total,
        'datetime'=>gmdate(time()),
        'type'=>'Deposit',
        'status'=>'Pending',
        'currency_type'=>'fiat',
        'account_number'=>$account_number,
        'account_name'=>$account_name,
        'bank_name'=>$bank_name,
        'bank_swift_code'=>$bank_swift,
        'bank_country'=>$bank_country,
        'bank_city'=>$bank_city,
        'bank_address'=>$bank_address,
        'bank_postalcode'=>$bank_postalcode,
        // 'upload_pdf_deposit'=>$fname,
        );
    $insert = $this->common_model->insertTableData('transactions', $insertData);
    $usersvalid = $this->common_model->getTableData('users', array('id' => $user_id));
    if ($insert) {
      $users = $usersvalid->row();
      //$email = getUserEmail($users->id);
      $prefix = get_prefix();
      $user = getUserDetails($user_id);
      $usernames = $prefix.'username';
      $username = $user->$usernames;
      $enc_email = getAdminDetails('1','email_id');
      $adminmail = decryptIt($enc_email);
      $email_template = 'Deposit_request';
      $special_vars = array(
        '###USERNAME###' => $username,
        '###COIN###' => $slct_fiat_currency->currency_symbol,
        '###AMOUNT###' => $total,
        '###LINK###' => front_url().'Th3D6rkKni8ht_2O22/deposit/view/'.$insert,
        // '###CANCEL_LINK###' => front_url().'elxisenergy_admin/deposit/reject/'.$insert
      );
      $this->email_model->sendMail($adminmail, '', '', $email_template, $special_vars);

      $data['status']='success';
      echo json_encode($data);
    }
  }
  }

  public function cancel_order($tradeid,$sym)
  {
    $user_id=$this->session->userdata('user_id');
    if($user_id=="") {  
      front_redirect('login', 'refresh');
    }
    $coinInfo = getcoindetail($sym);
    $userBal = getBalance($user_id, $coinInfo->id);
    $data_up['status']="cancelled";

    $coinOrderInfo = $this->common_model->getTableData('coin_order', array('trade_id'=>$tradeid))->row();
    $total = $userBal+$coinOrderInfo->Total;
    $query=$this->common_model->updateTableData('coin_order',array('trade_id'=>$tradeid),$data_up);
    if($query){
      updateBalance($user_id,$coinInfo->id,$total); 
      echo 1;  
    } else {
      echo 0;
    }
  }

  public function move_to_admin_wallet($coinname,$crypto_type='')
	{
		echo "MOVE To Admin Wallet Begins";
		echo "<br/>";
		echo $coinname."----".$crypto_type;
		echo "<br/>";
	    $coinname =  str_replace("%20"," ",$coinname);
        
	    $currency_det    =   $this->db->query("select * from elxisenergy_currency where currency_name = '".$coinname."' limit 1")->row(); 


	    if($currency_det->move_admin==1)
	    {
			//echo "inn";
	    $currency_status = $currency_det->currency_symbol.'_status';
	   //$address_list    =  $this->db->query("select * from tarmex_crypto_address where ".$currency_status." = '1' ")->result(); 
	   $address_list    =  $this->db->query("select * from elxisenergy_transactions where type = 'Deposit' and status = 'Completed' and currency_id = ".$currency_det->id." and crypto_type = '".$crypto_type."' and amount > $currency_det->move_coin_limit and admin_move = 0 ")->result(); 
		echo "Total Transaction pending".count($address_list);
		echo "<br/>";
	    $fetch           =  $this->db->query("select * from elxisenergy_admin_wallet where id='1' limit 1")->row(); 
	    $get_addr        =  json_decode($fetch->addresses,true);
	    
        
        $coin_type = $currency_det->coin_type;
		// Added to make currency_decimal dynamic
		$currency_decimal = $currency_det->currency_decimal;
		if($crypto_type == 'tron' && $currency_det->trx_currency_decimal != '')
		{
			$currency_decimal = $currency_det->trx_currency_decimal;
		} else if($crypto_type == 'bsc' && $currency_det->bsc_currency_decimal != '')
		{
			$currency_decimal = $currency_det->bsc_currency_decimal;
		}

		//echo $currency_decimal; exit;
		
	    $coin_decimal = coin_decimal($currency_decimal);
	    
	    $min_deposit_limit = $currency_det->move_coin_limit;


	    if($coinname!="")
	    {
	        $i =1;
            if(!empty($address_list)){
	        foreach ($address_list as $key => $value) {
				echo $value->trans_id."starts";
				echo "<br/>";
	        	$from='';
	                //$arr       = unserialize($value->address);
	                //$from      = $arr[$currency_det->id];

					$crypto_type = $value->crypto_type; // modifying this for making crypto_type dynamic
					if($value->crypto_type == 'tron')
						$curr_symbol = 'TRX';
					else if($value->crypto_type == 'bsc')
						$curr_symbol = 'BNB';
					else if($value->crypto_type == 'eth')
						$curr_symbol = 'ETH';


					$currency_decimal = $currency_det->currency_decimal;
					if($crypto_type == 'tron' && $currency_det->trx_currency_decimal != '')
					{
						$currency_decimal = $currency_det->trx_currency_decimal;
					} else if($crypto_type == 'bsc' && $currency_det->bsc_currency_decimal != '')
					{
						$currency_decimal = $currency_det->bsc_currency_decimal;
					}
					$coin_decimal = coin_decimal($currency_decimal);

					$toaddress       =  $get_addr[$curr_symbol];  // modifying this for making crypto_type dynamic
	        	    $from = $value->crypto_address;

	                $user_id = $value->user_id;
	                $trans_id = $value->trans_id;
	                 $from_address='';$amount=0;

	                 if($coin_type=="token" && $crypto_type=='tron')
	                 {
	                 	$tron_private = gettronPrivate($user_id);
						$crypto_type_other = array('crypto'=>$crypto_type,'tron_private'=>$tron_private);
	                 	$amount    = $this->local_model->wallet_balance($coinname,$from,$crypto_type_other);
	                 } 
					 else if($coin_type == 'token')
					 {
						$crypto_type_other = array('crypto'=>$crypto_type);
						$amount    = $this->local_model->wallet_balance($coinname,$from,$crypto_type_other);
					 }
	                 else
	                 {
	                 	$amount    = $this->local_model->wallet_balance($coinname,$from);
	                 }

	                 


	                $minamt    = $currency_det->min_withdraw_limit;
	                $from_address = trim($from); 
	                $to = trim($toaddress);
	        
	                if($from_address!='0') {
	                	/*echo "Address - ".$from_address;
	                	echo "Balance - ".$amount;*/
	                if($amount>=$min_deposit_limit) 
	                {
	                	echo $amount."<br/>";

	                	echo "transfer";
						echo "<br/>";
						echo "CRYPTO TYPE".$crypto_type;
						echo "<br/>";
						echo "COIN TYPE".$coin_type;
	                	
		                if($coin_type=="token")
		                {
							if($crypto_type=='eth')
							{
								$GasLimit = 70000;
		                        $GasPrice = $this->check_ethereum_functions('eth_gasPrice','Ethereum');
		                        //$GasPrice = 185 * 1000000000;
		                        
		                        $amount_send = $amount;
								$privateKey = getetherPrivate($user_id);
								// echo $amount; 
								// echo "<br/>";
								// echo $coin_decimal;
								// exit;
		                        $amount1 = $amount_send * $coin_decimal;

		                        echo "<br/>".$GasPrice."<br/>";

		                        $trans_det = array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice,'privateKey'=>$privateKey);
							}
							elseif($crypto_type=='bsc')
							{
								$GasLimit = 120000;
		                        //$GasPrice = $this->check_ethereum_functions('eth_gasPrice','BNB');

		                        $GasPrice = 30000000000;

		                        $amount_send = $amount;
								// echo $amount_send;
								// echo "<br/>";
								// echo $coin_decimal;
		                        $amount1 = $amount_send * $coin_decimal;
								
	                            echo "<br/>".$GasPrice."<br/>";

		                        $trans_det = array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice);

		                        // echo "<pre>";print_r($trans_det);
		                        // exit();
							}
							else
							{
					            $amount1 = $amount * $coin_decimal;
					            $fee_limit = 2000000;

					            $privateKey = gettronPrivate($user_id);
								//$trans_det 	= array('owner_address'=>$from_address,'to_address'=>$to,'amount'=>rtrim(sprintf("%.0f", $amount1), "."),'privateKey'=>$privateKey);

								$trans_det 	= array('owner_address'=>$from_address,'to_address'=>$to,'amount'=>(float)$amount1,'privateKey'=>$privateKey);

							}
		                	
                            if($crypto_type=='eth')
		                	{
		                		$eth_balance = $this->local_model->wallet_balance("Ethereum",$from_address); // get balance from blockchain
		                		$transfer_currency = "Ethereum";
		                		$check_amount = "0.005";
		                		//$check_amount = "0.01";
		                	}
		                	elseif($crypto_type=='tron')
		                	{
		                		$eth_balance = $this->local_model->wallet_balance("Tron",$from_address); // get balance from blockchain
		                		$transfer_currency = "Tron";
		                		$check_amount = "5";
		                	}
		                	else
		                	{
		                		$eth_balance = $this->local_model->wallet_balance("BNB",$from_address); // get balance from blockchain
		                		$transfer_currency = "BNB";
		                		$check_amount = "0.004";
		                	}
							echo "Balance".$eth_balance;
							echo "<br/>";
							echo "Check Amount".$check_amount;

		                	if($eth_balance >= $check_amount)
		                	{
								echo "MOVEADMINWALLET_IF";
								//exit;
		                		if($crypto_type=='eth' || $crypto_type=='bsc')
		                		{
		                			$txn_count = $this->get_pendingtransaction($from_address,$coinname);
		                		}
		                		else
		                		{
		                			$txn_count = 0;
		                		}
		                		
		                		if($txn_count==0)
		                		{
									echo $coinname;
		                			print_r($trans_det);
									echo $crypto_type;
									//exit;
		                			$send_money_res_token = $this->local_model->make_transfer($coinname,$trans_det,$crypto_type); // transfer to admin
									echo "inini";
									print_r($send_money_res_token);
									//exit;
                                   if($send_money_res_token !="" || $send_money_res_token !="error" || $send_money_res_token['error']=='')
                                    {
                                    	$tnx_data = array(
											'userid'=>$value->user_id,
											'crypto_address' => $from_address,
											'amount'=>(float)$amount,
											'currency_symbol'=>$currency_det->currency_symbol,
											'currency_id'=>$value->currency_id,
											'type'=>'User to Admin Wallet',
											'status'=>'Completed',
											'date_created'=>date('Y-m-d H:i:s'),
											'txn_id'=>$send_money_res_token
										);
										$ins = $this->common_model->insertTableData('admin_wallet_logs',$tnx_data);
	                                    $update = $this->common_model->updateTableData("transactions",array("admin_move"=>0,"trans_id"=>$trans_id),array("admin_move"=>1));
			                			
                                    }
		                			
		                		}
		                		
                                
		                	}
		                	else
		                	{
								echo "else";
								//exit;
		                		if($crypto_type=='eth')
		                		{
		                		$eth_amount = 0.005;
                                $GasLimit1 = 21000;
                                $Gas_calc1 = $this->check_ethereum_functions('eth_gasPrice','Ethereum');
		                        $Gwei1 = $Gas_calc1;
		                        $GasPrice1 = $Gwei1;
		                        $Gas_res1 = $Gas_calc1 / 1000000000;
		                        $Gas_txn1 = $Gas_res1 / 1000000000;
                                $txn_fee = $GasLimit1 * $Gas_txn1;
                                //$send_amount = $eth_amount + $txn_fee;
		                		$eth_amount1 = $eth_amount * 1000000000000000000;
		                        $nonce1 = $this->get_transactioncount($to,$coinname);
								$privateKey = getadminetherPrivate(1);
		                        $eth_trans = array('from'=>$to,'to'=>$from_address,'value'=>(float)$eth_amount1,'gas'=>(float)$GasLimit1,'gasPrice'=>(float)$GasPrice1,"privateKey"=>$privateKey);

		                		}
		                		elseif($crypto_type=='bsc')
		                		{
		                		$eth_amount = 0.005;
                                $GasLimit1 = 120000;
                                //$Gas_calc1 = $this->check_ethereum_functions('eth_gasPrice','BNB');
                                $Gas_calc1 = 30000000000;
		                        $Gwei1 = $Gas_calc1;
		                        $GasPrice1 = $Gwei1;
		                        $Gas_res1 = $Gas_calc1 / 1000000000;
		                        $Gas_txn1 = $Gas_res1 / 1000000000;
                                $txn_fee = $GasLimit1 * $Gas_txn1;
                                //$send_amount = $eth_amount + $txn_fee;
		                		$eth_amount1 = $eth_amount * 1000000000000000000;
		                        $nonce1 = $this->get_transactioncount($to,$coinname);
		                        $eth_trans = array('from'=>$to,'to'=>$from_address,'value'=>(float)$eth_amount1,'gas'=>(float)$GasLimit1,'gasPrice'=>(float)$GasPrice1);

		                		}
		                		else
		                		{
		                		
					                $amount1 = 5 * 1000000;
					                $privateKey = getadmintronPrivate(1);
									$eth_trans 		= array('fromAddress'=>$to,'toAddress'=>$from_address,'amount'=>(float)$amount1,"privateKey"=>$privateKey);

		                		}

		                		if($crypto_type=='eth' || $crypto_type=='bsc')
		                		{
		                			$txn_count = $this->get_pendingtransaction($to,$transfer_currency);
		                		}
		                		else
		                		{
		                			$txn_count = 0;
		                		}
								// echo "innn";
								// echo $txn_count;
								// print_r($eth_trans); exit;
                                
                               if($txn_count==0)
                               {
								  
                               	$send_money_res = $this->local_model->make_transfer($transfer_currency,$eth_trans); // admin to user wallet

                               	if($send_money_res !="" || $send_money_res !="" || $send_money_res['error']=='')
                               	{
                               		 $tnx_data = array(
		                                        'userid'=>$value->user_id,
		                                        'crypto_address' => $from_address,
		                                        'amount'=>(float)$amount,
		                                        'currency_symbol'=>$currency_det->currency_symbol,
												'currency_id'=>$value->currency_id,
												'type'=>'Admin to User Wallet',
		                                        'status'=>'Completed',
		                                        'date_created'=>date('Y-m-d H:i:s'),
		                                        'txn_id'=>$send_money_res
		                                    );
		                           $ins = $this->common_model->insertTableData('admin_wallet_logs',$tnx_data);
                               	}
                               }
                              
		                	}
		                }
		                 else
		                {
							
							// Coin deposit transfer from user wallet to admin wallet 
							$coin_transfer = '';
							if($crypto_type=='eth')
							{
							$GasLimit = 70000;
	                        $Gas_calc = $this->check_ethereum_functions('eth_gasPrice','Ethereum');
	                        echo "<br/>".$Gas_calc."<br/>";
	                        $Gwei = $Gas_calc;
	                        $GasPrice = $Gwei;
	                        $Gas_res = $Gas_calc / 1000000000;
	                        $Gas_txn = $Gas_res / 1000000000;
	                        $txn_fee = $GasLimit * $Gas_txn;
	                        echo "Transaction Fee".$txn_fee."<br/>";
	                        $amount_send = ($amount - $txn_fee)-0.0005;
	                        echo "Amount Send ".$amount_send."<br/>";

	                        echo "Total Amount ".($txn_fee+$amount_send)."<br/>";
	                        $amount1 = ($amount_send * 1000000000000000000);

	                        echo sprintf("%.40f", $amount1)."<br/>";
	                        $coin_transfer = "Ethereum";
							$privateKey = getetherPrivate($user_id);
	                        $cointrans_det = array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice,"privateKey"=>$privateKey);

	                       /* echo "<pre>";
	                        print_r($cointrans_det);*/
							}
							elseif($crypto_type=='bsc')
							{
							$GasLimit = 120000;
	                        //$Gas_calc = $this->check_ethereum_functions('eth_gasPrice','BNB');

	                        $Gas_calc = 30000000000;
			                echo "<br/>".$Gas_calc."<br/>";
	                        $Gwei = $Gas_calc;
	                        $GasPrice = $Gwei;
	                        $Gas_res = $Gas_calc / 1000000000;
	                        $Gas_txn = $Gas_res / 1000000000;
	                        $txn_fee = $GasLimit * $Gas_txn;
							echo "Transaction Fee".$txn_fee."<br/>";
	                        $amount_send = ($amount - $txn_fee);
							echo "Amount Send ".$amount_send."<br/>";
	                        $amount1 = ($amount_send * 1000000000000000000);
								                        
							echo sprintf("%.40f", $amount1)."<br/>";
	                        $coin_transfer = "BNB";
	                        $cointrans_det = array('from'=>$from_address,'to'=>$to,'value'=>(float)$amount1,'gas'=>(float)$GasLimit,'gasPrice'=>(float)$GasPrice);
							}
							else
							{
								$from_address = trim($from_address);
								$to = trim($to);	
				                $amount1 = $amount * 1000000;
				                $privateKey = gettronPrivate($user_id);
				                $coin_transfer = "Tron";
								$cointrans_det = array('fromAddress'=>$from_address,'toAddress'=>$to,'amount'=>(float)$amount1,"privateKey"=>$privateKey);
							}
		                	
		                    if($crypto_type=='eth' || $crypto_type=='bsc')
	                		{
	                			$txn_count = $this->get_pendingtransaction($from_address,$coin_transfer);
	                		}
	                		else
	                		{
	                			$txn_count = 0;
	                		}
	                		
                            echo "txn count";
                             echo "<br>";
                            echo $txn_count;
                            echo "<br>";
	                		if($txn_count==0)
	                		{
								echo $coin_transfer;
								echo "<br/>";
								print_r($cointrans_det);
								//exit;
                            $send_money_res_coin = $this->local_model->make_transfer($coin_transfer,$cointrans_det); // transfer to admin
							// echo "<pre>";
							// print_r($send_money_res_coin);
							// exit;

                            if($send_money_res_coin !="" || $send_money_res_coin !="" || $send_money_res_coin['error']=='')
                           	{
								$tnx_data = array(
									'userid'=>$value->user_id,
									'crypto_address' => $from_address,
									'amount'=>(float)$amount,
									'currency_symbol'=>$currency_det->currency_symbol,
									'currency_id'=>$value->currency_id,
									'status'=>'Completed',
									'type'=>'User to Admin Wallet',
									'date_created'=>date('Y-m-d H:i:s'),
									'txn_id'=>$send_money_res_coin
								);
								$ins = $this->common_model->insertTableData('admin_wallet_logs',$tnx_data);
                				$update = $this->common_model->updateTableData("transactions",array("admin_move"=>0,"trans_id"=>$trans_id),array("admin_move"=>1));
                		    }
	                			
	                			
	                			
	                		}
	                		

                          
                           
		                	
		                }
		       
                        /*if($send_money_res!="" || $send_money_res!="error")
                        {
		                    $trans_data = array(
		                                        'userid'=>$value->user_id,
		                                        'crypto_address' => $from_address,
		                                        'type'=>'deposit',
		                                        'amount'=>(float)$amount,
		                                        'currency_symbol'=>$currency_det->currency_symbol,
		                                        'status'=>'Completed',
		                                        'date_created'=>date('Y-m-d H:i:s'),
		                                        'currency_id'=>$currency_det->id,
		                                        'txn_id'=>$send_money_res
		                                    );
		                    $insert = $this->common_model->insertTableData('admin_wallet_logs',$trans_data);*/
		                  
		                    $result = array('status'=>'success','message'=>'update deposit success');
	                    //}
	                }
	                else
	                {
                      $result = array('status'=>'failed','message'=>'update deposit failed insufficient balance');
	                }

	            }
	            else
	            {
	                $result = array('status'=>'failed','message'=>'invalid address');	
	            }

	        $i++;}
	       }
	       else
	       	{
	       		$result = array("status"=>"failed","message"=>"transactions not found for admin wallet");
	       	}

	    }
	    echo json_encode($result);

	    }
	    
	}


  public function cancel_all_order()
  {
    $user_id=$this->session->userdata('user_id');
    if($user_id=="") {  
      front_redirect('login', 'refresh');
    }
    
    if(!empty($_POST['tradeIds'])) {
      $exp = explode(',', $_POST['tradeIds']);
      $where_in=array('CO.trade_id', $exp);
      $where=array('CO.userId'=>$user_id);
      $get_openorder = $this->common_model->getleftJoinedTableData('coin_order as CO','',$where,'','','','','','','','',$where_in);
      if($get_openorder->num_rows() >= 1) {
        $coinOrderInfo = $get_openorder->result();
        if(!empty($coinOrderInfo)) {
          foreach ($coinOrderInfo as $key => $order) {

            $pair_symbol = $order->pair_symbol;
            $expSym = explode('/', $pair_symbol);
            $type = $order->Type;
            if($type=='buy') {
              $currency_sym = $expSym[1];
            } else {
              $currency_sym = $expSym[0];
            }

            $coinInfo = getcoindetail($currency_sym);
            $userBal = getBalance($user_id, $coinInfo->id);

            $total = $userBal+$order->Total;
            $data_up['status']="cancelled";
            $query=$this->common_model->updateTableData('coin_order',array('trade_id'=>$order->trade_id),$data_up);
            if($query){
              updateBalance($user_id,$coinInfo->id,$total);  
            } 
            updateBalance($user_id,$coinInfo->id,$total); 
            // echo $total.'<br>';
          }


        }
        


        // echo "<pre>";print_r( $coinOrderInfo );

      } else {
        //$data['open_order'] = 0;
      }


    }  

  
  }

	function privacy(){
		$this->load->view('front/common/privacy');
	}

	function report(){
		$start_date = date("Y-m-d");
		$data['site_common'] = site_common();
		$user_id=$this->session->userdata('user_id');
		$start_date_ts = strtotime($start_date);
		$end_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . "-1 month" ) );
		$end_date_ts = strtotime($end_date);
		// print_r($end_date_ts);exit;
		$user_id=$this->session->userdata('user_id');
		if($user_id=="") {  
			front_redirect('login', 'refresh');
		}

		$data['staking_admin'] = $this->common_model->getTableData('staking_admin')->row();
		$data['staking_periods'] = $this->common_model->getTableData('staking_period')->result();

		$where=array('st.user_id'=>$user_id,'st.staking_start_date >='=>$end_date,'st.staking_start_date <='=>$start_date);
		$orderBy=array('st.id','asc');
		$joins = array('staking_period as sp'=>'sp.staking_period_id = st.staking_period_id');
		$data['staking_records'] = $this->common_model->getJoinedTableData('staking as st',$joins,$where,'','','','','','',$orderBy)->result();

		$data['leverage_records'] = $this->common_model->customQuery("SELECT lg.*, `cybw`.currency_symbol AS borrow_currency_symbol,`cycl`.`currency_symbol` AS collateral_currency_symbol,
			`cybw`.`image` AS borrow_currency_image,`cycl`.`image` AS collateral_currency_image
			FROM `elxisenergy_leverage` `lg`
			JOIN `elxisenergy_currency` cybw
			JOIN `elxisenergy_currency` cycl
			ON `lg`.`borrow_coin_id` = `cybw`.`id` AND `lg`.`collateral_coin_id` = `cycl`.`id` WHERE `lg`.`leverage_start_date`<= '$start_date' AND `lg`.`leverage_start_date`>= '$end_date' AND `lg`.`user_id` = '$user_id'")->result();

		$data['deposit_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id,'type'=>'Deposit','datetime >'=>$end_date_ts,'datetime <'=>$start_date_ts),'','','','','','',array('trans_id','DESC'))->result();
		
		$data['withdraw_history'] = $this->common_model->getTableData('transactions',array('user_id'=>$user_id,'type'=>'Withdraw','datetime >'=>$end_date_ts,'datetime <'=>$start_date_ts),'','','','','','',array('trans_id','DESC'))->result();
		
		$history_where = "WHERE CO.userId = '$user_id' AND CO.datetime <= '$start_date' AND CO.datetime >= '$end_date'";
		$data['spot_trading'] = $this->common_model->customQuery("SELECT CO.*,SUM(CO.Amount) as TotAmount,date_format(CO.datetime,'%d-%m-%Y %H:%i') as trade_time,sum(OT.filledAmount) as totalamount FROM elxisenergy_coin_order CO LEFT JOIN elxisenergy_ordertemp OT on CO.trade_id = OT.sellorderId OR CO.trade_id = OT.buyorderId $history_where GROUP BY CO.trade_id")->result();


		$groupby = array('buyback_id');
		$buybacks = $this->common_model->getTableData('buyback',array('status'=>1,'user_id'=>$user_id),'','','','','','',array('id','DESC'),$groupby)->result();

		$cancelgroupby = array('parent_id,user_id,user_status');

		$cancels = $this->common_model->getTableData('buyback',array('status'=>0,'user_id'=>$user_id),'','','','','','',array('id','DESC'),$groupby)->result();

		$data['buybacks'] = array_merge($buybacks,$cancels);

		// echo "<pre>";
		// print_r($data['buybacks']);
		// echo "<pre>"; 
		// exit();

		$data['currencies'] = $this->common_model->getTableData('currency',array('status'=>1))->result();

		
		$this->load->view('front/user/report',$data);
	}

	function reportfilter(){
		$user_id=$this->session->userdata('user_id');
		
        if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$currency_id = $this->input->post('curr_id');
		$status = $this->input->post('deposit_status');
		$from = $this->input->post('from');
		$month = $this->input->post('month');

		$start_date = date("Y-m-d");
		$start_date_ts = strtotime($start_date);
		$end_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . " -".$month." month" ) );
		$end_date_ts = strtotime($end_date);


		if($from == "exchange"){
			if($status == '0'){
				$history_where = "WHERE CO.userId = '$user_id' AND CO.datetime <= '$start_date' AND CO.datetime >= '$end_date'";
				// $history_where = "WHERE userId = '$user_id'";
				$query_string = "SELECT CO.*,SUM(CO.Amount) as TotAmount,date_format(CO.datetime,'%d-%m-%Y %H:%i') 
				as trade_time,sum(OT.filledAmount) as totalamount FROM elxisenergy_coin_order CO 
				LEFT JOIN elxisenergy_ordertemp OT on CO.trade_id = OT.sellorderId OR 
				CO.trade_id = OT.buyorderId $history_where GROUP BY CO.trade_id";
			}else{
				$history_where = "WHERE CO.userId = '$user_id' AND CO.datetime <= '$start_date' AND CO.datetime >= '$end_date' AND `CO`.`status`='$status'";
				// $history_where = "WHERE userId = '$user_id' AND `CO`.`status`='$status'";
				$query_string = "SELECT CO.*,SUM(CO.Amount) as TotAmount,date_format(CO.datetime,'%d-%m-%Y %H:%i') 
				as trade_time,sum(OT.filledAmount) as totalamount FROM elxisenergy_coin_order CO 
				LEFT JOIN elxisenergy_ordertemp OT on CO.trade_id = OT.sellorderId OR 
				CO.trade_id = OT.buyorderId $history_where  GROUP BY CO.trade_id";

			}
		}else if($from == "staking"){
			$where=array('st.user_id'=>$user_id,'st.staking_start_date >='=>$end_date,'st.staking_start_date <='=>$start_date);
			$orderBy=array('st.id','asc');
			$joins = array('staking_period as sp'=>'sp.staking_period_id = st.staking_period_id');
			$data['deposit_history'] = $this->common_model->getJoinedTableData('staking as st',$joins,$where,'','','','','','',$orderBy)->result();
			echo json_encode($data['deposit_history']);exit;
		}else if($from == "leverage"){
			$data['leverage_records'] = $this->common_model->customQuery("SELECT lg.*, `cybw`.currency_symbol AS borrow_currency_symbol,`cycl`.`currency_symbol` AS collateral_currency_symbol,
			`cybw`.`image` AS borrow_currency_image,`cycl`.`image` AS collateral_currency_image
			FROM `elxisenergy_leverage` `lg`
			JOIN `elxisenergy_currency` cybw
			JOIN `elxisenergy_currency` cycl
			ON `lg`.`borrow_coin_id` = `cybw`.`id` AND `lg`.`collateral_coin_id` = `cycl`.`id` WHERE `lg`.`leverage_start_date`<= '$start_date' AND `lg`.`leverage_start_date` >= '$end_date' AND `lg`.`user_id` = '$user_id'")->result();
			echo json_encode($data['leverage_records']);exit;
		}else{
			if($currency_id == 0){
				if($status == 0){
					$query_string = "SELECT tnscn.*, `cy`.currency_symbol AS currency_symbol,`cy`.`id` AS currency_id,`cy`.`online_usdprice` AS online_usdprice,
					`cy`.`image` AS currency_image
					FROM `elxisenergy_transactions` `tnscn`
					JOIN `elxisenergy_currency` cy
					ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='$from' AND `tnscn`.`user_id`='$user_id' AND `tnscn`.`datetime` <= '$start_date_ts' AND `tnscn`.`datetime` >= '$end_date_ts' ORDER BY tnscn.trans_id DESC";
					
				}else{
					$query_string = "SELECT tnscn.*, `cy`.currency_symbol AS currency_symbol,`cy`.`id` AS currency_id,`cy`.`online_usdprice` AS online_usdprice,
						`cy`.`image` AS currency_image
						FROM `elxisenergy_transactions` `tnscn`
						JOIN `elxisenergy_currency` cy
						ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='$from' AND `tnscn`.`user_id`='$user_id' AND `tnscn`.`status`='$status' AND `tnscn`.`datetime` <= '$start_date_ts' AND `tnscn`.`datetime` >= '$end_date_ts' ORDER BY tnscn.trans_id DESC";
				}
			}else{
				if($status == 0){
					$query_string = "SELECT tnscn.*, `cy`.currency_symbol AS currency_symbol,`cy`.`id` AS currency_id,`cy`.`online_usdprice` AS online_usdprice,
					`cy`.`image` AS currency_image
					FROM `elxisenergy_transactions` `tnscn`
					JOIN `elxisenergy_currency` cy
					ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='$from' AND `tnscn`.`user_id`='$user_id'  AND `tnscn`.`currency_id`='$currency_id' AND `tnscn`.`datetime` <= '$start_date_ts' AND `tnscn`.`datetime` >= '$end_date_ts' ORDER BY tnscn.trans_id DESC";
				}else{
					$query_string = "SELECT tnscn.*, `cy`.currency_symbol AS currency_symbol,`cy`.`id` AS currency_id,`cy`.`online_usdprice` AS online_usdprice,
						`cy`.`image` AS currency_image
						FROM `elxisenergy_transactions` `tnscn`
						JOIN `elxisenergy_currency` cy
						ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='$from' AND `tnscn`.`user_id`='$user_id'  AND `tnscn`.`currency_id`='$currency_id' AND `tnscn`.`status`='$status' AND `tnscn`.`datetime` <= '$start_date_ts' AND `tnscn`.`datetime` >= '$end_date_ts' ORDER BY tnscn.trans_id DESC";
				}
			}
		}
		
		$data['deposit_history'] = $this->common_model->customQuery($query_string)->result();
		echo json_encode($data['deposit_history']);exit;
	}

	function funds_withdraw(){
        $user_id=$this->session->userdata('user_id');
        $amt = array();
        $sum = 0;
        $sum_usd = 0;
		$currency_id = $this->input->post('currency_id');
		$type = $this->input->post('type');
		$duration = $this->input->post('duration');
		$date = date('Y-m-d 23:59:59');
		$newdate = date('Y-m-d 00:00:00', strtotime('-'.$duration.' months', strtotime($date))); 
		$currencies = $this->common_model->getTableData('currency',array('id' =>$currency_id ))->row();
		$currency_symbol = $currencies->currency_symbol;
		$where = array('transactions.type'=>$type,'transactions.currency_id' => $currency_id);
		// $withdrawals = $this->common_model->getTableData('transactions',$where,'','','','','','','')->result();
		
		$usd = $this->common_model->customQuery("SELECT online_usdprice FROM elxisenergy_currency WHERE currency_symbol = '$currency_symbol' ")->row();
		$withdrawals = $this->db->query("select * from elxisenergy_transactions where elxisenergy_transactions.currency_id = '$currency_id' AND elxisenergy_transactions.user_id = '$user_id' AND elxisenergy_transactions.type ='$type' AND elxisenergy_transactions.datetime BETWEEN '$newdate' AND '$date'")->result(); 
		
		foreach($withdrawals as $withdraw){
			array_push($amt, (float)$withdraw->amount);
			
		}
		$sum += round(array_sum($amt), 2);
		$sum_usd += number_format(($sum*$usd->online_usdprice),2);
		// echo $sum_usd;
		// die;
		$response = array(
           'status' => 'success',
            'sum' => $sum,
            'currency_symbol' => $currency_symbol,
            'duration' => $duration,
            'sum_usd' => $sum_usd
        ); 
		echo json_encode($response);exit;
	}


	function account_overview(){
        $user_id=$this->session->userdata('user_id');
        $amt = array();
        $sum = 0;
		$currency_id = $this->input->post('currency_id');
		// $type = $this->input->post('type');
		$duration = $this->input->post('duration');
		
		$date = date('Y-m-d');
		$newdate = date('Y-m-d', strtotime('-'.$duration.' months', strtotime($date))); 
		$currencies = $this->common_model->getTableData('currency',array('id' =>$currency_id ))->row();
		$currency_symbol = $currencies->currency_symbol;
		$dig_currency_sell = $this->common_model->customQuery("SELECT SUM(Price) as total_price,COUNT(Price) as total_count, Type as type  FROM elxisenergy_coin_order WHERE userId = '$user_id' AND status = 'filled' AND Type = 'sell' AND pair_symbol like '%BTC%' AND orderDate>now() - interval 6 month")->row();
		$dig_currency_buy = $this->common_model->customQuery("SELECT SUM(Price) as total_price,COUNT(Price) as total_count, Type as type  FROM elxisenergy_coin_order WHERE userId = '$user_id' AND status = 'filled' AND Type = 'buy' AND pair_symbol like '%BTC%' AND orderDate BETWEEN '$newdate' AND '$date'")->row();
		$dig_currency_sell_chart = $this->common_model->customQuery("SELECT SUM(Price) as total_price,COUNT(Price) as total_count, Type as type  FROM elxisenergy_coin_order WHERE userId = '$user_id' AND status = 'filled' AND Type = 'sell' AND pair_symbol like '%$currency_symbol%' AND orderDate>now() - interval '$duration' month")->row();

		$dig_currency_buy_chart = $this->common_model->customQuery("SELECT SUM(Price) as total_price,COUNT(Price) as total_count, Type as type  FROM elxisenergy_coin_order WHERE userId = '$user_id' AND status = 'filled' AND Type = 'buy' AND pair_symbol like '%$currency_symbol%' AND orderDate>now() - interval $duration month")->row();
		$usd1 = $this->common_model->customQuery("SELECT online_usdprice FROM elxisenergy_currency WHERE currency_symbol = '$currency_symbol' ")->row();
		$chart_data = array();
 
  
   
           $type_sell = $dig_currency_sell->type;
           $total_sell = "$".$dig_currency_sell->total_price* $usd1->online_usdprice."(BTC)";                                      
                                                     
    
  
  array_push($chart_data, array('type' => $type_sell,'total' => $total_sell));


 
 
           $type_buy = "buy";
           if($dig_currency_buy->total_price == ""){
             $total_buy = "$"."0.00"* $usd1->online_usdprice."(BTC)";
           }else{
            $total_buy = "$".$dig_currency_buy->total_price* $usd1->online_usdprice."(BTC)";
           }
                                                 
                                                     
    
  
  array_push($chart_data, array('type' => $type_buy,'total' => $total_buy));

  
		$response = array(
           'status' => 'success',
            'sum' => $sum,
            'currency_symbol' => $currency_symbol,
            'duration' => $duration,
            'chart_data' => json_encode($chart_data)
        ); 
		echo json_encode($response);exit;
	}

	function lex_reports(){
        $user_id=$this->session->userdata('user_id');
        $amt = array();
        $sum = 0;
		$currency_id = $this->input->post('currency_id');
		// $type = $this->input->post('type');
		$duration = $this->input->post('duration');

		$currencies = $this->common_model->getTableData('currency',array('id' =>$currency_id ))->row();
		$date = date('Y-m-d 23:59:59');
		$newdate = date('Y-m-d 00:00:00', strtotime('-'.$duration.' months', strtotime($date))); 
		$currency_symbol = $currencies->currency_symbol;
		$where = array('elxisenergy_token_request.currency_id' => $currency_id,'elxisenergy_token_request.status' => 'Completed');
		// $withdrawals = $this->common_model->getTableData('transactions',$where,'','','','','','','')->result();
		$withdrawals = $this->db->query("select * from elxisenergy_token_request where elxisenergy_token_request.currency_id = '$currency_id' AND elxisenergy_token_request.status ='Completed' AND elxisenergy_token_request.date_added BETWEEN '$newdate' AND '$date'")->result(); 
		
		foreach($withdrawals as $withdraw){
			array_push($amt, (float)$withdraw->total_amount);
		}
		$sum += round(array_sum($amt), 2);
		$response = array(
           'status' => 'success',
            'sum' => $sum,
            'currency_symbol' => $currency_symbol,
            'duration' => $duration
        ); 
		echo json_encode($response);exit;
	}

	function fiat_crypto(){
        $user_id=$this->session->userdata('user_id');
        $amt = array();
        $sum = 0;
		
		$duration = $this->input->post('duration');
      $chart_data_1 = array();
		$dig_currency = $this->common_model->customQuery("SELECT SUM(total_amount) as total_price,COUNT(total_amount) as total_count, mode as mode  FROM elxisenergy_token_request WHERE user_id = '$user_id' AND mode = 'online'  AND date_added>now() - interval '$duration' month")->row();
       
       if($dig_currency->total_price == ""){
       	 $total_fiat = "0.00";
       }else{
       	$total_fiat = $dig_currency->total_price;
       }
       array_push($chart_data_1, array('coin' => 'fiat','volume' => $total_fiat));

        $dig_currency_crypto = $this->common_model->customQuery("SELECT SUM(total_amount) as total_price,COUNT(total_amount) as total_count, mode as mode  FROM elxisenergy_token_request WHERE user_id = '$user_id' AND mode = 'crypto'  AND date_added>now() - interval '$duration' month")->row();

        if($dig_currency_crypto->total_price == ""){
       	 $total_crypto = "0.00";
        }else{
       	$total_crypto = $dig_currency_crypto->total_price;
        }
         
		
        array_push($chart_data_1, array('coin' => 'crypto','volume' => $total_crypto));
       
		$response = array(
           'status' => 'success',
            'chart_data_1' => $chart_data_1
           
        ); 
		echo json_encode($response);exit;
	}

	function portfolio_assets(){
		$user_id=$this->session->userdata('user_id');
		$duration = $this->input->post('id');
        
		$time = explode("_", $duration);
		$dur = $time[0];
        $amount_arr = array();
        $curr_date = date('Y-m-d');
        if($duration == ""){
        	$results = $this->common_model->customQuery("SELECT *  FROM elxisenergy_wallet_logs WHERE user_id = '$user_id' AND created_at like '%$curr_date%'")->result();

           foreach($results as $result){
					$converted_time = date('h:i:s a', strtotime($result->created_at));
					$hr = date('ha',strtotime($converted_time));
					
					$amount_arr[$hr] = floatval($result->balance_usd);
					
				}
        }else{
            if($time[1] == "day"){
			if($time[0] == "1"){
				$curr_date = date('Y-m-d');
				$results = $this->common_model->customQuery("SELECT *  FROM elxisenergy_wallet_logs WHERE user_id = '$user_id' AND created_at like '%$curr_date%'")->result();
				foreach($results as $result){
					$converted_time = date('h:i:s a', strtotime($result->created_at));
					$hr = date('ha',strtotime($converted_time));
					
					$amount_arr[$hr] = floatval($result->balance_usd);
					
				}
				
			}elseif($time[0] == "7"){
				$results = $this->common_model->customQuery("SELECT *,MAX(balance_usd) as total  FROM elxisenergy_wallet_logs WHERE user_id = '$user_id' AND created_at>now() - interval '$dur' day GROUP BY DATE(created_at)")->result();
				foreach($results as $result){
					$date = date('Y-m-d',strtotime($result->created_at));
					$amount_arr[$date] = floatval($result->total);

				}
			}
		}elseif($time[1] == "month"){
           if($time[0] == "1"){
           	 $results = $this->common_model->customQuery("SELECT *,MAX(balance_usd) as total  FROM elxisenergy_wallet_logs WHERE user_id = '$user_id' AND created_at>now() - interval '$dur' month GROUP BY DATE(created_at)")->result();
           	 foreach($results as $result){
					$date = date('Y-m-d',strtotime($result->created_at));
					$amount_arr[$date] = floatval($result->total);

				}
				
           }elseif($time[0] == "3"){
           	$results = $this->common_model->customQuery("SELECT *,MAX(balance_usd) as total  FROM elxisenergy_wallet_logs WHERE user_id = '$user_id' AND created_at>now() - interval '$dur' month GROUP BY DATE(created_at)")->result();
           	 foreach($results as $result){
					$date = date('Y-m-d',strtotime($result->created_at));
					$amount_arr[$date] = floatval($result->total);

				}

           }
		}elseif($time[1] == "year"){
           if($time[0] == "1"){
           	$results = $this->common_model->customQuery("SELECT *,MAX(balance_usd) as total  FROM elxisenergy_wallet_logs WHERE user_id = '$user_id' AND created_at>now() - interval '$dur' year GROUP BY MONTH(created_at)")->result();
           	foreach($results as $result){
					$date = date('M',strtotime($result->created_at));
					$amount_arr[$date] = floatval($result->total);


				}

           }
		}elseif($time[1] == "together"){
			
			 $results = $this->common_model->customQuery("SELECT *,MAX(balance_usd) as total  FROM elxisenergy_wallet_logs WHERE user_id = '$user_id' GROUP BY YEAR(created_at)")->result();
           	foreach($results as $result){
					$date = date('Y',strtotime($result->created_at));
					$amount_arr[$date] = floatval($result->total);


				}
				
		}elseif($duration == "all"){
			 $results = $this->common_model->customQuery("SELECT *,MAX(balance_usd) as total  FROM elxisenergy_wallet_logs WHERE user_id = '$user_id' GROUP BY YEAR(created_at)")->result();
           	foreach($results as $result){
					$date = date('Y',strtotime($result->created_at));
					$amount_arr[$date] = floatval($result->total);


				}
		}
        }
		
        
		$response = array(
           'status' => 'success',
            'amount_arr' => $amount_arr,
            'duration' => $duration
           
        ); 
       echo json_encode($response);exit;
	}

	function exportToExcel(){

		$user_id=$this->session->userdata('user_id');
		
        if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}

		$currency_id = $this->input->post('curr_id');
		$status = $this->input->post('status');
		$from = $this->input->post('from');
		$extension = $this->input->post('extension');
		$month = $this->input->post('month');

		$start_date = date("Y-m-d");
		$start_date_ts = strtotime($start_date);
		$end_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . " -".$month." month" ) );
		$end_date_ts = strtotime($end_date);
		
		if($from == "exchange"){
			if($status == 0){
				$history_where = "WHERE CO.userId = '$user_id' AND CO.datetime < '$start_date' AND CO.datetime > '$end_date'";
				$query_string = "SELECT CO.*,SUM(CO.Amount) as TotAmount,date_format(CO.datetime,'%d-%m-%Y %H:%i') 
				as trade_time,sum(OT.filledAmount) as totalamount FROM elxisenergy_coin_order CO 
				LEFT JOIN elxisenergy_ordertemp OT on CO.trade_id = OT.sellorderId OR 
				CO.trade_id = OT.buyorderId $history_where GROUP BY CO.trade_id";
			}else{
				$history_where = "WHERE CO.userId = '$user_id' AND CO.datetime < '$start_date' AND CO.datetime > '$end_date' AND `CO`.`status`='$status'";
				// $history_where = "WHERE userId = '$user_id' AND `CO`.`status`='$status'";
				$query_string = "SELECT CO.*,SUM(CO.Amount) as TotAmount,date_format(CO.datetime,'%d-%m-%Y %H:%i') 
				as trade_time,sum(OT.filledAmount) as totalamount FROM elxisenergy_coin_order CO 
				LEFT JOIN elxisenergy_ordertemp OT on CO.trade_id = OT.sellorderId OR 
				CO.trade_id = OT.buyorderId $history_where  GROUP BY CO.trade_id";
			}
			$getdata = $this->common_model->customQuery($query_string)->result();
		}else if($from == "staking"){
			$where=array('st.user_id'=>$user_id,'st.staking_start_date >'=>$end_date,'st.staking_start_date <'=>$start_date);
			$orderBy=array('st.id','asc');
			$joins = array('staking_period as sp'=>'sp.staking_period_id = st.staking_period_id');
			$getdata = $this->common_model->getJoinedTableData('staking as st',$joins,$where,'','','','','','',$orderBy)->result();
			
		}else if($from == "leverage"){
			$getdata  = $this->common_model->customQuery("SELECT lg.*, `cybw`.currency_symbol AS borrow_currency_symbol,`cycl`.`currency_symbol` AS collateral_currency_symbol,
			`cybw`.`image` AS borrow_currency_image,`cycl`.`image` AS collateral_currency_image
			FROM `elxisenergy_leverage` `lg`
			JOIN `elxisenergy_currency` cybw
			JOIN `elxisenergy_currency` cycl
			ON `lg`.`borrow_coin_id` = `cybw`.`id` AND `lg`.`collateral_coin_id` = `cycl`.`id` WHERE `lg`.`leverage_start_date`< '$start_date' AND `lg`.`leverage_start_date`> '$end_date'")->result();
		}else {
			if($currency_id == 0){
				if($status == 0){
					$query_string = "SELECT tnscn.*, `cy`.currency_symbol AS currency_symbol,
					`cy`.`image` AS currency_image
					FROM `elxisenergy_transactions` `tnscn`
					JOIN `elxisenergy_currency` cy
					ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='$from' AND `tnscn`.`user_id`='$user_id' AND `tnscn`.`datetime` < '$start_date_ts' AND `tnscn`.`datetime` > '$end_date_ts' ORDER BY tnscn.trans_id DESC";
					
				}else{
					$query_string = "SELECT tnscn.*, `cy`.currency_symbol AS currency_symbol,
						`cy`.`image` AS currency_image
						FROM `elxisenergy_transactions` `tnscn`
						JOIN `elxisenergy_currency` cy
						ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='$from' AND `tnscn`.`user_id`='$user_id' AND `tnscn`.`status`='$status' AND `tnscn`.`datetime` < '$start_date_ts' AND `tnscn`.`datetime` > '$end_date_ts' ORDER BY tnscn.trans_id DESC";
				}
			}else{
				if($status == 0){
					$query_string = "SELECT tnscn.*, `cy`.currency_symbol AS currency_symbol,
					`cy`.`image` AS currency_image
					FROM `elxisenergy_transactions` `tnscn`
					JOIN `elxisenergy_currency` cy
					ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='$from' AND `tnscn`.`user_id`='$user_id'  AND `tnscn`.`currency_id`='$currency_id' AND `tnscn`.`datetime` < '$start_date_ts' AND `tnscn`.`datetime` > '$end_date_ts' ORDER BY tnscn.trans_id DESC";
				}else{
					$query_string = "SELECT tnscn.*, `cy`.currency_symbol AS currency_symbol,
						`cy`.`image` AS currency_image
						FROM `elxisenergy_transactions` `tnscn`
						JOIN `elxisenergy_currency` cy
						ON `tnscn`.`currency_id` = `cy`.`id` WHERE `tnscn`.`type`='$from' AND `tnscn`.`user_id`='$user_id'  AND `tnscn`.`currency_id`='$currency_id' AND `tnscn`.`status`='$status' AND `tnscn`.`datetime` < '$start_date_ts' AND `tnscn`.`datetime` > '$end_date_ts' ORDER BY tnscn.trans_id DESC";
				}
			}
			$getdata = $this->common_model->customQuery($query_string)->result();
		}
		
		error_reporting(E_ALL);
		require_once('vendor/phpoffice/phpspreadsheet/autoloader_psr.php');
require_once('vendor/phpoffice/phpspreadsheet/autoloader.php');
		// Create new Spreadsheet object
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
	  // Set document properties
		$spreadsheet->getProperties()->setCreator('miraimedia.co.th')
			->setLastModifiedBy('Cholcool')
			->setTitle('how to export data to excel use phpspreadsheet in codeigniter')
			->setSubject('Generate Excel use PhpSpreadsheet in CodeIgniter')
			->setDescription('Export data to Excel Work for me!');
			
	  // add style to the header
		$styleArray = array(
			'font' => array(
			  'bold' => true,
			),
			'alignment' => array(
			  'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			  'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			),
			'borders' => array(
				'top' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
					'color' => array('rgb' => '333333'),
				),
				'bottom' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
					'color' => array('rgb' => '333333'),
				),
				'left' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
					'color' => array('rgb' => '333333'),
				),
				'right' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
					'color' => array('rgb' => '333333'),
				),
			),
			'fill' => array(
				'type'       => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
				'rotation'   => 90,
				'startcolor' => array('rgb' => '0d0d0d'),
				'endColor'   => array('rgb' => 'f2f2f2'),
			),
		);
		
		  // auto fit column to content
		foreach(range('A', 'E') as $columnID) {
			$spreadsheet->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
			// $spreadsheet->getActiveSheet()->getStyle($columnID)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
			// $spreadsheet->getActiveSheet()->getStyle($columnID)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
		}
	  	// set the names of header cells
		if($from == "exchange"){
			$spreadsheet->getActiveSheet()->getStyle('A1:E1')->applyFromArray($styleArray);
			$sheet->setCellValue('A1', 'Pairs');
			$sheet->setCellValue('B1', 'Time');
			$sheet->setCellValue('C1', 'Volume');
			$sheet->setCellValue('D1', 'Price');
			$sheet->setCellValue('E1', 'Status');
			// Add some data
			$x = 2;
			foreach($getdata as $get){
				$sheet->setCellValue('A'.$x, $get->pair_symbol);
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('B'.$x, $get->datetime);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('C'.$x, $get->Amount);
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('D'.$x, $get->Price);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('E'.$x, $get->status);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$x++;
			}
		}else if($from == "staking"){
			$spreadsheet->getActiveSheet()->getStyle('A1:F1')->applyFromArray($styleArray);
			$sheet->setCellValue('A1', 'Volume');
			$sheet->setCellValue('B1', 'Stake Date');
			$sheet->setCellValue('C1', 'Interest End Date');
			$sheet->setCellValue('D1', 'Redemption Date');
			$sheet->setCellValue('E1', 'Est.Apy');
			$sheet->setCellValue('F1', 'Status');
			// Add some data
			$x = 2;
			foreach($getdata as $get){
				$sheet->setCellValue('A'.$x, $get->staking_amount." LEX");
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('B'.$x, $get->staking_start_date);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('C'.$x, $get->staking_end_date);
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('D'.$x, $get->redemption_date);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('E'.$x, $get->apy);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				if($get->is_redeemed == '1')
                {
                    $status = "Redeemed";
                } else {
                    $status = "Not Redeemed";
                }
				$sheet->setCellValue('F'.$x, $status);
				$spreadsheet->getActiveSheet()->getStyle('F'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('F'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$x++;
			}
		}else if($from == "leverage"){
			$spreadsheet->getActiveSheet()->getStyle('A1:H1')->applyFromArray($styleArray);
			$sheet->setCellValue('A1', 'Borrow / Collateral');
			$sheet->setCellValue('B1', 'Range');
			$sheet->setCellValue('C1', 'Liqudation Price (DYDX/USDC)');
			$sheet->setCellValue('D1', 'Daily Interest Rate');
			$sheet->setCellValue('E1', 'Total Interest Amount');
			$sheet->setCellValue('F1', 'Repayment Amount');
			$sheet->setCellValue('G1', 'LTV');
			$sheet->setCellValue('H1', 'Status');
			// Add some data
			$x = 2;
			foreach($getdata as $get){
				$sheet->setCellValue('A'.$x, $get->borrow_amount." ".$get->borrow_currency_symbol." / ".$get->collateral_amount." ".$get->collateral_currency_symbol);
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('B'.$x, $get->leverage_start_date."\n".$get->leverage_end_date);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('C'.$x, $get->liqudation_price);
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('D'.$x, $get->daily_interest_rate);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('E'.$x, $get->total_interest_amount." ".$get->borrow_currency_symbol);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('F'.$x, $get->repayment_amount." ".$get->borrow_currency_symbol);
				$spreadsheet->getActiveSheet()->getStyle('F'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('F'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('G'.$x, $get->ltv);
				$spreadsheet->getActiveSheet()->getStyle('G'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('G'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
                if($get->is_repaid == '1')
                {
                    $status =  "Repaid";
                } else {
                    $status =  "Not Repaid";
                }
				$sheet->setCellValue('H'.$x, $status);
				$spreadsheet->getActiveSheet()->getStyle('H'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('H'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$x++;
			}
		}else{
			$spreadsheet->getActiveSheet()->getStyle('A1:F1')->applyFromArray($styleArray);
			$sheet->setCellValue('A1', 'Assets');
			$sheet->setCellValue('B1', 'Time');
			$sheet->setCellValue('C1', 'Volume');
			$sheet->setCellValue('D1', 'Price');
			$sheet->setCellValue('E1', 'Transaction Id');
			$sheet->setCellValue('F1', 'Status');
			
			$x = 2;
			foreach($getdata as $get){
				$sheet->setCellValue('A'.$x, strtoupper(getcryptocurrency($get->currency_id)));
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('A'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('B'.$x, date('d-M-Y H:i',$get->datetime));
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('B'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('C'.$x, $get->amount." ".strtoupper(getcryptocurrency($get->currency_id)));
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('C'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$usd_currency_value =getUsdValue($get->currency_id)->online_usdprice;
				$usd_amount = abs(($get->amount) * ($usd_currency_value));
				$sheet->setCellValue('D'.$x, $usd_amount);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('D'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('E'.$x, $get->transaction_id." ".$get->borrow_currency_symbol);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('E'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$sheet->setCellValue('F'.$x, $get->status." ".$get->borrow_currency_symbol);
				$spreadsheet->getActiveSheet()->getStyle('F'.$x)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
				$spreadsheet->getActiveSheet()->getStyle('F'.$x)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
				$x++;
			}
		}
	  	//Create file excel.xlsx
		$fileName = 'report';
		if($extension == 'csv'){          
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
			$fileName = $fileName.'.csv';
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		} elseif($extension == 'xlsx') {
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
			$fileName = $fileName.'.xls';
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		}elseif($extension == 'pdf') {

			

			$this->load->library('pdf');
        $html = $this->load->view('front/user/generatePdf', [], true);
        $this->pdf->createPDF($html, 'mypdf', false);
			// // $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf($spreadsheet);
			// $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
			// // echo "<pre>";
			// // print_r($writer);
			// // exit;
			// $fileName = $fileName.'.pdf';
			// header('Content-Type: application/pdf');
			// header('Content-Disposition: attachment;filename="report.pdf"');
			// header('Cache-Control: max-age=0');
			// $writer->save('php://output');
			// exit;


// 			\PhpOffice\PhpSpreadsheet\IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);

// // Redirect output to a client’s web browser (PDF)

// header('Content-Type: application/pdf');
// header('Content-Disposition: attachment;filename="01simple.pdf"');
// header('Cache-Control: max-age=0');

// $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');
// // echo "<pre>";
// // print_r($writer);
// // exit;
// $writer->save("01simple.pdf");
// exit;

			// $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Mpdf');
			// header('Content-Type: application/pdf');
			// header('Content-Disposition: attachment;filename="01simple.pdf"');
			// header('Cache-Control: max-age=0');
			// $writer->save('php://output');
      //$writer->save("demo.pdf");
			exit;
		} else {
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
			$fileName = $fileName.'.xls';
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		}

        header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');
        $writer->save('php://output');

		// $this->output->set_header('Content-Type: application/vnd.ms-excel');
		// $this->output->set_header("Content-type: application/csv");
		// $this->output->set_header('Cache-Control: max-age=0');

		// $writer->save($fileName);
		// $filepath = base_url().$fileName;
		
		// $response =  array(
		// 	'status' => $from,
		// 	'file' => $filepath
		// );

		// die(json_encode($response));

	}

	function lex_calculation()
	{
		$user_id=$this->session->userdata('user_id');
		
        if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$from = $this->input->post('from');
		$curr_id = $this->input->post('curr_id');
		$curr_symbol = $this->input->post('curr_symbol');
		$fiat_currency = $this->input->post('fiat_currency');
		$purchase_curr_amount = $this->input->post('purchase_curr_amount');

		$w_isValids   = $this->common_model->getTableData('token_request', array('user_id' => $user_id, 'status'=>'Pending','currency_id'=>$curr_id));
		$count        = $w_isValids->num_rows();

		if($from == 'purchase_lex' || $from == 'purchase_crypto')
		{
			if($from == 'purchase_lex')
			{
				if($fiat_currency == 'USD')
				{
					$curr_usd = $this->common_model->getTableData("currency",array("id"=>$curr_id))->row('online_usdprice');
					$lex_to_usd = 0.016219;
				} else {
					$curr_usd = $this->common_model->getTableData("currency",array("id"=>$curr_id))->row('online_eurprice');
					$lex_to_usd = 0.015;
				}
				$lex_amount = $purchase_curr_amount;

				$converted_purchase_value = $lex_amount * $lex_to_usd;
				$crypto_usd = json_decode(convercurr($fiat_currency,$curr_symbol),true);
				$crypto_usd = number_format($crypto_usd[$curr_symbol],8);

				$final_crypto_value = $converted_purchase_value * $crypto_usd;

				$balance = getBalance($user_id,$curr_id);
				$admin_address = getadminAddress(1,$curr_symbol);
				$response = array(
					"lex"=>$purchase_curr_amount,
					"lex_usd"=>number_format($converted_purchase_value,2),
					"crypto_value"=>number_format($final_crypto_value,8),
					"balance"=>$balance,
					"curr_usd"=>number_format($curr_usd,2),
					"lex_to_usd"=>$lex_to_usd,
					"admin_address"=>$admin_address,
					"pending_count"=>$count
				);
			} else {
				if($fiat_currency == 'USD')
				{
					$curr_usd = $this->common_model->getTableData("currency",array("id"=>$curr_id))->row('online_usdprice');
					$lex_to_usd = 0.016219;
				} else {
					$curr_usd = $this->common_model->getTableData("currency",array("id"=>$curr_id))->row('online_eurprice');
					$lex_to_usd = 0.015;
				}
				$crypto_amount = $purchase_curr_amount;

				$crypto_usd = json_decode(convercurr($curr_symbol,$fiat_currency),true);
				$crypto_usd = number_format($crypto_usd[$fiat_currency],8);

				$converted_purchase_value = $crypto_amount * $curr_usd;

				$final_crypto_value = $converted_purchase_value/$lex_to_usd;

				$balance = getBalance($user_id,$curr_id);
				$admin_address = getadminAddress(1,$curr_symbol);
				$response = array(
					"lex"=>$final_crypto_value,
					"lex_usd"=>number_format($converted_purchase_value,2),
					"crypto_value"=>$purchase_curr_amount,
					"balance"=>$balance,
					"curr_usd"=>number_format($curr_usd,2),
					"lex_to_usd"=>$lex_to_usd,
					"admin_address"=>$admin_address,
					"pending_count"=>$count
				);
			}
		} 
		else if($from == 'buy_lex' || $from == 'buy_crypto')
		{
			if($from == 'buy_lex')
			{
				//$curr_usd = $this->common_model->getTableData("currency",array("id"=>$curr_id))->row('online_usdprice');
				$purchase_cost_currency = $this->input->post('purchase_cost_currency');
				$lex_balance_before = getBalance($user_id,8);
				$lex_amount = $purchase_curr_amount;
				$lex_balance_after = $lex_balance_before + $lex_amount;
				
				if($fiat_currency == 'USD')
				{
					$lex_to_usd = 0.016219;
					$lex_conversion = $lex_to_usd;
				}
				else {
					$lex_to_usd = 0.015;
					$lex_conversion = $lex_to_usd;
				}

				if($curr_symbol == 'USD')
				{
					$crypto_to_usd = 0.016219;
				}
				else {
					$crypto_to_usd = 0.015;
				}

				$final_crypto_value = $lex_amount*$crypto_to_usd;

				$balance = getBalance($user_id,$curr_id);
				$revised_balance = $balance - $final_crypto_value;
				$response = array(
					"lex"=>$purchase_curr_amount,
					"lex_balance_before"=>$lex_balance_before,
					"lex_balance_after"=>number_format($lex_balance_after,8),
					"lex_balance_after_usd"=>number_format(($lex_balance_after*$lex_to_usd),2),
					"lex_usd"=>number_format($converted_purchase_value,2),
					"crypto_value"=>number_format(($lex_amount*$lex_conversion),2),
					"balance"=>$balance,
					"revised_currency_balance"=>$revised_balance,
					"lex_to_usd"=>$lex_to_usd,
					"pending_count"=>$count
				);
			} else {
				$purchase_cost_currency = $this->input->post('purchase_cost_currency');
				$lex_balance_before = getBalance($user_id,8);
				$crypto_amount = $purchase_curr_amount;
				
				if($fiat_currency == 'USD')
				{
					$lex_to_usd = 0.016219;
					$lex_conversion = $lex_to_usd;
				}
				else {
					$lex_to_usd = 0.015;
					$lex_conversion = $lex_to_usd;
				}

				if($curr_symbol == 'USD')
				{
					$crypto_to_usd = 0.016219;
				}
				else {
					$crypto_to_usd = 0.015;
				}

				$final_crypto_value = $crypto_amount/$crypto_to_usd;

				$balance = getBalance($user_id,$curr_id);
				$revised_balance = $balance - $final_crypto_value;

				$lex_balance_after = $lex_balance_before + $final_crypto_value;

				$response = array(
					"lex"=>number_format($final_crypto_value,8),
					"lex_balance_before"=>$lex_balance_before,
					"lex_balance_after"=>number_format($lex_balance_after,8),
					"lex_balance_after_usd"=>number_format(($lex_balance_after*$lex_to_usd),2),
					"lex_usd"=>number_format($converted_purchase_value,2),
					"crypto_value"=>$purchase_curr_amount,
					"balance"=>$balance,
					"revised_currency_balance"=>$revised_balance,
					"lex_to_usd"=>$lex_to_usd,
					"pending_count"=>$count
				);
			}
		} else {
			if($fiat_currency == 'USD')
			{
				$curr_usd = $this->common_model->getTableData("currency",array("id"=>$curr_id))->row('online_usdprice');
			} else {
				$curr_usd = $this->common_model->getTableData("currency",array("id"=>$curr_id))->row('online_eurprice');
			}
			$balance = getBalance($user_id,$curr_id);
			$trading_balance = getTradingBalance($user_id,$curr_id);
			$total_balance = $balance + $trading_balance;


			$balance_percentage = number_format(((($balance - $purchase_curr_amount)  * 100)/$balance),2);
			$coin_to_usd  		= json_decode(convercurr($curr_symbol,$fiat_currency),true);
			$usd_value_for_coin =($coin_to_usd[$fiat_currency]);
			$amount_in_usd =$purchase_curr_amount * $usd_value_for_coin;
			$lex_to_usd = 0.016219;
			if($curr_symbol == 'USDT' || $curr_symbol == 'USDC')
			{
				$lex = $purchase_curr_amount * $lex_to_usd;
			} else {
				$lex = $amount_in_usd / $lex_to_usd;
			}
			$lex_balance = getBalance($user_id,8);
			$lex_balance_after_swap = $lex_balance + $lex;
			$lex_balance_after_swap_usd = $lex_balance_after_swap * $lex_to_usd;
			$response = array(
				"lex"=>$lex,
				"balance"=>$balance,
				"balance_percentage"=>$balance_percentage,
				"curr_usd"=>number_format($purchase_curr_amount*$curr_usd,2),
				"lex_usd"=>number_format($lex*$lex_to_usd,2),
				"lex_balance_after_swap"=>$lex_balance_after_swap,
				"lex_balance_after_swap_usd"=>number_format($lex_balance_after_swap_usd,2),
				"curr_usd_value"=>number_format($curr_usd,2),
				"lex_to_usd"=>$lex_to_usd,
				"pending_count"=>$count
			);
		}
		echo json_encode($response);exit;
	}

	function getPairPrice(){
		$user_id=$this->session->userdata('user_id');
		
        if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		// $currency_id = $this->input->post('curr_id');
		$borrow_coin_id = $this->input->post('borrow_coin_id');
		$collateral_coin_id = $this->input->post('collateral_coin_id');
		$from = $this->input->post('from');
		$digital = $this->common_model->getTableData('currency',array('status'=>1,'id'=>$collateral_coin_id))->row();
		
		if($digital){
			$balance = getBalance($user_id,$collateral_coin_id);
			
			$trading_balance = getTradingBalance($user_id,$collateral_coin_id);
			$default_currency=$this->common_model->getTableData('users',array('id'=>$user_id),'default_currency')->row();
			$default_currency = json_decode(json_encode($default_currency->default_currency));
			if($default_currency == "USD"){
				$usd_balance = abs($balance * $digital->online_usdprice);
			}else{
				$usd_balance = abs($balance * $digital->online_eurprice);
			}
			$borrow_coin_symbol = json_decode(json_encode($this->common_model->getTableData('currency',array('status'=>1,'id'=>$borrow_coin_id),'currency_symbol')->row()));
			$collateral_coin_symbol = json_decode(json_encode($this->common_model->getTableData('currency',array('status'=>1,'id'=>$collateral_coin_id),'currency_symbol')->row()));
			
			$borrow_min = json_decode(json_encode($this->common_model->getTableData('currency',array('status'=>1,'id'=>$borrow_coin_id),'borrow_min')->row()));
			$borrow_max = json_decode(json_encode($this->common_model->getTableData('currency',array('status'=>1,'id'=>$borrow_coin_id),'borrow_max')->row()));
			
			$collateral_min = json_decode(json_encode($this->common_model->getTableData('currency',array('status'=>1,'id'=>$collateral_coin_id),'collateral_min')->row()));
			$collateral_max = json_decode(json_encode($this->common_model->getTableData('currency',array('status'=>1,'id'=>$collateral_coin_id),'collateral_max')->row()));
			
			$collateral_initial_ltv = json_decode(json_encode($this->common_model->getTableData('currency',array('status'=>1,'id'=>$collateral_coin_id),'initial_ltv')->row()));
			
			$borrow_min = $borrow_min->borrow_min;
			$borrow_max = $borrow_max->borrow_max;
			$collateral_min = $collateral_min->collateral_min;
			$collateral_max = $collateral_max->collateral_max;

			$borrow_coin_symbol = $borrow_coin_symbol->currency_symbol;
			$collateral_coin_symbol = $collateral_coin_symbol->currency_symbol;
			$collateral_initial_ltv = $collateral_initial_ltv->initial_ltv;
			$coin_pair = $collateral_coin_symbol."".$borrow_coin_symbol;
			$price = $this->binanceCoinConverter($coin_pair);
			if($price){
				$price = number_format((float)$price, 8, '.', '');
			}else{
				$coin_pair = $borrow_coin_symbol."".$collateral_coin_symbol;
				$price = $this->binanceCoinConverter($coin_pair);
				if($price != 0){
					$price =1/$price;
					$price = number_format((float)$price, 8, '.', '');
				}else{
					$price =0;
				}
			}

			if($from == "borrow") {
				// $data['coin_id'] = $borrow_coin_id;
				// $digital = $this->common_model->getTableData('currency',array('status'=>1,'id'=>$collateral_coin_id))->row();
				$data['currencies'] = $this->common_model->getTableData('currency', array('status'=>1), '', '','', '', '', '', '','', array('id',$borrow_coin_id),'')->result();
				$data['item_id'] = "collateral_coin_";
				$data['srch_id'] = "collateral_currency_search";
				$view = $this->load->view('front/user/load_currency',$data,true);
			} else if($from == "collateral") {
				$data['currencies'] = $this->common_model->getTableData('currency', array('status'=>1), '', '','', '', '', '', '','', array('id',$collateral_coin_id),'')->result();
				$data['item_id'] = "borrow_coin_";
				$data['srch_id'] = "borrow_currency_search";
				$view = $this->load->view('front/user/load_currency',$data,true);
			}else{
				$view = $this->load->view('front/user/load_currency',$data,true);
			}
			echo json_encode(array('status' => $digital->status,'borrow_min' => $borrow_min,'borrow_max' => $borrow_max,'collateral_min' => $collateral_min,'collateral_max' => $collateral_max,'initial_ltv' => $collateral_initial_ltv,'price' => $price,'balance' => $balance,'trading_balance' => $trading_balance,'usd' => $usd_balance,'default_currency' => $default_currency,'currency_list' => $view));die;
		}else{
			$digital = $this->common_model->getTableData('currency',array('id'=>$collateral_coin_id))->row();
			if($digital){
				echo json_encode(array('status' => $digital->status,'msg' => $this->lang->line('Selected currency is deactivated. Please refresh the page and try again')));die;
			}else{
				echo json_encode(array('status' => 0 , 'msg' => $this->lang->line('Selected currency is not in the list. Please refresh the page and try again')));die;
			}
		}
		
	}


	function binanceCoinConverter($coin_pair){
		$url = "https://api.binance.com/api/v1/ticker/24hr?symbol=".$coin_pair;
		$data = json_decode(file_get_contents($url));

		return $data->lastPrice;
	}

	function vipuser()
	{
		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$this->load->helper(array('form'));
		$this->load->library('form_validation');
		$this->form_validation->set_rules('first_name', 'FirstName', 'required');
		$this->form_validation->set_rules('last_name', 'LastName', 'required');
		$this->form_validation->set_rules('phone_number', 'PhoneNumber', 'required');
		$this->form_validation->set_rules('email', 'Email', 'required');
		$this->form_validation->set_rules('company_name', 'CompanyName', 'required');
		$this->form_validation->set_rules('business_description', 'BuisnessDescription', 'required');
		$this->form_validation->set_rules('website', 'Website', 'required');
		$this->form_validation->set_rules('region', 'Region', 'required');
		$this->form_validation->set_rules('purchase_amount', 'PurchaseAmount', 'required');
		$this->form_validation->set_rules('note', 'Note', 'required');

		if($this->input->post())
		{
			$first_name = validateTextBox($this->input->post('first_name'));
			$last_name = validateTextBox($this->input->post('last_name'));
			$phone_number = validateTextBox($this->input->post('phone_number'));
			$email = validateEmail($this->input->post('email'));
			$company_name = validateTextBox($this->input->post('company_name'));
			$business_description = validateTextBox($this->input->post('business_description'));
			$website = $this->input->post('website');
			$region = validateTextBox($this->input->post('region'));
			$purchase_amount = validateTextBox($this->input->post('purchase_amount'));
			$note = validateTextBox($this->input->post('note'));
			if ($this->form_validation->run())
			{ 
				// $joins = array('users as u'=>'u.id = uv.user_id');
				// $where=array('uv.user_id'=>$user_id,'u.is_vip !='=>'2');
				// $vip_entries = $this->common_model->getJoinedTableData('users_vip as uv',$joins,$where,'','','','','','','')->result();
				$vip_entries = $this->common_model->getTableData('users_vip as uv', array('user_id' => $user_id,'is_vip !='=>'2'))->row_array();
				if(count($vip_entries) == 0)
				{
					$insertData = array(

						'user_id' => $user_id,
						'first_name' => $first_name,
						'last_name' => $last_name,
						'phone_number' => $phone_number,
						'email'    => $email,
						'company_name' => $company_name,
						'business_description' => $business_description,
						'website' => $website,
						'region' => $region,
						'purchase_amount' => $purchase_amount,
						'note' => $note,
						'created_at'=>date("Y-m-d H:i:s")

					);
					$insert = $this->common_model->insertTableData('users_vip', $insertData);
					$this->session->set_flashdata('success', "Thank you for showing interest in VIP Account. You will get verified after the admin approval");
					front_redirect('home', 'refresh');
				} else {
					$this->session->set_flashdata('error', "You have already submitted the VIP application. Please wait.");
					front_redirect('home', 'refresh');
				}

			} else {
				$this->session->set_flashdata('error', "Invalid Please Try Again!");
				front_redirect('home', 'refresh');
			}
		}
	}

	// Buyback

	public function buyback()
	{



		$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}

		// Expire Update

		$this->buyback_expire(); 
		

		$data['site_common'] = site_common();
		$orderBy=array('id','asc');
		$data['lex_rec'] = $this->common_model->getTableData('currency', array('currency_symbol' => 'LEX'))->row();
		$data['buyback'] = $this->common_model->getTableData('buyback', array('user_id' => $user_id,'user_status'=>'active','status'=>1,'type'=>'insert'),'','','','','','',$orderBy)->row();

		$data['updatebuyback'] = $this->common_model->getTableData('buyback', array('user_id' => $user_id,'user_status'=>'active','status'=>1,'type'=>'update'),'','','','','','',$orderBy)->row();

		$userdetails = $this->common_model->getTableData('users', array('id' => $user_id))->row();
		$default_currency = $this->common_model->getTableData('currency', array('currency_symbol' => $userdetails->default_currency))->row();
		if($userdetails->default_currency=='EUR')
		{
			$data['currency_price'] = $default_currency->online_eurprice;
			$data['currency_symbol'] = '€';
		}
		else
		{
			$data['currency_price'] = $default_currency->online_usdprice;
			$data['currency_symbol'] = '$';
		}



		// POST Request

		if($this->input->post())
		{
			$amount = $this->input->post('amount');
			$price = $this->input->post('price');
			$note = $this->input->post('note');
			if($amount!='' && $price!='')
			{

				$total = $amount * $price;
				$userbalance_main    = getBalance($user_id,$data['lex_rec']->id);
				$userbalance_trading = getTradingBalance($user_id,$data['lex_rec']->id);
				if($amount > $userbalance_main)
					{ 
						$this->session->set_flashdata('error', 'Please fund your Main Account with LEX');
						front_redirect('buyback', 'refresh');
					}

				else {			

				$total_balance = $userbalance_main + $userbalance_trading;
				if($total_balance > 0) {

				$cal = $amount / $total_balance; 
			    $calc = $cal * 100;
			    $perc_calc = number_format($calc,2);
			    // $balance_percentage = 100 - $perc_calc;
			    $balance_percentage = $perc_calc;
			    
				}
				
				if(empty($data['buyback']))
				{
					$type = 'insert';
					$parent = 0;
					$buyback_id = mt_rand(100000,888888);
					// $edit_status =0;
				}
				else
				{
					$type = 'update';
					$parent = $data['buyback']->id;
					$buyback_id =  $data['buyback']->buyback_id;
					
				}	
				$edit_status = 1; 
					
				$fiat_amount = $amount * $price;

				if($fiat_amount > 0 && $amount > 0) {

				$insertData = array(
					'parent_id' => $parent,
					'buyback_id' => $buyback_id,
					'user_id' => $user_id,
					'amount' => $amount,
					'fiat_amount' => $fiat_amount,
					'price' => $price,
					'total' => $total,
					'main_balance'    => $userbalance_main,
					'trading_balance' => $userbalance_trading,
					'balance_percentage' => $balance_percentage,
					'currency' => $data['lex_rec']->id,
					'fiatcurrency' => $default_currency->id,
					'total' => $total,
					'note' => $note,
					'status' => 1,
					'type' => $type,
					'user_status' => 'active',
					'edit_status' => $edit_status,
					'datetime'=>date("Y-m-d H:i:s"),
					'update_time' => date("Y-m-d H:i:s")

				);

				if(empty($data['buyback']))
				{
					$insert = $this->common_model->insertTableData('buyback', $insertData);
				}
				else if(isset($data['buyback']) && empty($data['updatebuyback']))
				{

				    $updatdata = array(
						'parent_id' => $parent,
						'edit_status' => $edit_status
					);
					// Insert Row Update
					$this->common_model->updateTableData('buyback',array('buyback_id'=>$data['buyback']->buyback_id),$updatdata);
					$insert = $this->common_model->insertTableData('buyback', $insertData);
				}
				
				if($insert)
				{ 
					$this->session->set_flashdata('success', "Order placed successfully");
					front_redirect('buyback', 'refresh');
				}
				else
				{
					$this->session->set_flashdata('error', "Invalid Please Try Again!");
					front_redirect('buyback', 'refresh');
				}


			}
			else
			{
				$this->session->set_flashdata('error', "Invalid Please Try Again!");
					front_redirect('buyback', 'refresh');
			}


			 }


			}


		}




		$data['user_id'] = $user_id;
        
        $data['dig_currency'] = $this->common_model->getTableData('currency', array('status' => 1,'currency_symbol'=> 'LEX'), '', '', '', '', '', '', array('sort_order', 'ASC'))->result();
        $this->load->view('front/user/buyback',$data);




	}


	 public function cancelbuyback($id="")
	 {
	 	$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}


		$id= decryptIt($id);

		if($id > 0) {

			$buyback = $this->common_model->getTableData('buyback', array('id' => $id,'user_status' =>'active'))->row();
			$updaterow = $this->common_model->getTableData('buyback',array('parent_id'=>$id,'type'=>'update'),'','','','','','',array('id', 'DESC'))->row();

			if(!empty($buyback))
			{


				$updatedata = array(
					'status' => 0,
					'user_status' => 'cancelled',
					// 'update_time' => date("Y-m-d H:i:s")
				);

				if(isset($updaterow))
				{
					$update = $this->common_model->updateTableData('buyback',array('parent_id'=>$id),$updatedata);
				}
				else
				{
					$update = $this->common_model->updateTableData('buyback',array('id'=>$id),$updatedata);
				} 

				if($update)
				{
					$this->session->set_flashdata('success', "Order Cancelled successfully");
					front_redirect('buyback', 'refresh');
				}
				else
				{
					$this->session->set_flashdata('error', "Invalid Please Try Again!");
					front_redirect('buyback', 'refresh');
				}
			}
		}
		else
		{
			$this->session->set_flashdata('error', "Invalid Please Try Again!");
			front_redirect('buyback', 'refresh');
		}

	 }	


// Buyback Fund Transfer

	 public function buyback_fundtransfer()
	 {


	 	$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}


	 		if($_POST){
			$currency_id = $this->input->post('coin_id');
			$currency_value = $this->input->post('coin_value');
			$transfer_from = $this->input->post('transfer_from');

			if($transfer_from == "trading account"){
				$trading_balance = getTradingBalance($user_id,$currency_id);
			
				if($trading_balance >= $currency_value){
					unset($_POST['transfer_from']);
					$trading_main_balance = abs($trading_balance - $currency_value);
					$update_trade_balance = updateTradingBalance($user_id,$currency_id,$trading_main_balance);
					$balance = getBalance($user_id,$currency_id);
					$update_balance = $balance + $currency_value;
					$update_balance = updateBalance($user_id,$currency_id,$update_balance);
					$this->session->set_flashdata('success','Amount transferred successfully');
					front_redirect('buyback', 'refresh');
				}else{
					$this->session->set_flashdata('failure','Amount should be less than or equal to balance');
					front_redirect('buyback', 'refresh');
				}
			}else{
				$balance = getBalance($user_id,$currency_id);
			
				if($balance >= $currency_value){
					unset($_POST['transfer_from']);
					$main_balance = abs($balance - $currency_value);
					$update_balance = updateBalance($user_id,$currency_id,$main_balance);
					$trading_balance = getTradingBalance($user_id,$currency_id);
					$update_trade_balance = $trading_balance + $currency_value;
					$update_trading_balance = updateTradingBalance($user_id,$currency_id,$update_trade_balance);
					$this->session->set_flashdata('success','Amount transferred successfully');
					front_redirect('buyback', 'refresh');
				}else{
					$this->session->set_flashdata('failure','Amount should be less than ');
					front_redirect('buyback', 'refresh');
				}
			}
			
		}
	 } 


	 // Buyback Expired 
	  public function buyback_expire()
	 {
	 
	 	$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}

		$buybacks = $this->common_model->getTableData('buyback', array('user_status' =>'active'))->result();

		foreach ($buybacks as $buyback) {
			
			
			$buybackdatetime = $buyback->datetime;
			$expiredatetime = date( "Y-m-d H:i:s", strtotime( "$buybackdatetime +7 day" ) );
			$currentdatetime = date("Y-m-d H:i:s");
			$updaterow = $this->common_model->getTableData('buyback',array('parent_id'=>$buyback->id,'type'=>'update'),'','','','','','',array('id', 'DESC'))->row();


			if($currentdatetime >= $expiredatetime)
			{

				$updatedata = array( 
					'status' => 0,
					'user_status' => 'expired'
				);
				if(isset($updaterow))
				{
					$update = $this->common_model->updateTableData('buyback',array('parent_id'=>$buyback->id),$updatedata);
				}
				else
				{
					$update = $this->common_model->updateTableData('buyback',array('id'=>$buyback->id),$updatedata);
				} 

			}


		}



	 }

	 public function sumsub_kyc()
	 {
	 	$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$user_details = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		if($user_details->sumsub_status == 'Accepted')
		{
			$this->session->set_flashdata('success', 'You have already validated the KYC');
			redirect(base_url().'home');
		}
		$data['site_common'] = site_common();

	 	$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,"http://127.0.0.1:8080/api/createAccessToken");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		$output = curl_exec($ch);
		$output = json_decode($output);
		curl_close($ch);

		$data['user_id'] = $user_id;
		$data['sumsub_token_details'] = $output;
		$update = array(
			'sumsub_unique_id'=> $output->applicant_id,
			'sumsub_status'=> 'Initiated'
		);
		$is_updated = $this->common_model->updateTableData('users',array('id'=>$user_id),$update);
        
        $this->load->view('front/user/sumsub_kyc',$data);


	 }

	 public function sumsub_kyc_status()
	 {
	 	$user_id=$this->session->userdata('user_id');
		if($user_id=="")
		{	
			$this->session->set_flashdata('error', $this->lang->line('you are not logged in'));
			redirect(base_url().'home');
		}
		$user_details = $this->common_model->getTableData('users',array('id'=>$user_id))->row();
		$sumsub_unique_id = $user_details->sumsub_unique_id;
		$ch = curl_init();
	      $params = array(
	            "sumsub_applicant_id" => $sumsub_unique_id
	        );
        curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8080/api/status");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        $headers = array();
        $headers[] = "Content-Type : application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $response = json_decode($result,true);
        if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        
        if($response != '')
        {
        	$status = "Rejected";
        	if($response['data']['reviewStatus'] == 'init')
        	{
        		$status = "Initiated";
        	} else if($response['data']['reviewStatus'] == 'pending' || $response['data']['reviewStatus'] == 'prechecked' || $response['data']['reviewStatus'] == 'queued') 
        	{
        		$status = "Pending";
        	} else if($response['data']['reviewStatus'] == 'onHold')
        	{
        		$status = "On Hold";
        	} else if($response['data']['reviewStatus'] == 'completed')
        	{
        		$status = "Accepted";
        	}
        	$update = array(
				'sumsub_status'=> $status
			);
			$is_updated = $this->common_model->updateTableData('users',array('id'=>$user_id),$update);

			$this->session->set_flashdata('success', "Your KYC Status has been updated. Currently its in ".$status." status");
			redirect(base_url().'home');
        }


	 }





}
