<?php
/**
 * LifecyclePropertyEventsListener.php
 *
 * @author Jean-Guilhem Rouel <jean-gui@w3.org>
 *
 * @copyright Copyright <C2><A9> 2014 W3C <C2><AE> (MIT, ERCIM, Keio) {@link http://www.w3.org/Consortium/Legal/2002/ipr-notice-20021231 Usage policies apply}.
 */
namespace W3C\LifecycleEventsBundle\EventListener;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\PersistentCollection;
use W3C\LifecycleEventsBundle\Annotation\Change;
use W3C\LifecycleEventsBundle\Services\LifecycleEventsDispatcher;

/**
 * Listen to Doctrine preUpdate to feed a LifecycleEventsDispatcher
 */
class LifecyclePropertyEventsListener
{
    /**
     * Events dispatcher
     *
     * @var LifecycleEventsDispatcher
     */
    private $dispatcher;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * Constructs a new instance
     *
     * @param LifecycleEventsDispatcher $dispatcher the dispatcher to fed
     * @param Reader $reader
     */
    public function __construct(LifecycleEventsDispatcher $dispatcher, Reader $reader)
    {
        $this->dispatcher = $dispatcher;
        $this->reader     = $reader;
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $this->addPropertyChanges($args);
        $this->addCollectionChanges($args);
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    private function addPropertyChanges(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $realClass = ClassUtils::getRealClass(get_class($entity));

        foreach ($args->getEntityChangeSet() as $property => $change) {
            /** @var Change $annotation */
            $annotation = $this->reader->getPropertyAnnotation(
                new \ReflectionProperty($realClass, $property),
                Change::class
            );

            if ($annotation) {
                $this->dispatcher->addPropertyChange(
                    $annotation,
                    $args->getEntity(),
                    $property,
                    $change[0],
                    $change[1]
                );
            }
        }
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    private function addCollectionChanges(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $realClass = ClassUtils::getRealClass(get_class($entity));

        /** @var PersistentCollection $update */
        foreach ($args->getEntityManager()->getUnitOfWork()->getScheduledCollectionUpdates() as $update) {
            $property   = $update->getMapping()['fieldName'];
            /** @var Change $annotation */
            $annotation = $this->reader->getPropertyAnnotation(
                new \ReflectionProperty($realClass, $property),
                Change::class
            );

            // Make sure $u belongs to the entity we are working on
            if (!$annotation || $update->getOwner() !== $entity) {
                continue;
            }

            $this->dispatcher->addCollectionChange(
                $annotation,
                $args->getEntity(),
                $property,
                $update->getDeleteDiff(),
                $update->getInsertDiff()
            );
        }
    }
}

?>