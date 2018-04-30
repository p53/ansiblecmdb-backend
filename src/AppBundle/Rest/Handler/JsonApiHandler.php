<?php


namespace AppBundle\Rest\Handler;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonApiHandler {
    
    private $logger;
    private $templatingService;
    
    public function __construct(LoggerInterface $logger = null, $templating)
    {
        $this->logger = $logger;
        $this->templatingService = $templating;
    }
    
    /**
     * Converts the viewdata to a JSON API format.
     *
     * @return Response
     */
    public function createResponse(ViewHandler $handler, View $view, Request $request, $format)
    {
        try {
            $content = $this->renderContent($view, $format);
            $code = $view->getStatusCode();
            
            if(!$code)
            {
                $code = Response::HTTP_OK;
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error($e);
            }
            
            $content = sprintf('%s:<br/><pre>%s</pre>', $e->getMessage(), $e->getTraceAsString());
            $code = Response::HTTP_BAD_REQUEST;
        }

        $response = new Response($content, $code);
        $response->headers->set('Content-Type', $request->getMimeType($format));
        
        return $response;
    }
    
    public function renderContent($view, $format)
    {
        $data = $view->getData();
        $tplVar = $view->getTemplateVar() ? $view->getTemplateVar() : 'data';
        $tpl = "AppBundle:Handler:" . $format . ".html.twig";

        return $this->templatingService->render($tpl, array($tplVar => $data));
    }
    
}
