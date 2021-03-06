<?php

namespace Concerto\PanelBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Concerto\PanelBundle\Service\HomeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HomeController {

    private $templating;
    private $homeService;
    private $request;

    public function __construct(EngineInterface $templating, HomeService $homeService, Request $request) {
        $this->templating = $templating;
        $this->homeService = $homeService;
        $this->request = $request;
    }

    /**
     * @return Response
     */
    public function indexAction() {
        return $this->templating->renderResponse('ConcertoPanelBundle::home.html.twig');
    }

    /**
     * @return Response
     */
    public function featuredCollectionAction($format = "json") {
        return $this->templating->renderResponse("ConcertoPanelBundle::collection.$format.twig", array(
                    'collection' => $this->homeService->getFeaturedTests()
        ));
    }

}
