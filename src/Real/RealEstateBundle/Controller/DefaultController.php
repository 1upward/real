<?php

namespace Real\RealEstateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('RealRealEstateBundle:Default:index.html.twig', array('name' => $name));
    }
}
