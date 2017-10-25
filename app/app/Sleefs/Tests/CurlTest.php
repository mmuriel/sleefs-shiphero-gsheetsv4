<?php

namespace Sleefs\Test;

use Illuminate\Foundation\Testing\TestCase ;
use Illuminate\Contracts\Console\Kernel;

use Sleefs\Helpers\curl\Curl;


class CurlTest extends TestCase {

	public function setUp(){
        parent::setUp();
    }


    public function testGetWithCURLClass(){

    	$res = Curl::urlGet('http://localhost/api/tests/curl/param1?checker=param2');
    	$this->assertRegExp("/^1\. param2 \- param1/",$res);

    }

    public function testPostWithCURLClass(){

    	$content = array("v1"=>"Valor 1","v2"=>"Valor 2");
    	$res = Curl::urlPost('http://localhost/api/tests/curl',json_encode($content));
    	$res = json_decode($res);
    	$this->assertRegExp("/(Valor\ 1)/",$res,"No contiene el 'Valor 1' en ".$res);

    }


    public function testPutWithCURLClass(){

    	$content = array("v1"=>"Valor 1","v2"=>"Valor 2");
    	$res = Curl::urlPUT('http://localhost/api/tests/curl',json_encode($content));
    	$res = json_decode($res);
    	$this->assertRegExp("/(Valor\ 1)/",$res,"No contiene el 'Valor 1' en ".$res);

    }

    public function testDeleteWithCURLClass(){

    	$content = array("v1"=>"Valor 1","v2"=>"Valor 2");
    	$res = Curl::urlDelete('http://localhost/api/tests/curl',json_encode($content));
    	//var_dump($res);
    	//$res = json_decode($res);
    	$this->assertRegExp("/(Valor\ 1)/",$res,"No contiene el 'Valor 1' en ".$res);

    }

	/* Preparing the Test */
	public function createApplication(){
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

}