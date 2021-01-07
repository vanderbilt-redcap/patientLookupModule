<?php

$twigLoader = new Twig_Loader_Filesystem(__DIR__."/templates");
$twig = new Twig_Environment($twigLoader);


$project = $_GET['pid'];

if($project == "") {
    throw new Exception("No project selected");
}