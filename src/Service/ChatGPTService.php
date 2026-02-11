<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatGPTService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, string $openaiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $openaiApiKey;
    }

    /**
     * Demande à ChatGPT de jouer le tour d'une IA
     */
    public function playAITurn(array $character, int $diceResult, array $possibleMoves, array $gameState): array
    {
        try {
            $prompt = $this->buildTurnPrompt($character, $diceResult, $possibleMoves, $gameState);

            // Log du début de l'appel
            error_log('ChatGPT: Appel pour ' . $character['name'] . ' avec ' . count($possibleMoves) . ' mouvements possibles');

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 800
                ]
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $errorContent = $response->getContent(false);
                error_log('ChatGPT erreur HTTP ' . $statusCode . ': ' . $errorContent);
                throw new \Exception('Erreur API OpenAI: ' . $statusCode);
            }

            $data = $response->toArray();
            $aiResponse = $data['choices'][0]['message']['content'] ?? null;

            if (!$aiResponse) {
                error_log('ChatGPT: Réponse vide');
                throw new \Exception('Réponse ChatGPT vide');
            }

            error_log('ChatGPT réponse brute: ' . substr($aiResponse, 0, 200));

            // Extraire le JSON de la réponse (au cas où il y aurait du texte autour)
            $jsonStr = $this->extractJson($aiResponse);

            if (!$jsonStr) {
                error_log('ChatGPT: Impossible d\'extraire le JSON de la réponse');
                throw new \Exception('Impossible d\'extraire le JSON');
            }

            // Parser la réponse JSON
            $decision = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('ChatGPT: Erreur JSON - ' . json_last_error_msg());
                throw new \Exception('Réponse ChatGPT invalide (JSON)');
            }

            // Valider que la décision contient les champs nécessaires
            if (!isset($decision['action'])) {
                error_log('ChatGPT: Action manquante dans la réponse');
                throw new \Exception('Réponse IA invalide: action manquante');
            }

            // Si c'est un mouvement, vérifier que target existe
            if ($decision['action'] === 'move' && !isset($decision['target'])) {
                error_log('ChatGPT: Target manquant pour action move');
                throw new \Exception('Réponse IA invalide: target manquant pour action move');
            }

            error_log('ChatGPT: Décision validée - action: ' . $decision['action']);
            return $decision;

        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            // Erreur HTTP spécifique
            error_log('ChatGPT erreur HTTP: ' . $e->getMessage());
            error_log('Réponse: ' . $e->getResponse()->getContent(false));

            if (!empty($possibleMoves)) {
                $randomMove = $possibleMoves[array_rand($possibleMoves)];
                return [
                    'action' => 'move',
                    'target' => $randomMove['destination'],
                    'reasoning' => 'Mouvement automatique (erreur IA)'
                ];
            }

            return [
                'action' => 'wait',
                'reasoning' => 'Aucun mouvement possible'
            ];
        } catch (\Exception $e) {
            // Autres erreurs
            error_log('ChatGPT erreur: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            if (!empty($possibleMoves)) {
                $randomMove = $possibleMoves[array_rand($possibleMoves)];
                return [
                    'action' => 'move',
                    'target' => $randomMove['destination'],
                    'reasoning' => 'Mouvement automatique (erreur IA)'
                ];
            }

            return [
                'action' => 'wait',
                'reasoning' => 'Aucun mouvement possible'
            ];
        }
    }

    private function getSystemPrompt(): string
    {
        return "Tu es un joueur IA dans une partie de Cluedo.

RÈGLES DU JEU:
- Le plateau fait 24x24 cases (coordonnées de 0,0 à 23,23)
- Tu peux te déplacer du nombre de cases indiqué par les dés (2-12)
- Les cases vides (0) sont des couloirs accessibles
- Les murs (1) bloquent le passage et sont infranchissables
- Les portes (2) permettent d'entrer dans les pièces
- Les pièces sont numérotées de 3 à 11 et tu peux t'y déplacer en une seule fois une fois la porte atteinte
- Tu dois trouver le meurtrier, l'arme et le lieu du crime

STRUCTURE DU PLATEAU (grid):
- 0 = Couloir (case vide, accessible)
- 1 = Mur (infranchissable)
- 2 = Porte (entrée vers une pièce)
- 3 = Cuisine
- 4 = Salle de billard
- 5 = Bibliothèque
- 6 = Véranda
- 7 = Salle à manger
- 8 = Salon
- 9 = Hall
- 10 = Bureau
- 11 = Studio

PERSONNAGES:
- Colonel Moutarde (jaune)
- Mademoiselle Rose (rouge)
- Révérend Olive (vert)
- Professeur Violet (violet)
- Madame Leblanc (blanc)
- Docteur Lenoir (bleu)

LIEUX (correspondent aux numéros de pièces):
- Cuisine (3), Salle de billard (4), Bibliothèque (5), Véranda (6), Salle à manger (7), Salon (8), Hall (9), Bureau (10), Studio (11)

ARMES:
- Poignard, Chandelier, Revolver, Corde, Clé anglaise, Matraque

PASSAGES SECRETS:
- Cuisine ↔ Bureau
- Salle de billard ↔ Véranda

STRATÉGIE:
- Entre dans une pièce dès que possible pour faire des hypothèses
- Évite de rester dans les couloirs
- Choisis des mouvements qui te rapprochent des pièces non visitées
- Les mouvements possibles te sont donnés avec leurs coordonnées exactes

Réponds UNIQUEMENT au format JSON suivant:
{
  \"action\": \"move\" | \"hypothesis\" | \"accusation\",
  \"target\": { \"x\": number, \"y\": number } (si action=move),
  \"hypothesis\": { \"location\": string, \"character\": string, \"weapon\": string } (si action=hypothesis),
  \"reasoning\": string (explication courte de ta décision)
}";
    }

    private function buildTurnPrompt(array $character, int $diceResult, array $possibleMoves, array $gameState): string
    {
        $otherPlayers = array_filter($gameState['characters'] ?? [], function($c) use ($character) {
            return $c['id'] !== $character['id'];
        });

        $otherPlayersStr = implode("\n", array_map(function($c) {
            return sprintf("- %s: (%d, %d)", $c['name'], $c['position']['x'], $c['position']['y']);
        }, $otherPlayers));

        $hypothesesStr = '';
        if (!empty($gameState['previousHypotheses'])) {
            $hypothesesStr = implode("\n", array_map(function($h) {
                return sprintf("- %s suggère: %s dans %s avec %s",
                    $h['character'],
                    $h['hypothesis']['character'] ?? '?',
                    $h['hypothesis']['location'] ?? '?',
                    $h['hypothesis']['weapon'] ?? '?'
                );
            }, array_slice($gameState['previousHypotheses'], -3)));
        }

        // Formater les mouvements possibles de manière claire
        $movesStr = '';
        if (!empty($possibleMoves)) {
            $movesList = array_slice($possibleMoves, 0, 10); // Limiter à 10 premiers mouvements pour ne pas surcharger
            $movesStr = implode("\n", array_map(function($move) use ($gameState) {
                $x = $move['destination']['x'];
                $y = $move['destination']['y'];
                $cellType = $gameState['grid'][$y][$x] ?? 0;

                $cellDesc = match($cellType) {
                    0 => 'couloir',
                    1 => 'mur',
                    2 => 'porte',
                    3 => 'Cuisine',
                    4 => 'Salle de billard',
                    5 => 'Bibliothèque',
                    6 => 'Véranda',
                    7 => 'Salle à manger',
                    8 => 'Salon',
                    9 => 'Hall',
                    10 => 'Bureau',
                    11 => 'Studio',
                    default => 'inconnu'
                };

                $distance = count($move['chemin'] ?? []);
                return sprintf("  - (%d, %d) = %s (distance: %d cases)", $x, $y, $cellDesc, $distance);
            }, $movesList));

            if (count($possibleMoves) > 10) {
                $movesStr .= sprintf("\n  ... et %d autres mouvements possibles", count($possibleMoves) - 10);
            }
        }

        // Créer une mini-carte autour de la position actuelle (5x5)
        $currentX = $character['position']['x'];
        $currentY = $character['position']['y'];
        $miniMap = "\nCARTE LOCALE (5x5 autour de toi):\n";

        for ($y = max(0, $currentY - 2); $y <= min(23, $currentY + 2); $y++) {
            $row = '';
            for ($x = max(0, $currentX - 2); $x <= min(23, $currentX + 2); $x++) {
                if ($x === $currentX && $y === $currentY) {
                    $row .= '[X]'; // Position actuelle
                } else {
                    $cell = $gameState['grid'][$y][$x] ?? 0;
                    $row .= match($cell) {
                        0 => ' . ',
                        1 => ' # ',
                        2 => ' D ',
                        default => sprintf('[%d]', $cell)
                    };
                }
            }
            $miniMap .= $row . "\n";
        }
        $miniMap .= "Légende: X=toi, .=couloir, #=mur, D=porte, [3-11]=pièces\n";

        return sprintf("C'est ton tour de jouer !

PERSONNAGE: %s
POSITION ACTUELLE: (%d, %d)
RÉSULTAT DÉS: %d
%s

MOUVEMENTS POSSIBLES (%d au total):
%s

POSITIONS DES AUTRES JOUEURS:
%s

HYPOTHÈSES PRÉCÉDENTES:
%s

CARTES QUE TU DÉTIENS:
%s

Analyse la situation et choisis le meilleur mouvement. Privilégie les pièces pour faire des hypothèses.

IMPORTANT: Tu DOIS répondre UNIQUEMENT avec un objet JSON valide, sans texte avant ou après. Format:
{
  \"action\": \"move\",
  \"target\": {\"x\": 12, \"y\": 15},
  \"reasoning\": \"Je me dirige vers...\"
}",
            $character['name'],
            $currentX,
            $currentY,
            $diceResult,
            $miniMap,
            count($possibleMoves),
            $movesStr ?: 'Aucun mouvement possible',
            $otherPlayersStr ?: 'Aucune',
            $hypothesesStr ?: 'Aucune',
            implode(', ', $character['cards'] ?? []) ?: 'Non révélées'
        );
    }

    /**
     * Extrait le JSON d'une réponse qui peut contenir du texte avant/après
     */
    private function extractJson(string $response): ?string
    {
        // Essayer de trouver un objet JSON dans la réponse
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            return $matches[0];
        }

        // Si pas trouvé, retourner la réponse telle quelle
        return trim($response);
    }
}
