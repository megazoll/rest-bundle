<?php
namespace Lemon\RestBundle\Object;

use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use Lemon\RestBundle\Event\ObjectEvent;
use Lemon\RestBundle\Event\PostSearchEvent;
use Lemon\RestBundle\Event\PreSearchEvent;
use Lemon\RestBundle\Event\RestEvents;
use Lemon\RestBundle\Model\SearchResults;
use Lemon\RestBundle\Object\Criteria;
use Lemon\RestBundle\Object\Exception\NotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Manager
{
    protected $doctrine;
    protected $eventDispatcher;
    protected $class;

    /**
     * @param Doctrine $doctrine
     * @param EventDispatcher $eventDispatcher
     * @param string $class
     */
    public function __construct(
        Doctrine $doctrine,
        EventDispatcher $eventDispatcher,
        $class
    ) {
        $this->doctrine = $doctrine;
        $this->eventDispatcher = $eventDispatcher;
        $this->class = $class;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectManager|object
     */
    protected function getManager()
    {
        return $this->doctrine->getManagerForClass($this->class);
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    protected function getRepository()
    {
        return $this->doctrine->getManagerForClass($this->class)
            ->getRepository($this->class);
    }

    /**
     * @param Criteria $criteria
     * @return SearchResults
     */
    public function search(Criteria $criteria)
    {
        $this->eventDispatcher->dispatch(RestEvents::PRE_SEARCH, new PreSearchEvent($criteria));

        $objects = $this->getRepository()->findBy(
            $criteria->toArray(),
            $criteria->getOrderBy(),
            $criteria->getLimit(),
            $criteria->getOffset()
        );

        $results = new SearchResults($objects);

        $this->eventDispatcher->dispatch(RestEvents::POST_SEARCH, new PostSearchEvent($results));

        return $results;
    }

    /**
     * @param object $object
     * @return mixed
     */
    public function create($object)
    {
        $em = $this->getManager();

        $this->eventDispatcher->dispatch(RestEvents::PRE_CREATE, new ObjectEvent($object));

        $em->persist($object);
        $em->flush();
        $em->refresh($object);

        $this->eventDispatcher->dispatch(RestEvents::POST_CREATE, new ObjectEvent($object));

        return $object;
    }

    /**
     * @param integer $id
     * @return object
     */
    public function retrieve($id)
    {
        if (!($object = $this->getRepository()->findOneBy(array('id' => $id)))) {
            throw new NotFoundException("Object not found");
        }
        return $object;
    }

    /**
     * @param object $object
     * @return object
     */
    public function update($object)
    {
        $em = $this->getManager();

        $original = $this->retrieve(IdHelper::getId($object));

        $this->eventDispatcher->dispatch(RestEvents::PRE_UPDATE, new ObjectEvent($object, $original));

        $object = $em->merge($object);

        $em->persist($object);
        $em->flush();
        $em->refresh($object);

        $this->eventDispatcher->dispatch(RestEvents::POST_UPDATE, new ObjectEvent($object, $original));

        return $object;
    }

    /**
     * @param integer $id
     */
    public function delete($id)
    {
        $object = $this->retrieve($id);

        $em = $this->getManager();

        $this->eventDispatcher->dispatch(RestEvents::PRE_DELETE, new ObjectEvent($object));

        $em->remove($object);
        $em->flush();

        $this->eventDispatcher->dispatch(RestEvents::POST_DELETE, new ObjectEvent($object));
    }
}
