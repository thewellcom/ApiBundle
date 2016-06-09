<?php

namespace TheWellCom\ApiBundle\Api\Repository;

use League\Fractal\Manager;
use League\Fractal\Pagination\Cursor;
use League\Fractal\Resource\Collection;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Doctrine\ORM\EntityManager;

/**
 *
 */
class EntityRepository
{
    protected $entityClassName;
    protected $em;
    protected $transformer;

    public function __construct(EntityManager $em, $entityClassName, $transformer)
    {
        $this->em = $em;
        $this->entityClassName = $entityClassName;
        $this->transformer = $transformer;
    }

    public function find($id)
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t')
            ->from($this->entityClassName, 't')
            ->where('t.id = :id')
            ->setParameter('id', $id);

        return $qb->getQuery()->getSingleResult();
    }

    public function getCollectionQuery(array $criteria)
    {
        $qb = $this->em->createQueryBuilder()
            ->from($this->entityClassName, 'u')
        ;

        unset($criteria['limit']);
        unset($criteria['cursor']);
        unset($criteria['previous']);
        unset($criteria['next']);

        if ($criteria) {
            $criteria0 = $this->underscore2CamelCase(array_keys($criteria)[0]);
            $qb->where($qb->expr()->like('u.'.$criteria0, ':'.$criteria0))
                ->setParameter($criteria0, '%'.$criteria[array_keys($criteria)[0]].'%');

            unset($criteria[array_keys($criteria)[0]]);

            if ($criteria) {
                foreach ($criteria as $key => $value) {
                    if (!$value) {
                        continue;
                    }

                    $key = $this->underscore2CamelCase(array_keys($criteria)[0]);
                    $qb->andWhere($qb->expr()->like('u.'.$key, ':'.$key))
                        ->setParameter($key, '%'.$value.'%');
                }
            };
        }

        return $qb;
    }

    public function findAll(array $criteria, $limit, $cursor, $previousCursor)
    {
        $qb = $this->getCollectionQuery($criteria);

        $qb->select('u');
        $adapter = new DoctrineORMAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);

        $maxPerPage = $limit;
        $currentCursor = $cursor;

        try {
            $entities = $pagerfanta
                ->setMaxPerPage($maxPerPage)
                ->setCurrentPage($currentCursor)
                ->getCurrentPageResults()
            ;
        } catch (\Pagerfanta\Exception\NotValidCurrentPageException $e) {
            throw new NotFoundResourceException("Cette page n'existe pas.");
        }

        $newCursor = null;
        $count = $this->count($criteria);

        if ($count > $maxPerPage * $currentCursor) {
            $newCursor = $currentCursor + 1;
        }

        if (!$previousCursor && $currentCursor > 1) {
            $previousCursor = $currentCursor - 1;
        }

        $cursor = new Cursor($currentCursor, $previousCursor, $newCursor, count($entities));

        $resource = new Collection($entities, $this->transformer);
        $resource->setCursor($cursor);
        $fractal = new Manager();

        return array_merge($fractal->createData($resource)->toArray(), ['total' => $count]);
    }

    public function count(array $criteria)
    {
        $qb = $this->getCollectionQuery($criteria);

        $qbCount = $qb->select('count(u.id)');

        return (int) $qbCount->getQuery()->getSingleScalarResult();
    }

    public function underscore2CamelCase($text)
    {
        $text = str_replace(' ', '', ucwords(str_replace('_', ' ', $text)));
        $text[0] = strtolower($text[0]);

        return $text;
    }
}
