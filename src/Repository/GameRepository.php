<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Trouver toutes les parties d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les parties en cours d'un utilisateur
     */
    public function findActiveGamesByUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'en_cours')
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
