<?php

namespace TheWellCom\ApiBundle\Controller;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiController extends Controller
{
    public function getAction(Request $request, $id)
    {
        $resourceKey = $request->attributes->get('thewellcomResourceKey');
        $repoId = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'repository');
        $transformerId = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'transformer');
        $transformer = $this->get($transformerId);

        $entity = $this
            ->get($repoId)->find($id);

        if (!$entity) {
            return new JsonResponse([
                'statusCode' => 404,
                'error' => 'Not Found',
            ]);
        }

        $resource = new Item($entity, $transformer);
        $fractal = new Manager();
        $entity = $fractal->createData($resource)->toArray();

        return new JsonResponse($entity);
    }

    public function listAction(Request $request)
    {
        $resourceKey = $request->attributes->get('thewellcomResourceKey');
        $repoId = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'repository');
        parse_str($request->getQueryString(), $criteria);
        $maxPerPage = (int) $request->get('limit', 10) ?: 10;
        $currentCursor = (int) $request->get('cursor', 1) ?: 1;
        $previousCursor = $request->get('previous', null) ?: null;
        $array = $this
            ->get($repoId)
            ->findAll($criteria, $maxPerPage, $currentCursor, $previousCursor);

        return new JsonResponse($array);
    }

    public function addAction(Request $request)
    {
        $resourceKey = $request->attributes->get('thewellcomResourceKey');
        $managerId = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'manager');
        $urlName = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'get');
        $manager = $this->get($managerId);
        $entity = $manager->getNew();
        $form = $this->createForm($manager->getFormTypeClassName(), $entity);
        $json_data = json_decode($request->getContent(), true);
        $form->submit($json_data); // Instead of $form->handleRequest($json_data) with form data like foo=bar&name=John with the string type, here we have json data with request payload

        if ($form->isValid()) {
            return $manager->persistAndReturnJsonResponse($entity, $urlName, $this->getDoctrine()->getManager());
        }

        $errors = array();

        foreach ($form as $fieldName => $formField) {
            foreach ($formField->getErrors(true) as $error) {
                $errors[$fieldName] = $error->getMessage();
            }
        }

        $statusCode = 400;

        return new JsonResponse([
            'statusCode' => $statusCode,
            'error' => $errors,
        ], $statusCode);
    }

    public function updateAction($id, Request $request)
    {
        $resourceKey = $request->attributes->get('thewellcomResourceKey');
        $repoId = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'repository');
        $managerId = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'manager');
        $entity = $this
            ->get($repoId)->find($id);
        $manager = $this->get($managerId);
        $form = $this->createForm($manager->getFormTypeClassName(), $entity);
        $json_data = json_decode($request->getContent(), true);
        $form->submit($json_data);

        if ($form->isValid()) {
            return $manager->updateAndReturnJsonResponse($entity, $this->getDoctrine()->getManager(), $request);
        }

        $errors = array();

        foreach ($form as $fieldName => $formField) {
            foreach ($formField->getErrors(true) as $error) {
                $errors[$fieldName] = $error->getMessage();
            }
        }

        $statusCode = 400;

        return new JsonResponse([
            'statusCode' => $statusCode,
            'error' => $errors,
        ], $statusCode);
    }

    public function deleteAction(Request $request, $id)
    {
        $resourceKey = $request->attributes->get('thewellcomResourceKey');
        $repoId = sprintf('api.%s.%s.%s', 'v1', $resourceKey, 'repository');

        // temporary: do not select entity to delete with an other request
        $entity = $this
            ->get($repoId)->find($id);

        if ($entity) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($entity);
            $em->flush();

            $statusCode = 204;

            return new JsonResponse([
                'statusCode' => $statusCode,
                'error' => null,
            ], $statusCode);
        }

        $statusCode = 404;

        return new JsonResponse([
            'statusCode' => $statusCode,
            'error' => null,
        ], $statusCode);
    }
}
