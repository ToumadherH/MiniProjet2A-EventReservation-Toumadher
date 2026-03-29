<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return Event[]
     */
    public function findPaginatedWithFilters(
        int $page,
        int $limit,
        ?string $query = null,
        ?string $location = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.date', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $query && '' !== trim($query)) {
            $qb
                ->andWhere('LOWER(e.title) LIKE :query OR LOWER(e.description) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($query)).'%');
        }

        if (null !== $location && '' !== trim($location)) {
            $qb
                ->andWhere('LOWER(e.location) LIKE :location')
                ->setParameter('location', '%'.mb_strtolower(trim($location)).'%');
        }

        if (null !== $dateFrom) {
            $qb
                ->andWhere('e.date >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if (null !== $dateTo) {
            $qb
                ->andWhere('e.date <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        return $qb->getQuery()->getResult();
    }

    public function countWithFilters(
        ?string $query = null,
        ?string $location = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
    ): int {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)');

        if (null !== $query && '' !== trim($query)) {
            $qb
                ->andWhere('LOWER(e.title) LIKE :query OR LOWER(e.description) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($query)).'%');
        }

        if (null !== $location && '' !== trim($location)) {
            $qb
                ->andWhere('LOWER(e.location) LIKE :location')
                ->setParameter('location', '%'.mb_strtolower(trim($location)).'%');
        }

        if (null !== $dateFrom) {
            $qb
                ->andWhere('e.date >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if (null !== $dateTo) {
            $qb
                ->andWhere('e.date <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    //    /**
    //     * @return Event[] Returns an array of Event objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Event
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
