 <?php
 defined('BASEPATH') OR exit('No direct script access allowed');
 
 class Certificado_Model extends CI_Model {
 
 	public function __construct(){
		parent::__construct();
		$this->load->database();
	}
 	
 	public function agregar_certificado($rfc, $certificate, $private_key, $password){
 		$data = array(
    		"rfc" =>  $rfc,
			"certificate"	 =>  $certificate,
			"private_key" =>  $private_key,
			"password" => $password
    	);
 		var_dump($data);
 	}
 
 }
 
 /* End of file Certificado_Model.php */
 /* Location: ./application/models/Certificado_Model.php */