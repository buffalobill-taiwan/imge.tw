<?php

class render{
    protected $path;
    protected $argv;
    public function __construct($p){
        $this->path = $p;
        $this->argv = array();
    }

    public function render($template){
        $file = $this->path."/".$template.".tpl.php";
        if(!file_exists($file)){
            $this->showError("template file '$template.tpl.php' not exists!");
        }
        extract($this->argv);
        include($file);
    }

    public function addVar($name, $var){
        $this->argv[$name] = $var;
    }

    public function showError($e){
        $this->resJSON(array("success" => false, "error" => $e));
    }

    public function resJSON($obj){
        header("Content-Type: application/json");
        print json_encode($obj);
        exit();
    }
}
