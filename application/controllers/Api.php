<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require (APPPATH . '/libraries/REST_Controller.php');
require (APPPATH . '/libraries/Certificados.php');

class Api extends REST_Controller {
	private $rfc;
	private $pass;
	private $path_keyFile;
	private $path_cerFile;
	private $path_cerPemFile;
	private $path_keyPemFile;
	private $dataCert = [
		"serial" => null,
		"fecha_inicio" => null,
		"fecha_vigencia" => null,
		"certificate" => null,
		"private_key" => null,
		"rfc" => null,
		"pass" => null
	];

	public function __construct() {
        parent::__construct();
		$this->load->model('certificado_model');
		$this->load->library(['ftp','certificados','form_validation']);
		$this->load->helper(['url', 'file']);
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
        	$this->rfc = $this->post("rfc");
        	$this->pass = $this->post("password");
	        $certificate = $this->upload_file("certificate");
	        $key = $this->upload_file("private_key");
	        $this->dataCert['rfc'] = $this->rfc;
	        $this->dataCert['pass'] = $this->pass;
	        
	        if (array_key_exists('success', $certificate) && array_key_exists('success', $key)) {
	        	$this->dataCert['certificate'] = $certificate['success']['file_name'];
	        	$this->dataCert['private_key'] = $key['success']['file_name'];
	        	$keyPem = $this->checkCertificate($this->dataCert['certificate'],$this->dataCert['private_key'] );
	        	if ($keyPem['result']) {	        		
	        		$certificateContent = $this->getContentFile($this->path_cerPemFile);
	        		$keyContent = $this->getContentFile($this->path_keyPemFile);
	        		$certificado_nuevo = $this->certificado_model->agregar_certificado($this->dataCert);
	        		if($certificado_nuevo === false){
	        			if ($this->removeDirectory()) {
		        			$respuesta = "Ocurrio un error al registrar el certificado.";
				            $this->setResponse(false, $respuesta);
	        			}else{
	        				$respuesta = "Ocurrio un error al eliminar los archivos.";
	        				$this->setResponse(false, $respuesta);
	        			}	        			
			        }else{
			        	$this->setResponse(true, null,$certificateContent, $keyContent);
			        }	        		
	        	}else{
	        		if ($this->removeDirectory()) {
	        			$respuesta = $keyPem['error'];
	        			$this->setResponse(false, $respuesta);
	        		} else {
	        			$respuesta = "Ocurrio un error al eliminar los archivos.";
	        			$this->setResponse(false, $respuesta);
	        		}	
	        	}	        	
	        } else {
	        	$respuesta ="Ocurrio un error al procesar los archivos.";
	            $this->setResponse(false, $respuesta);
	        }	        	        
        }else{
        	$respuesta = $this->form_validation->error_array();
            $this->setResponse(false, $respuesta);
        }     
    }

    public function upload_file($file)
    {             
    	$resultado = null;          
    	$config['upload_path'] = './uploads/'. $this->rfc;
		$config['allowed_types'] = '*';
		$this->load->library('upload', $config);

		$directorio = $this->createDirectoy();
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

    private function createDirectoy(){
    	$resultado = false;
    	if (!is_dir('uploads/'.$this->rfc)) {
    		$resultado = mkdir('./uploads/' . $this->rfc, 0777, TRUE);
    	}else{
    		$resultado = true;
    	}
    	return $resultado;
    }

    private function removeDirectory(){
    	$elimina = delete_files('./uploads/' . $this->rfc, true);
    	if ($elimina) {
    		$resultado = rmdir('./uploads/' . $this->rfc);  
    	}else{
    		$resultado = false;  
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

	private function checkCertificate($fileCer, $fileKey){
		$this->path_keyFile = "uploads/".$this->rfc."/".$fileKey;
		$this->path_cerFile = "uploads/".$this->rfc."/".$fileCer;	
		$resultado = $this->certificados->generaKeyPem($this->path_keyFile, $this->pass);	
		if($resultado['result']) {
			$this->path_keyPemFile = $this->path_keyFile.".pem";
			$resultado = $this->getCerPemFile();					
		}
		return $resultado;			
	}

	private function getCerPemFile(){
		$resultado = $this->certificados->generaCerPem($this->path_cerFile);
		if ($resultado['result']) {
			$this->path_cerPemFile = $this->path_cerFile.".pem";
			$resultado = $this->getPair();
		}		
		return $resultado;	
	}

	private function getPair(){
		$resultado = $this->certificados->pareja($this->path_cerPemFile, $this->path_keyPemFile);
		if ($resultado['result']) {
			$resultado = $this->validateCert();
		}
		return $resultado;
	}

	private function validateCert(){
		$resultado = $this->certificados->validarCertificado($this->path_cerPemFile);
		if ($resultado['result']) {
			$resultado = $this->getSerialCert();
		}
		return $resultado;
	}

	private function getSerialCert(){
		$resultado = $this->certificados->getSerialCert($this->path_cerPemFile);
		if ($resultado['result']) {
			$this->dataCert['serial'] = $resultado['serial'];
			$resultado = $this->getFechaIniCert();
		}
		return $resultado;
	}

	private function getFechaIniCert(){
		$resultado = $this->certificados->getFechaInicio($this->path_cerPemFile);
		if ($resultado['result']) {
			$this->dataCert['fecha_inicio'] = $resultado['fecha'];
			$resultado = $this->getFechaVigCert();
		}
		return $resultado;
	}

	private function getFechaVigCert(){
		$resultado = $this->certificados->getFechaVigencia($this->path_cerPemFile);
		if ($resultado['result']) {
			$this->dataCert['fecha_vigencia'] = $resultado['fecha'];
			$resultado = $this->validateRFC();
		}
		return $resultado;
	}

	private function validateRFC(){
		$resultado = $this->certificados->getRFCCert($this->path_cerPemFile);
		if ($resultado['result']) {
			if ($resultado['rfc'] === $this->rfc) {
				$resultado = ['result' => 1];
			}else{
				$resultado = ['result' => 0, 'error' => 'El RFC no corresponde al certificado'];
			}
		}
		return $resultado;
	}

	private function getContentFile($file){
		$file = file_get_contents($file);
		return $file;
	}
}

/* End of file Api.php */
/* Location: ./application/controllers/Api.php */