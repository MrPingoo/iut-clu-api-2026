<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/games')]
class GameController extends AbstractController
{
    /**
     * Liste toutes les parties de l'utilisateur connecté
     */
    #[Route('', name: 'api_games_list', methods: ['GET'])]
    public function list(GameRepository $gameRepository): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $games = $gameRepository->findByUser($user);

        $data = array_map(function (Game $game) {
            return [
                'id' => $game->getId(),
                'date' => $game->getCreatedAt()->format('c'), // Format ISO 8601
                'character' => $game->getCharacter(),
                'status' => $game->getStatus(),
                'characterColor' => $this->getCharacterColor($game->getCharacter()),
            ];
        }, $games);

        return $this->json($data);
    }

    /**
     * Créer une nouvelle partie
     */
    #[Route('', name: 'api_games_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['character'])) {
            return $this->json([
                'message' => 'Le personnage est requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $game = new Game();
        $game->setUser($user);
        $game->setCharacter($data['character']);

        // Générer une solution aléatoire
        $solution = $this->generateSolution();
        $game->setSolution(json_encode($solution));

        $entityManager->persist($game);
        $entityManager->flush();

        return $this->json([
            'id' => $game->getId(),
            'date' => $game->getCreatedAt()->format('c'),
            'character' => $game->getCharacter(),
            'status' => $game->getStatus(),
            'characterColor' => $this->getCharacterColor($game->getCharacter()),
        ], Response::HTTP_CREATED);
    }

    /**
     * Obtenir les détails d'une partie
     */
    #[Route('/{id}', name: 'api_games_show', methods: ['GET'])]
    public function show(Game $game): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user || $game->getUser()->getId() !== $user->getId()) {
            return $this->json(['message' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'id' => $game->getId(),
            'date' => $game->getCreatedAt()->format('c'),
            'character' => $game->getCharacter(),
            'status' => $game->getStatus(),
            'characterColor' => $this->getCharacterColor($game->getCharacter()),
            'history' => $game->getHistory() ? json_decode($game->getHistory(), true) : [],
        ]);
    }

    /**
     * Supprimer une partie
     */
    #[Route('/{id}', name: 'api_games_delete', methods: ['DELETE'])]
    public function delete(
        Game $game,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user || $game->getUser()->getId() !== $user->getId()) {
            return $this->json(['message' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($game);
        $entityManager->flush();

        return $this->json(['message' => 'Partie supprimée'], Response::HTTP_OK);
    }

    /**
     * Générer une solution aléatoire
     */
    private function generateSolution(): array
    {
        $characters = [
            'Colonel Moutarde',
            'Mademoiselle Rose',
            'Révérend Olive',
            'Professeur Violet',
            'Madame Leblanc',
            'Docteur Lenoir'
        ];

        $weapons = [
            'Poignard',
            'Chandelier',
            'Revolver',
            'Corde',
            'Clé anglaise',
            'Matraque'
        ];

        $rooms = [
            'Salon',
            'Salle à manger',
            'Cuisine',
            'Bureau',
            'Bibliothèque',
            'Salle de billard',
            'Véranda',
            'Hall',
            'Studio'
        ];

        return [
            'character' => $characters[array_rand($characters)],
            'weapon' => $weapons[array_rand($weapons)],
            'room' => $rooms[array_rand($rooms)]
        ];
    }

    /**
     * Obtenir la couleur d'un personnage
     */
    private function getCharacterColor(string $character): string
    {
        $colors = [
            'Colonel Moutarde' => 'yellow',
            'Mademoiselle Rose' => 'red',
            'Révérend Olive' => 'green',
            'Professeur Violet' => 'purple',
            'Madame Leblanc' => 'white',
            'Docteur Lenoir' => 'blue'
        ];

        return $colors[$character] ?? 'blue';
    }
}
