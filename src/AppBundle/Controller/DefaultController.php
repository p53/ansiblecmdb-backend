<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class DefaultController extends Controller
{
    public function indexAction(Request $request)
    {
        $formBuilder = $this->createFormBuilder();
        $formBuilder->add('user', TextType::class);
        $formBuilder->add('password', PasswordType::class);
        $formBuilder->add('rememberme', CheckboxType::class, array('required' => false));
        $formBuilder->add('save', SubmitType::class, array('label' => 'Login'));
        
        $form = $formBuilder->getForm();
        
        return $this->render(
            'AppBundle:Default:index.html.twig', 
            array(
                'form' => $form->createView()
            )
        );
    }

}
