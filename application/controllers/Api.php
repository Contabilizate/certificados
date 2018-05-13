<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require (APPPATH . '/libraries/REST_Controller.php');
require (APPPATH . '/libraries/Certificados.php');

class Api extends REST_Controller {
	private $certificado;

	public function __construct() {
        parent::__construct();
		$this->load->model('certificado_model');
		$this->load->library(['ftp','certificados','form_validation']);
		$this->load->helper('url');
    }

	public function index()
	{
		return "Hol";	
	}

	private function setResponse($success, $error = null, $certificate_pem = null, $private_key = null){
		if ($success) {
			$respuesta = [
        		"success" => $success,
        		"certificate_pem" => $certificate_pem,
        		"private_key" => $private_key
        	];
		} else {
			$respuesta = [
        		"success" => $success,
        		"error:" => $error
        	];
		}
		return $this->response($respuesta, 200);
	}

	public function certificado_post()
    {
        // Para crear un recurso
        if ($this->validateInput()) {
        	$rfc = $this->post("rfc");
        	$pass = $this->post("password");
	        $certificate = $this->upload_file("certificate", $rfc);
	        $key = $this->upload_file("private_key", $rfc);

	        
	        if (array_key_exists('success', $certificate) && array_key_exists('success', $key)) {
	        	$keyPem = $this->checkCertificate($certificate['success']['file_name'], $key['success']['file_name'], $pass, $rfc);
	        	if ($keyPem['result']) {
	        		$respuesta = "Archivo generado con exito";
	        		$this->setResponse(true, null,null, $respuesta);
	        	}else{
	        		$respuesta = $keyPem['error'];
	        		$this->setResponse(false, $respuesta);
	        	}	        	
	        } else {
	        	$respuesta ="Ocurrio un error al procesar los archivos.";
	            $this->setResponse(false, $respuesta);
	        }
	        
	        /*
	        $certificado_nuevo = $this->certificado_model->agregar_certificado($this->post("rfc"),$this->post("certificate"),$this->post("private_key"), $this->post("password"));
	        if($certificado_nuevo === false){
	            $this->response(500);
	        }else{
	        	$respuesta = [
	        		"success" => true,
	        		"certificate_pem" => "Hola",
	        		"private_key" => "Hola"
	        	];
	            $this->response($respuesta, 200);
	        }*/	
        }else{
        	$respuesta = $this->form_validation->error_array();
            $this->setResponse(false, $respuesta);
        }     
    }

    public function upload_file($file, $rfc)
    {             
    	$resultado = null;          
    	$config['upload_path'] = './uploads/'. $rfc;
		$config['allowed_types'] = '*';
		$this->load->library('upload', $config);

		$directorio = $this->createDirectoy($rfc);
		if ($directorio) {
			if ( ! $this->upload->do_upload($file)){
	                $error = array('error' => $this->upload->display_errors());
					$resultado = $error;                    
	        }else{
	        	$data = array('success' => $this->upload->data());
	            $resultado = $data;	           
	        }
		}
		return $resultado;
    }

    private function createDirectoy($rfc){
    	$resultado = false;
    	if (!is_dir('uploads/'.$rfc)) {
    		$resultado = mkdir('./uploads/' . $rfc, 0777, TRUE);
    	}else{
    		$resultado = true;
    	}
    	return $resultado;
    }

    private function validateInput(){
    	$this->form_validation->set_rules('rfc', 'RFC', 'required|callback__rfcRegex|min_length[12]|max_length[20]',
    		[
                'required' => 'El %s es requerido.',
                'min_length' => 'El %s debe tener una longitud de al menos 12 caracteres.',
                'max_length' => 'El %s debe tener una longitud de máxima de 20 caracteres.',
                '_rfcRegex' => 'El %s no tiene un formato válido'                        
        	]);
    	if (empty($_FILES['certificate']['name'])){
		    $this->form_validation->set_rules('certificate', 'Certificado', 'required');
		}else{
			$this->form_validation->set_rules('certificate', 'Certificado', 'callback__cerFile', [
                	'_cerFile' => 'El %s debe tener una extensión cer.',
            	]);
		}
    	if (empty($_FILES['private_key']['name'])){
	    	$this->form_validation->set_rules('private_key', 'Llave privada', 'required');
	    }else{
	    	$this->form_validation->set_rules('private_key', 'Llave privada', 'callback__keyFile', [
                	'_keyFile' => 'El %s debe tener una extensión key.',
            	]);
	    }
    	$this->form_validation->set_rules('password', 'Contraseña', 'required');
    	
    	return $this->form_validation->run();
    }


    public function _rfcRegex($rfc) {
	  	if (preg_match('/^([A-ZÑ&]{3,4}) ?(?:- ?)?(\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])) ?(?:- ?)?([A-Z\d]{2})([A\d])$/', $rfc ) ){
	    	return true;
	  	}else{
	    	return false;
	  	}
	}

	public function _cerFile() {
		if ($this->getExtension($_FILES['certificate']['name'])[1] == "cer") {
			return true;
		} else {
			return false;
		}
	}

	public function _keyFile() {
	  	if ($this->getExtension($_FILES['private_key']['name'])[1] == "key") {
			return true;
		} else {
			return false;
		}
	}

	private function getExtension($fileName){
		$extension = explode(".", $fileName);
		return $extension;
	}

	private function checkPassValid($file){
		$this->certificados();	
	}

	private function checkCertificate($filePem, $fileKey, $pass, $rfc){
		$pathKey = "uploads/".$rfc."/".$fileKey;
		$pathCer = "uploads/".$rfc."/".$filePem;
		$resultado = $this->certificados->generaKeyPem($pathKey, $pass);
		if($resultado['result']) {
			$resultado = $this->getCerPemFile($pathCer);					
		}
		return $resultado;			
	}

	private function getCerPemFile($fileCer){
		$resultado = $this->certificados->generaCerPem($fileCer);
		if ($resultado['result']) {
			$this->getPair();
		}		
		return $resultado;	
	}

	private function getSerialCert($nombreCerPem){
		$resultado = $this->certificados->getSerialCert($nombreCerPem);
		return $resultado;
	}

	private function getFechaIniCert($nombreCerPem){
		$resultado = $this->certificados->getFechaInicio($nombreCerPem);
		return $resultado;
	}

	private function getFechaVigCert($nombreCerPem){
		$resultado = $this->certificados->getFechaVigencia($nombreCerPem);
		return $resultado;
	}

	private function validateCert($nombreCerPem){
		$resultado = $this->certificados->validarCertificado($nombreCerPem);
		return $resultado;
	}

	private function getPair($nombreCerPem, $nombreKeyPem, $rfc){
		$pathCer = "uploads/".$rfc."/".$nombreCerPem;
		$pathKey = "uploads/".$rfc."/".$nombreKeyPem;
		$resultado = $this->certificados->pareja($pathCer, $pathKey);
	}


}

/* End of file Api.php */
/* Location: ./application/controllers/Api.php */