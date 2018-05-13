 <?php
 defined('BASEPATH') OR exit('No direct script access allowed');
 
 class Certificado_Model extends CI_Model {
 
 	public function __construct(){
		parent::__construct();
		$this->load->database();
	}
 	
 	public function agregar_certificado($data){
 		$fecha_inicio = $this->getDateTimeFormat($data['fecha_inicio']);
 		$fecha_vigencia = $this->getDateTimeFormat($data['fecha_vigencia']);
 		$info = [
    		"rfc" =>  $data['rfc'],
			"certificate" => $data['certificate'],
			"private_key" => $data['private_key'],
			"serial" => $data['serial'],
			"fecha_inicio" => $fecha_inicio,
			"fecha_vigencia" => $fecha_vigencia,
			"password" => $data['pass'],
			"created_at" => date('Y-m-d H:i:s')
    	];
 		return $this->db->insert('certificados', $info);
 	}

 	private function getDateTimeFormat($fecha){
 		$resultado = date('Y-m-d H:i:s', strtotime($fecha));
 		return $resultado;
 	}
 
 }
 
 /* End of file Certificado_Model.php */
 /* Location: ./application/models/Certificado_Model.php */