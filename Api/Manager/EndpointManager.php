<?php

namespace TheWellCom\ApiBundle\Api\Manager;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EndpointManager
{
    protected $entityClassName;
    protected $formTypeClassName;
    protected $transformerClassName;
    protected $router;

    public function __construct($entityClassName, $formTypeClassName, $transformerClassName, Router $router)
    {
        $this->entityClassName = $entityClassName;
        $this->formTypeClassName = $formTypeClassName;
        $this->transformerClassName = $transformerClassName;
        $this->router = $router;
    }

    public function getNew()
    {
        return new $this->entityClassName();
    }

    public function getFormTypeClassName()
    {
        return $this->formTypeClassName;
    }

    public function getTransformer()
    {
        return new $this->transformerClassName();
    }

    public function persistAndReturnJsonResponse($entity, $urlName, EntityManager $em)
    {
        $em->persist($entity);
        $em->flush();
        $json = new JsonResponse([
            'error' => null,
        ], 201);

        $url = $this->router->generate($urlName, ['id' => $entity->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $json->headers->set('Location', $url);

        return $json;
    }

    public function updateAndReturnJsonResponse($entity, EntityManager $em, Request $request)
    {
        $resourceKey = $request->attributes->get('thewellcomResourceKey');
        $urlName = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'get');
        $em->persist($entity);
        $em->flush();
        $json = new JsonResponse([
            'error' => null,
        ], 200);

        $url = $this->router->generate($urlName, ['id' => $entity->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $json->headers->set('Location', $url);

        return $json;
    }
}
